<?php
/*
 * rivers.php - River tool data layer: gauge sync, search, incremental daily history, window comparisons.
 * All times are UTC. USGS access goes through includes/usgs.php.
 */
require_once __DIR__ . '/usgs.php';

const RIVER_ACTIVE_DAYS = 30;
const RIVER_DAILY_REFRESH = 43200;
const RIVER_CHART_POINTS = 400;
const RIVER_DEFAULT_WINDOW = '1W';

const RIVER_WINDOWS = [
    '3D'  => ['label' => '3 days',       'days' => 3,    'source' => 'continuous'],
    '1W'  => ['label' => '1 week',       'days' => 7,    'source' => 'continuous'],
    '1M'  => ['label' => '1 month',      'days' => 30,   'source' => 'daily'],
    '3M'  => ['label' => '3 months',     'days' => 91,   'source' => 'daily'],
    '6M'  => ['label' => '6 months',     'days' => 182,  'source' => 'daily'],
    'YTD' => ['label' => 'Year to date', 'days' => null, 'source' => 'daily'],
    '1Y'  => ['label' => '1 year',       'days' => 365,  'source' => 'daily'],
];

const STATE_FIPS = [
    'AL' => '01', 'AK' => '02', 'AZ' => '04', 'AR' => '05', 'CA' => '06', 'CO' => '08', 'CT' => '09', 'DE' => '10',
    'DC' => '11', 'FL' => '12', 'GA' => '13', 'HI' => '15', 'ID' => '16', 'IL' => '17', 'IN' => '18', 'IA' => '19',
    'KS' => '20', 'KY' => '21', 'LA' => '22', 'ME' => '23', 'MD' => '24', 'MA' => '25', 'MI' => '26', 'MN' => '27',
    'MS' => '28', 'MO' => '29', 'MT' => '30', 'NE' => '31', 'NV' => '32', 'NH' => '33', 'NJ' => '34', 'NM' => '35',
    'NY' => '36', 'NC' => '37', 'ND' => '38', 'OH' => '39', 'OK' => '40', 'OR' => '41', 'PA' => '42', 'RI' => '44',
    'SC' => '45', 'SD' => '46', 'TN' => '47', 'TX' => '48', 'UT' => '49', 'VT' => '50', 'VA' => '51', 'WA' => '53',
    'WV' => '54', 'WI' => '55', 'WY' => '56', 'PR' => '72',
];

const RIVER_STATE_NAMES = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
    'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida',
    'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri',
    'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
    'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont',
    'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    'PR' => 'Puerto Rico',
];

const RIVER_PARAM_SHORT = ['00060' => 'Flow', '00065' => 'Height', '00010' => 'Temp', '63680' => 'Turbidity', '00300' => 'Oxygen'];

const RIVER_NAME_WORDS = [
    'R' => 'River', 'RV' => 'River', 'C' => 'Creek', 'CR' => 'Creek', 'CK' => 'Creek', 'LK' => 'Lake',
    'NR' => 'near', 'AB' => 'above', 'ABV' => 'above', 'BL' => 'below', 'BLW' => 'below', 'AT' => 'at',
    'NF' => 'North Fork', 'SF' => 'South Fork', 'MF' => 'Middle Fork', 'EF' => 'East Fork', 'WF' => 'West Fork',
    'F' => 'Fork', 'TRIB' => 'Tributary', 'MTH' => 'Mouth', 'CONF' => 'Confluence', 'HWY' => 'Highway',
    'SPGS' => 'Springs', 'MTN' => 'Mountain', 'OUTL' => 'Outlet', 'IN' => 'in', 'OF' => 'of', 'AND' => 'and',
    'THE' => 'the', 'NEAR' => 'near', 'ABOVE' => 'above', 'BELOW' => 'below',
];

function river_states(): array
{
    return array_values(array_filter(
        array_map('strtoupper', (array) usgs_config()['river_states']),
        fn($s) => isset(STATE_FIPS[$s])
    ));
}

/* "KENAI R AT SOLDOTNA AK" -> "Kenai River at Soldotna" */
function river_display_name(string $raw, string $state = ''): string
{
    $words = preg_split('/\s+/', trim($raw)) ?: [];
    if ($state !== '' && strtoupper((string) end($words)) === $state) {
        array_pop($words);
    }
    $out = [];
    foreach ($words as $i => $word) {
        $core = rtrim($word, ',.');
        $tail = substr($word, strlen($core));
        $upper = strtoupper($core);
        if (isset(RIVER_NAME_WORDS[$upper])) {
            $text = RIVER_NAME_WORDS[$upper];
        } elseif (preg_match('/\d/', $core)) {
            $text = $upper;
        } else {
            $text = ucfirst(strtolower($core));
        }
        $out[] = ($i === 0 ? ucfirst($text) : $text) . $tail;
    }
    return implode(' ', $out);
}

function river_site_url(string $siteId, array $query = []): string
{
    return url('services/rivers/site.php') . '?' . http_build_query(['id' => $siteId] + $query);
}

/* Native USGS units -> [value, unit label] for 'us' or 'metric'. Mirrors convert() in assets/js/rivers.js. */
function river_convert(string $code, float $value, string $units): array
{
    return match (true) {
        $code === '00010' && $units === 'us'     => [$value * 9 / 5 + 32, '°F'],
        $code === '00010'                        => [$value, '°C'],
        $code === '00060' && $units === 'metric' => [$value * 0.0283168, 'm³/s'],
        $code === '00060'                        => [$value, 'cfs'],
        $code === '00065' && $units === 'metric' => [$value * 0.3048, 'm'],
        $code === '00065'                        => [$value, 'ft'],
        default                                  => [$value, USGS_PARAMS[$code]['unit'] ?? ''],
    };
}

function river_format(float $value): string
{
    $abs = abs($value);
    return number_format($value, $abs >= 100 ? 0 : ($abs >= 10 ? 1 : 2));
}

function river_time_ago(string $iso): string
{
    $mins = max(0, (int) floor((time() - (int) strtotime($iso)) / 60));
    return match (true) {
        $mins < 1    => 'just now',
        $mins < 60   => $mins . ' min ago',
        $mins < 1440 => floor($mins / 60) . ' hr ago',
        default      => floor($mins / 1440) . ' days ago',
    };
}

function river_site_allowed(string $siteType): bool
{
    return str_starts_with($siteType, 'ST') || in_array($siteType, ['LK', 'ES'], true);
}

/*
 * Weekly: which gauges in the state reported any tracked measurement in the last 30 days, plus their names
 * and coordinates. About 1 + ceil(active / 100) USGS requests per state.
 */
function river_sync_state(string $state): array
{
    $fips = STATE_FIPS[$state] ?? null;
    if (!$fips) {
        return ['ok' => false, 'state' => $state, 'error' => 'Unknown state code'];
    }

    $latest = usgs_get('latest-continuous', [
        'state_code'     => $fips,
        'parameter_code' => implode(',', array_keys(USGS_PARAMS)),
        'properties'     => 'monitoring_location_id,parameter_code,time',
        'limit'          => 10000,
    ], 3600);
    if (!$latest['ok'] || $latest['stale']) {
        return ['ok' => false, 'state' => $state, 'error' => 'USGS latest values unavailable'];
    }

    $cutoff = time() - RIVER_ACTIVE_DAYS * 86400;
    $active = [];
    foreach ($latest['rows'] as $row) {
        $t = strtotime((string) ($row['time'] ?? ''));
        $id = (string) ($row['monitoring_location_id'] ?? '');
        $code = (string) ($row['parameter_code'] ?? '');
        if (!$t || $t < $cutoff || !usgs_valid_site_id($id) || !isset(USGS_PARAMS[$code])) {
            continue;
        }
        $active[$id]['params'][$code] = true;
        $active[$id]['last'] = max($active[$id]['last'] ?? 0, $t);
    }
    if (!$active) {
        return ['ok' => false, 'state' => $state, 'error' => 'No active gauges returned'];
    }

    $requests = [];
    foreach (array_chunk(array_keys($active), 100) as $i => $chunk) {
        $requests["m{$i}"] = ['collection' => 'monitoring-locations', 'ttl' => 518400, 'params' => [
            'id'           => implode(',', $chunk),
            'skipGeometry' => 'false',
            'properties'   => 'monitoring_location_name,county_name,site_type_code',
            'limit'        => 200,
        ]];
    }
    $meta = usgs_get_many($requests);
    foreach ($meta as $m) {
        if (!$m['ok']) {
            return ['ok' => false, 'state' => $state, 'error' => 'USGS monitoring locations unavailable'];
        }
    }

    $db = get_db();
    $syncStart = $db->query('SELECT NOW()')->fetchColumn();
    $upsert = $db->prepare(
        'INSERT INTO river_sites (site_id, name, display_name, state, county, site_type, lat, lon, params, last_reading_at, synced_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), display_name = VALUES(display_name), state = VALUES(state),
             county = VALUES(county), site_type = VALUES(site_type), lat = VALUES(lat), lon = VALUES(lon),
             params = VALUES(params), last_reading_at = VALUES(last_reading_at), synced_at = NOW()'
    );

    $saved = 0;
    $db->beginTransaction();
    foreach ($meta as $m) {
        foreach ($m['rows'] as $row) {
            $id = (string) ($row['_id'] ?? '');
            $name = trim((string) ($row['monitoring_location_name'] ?? ''));
            if (!isset($active[$id]) || $name === '' || !river_site_allowed((string) ($row['site_type_code'] ?? ''))) {
                continue;
            }
            $codes = array_values(array_filter(array_keys(USGS_PARAMS), fn($c) => isset($active[$id]['params'][$c])));
            $upsert->execute([
                $id, $name, river_display_name($name, $state), $state, $row['county_name'] ?? null,
                $row['site_type_code'] ?? null, $row['_lat'] ?? null, $row['_lon'] ?? null,
                implode(',', $codes), gmdate('Y-m-d H:i:s', $active[$id]['last']),
            ]);
            $saved++;
        }
    }
    $del = $db->prepare('DELETE FROM river_sites WHERE state = ? AND synced_at < ?');
    $del->execute([$state, $syncStart]);
    $removed = $del->rowCount();
    $db->commit();

    if ($removed) {
        $db->exec('DELETE d FROM river_daily d LEFT JOIN river_sites s ON s.site_id = d.site_id WHERE s.site_id IS NULL');
    }

    return ['ok' => true, 'state' => $state, 'active' => count($active), 'saved' => $saved, 'removed' => $removed,
            'requests' => 1 + count($requests)];
}

function river_site(string $siteId): ?array
{
    $stmt = get_db()->prepare('SELECT * FROM river_sites WHERE site_id = ?');
    $stmt->execute([$siteId]);
    $site = $stmt->fetch();
    if (!$site) {
        return null;
    }
    $site['param_list'] = $site['params'] === '' ? [] : explode(',', $site['params']);
    return $site;
}

function river_sites_by_state(string $state): array
{
    $stmt = get_db()->prepare(
        'SELECT site_id, display_name, county, params, last_reading_at FROM river_sites WHERE state = ? ORDER BY display_name'
    );
    $stmt->execute([$state]);
    return $stmt->fetchAll();
}

/* Every word must appear in the readable name; digits also match the site number. */
function river_search(string $q, int $limit = 10): array
{
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if (mb_strlen($q) < 2) {
        return [];
    }

    $where = [];
    $args = [];
    foreach (array_slice(explode(' ', $q), 0, 6) as $word) {
        $like = '%' . addcslashes($word, '%_\\') . '%';
        if (ctype_digit($word)) {
            $where[] = '(display_name LIKE ? OR site_id LIKE ?)';
            array_push($args, $like, $like);
        } else {
            $where[] = 'display_name LIKE ?';
            $args[] = $like;
        }
    }
    $states = river_states();
    if (!$states) {
        return [];
    }
    $where[] = 'state IN (' . implode(',', array_fill(0, count($states), '?')) . ')';
    array_push($args, ...$states);

    $starts = addcslashes($q, '%_\\') . '%';
    $sql = 'SELECT site_id, display_name, state, county FROM river_sites WHERE ' . implode(' AND ', $where)
        . ' ORDER BY (display_name LIKE ?) DESC, display_name LIMIT ' . max(1, min(25, $limit));
    $args[] = $starts;

    $stmt = get_db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function river_record_view(string $siteId): void
{
    try {
        get_db()->prepare(
            'INSERT INTO river_site_views (site_id, viewed_on, views) VALUES (?, UTC_DATE(), 1)
             ON DUPLICATE KEY UPDATE views = views + 1'
        )->execute([$siteId]);
    } catch (Throwable $e) {
        error_log('river_site_views insert failed: ' . $e->getMessage());
    }
}

/*
 * Keeps river_daily current: the first call stores ~2 years of daily means; later calls (at most every
 * 12 hours) re-request only the last week, which also picks up USGS revisions to provisional data.
 */
function river_sync_daily(array $site): array
{
    $syncedAt = $site['daily_synced_at'] ? strtotime($site['daily_synced_at'] . ' UTC') : 0;
    if ($syncedAt > time() - RIVER_DAILY_REFRESH || !$site['param_list']) {
        return ['ok' => true, 'stale' => false, 'as_of' => $syncedAt ?: null];
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT MAX(day) FROM river_daily WHERE site_id = ?');
    $stmt->execute([$site['site_id']]);
    $maxDay = $stmt->fetchColumn();
    $from = $maxDay
        ? gmdate('Y-m-d', strtotime($maxDay . ' UTC -7 days'))
        : gmdate('Y-m-d', strtotime('-2 years -10 days'));

    $r = usgs_get('daily', [
        'monitoring_location_id' => $site['site_id'],
        'parameter_code'         => implode(',', $site['param_list']),
        'statistic_id'           => '00003',
        'time'                   => $from . '/' . gmdate('Y-m-d'),
        'properties'             => 'parameter_code,time,value',
        'limit'                  => 10000,
    ], 0, false);

    if (!$r['ok']) {
        return ['ok' => (bool) $maxDay, 'stale' => true, 'as_of' => $syncedAt ?: null];
    }

    $values = [];
    foreach ($r['rows'] as $row) {
        $code = (string) ($row['parameter_code'] ?? '');
        if (isset(USGS_PARAMS[$code]) && is_numeric($row['value'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $row['time'])) {
            array_push($values, $site['site_id'], $code, substr((string) $row['time'], 0, 10), (float) $row['value']);
        }
    }
    foreach (array_chunk($values, 4 * 500) as $chunk) {
        $db->prepare(
            'INSERT INTO river_daily (site_id, param, day, value) VALUES '
            . implode(',', array_fill(0, count($chunk) / 4, '(?,?,?,?)'))
            . ' ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute($chunk);
    }
    $db->prepare('UPDATE river_sites SET daily_synced_at = UTC_TIMESTAMP() WHERE site_id = ?')->execute([$site['site_id']]);

    return ['ok' => true, 'stale' => false, 'as_of' => time()];
}

/* [curStart, curEnd, prevStart, prevEnd] as unix seconds; "prev" is the same window one year earlier. */
function river_window_range(string $win, ?int $now = null): array
{
    $now ??= time();
    $cfg = RIVER_WINDOWS[$win];
    $end = new DateTimeImmutable('@' . $now);

    if ($cfg['source'] === 'daily') {
        $today = $end->setTime(0, 0);
        $end = $today->modify('+1 day');
        $start = $cfg['days'] === null
            ? $today->setDate((int) $today->format('Y'), 1, 1)
            : $end->modify('-' . $cfg['days'] . ' days');
    } else {
        $start = $end->modify('-' . $cfg['days'] . ' days');
    }

    return [
        $start->getTimestamp(), $end->getTimestamp(),
        $start->modify('-1 year')->getTimestamp(), $end->modify('-1 year')->getTimestamp(),
    ];
}

/*
 * Continuous (15-minute) points for 3D/1W: this week (15-min cache) and the same week last year (30-day cache).
 * Both windows request the same two URLs (the last 7 days of each year), so switching tabs costs nothing.
 */
function river_continuous_points(array $site, int $curStart, int $curEnd, int $prevStart, int $prevEnd): array
{
    $codes = implode(',', $site['param_list']);
    $lyFrom = gmdate('Y-m-d', $prevEnd - 7 * 86400) . 'T00:00:00Z';
    $lyTo = gmdate('Y-m-d', $prevEnd + 86400) . 'T00:00:00Z';
    $base = ['monitoring_location_id' => $site['site_id'], 'parameter_code' => $codes,
             'properties' => 'parameter_code,time,value', 'limit' => 10000];

    $r = usgs_get_many([
        'cur'  => ['collection' => 'continuous', 'ttl' => 900, 'params' => $base + ['time' => 'P7D']],
        'prev' => ['collection' => 'continuous', 'ttl' => 2592000, 'params' => $base + ['time' => "{$lyFrom}/{$lyTo}"]],
    ]);

    $points = ['cur' => [], 'prev' => []];
    foreach (['cur' => [$curStart, $curEnd], 'prev' => [$prevStart, $prevEnd]] as $which => [$from, $to]) {
        foreach ($r[$which]['rows'] as $row) {
            $t = strtotime((string) ($row['time'] ?? ''));
            $code = (string) ($row['parameter_code'] ?? '');
            if ($t && $t >= $from && $t < $to && isset(USGS_PARAMS[$code]) && is_numeric($row['value'] ?? null)) {
                $points[$which][$code][] = [$t, (float) $row['value']];
            }
        }
    }

    return [
        'points' => $points,
        'ok'     => $r['cur']['ok'] || $r['prev']['ok'],
        'stale'  => $r['cur']['stale'] || $r['prev']['stale'],
        'as_of'  => $r['cur']['fetched_at'],
    ];
}

/* Daily-mean points for 1M..1Y from river_daily (timestamped at noon UTC so dates read correctly in US time zones). */
function river_daily_points(array $site, int $curStart, int $curEnd, int $prevStart, int $prevEnd): array
{
    $sync = river_sync_daily($site);
    $stmt = get_db()->prepare(
        'SELECT param, day, value FROM river_daily WHERE site_id = ? AND ((day >= ? AND day < ?) OR (day >= ? AND day < ?))'
    );
    $stmt->execute([$site['site_id'], gmdate('Y-m-d', $curStart), gmdate('Y-m-d', $curEnd),
                    gmdate('Y-m-d', $prevStart), gmdate('Y-m-d', $prevEnd)]);

    $points = ['cur' => [], 'prev' => []];
    foreach ($stmt->fetchAll() as $row) {
        $t = strtotime($row['day'] . ' 12:00:00 UTC');
        $which = $t >= $curStart ? 'cur' : 'prev';
        $points[$which][$row['param']][] = [$t, (float) $row['value']];
    }

    return ['points' => $points, 'ok' => $sync['ok'], 'stale' => $sync['stale'], 'as_of' => $sync['as_of']];
}

/* Averages points into at most RIVER_CHART_POINTS buckets on the current window's timeline. */
function river_bucket(array $points, int $start, int $bucket, int $count, int $shift = 0): array
{
    $sum = array_fill(0, $count, 0.0);
    $n = array_fill(0, $count, 0);
    foreach ($points as [$t, $v]) {
        $i = intdiv($t + $shift - $start, $bucket);
        if ($i >= 0 && $i < $count) {
            $sum[$i] += $v;
            $n[$i]++;
        }
    }
    $out = [];
    for ($i = 0; $i < $count; $i++) {
        $out[] = $n[$i] ? round($sum[$i] / $n[$i], 2) : null;
    }
    return $out;
}

function river_stats(array $points): ?array
{
    if (!$points) {
        return null;
    }
    $values = array_column($points, 1);
    return [
        'avg' => round(array_sum($values) / count($values), 2),
        'min' => round(min($values), 2),
        'max' => round(max($values), 2),
        'n'   => count($values),
    ];
}

/* Chart + stats payload for one site and window: this period vs the same period last year, every measurement. */
function river_series(array $site, string $win): array
{
    [$curStart, $curEnd, $prevStart, $prevEnd] = river_window_range($win);
    $source = RIVER_WINDOWS[$win]['source'];
    $data = $source === 'daily'
        ? river_daily_points($site, $curStart, $curEnd, $prevStart, $prevEnd)
        : river_continuous_points($site, $curStart, $curEnd, $prevStart, $prevEnd);

    $span = $curEnd - $curStart;
    $step = $source === 'daily' ? 86400 : 900;
    $bucket = max($step, (int) ceil($span / RIVER_CHART_POINTS / $step) * $step);
    $count = (int) ceil($span / $bucket);
    $shift = $curStart - $prevStart;

    $x = [];
    for ($i = 0; $i < $count; $i++) {
        $x[] = $curStart + $i * $bucket + intdiv($bucket, 2);
    }

    $params = [];
    foreach ($site['param_list'] as $code) {
        $cur = $data['points']['cur'][$code] ?? [];
        $prev = $data['points']['prev'][$code] ?? [];
        if (!$cur && !$prev) {
            continue;
        }
        $curStats = river_stats($cur);
        $prevStats = river_stats($prev);
        $params[$code] = [
            'label' => USGS_PARAMS[$code]['label'],
            'unit'  => USGS_PARAMS[$code]['unit'],
            'cur'   => river_bucket($cur, $curStart, $bucket, $count),
            'prev'  => river_bucket($prev, $curStart, $bucket, $count, $shift),
            'stats' => [
                'cur'        => $curStats,
                'prev'       => $prevStats,
                'change_abs' => $curStats && $prevStats ? round($curStats['avg'] - $prevStats['avg'], 2) : null,
                'change_pct' => $curStats && $prevStats && $prevStats['avg'] != 0
                    ? round(($curStats['avg'] - $prevStats['avg']) / abs($prevStats['avg']) * 100, 1) : null,
            ],
        ];
    }

    return [
        'ok'     => $data['ok'],
        'stale'  => $data['stale'],
        'as_of'  => $data['as_of'] ? gmdate('c', (int) $data['as_of']) : null,
        'win'    => $win,
        'source' => $source,
        'range'  => [
            'cur'  => [gmdate('c', $curStart), gmdate('c', $curEnd)],
            'prev' => [gmdate('c', $prevStart), gmdate('c', $prevEnd)],
        ],
        'x'      => $x,
        'params' => $params,
    ];
}
