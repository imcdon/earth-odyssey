<?php
/*
 * river-reports.php - Fishing reports: permissions, saving, photos, and the conditions snapshot.
 * Conditions are fetched once when a report is saved (USGS gauge readings + a 7-day flow chart, Open-Meteo
 * weather for the week up to the trip, moon phase) and stored as JSON, so viewing a report never calls an API.
 * Editors can see and edit every report; authors only their own.
 */
require_once __DIR__ . '/rivers.php';

const RIVER_REPORT_MAX_SITES = 3;
const RIVER_REPORT_MAX_PHOTOS = 6;
const RIVER_REPORT_MAX_CATCHES = 50;
const RIVER_REPORT_PHOTO_MAX_BYTES = 12582912;
const RIVER_REPORT_PHOTO_MAX_DIM = 8000;
const RIVER_REPORT_READING_WINDOW = 10800;

const RIVER_REPORT_CLARITY = ['clear' => 'Clear', 'slightly-stained' => 'Slightly stained', 'stained' => 'Stained', 'muddy' => 'Muddy'];
const RIVER_REPORT_CROWDING = ['none' => 'Had it to myself', 'light' => 'Light', 'moderate' => 'Moderate', 'heavy' => 'Heavy'];

const RIVER_REPORT_SPECIES = [
    'King salmon (Chinook)', 'Silver salmon (Coho)', 'Sockeye salmon (Red)', 'Pink salmon', 'Chum salmon',
    'Rainbow trout', 'Steelhead', 'Brown trout', 'Brook trout', 'Cutthroat trout', 'Lake trout', 'Dolly Varden',
    'Arctic char', 'Arctic grayling', 'Bull trout', 'Mountain whitefish', 'Northern pike', 'Muskellunge', 'Walleye',
    'Yellow perch', 'Smallmouth bass', 'Largemouth bass', 'Striped bass', 'Bluegill', 'Crappie', 'Channel catfish',
    'Flathead catfish', 'Common carp', 'Sturgeon', 'Shad', 'Burbot', 'Sheefish',
];

/* ---------- Permissions and lookups ---------- */

function river_report_can_edit(array $user, array $report): bool
{
    return $user['role'] === 'editor' || (int) $report['author_id'] === (int) $user['id'];
}

function river_reports_for_user(array $user): array
{
    $sql = 'SELECT r.id, r.title, r.trip_date, r.updated_at, u.username AS author,
                   (SELECT GROUP_CONCAT(s.display_name ORDER BY rs.position SEPARATOR " / ")
                      FROM river_report_sites rs JOIN river_sites s ON s.site_id = rs.site_id WHERE rs.report_id = r.id) AS gauges,
                   (SELECT COALESCE(SUM(COALESCE(c.fish_count, 1)), 0) FROM river_report_catches c WHERE c.report_id = r.id) AS fish
              FROM river_reports r JOIN users u ON u.id = r.author_id';
    $args = [];
    if ($user['role'] !== 'editor') {
        $sql .= ' WHERE r.author_id = ?';
        $args[] = (int) $user['id'];
    }
    $stmt = get_db()->prepare($sql . ' ORDER BY r.trip_date DESC, r.id DESC');
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function river_report_get(int $id): ?array
{
    $db = get_db();
    $stmt = $db->prepare('SELECT r.*, u.username AS author FROM river_reports r JOIN users u ON u.id = r.author_id WHERE r.id = ?');
    $stmt->execute([$id]);
    $report = $stmt->fetch();
    if (!$report) {
        return null;
    }

    $stmt = $db->prepare(
        'SELECT rs.site_id, s.display_name, s.state, s.county, s.lat, s.lon, s.params
           FROM river_report_sites rs LEFT JOIN river_sites s ON s.site_id = rs.site_id
          WHERE rs.report_id = ? ORDER BY rs.position'
    );
    $stmt->execute([$id]);
    $report['sites'] = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM river_report_catches WHERE report_id = ? ORDER BY position');
    $stmt->execute([$id]);
    $report['catches'] = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM river_report_photos WHERE report_id = ? ORDER BY position, id');
    $stmt->execute([$id]);
    $report['photos'] = $stmt->fetchAll();

    $report['conditions'] = $report['conditions_json'] ? json_decode($report['conditions_json'], true) : null;
    return $report;
}

/* ---------- Saving ---------- */

/*
 * Validates and saves a report (new when $existing is null), its gauges, catch rows, photo changes and uploads,
 * then refreshes the conditions snapshot when the gauges, date, times or units changed.
 * Returns ['id' => ?int, 'errors' => [], 'warnings' => []].
 */
function river_report_save(array $user, ?array $existing, array $post, array $files): array
{
    [$data, $siteIds, $catches, $errors] = river_report_parse($post);
    if ($errors) {
        return ['id' => null, 'errors' => $errors, 'warnings' => []];
    }

    $db = get_db();
    $db->beginTransaction();
    $cols = ['title', 'trip_date', 'start_time', 'end_time', 'angler', 'measure_units', 'clarity', 'crowding',
             'access_point', 'hatch', 'tackle', 'writeup'];
    if ($existing) {
        $id = (int) $existing['id'];
        $db->prepare('UPDATE river_reports SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?')
            ->execute([...array_map(fn($c) => $data[$c], $cols), $id]);
    } else {
        $db->prepare('INSERT INTO river_reports (author_id, ' . implode(', ', $cols) . ') VALUES (?' . str_repeat(', ?', count($cols)) . ')')
            ->execute([(int) $user['id'], ...array_map(fn($c) => $data[$c], $cols)]);
        $id = (int) $db->lastInsertId();
    }

    $db->prepare('DELETE FROM river_report_sites WHERE report_id = ?')->execute([$id]);
    $ins = $db->prepare('INSERT INTO river_report_sites (report_id, position, site_id) VALUES (?, ?, ?)');
    foreach ($siteIds as $i => $siteId) {
        $ins->execute([$id, $i, $siteId]);
    }

    $db->prepare('DELETE FROM river_report_catches WHERE report_id = ?')->execute([$id]);
    $ins = $db->prepare(
        'INSERT INTO river_report_catches (report_id, position, species, fish_count, length, weight, kept, fly, caught_at, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($catches as $i => $c) {
        $ins->execute([$id, $i, $c['species'], $c['count'], $c['length'], $c['weight'], $c['kept'], $c['fly'], $c['time'], $c['note']]);
    }
    $db->commit();

    $warnings = river_report_save_photos($id, $existing['photos'] ?? [], $post, $files);

    $key = md5(json_encode([$siteIds, $data['trip_date'], $data['start_time'], $data['end_time'], $data['measure_units']]));
    $needsConditions = !$existing || $existing['conditions_key'] !== $key || empty($existing['conditions_json']) || !empty($post['refresh_conditions']);
    if ($needsConditions) {
        $conditions = river_report_build_conditions($siteIds, $data);
        if ($conditions) {
            $db->prepare('UPDATE river_reports SET conditions_json = ?, conditions_key = ?, conditions_at = UTC_TIMESTAMP() WHERE id = ?')
                ->execute([json_encode($conditions), $key, $id]);
            if (!$conditions['weather']) {
                $warnings[] = 'Weather could not be loaded right now. Tick "Refresh conditions" and save again later.';
            }
        } else {
            $warnings[] = 'River conditions could not be loaded right now. Tick "Refresh conditions" and save again later.';
        }
    }

    return ['id' => $id, 'errors' => [], 'warnings' => $warnings];
}

/* $_POST -> [report columns, gauge ids, catch rows, errors]. */
function river_report_parse(array $post): array
{
    $text = fn(string $k, int $max) => mb_substr(trim((string) ($post[$k] ?? '')), 0, $max);
    $time = function ($v): ?string {
        $v = trim((string) $v);
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v . ':00' : null;
    };
    $num = function ($v, float $max): ?float {
        $v = str_replace(',', '.', trim((string) $v));
        return is_numeric($v) && $v >= 0 && $v <= $max ? round((float) $v, 2) : null;
    };

    $errors = [];
    $data = [
        'title'         => $text('title', 200),
        'trip_date'     => (string) ($post['trip_date'] ?? ''),
        'start_time'    => $time($post['start_time'] ?? ''),
        'end_time'      => $time($post['end_time'] ?? ''),
        'angler'        => $text('angler', 120),
        'measure_units' => ($post['measure_units'] ?? '') === 'metric' ? 'metric' : 'us',
        'clarity'       => isset(RIVER_REPORT_CLARITY[$post['clarity'] ?? '']) ? $post['clarity'] : '',
        'crowding'      => isset(RIVER_REPORT_CROWDING[$post['crowding'] ?? '']) ? $post['crowding'] : '',
        'access_point'  => $text('access_point', 200),
        'hatch'         => $text('hatch', 5000),
        'tackle'        => $text('tackle', 5000),
        'writeup'       => $text('writeup', 100000),
    ];
    if ($data['title'] === '') {
        $errors[] = 'Give the report a title.';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $data['trip_date']);
    if (!$d || $d->format('Y-m-d') !== $data['trip_date'] || $data['trip_date'] < '1990-01-01' || $d->getTimestamp() > time() + 86400) {
        $errors[] = 'Enter the trip date (it can\'t be in the future).';
    }

    $siteIds = [];
    $labels = (array) ($post['site_label'] ?? []);
    foreach ((array) ($post['sites'] ?? []) as $i => $siteId) {
        $siteId = trim((string) $siteId);
        if ($siteId === '' && preg_match('/\b([A-Z]{2,10}-[0-9A-Za-z]{4,20})\b/', (string) ($labels[$i] ?? ''), $m)) {
            $siteId = $m[1];
        }
        if ($siteId !== '' && usgs_valid_site_id($siteId) && !in_array($siteId, $siteIds, true)) {
            $siteIds[] = $siteId;
        }
    }
    $siteIds = array_slice($siteIds, 0, RIVER_REPORT_MAX_SITES);
    if ($siteIds) {
        $stmt = get_db()->prepare('SELECT site_id FROM river_sites WHERE site_id IN (' . implode(',', array_fill(0, count($siteIds), '?')) . ')');
        $stmt->execute($siteIds);
        $known = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $siteIds = array_values(array_filter($siteIds, fn($s) => in_array($s, $known, true)));
    }
    if (!$siteIds) {
        $errors[] = 'Pick at least one gauge (search by river or town name).';
    }

    $catches = [];
    $rows = (array) ($post['catch_species'] ?? []);
    foreach (array_keys($rows) as $i) {
        $c = [
            'species' => mb_substr(trim((string) ($post['catch_species'][$i] ?? '')), 0, 80),
            'count'   => ($n = (int) ($post['catch_count'][$i] ?? 0)) > 0 ? min($n, 9999) : null,
            'length'  => $num($post['catch_length'][$i] ?? '', 9999),
            'weight'  => $num($post['catch_weight'][$i] ?? '', 9999),
            'kept'    => match ($post['catch_kept'][$i] ?? '') { 'kept' => 1, 'released' => 0, default => null },
            'fly'     => mb_substr(trim((string) ($post['catch_fly'][$i] ?? '')), 0, 120),
            'time'    => $time($post['catch_time'][$i] ?? ''),
            'note'    => mb_substr(trim((string) ($post['catch_note'][$i] ?? '')), 0, 255),
        ];
        if ($c['species'] !== '' || $c['count'] || $c['length'] || $c['weight'] || $c['fly'] !== '' || $c['note'] !== '') {
            $catches[] = $c;
        }
    }

    return [$data, $siteIds, array_slice($catches, 0, RIVER_REPORT_MAX_CATCHES), $errors];
}

function river_report_delete(array $report): void
{
    get_db()->prepare('DELETE FROM river_reports WHERE id = ?')->execute([(int) $report['id']]);
    $dir = river_report_photo_dir((int) $report['id'], false);
    if (is_dir($dir)) {
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }
}

/* ---------- Photos (stored under storage/, which the web can't reach; served by admin/river-report-photo.php) ---------- */

function river_report_photo_dir(int $reportId, bool $create = true): string
{
    return $create ? usgs_storage_dir('reports/' . $reportId) : dirname(__DIR__) . '/storage/reports/' . $reportId;
}

function river_report_save_photos(int $reportId, array $existing, array $post, array $files): array
{
    $db = get_db();
    $warnings = [];
    $delete = array_map('intval', (array) ($post['photo_delete'] ?? []));
    $captions = (array) ($post['photo_caption'] ?? []);
    $kept = 0;

    foreach ($existing as $photo) {
        if (in_array((int) $photo['id'], $delete, true)) {
            $db->prepare('DELETE FROM river_report_photos WHERE id = ?')->execute([$photo['id']]);
            @unlink(river_report_photo_dir($reportId) . '/' . $photo['file']);
            continue;
        }
        $kept++;
        if (isset($captions[$photo['id']])) {
            $db->prepare('UPDATE river_report_photos SET caption = ? WHERE id = ?')
                ->execute([mb_substr(trim((string) $captions[$photo['id']]), 0, 255), $photo['id']]);
        }
    }

    $uploads = $files['photos'] ?? null;
    if (!$uploads || !is_array($uploads['name'] ?? null)) {
        return $warnings;
    }
    $newCaptions = (array) ($post['new_photo_caption'] ?? []);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $types = [IMAGETYPE_WEBP => ['image/webp', 'webp'], IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png']];

    foreach (array_keys($uploads['name']) as $i) {
        $err = $uploads['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $name = htmlspecialchars((string) $uploads['name'][$i]);
        if ($kept >= RIVER_REPORT_MAX_PHOTOS) {
            $warnings[] = 'Only ' . RIVER_REPORT_MAX_PHOTOS . ' photos per report; "' . $name . '" was skipped.';
            continue;
        }
        $tmp = (string) $uploads['tmp_name'][$i];
        $info = $err === UPLOAD_ERR_OK && is_uploaded_file($tmp) ? @getimagesize($tmp) : false;
        $type = $info ? ($types[$info[2]] ?? null) : null;
        if (!$info || !$type || $finfo->file($tmp) !== $type[0]) {
            $warnings[] = '"' . $name . '" isn\'t a JPEG, PNG or WebP photo, so it was skipped.';
            continue;
        }
        if ($uploads['size'][$i] > RIVER_REPORT_PHOTO_MAX_BYTES || max($info[0], $info[1]) > RIVER_REPORT_PHOTO_MAX_DIM) {
            $warnings[] = '"' . $name . '" is too large; it was skipped.';
            continue;
        }
        $file = bin2hex(random_bytes(16)) . '.' . $type[1];
        if (!move_uploaded_file($tmp, river_report_photo_dir($reportId) . '/' . $file)) {
            $warnings[] = '"' . $name . '" could not be saved.';
            continue;
        }
        $db->prepare('INSERT INTO river_report_photos (report_id, position, file, mime, width, height, caption) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$reportId, $kept, $file, $type[0], $info[0], $info[1], mb_substr(trim((string) ($newCaptions[$i] ?? '')), 0, 255)]);
        $kept++;
    }
    return $warnings;
}

/* ---------- Conditions snapshot ---------- */

function river_report_build_conditions(array $siteIds, array $data): ?array
{
    $sites = array_values(array_filter(array_map('river_site', $siteIds)));
    if (!$sites) {
        return null;
    }
    // Trip time in the gauge's local time (from Open-Meteo's timezone); midpoint of start/end, else noon.
    $startMin = $data['start_time'] ? (int) substr($data['start_time'], 0, 2) * 60 + (int) substr($data['start_time'], 3, 2) : null;
    $endMin = $data['end_time'] ? (int) substr($data['end_time'], 0, 2) * 60 + (int) substr($data['end_time'], 3, 2) : null;
    $targetMin = $startMin !== null && $endMin !== null && $endMin > $startMin ? intdiv($startMin + $endMin, 2) : ($startMin ?? 720);

    $first = $sites[0];
    $weather = $first['lat'] !== null
        ? river_report_weather((float) $first['lat'], (float) $first['lon'], $data['trip_date'], intdiv($targetMin, 60), $data['measure_units'])
        : null;

    $offset = $weather['utc_offset'] ?? 0;
    $dayUtc = strtotime($data['trip_date'] . ' 00:00:00 UTC') - $offset;
    $target = $dayUtc + $targetMin * 60;
    $from = $dayUtc - 7 * 86400;
    $to = $dayUtc + 86400;

    $requests = [];
    foreach ($sites as $i => $site) {
        $requests["s{$i}"] = ['collection' => 'continuous', 'ttl' => 0, 'cache' => false, 'params' => [
            'monitoring_location_id' => $site['site_id'],
            'parameter_code'         => implode(',', $site['param_list'] ?: array_keys(USGS_PARAMS)),
            'time'                   => gmdate('Y-m-d\TH:i:s\Z', $from) . '/' . gmdate('Y-m-d\TH:i:s\Z', $to),
            'properties'             => 'parameter_code,time,value',
            'limit'                  => 10000,
        ]];
    }
    $results = usgs_get_many($requests);

    $gauges = [];
    foreach ($sites as $i => $site) {
        $points = [];
        foreach ($results["s{$i}"]['rows'] as $row) {
            $t = strtotime((string) ($row['time'] ?? ''));
            $code = (string) ($row['parameter_code'] ?? '');
            if ($t && isset(USGS_PARAMS[$code]) && is_numeric($row['value'] ?? null)) {
                $points[$code][] = [$t, (float) $row['value']];
            }
        }

        $readings = [];
        foreach ($points as $code => $pts) {
            $best = null;
            foreach ($pts as $p) {
                if (abs($p[0] - $target) <= RIVER_REPORT_READING_WINDOW && (!$best || abs($p[0] - $target) < abs($best[0] - $target))) {
                    $best = $p;
                }
            }
            if ($best) {
                $readings[$code] = ['value' => $best[1], 'time' => gmdate('c', $best[0])];
            }
        }

        $chartCode = isset($points['00060']) ? '00060' : (isset($points['00065']) ? '00065' : null);
        $chart = [];
        if ($chartCode) {
            $buckets = [];
            foreach ($points[$chartCode] as [$t, $v]) {
                $buckets[intdiv($t - $from, 7200)][] = $v;
            }
            ksort($buckets);
            foreach ($buckets as $b => $vals) {
                $chart[] = [$from + $b * 7200 + 3600, round(array_sum($vals) / count($vals), 2)];
            }
        }

        $gauges[] = [
            'site_id' => $site['site_id'], 'name' => $site['display_name'], 'state' => $site['state'], 'county' => $site['county'],
            'ok' => $results["s{$i}"]['ok'], 'readings' => $readings,
            'chart' => $chartCode ? ['code' => $chartCode, 'points' => $chart] : null,
        ];
    }

    return [
        'built_at' => gmdate('c'),
        'target'   => gmdate('c', $target),
        'range'    => [gmdate('c', $from), gmdate('c', $to)],
        'utc_offset' => $offset,
        'gauges'   => $gauges,
        'weather'  => $weather,
        'moon'     => river_moon($data['trip_date']),
    ];
}

/*
 * Open-Meteo for the 7 days ending on the trip date at the first gauge. Recent trips use the forecast API
 * (it keeps ~3 months of past data); older trips use the historical archive.
 */
function river_report_weather(float $lat, float $lon, string $tripDate, int $targetHour, string $units): ?array
{
    $recent = strtotime($tripDate) >= strtotime('-80 days');
    $base = $recent ? 'https://api.open-meteo.com/v1/forecast' : 'https://archive-api.open-meteo.com/v1/archive';
    $us = $units === 'us';
    $query = [
        'latitude' => round($lat, 4), 'longitude' => round($lon, 4), 'timezone' => 'auto',
        'start_date' => date('Y-m-d', strtotime($tripDate . ' -6 days')), 'end_date' => $tripDate,
        'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,rain_sum,snowfall_sum,wind_speed_10m_max,wind_direction_10m_dominant,cloud_cover_mean,sunrise,sunset',
        'hourly' => 'temperature_2m,precipitation,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m',
        'temperature_unit' => $us ? 'fahrenheit' : 'celsius', 'wind_speed_unit' => $us ? 'mph' : 'kmh', 'precipitation_unit' => $us ? 'inch' : 'mm',
    ];

    $ch = curl_init($base . '?' . http_build_query($query));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
                            CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'EarthOdyssey-RiverTool/1.0']);
    $t = microtime(true);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    usgs_log([['open-meteo', $status, 0, 0, (int) round((microtime(true) - $t) * 1000), strlen((string) $body)]]);
    $j = $status === 200 ? json_decode((string) $body, true) : null;
    if (!is_array($j) || empty($j['daily']['time']) || empty($j['hourly']['time'])) {
        return null;
    }

    $h = $j['hourly'];
    $hourIndex = array_flip($h['time']);
    $toPressure = fn($hpa) => $hpa === null ? null : ($us ? round($hpa * 0.02953, 2) : round($hpa, 1));

    $days = [];
    foreach ($j['daily']['time'] as $i => $date) {
        $pressures = [];
        foreach ($h['time'] as $k => $time) {
            if (str_starts_with($time, $date) && $h['pressure_msl'][$k] !== null) {
                $pressures[] = $h['pressure_msl'][$k];
            }
        }
        $days[] = [
            'date'     => $date,
            'role'     => $date === $tripDate ? 'trip' : ($date >= date('Y-m-d', strtotime($tripDate . ' -3 days')) ? 'lead' : 'week'),
            'tmax'     => $j['daily']['temperature_2m_max'][$i], 'tmin' => $j['daily']['temperature_2m_min'][$i],
            'precip'   => $j['daily']['precipitation_sum'][$i], 'snow' => $j['daily']['snowfall_sum'][$i],
            'wind'     => $j['daily']['wind_speed_10m_max'][$i], 'wind_dir' => river_compass($j['daily']['wind_direction_10m_dominant'][$i]),
            'cloud'    => $j['daily']['cloud_cover_mean'][$i],
            'pressure' => $pressures ? $toPressure(array_sum($pressures) / count($pressures)) : null,
            'sunrise'  => substr((string) $j['daily']['sunrise'][$i], 11, 5), 'sunset' => substr((string) $j['daily']['sunset'][$i], 11, 5),
        ];
    }

    $periods = [];
    foreach (['Morning' => [6, 10], 'Midday' => [11, 15], 'Evening' => [16, 20]] as $label => [$h1, $h2]) {
        $vals = ['temp' => [], 'cloud' => [], 'wind' => [], 'dir' => [], 'precip' => 0.0, 'pressure' => []];
        for ($hr = $h1; $hr <= $h2; $hr++) {
            $k = $hourIndex[sprintf('%sT%02d:00', $tripDate, $hr)] ?? null;
            if ($k === null) {
                continue;
            }
            $vals['temp'][] = $h['temperature_2m'][$k];
            $vals['cloud'][] = $h['cloud_cover'][$k];
            $vals['wind'][] = $h['wind_speed_10m'][$k];
            $vals['dir'][] = $h['wind_direction_10m'][$k];
            $vals['precip'] += (float) $h['precipitation'][$k];
            $vals['pressure'][] = $h['pressure_msl'][$k];
        }
        $avg = fn(array $a) => ($a = array_filter($a, fn($v) => $v !== null)) ? array_sum($a) / count($a) : null;
        $periods[] = [
            'label' => $label, 'hours' => sprintf('%d–%d', $h1 > 12 ? $h1 - 12 : $h1, $h2 > 12 ? $h2 - 12 : $h2) . ($h2 >= 12 ? ' pm' : ' am'),
            'temp' => ($v = $avg($vals['temp'])) === null ? null : round($v), 'cloud' => ($v = $avg($vals['cloud'])) === null ? null : round($v),
            'wind' => ($v = $avg($vals['wind'])) === null ? null : round($v), 'wind_dir' => river_compass(river_mean_direction($vals['dir'])),
            'precip' => round($vals['precip'], $us ? 2 : 1), 'pressure' => $toPressure($avg($vals['pressure'])),
        ];
    }

    // Pressure trend at the start of the trip (or noon): change over the previous 3 and 24 hours, in hPa.
    $k = $hourIndex[sprintf('%sT%02d:00', $tripDate, $targetHour)] ?? null;
    $p = $h['pressure_msl'];
    $change3 = $k !== null && $k >= 3 && $p[$k] !== null && $p[$k - 3] !== null ? $p[$k] - $p[$k - 3] : null;
    $change24 = $k !== null && $k >= 24 && $p[$k] !== null && $p[$k - 24] !== null ? $p[$k] - $p[$k - 24] : null;
    $trend = $change3 === null ? null : ($change3 >= 1 ? 'Rising' : ($change3 <= -1 ? 'Falling' : 'Steady'));

    return [
        'source' => $recent ? 'forecast' : 'archive', 'timezone' => (string) ($j['timezone'] ?? ''), 'utc_offset' => (int) ($j['utc_offset_seconds'] ?? 0),
        'units' => ['temp' => $us ? '°F' : '°C', 'wind' => $us ? 'mph' : 'km/h', 'precip' => $us ? 'in' : 'mm', 'pressure' => $us ? 'inHg' : 'hPa'],
        'days' => $days, 'periods' => $periods,
        'pressure' => [
            'start' => $h['time'][0], 'values' => array_map($toPressure, $p), 'trend' => $trend,
            'at' => $k !== null ? $toPressure($p[$k]) : null, 'at_hour' => $targetHour,
            'change_3h' => $change3 === null ? null : ($us ? round($change3 * 0.02953, 2) : round($change3, 1)),
            'change_24h' => $change24 === null ? null : ($us ? round($change24 * 0.02953, 2) : round($change24, 1)),
        ],
    ];
}

function river_compass($deg): ?string
{
    if ($deg === null) {
        return null;
    }
    $points = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
    return $points[(int) round(fmod((float) $deg + 360, 360) / 22.5) % 16];
}

/* Averages compass bearings correctly (350° and 10° average to 0°, not 180°). */
function river_mean_direction(array $degrees): ?float
{
    $degrees = array_filter($degrees, fn($d) => $d !== null);
    if (!$degrees) {
        return null;
    }
    $x = $y = 0.0;
    foreach ($degrees as $d) {
        $x += cos(deg2rad($d));
        $y += sin(deg2rad($d));
    }
    return fmod(rad2deg(atan2($y, $x)) + 360, 360);
}

/* Moon phase for a date (at noon UTC), from the mean synodic month; accurate to within about a day. */
function river_moon(string $date): array
{
    $synodic = 29.530588853;
    $jd = strtotime($date . ' 12:00:00 UTC') / 86400 + 2440587.5;
    $age = fmod($jd - 2451550.1, $synodic);
    if ($age < 0) {
        $age += $synodic;
    }
    $names = ['New moon', 'Waxing crescent', 'First quarter', 'Waxing gibbous', 'Full moon', 'Waning gibbous', 'Last quarter', 'Waning crescent'];
    return [
        'name'  => $names[(int) floor(($age / $synodic) * 8 + 0.5) % 8],
        'age'   => round($age, 1),
        'illum' => (int) round((1 - cos(2 * M_PI * $age / $synodic)) / 2 * 100),
    ];
}
