<?php
/*
 * river-admin.php - Numbers for the API usage panel (admin/river-api.php) and the low-quota banner on the admin home.
 * Reads river_api_log, river_site_views, and the status files written by includes/usgs.php.
 */
require_once __DIR__ . '/river-ranges.php';

const RIVER_API_LOW_SHARE = 0.2;
const RIVER_JOBS = [
    'snapshot'   => ['label' => 'Hourly update', 'note' => 'Readings, biggest changes, and popular-river preload', 'max_age' => 7200],
    'sites-sync' => ['label' => 'Weekly gauge list sync', 'note' => 'Which gauges are active in each state', 'max_age' => 8 * 86400],
];

/* Latest USGS quota reading if it's from the last hour: ['limit', 'remaining', 'at'] or null. */
function river_api_rate(): ?array
{
    $rate = usgs_status_read('usgs-rate');
    return $rate && time() - (int) $rate['at'] < 3600 ? $rate : null;
}

/* Warning text for the admin home page, or null when all is well. */
function river_api_alert(): ?string
{
    $rate = river_api_rate();
    if ($rate && $rate['limit'] > 0 && $rate['remaining'] < $rate['limit'] * RIVER_API_LOW_SHARE) {
        return sprintf('USGS requests are running low: %s of %s left this hour.', number_format($rate['remaining']), number_format($rate['limit']));
    }
    try {
        $refused = (int) get_db()->query(
            "SELECT COUNT(*) FROM river_api_log WHERE status = 429 AND endpoint <> 'open-meteo' AND created_at >= NOW() - INTERVAL 1 HOUR"
        )->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
    return $refused
        ? "USGS turned away {$refused} request" . ($refused === 1 ? '' : 's') . ' in the last hour (too many requests). Visitors are seeing saved data where it exists.'
        : null;
}

/* Everything the panel shows for the last $hours hours. */
function river_api_panel(int $hours): array
{
    $db = get_db();
    $since = 'created_at >= NOW() - INTERVAL ' . $hours . ' HOUR';

    $hourly = [];
    foreach ($db->query(
        "SELECT FLOOR(UNIX_TIMESTAMP(created_at) / 3600) AS h,
                SUM(endpoint <> 'open-meteo' AND cache_hit = 0) AS usgs, SUM(endpoint = 'open-meteo') AS weather
         FROM river_api_log WHERE {$since} GROUP BY h"
    ) as $row) {
        $hourly[(int) $row['h']] = ['usgs' => (int) $row['usgs'], 'weather' => (int) $row['weather']];
    }

    $totals = $db->query(
        "SELECT SUM(endpoint <> 'open-meteo' AND cache_hit = 0) AS usgs,
                SUM(endpoint <> 'open-meteo' AND cache_hit = 0 AND status <> 200) AS usgs_errors,
                SUM(endpoint = 'open-meteo') AS weather,
                SUM(endpoint = 'open-meteo' AND status <> 200) AS weather_errors,
                SUM(stale) AS stale
         FROM river_api_log WHERE {$since}"
    )->fetch();

    $kinds = [];
    foreach ($db->query(
        "SELECT CASE WHEN status = 429 THEN '429' WHEN status = 0 THEN 'timeout' WHEN status >= 500 THEN '5xx' ELSE 'other' END AS kind,
                endpoint = 'open-meteo' AS weather, COUNT(*) AS n, UNIX_TIMESTAMP(MAX(created_at)) AS last
         FROM river_api_log WHERE {$since} AND cache_hit = 0 AND status <> 200 GROUP BY kind, weather"
    ) as $row) {
        $kinds[] = ['kind' => $row['kind'], 'weather' => (bool) $row['weather'], 'n' => (int) $row['n'], 'last' => (int) $row['last']];
    }

    $recent = $db->query(
        "SELECT endpoint, status, stale, ms, UNIX_TIMESTAMP(created_at) AS t FROM river_api_log
         WHERE {$since} AND cache_hit = 0 AND status <> 200 ORDER BY id DESC LIMIT 10"
    )->fetchAll();

    $days = $hours <= 24 ? 1 : (int) ceil($hours / 24);
    $top = $db->query(
        'SELECT v.site_id, SUM(v.views) AS views, s.display_name, s.state FROM river_site_views v
         LEFT JOIN river_sites s ON s.site_id = v.site_id
         WHERE v.viewed_on >= UTC_DATE() - INTERVAL ' . $days . ' DAY
         GROUP BY v.site_id, s.display_name, s.state ORDER BY views DESC LIMIT 10'
    )->fetchAll();

    $jobs = [];
    foreach (RIVER_JOBS as $job => $cfg) {
        $jobs[$job] = $cfg + ['status' => river_job_state($job, $cfg['max_age']), 'data' => usgs_status_read('job-' . $job) ?? []];
    }

    return [
        'hourly' => $hourly, 'totals' => array_map('intval', $totals ?: []), 'kinds' => $kinds, 'recent' => $recent,
        'top' => $top, 'top_days' => $days, 'jobs' => $jobs, 'rate' => river_api_rate(),
    ];
}

/* 'ok' | 'failed' | 'running' | 'stuck' | 'overdue' | 'never' */
function river_job_state(string $job, int $maxAge): string
{
    $d = usgs_status_read('job-' . $job);
    if (!$d || empty($d['started_at'])) {
        return 'never';
    }
    $started = (int) $d['started_at'];
    $finished = (int) ($d['finished_at'] ?? 0);
    if ($finished < $started) {
        return time() - $started < 3600 ? 'running' : 'stuck';
    }
    if (time() - $finished > $maxAge) {
        return 'overdue';
    }
    return !empty($d['ok']) ? 'ok' : 'failed';
}

function river_api_error_label(string $kind): string
{
    return match ($kind) {
        '429'     => 'Too many requests (429)',
        'timeout' => 'Timed out or no response',
        '5xx'     => 'Server error (5xx)',
        default   => 'Other error (4xx)',
    };
}

/* Stacked hourly bars (USGS + weather) as inline SVG. */
function river_api_chart(array $hourly, int $hours): string
{
    $w = 960;
    $h = 190;
    $left = 40;
    $bottom = 24;
    $plotW = $w - $left - 8;
    $plotH = $h - $bottom - 10;
    $endHour = intdiv(time(), 3600);
    $startHour = $endHour - $hours + 1;
    $max = 1;
    foreach ($hourly as $v) {
        $max = max($max, $v['usgs'] + $v['weather']);
    }
    $step = 1;
    foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000, 10000] as $s) {
        $step = $s;
        if ($max / $s <= 4) {
            break;
        }
    }
    $top = (int) (ceil($max / $step) * $step);
    $barW = $plotW / $hours;

    $svg = '<svg class="api-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Requests per hour">';
    for ($y = 0; $y <= $top; $y += $step) {
        $py = round(10 + $plotH - $y / $top * $plotH, 1);
        $svg .= '<line class="grid" x1="' . $left . '" x2="' . ($w - 8) . '" y1="' . $py . '" y2="' . $py . '"/>'
            . '<text class="axis" x="' . ($left - 6) . '" y="' . ($py + 4) . '" text-anchor="end">' . number_format($y) . '</text>';
    }
    for ($i = 0; $i < $hours; $i++) {
        $hour = $startHour + $i;
        $x = round($left + $i * $barW, 2);
        $bw = max(1, round($barW - ($hours > 48 ? 0.5 : 2), 2));
        $u = $hourly[$hour]['usgs'] ?? 0;
        $m = $hourly[$hour]['weather'] ?? 0;
        $base = 10 + $plotH;
        if ($u) {
            $bh = round($u / $top * $plotH, 1);
            $svg .= '<rect class="bar-usgs" x="' . $x . '" y="' . round($base - $bh, 1) . '" width="' . $bw . '" height="' . $bh . '"><title>'
                . htmlspecialchars(river_admin_time($hour * 3600, 'M j, g A')) . ': ' . $u . ' USGS</title></rect>';
            $base -= $bh;
        }
        if ($m) {
            $bh = round($m / $top * $plotH, 1);
            $svg .= '<rect class="bar-weather" x="' . $x . '" y="' . round($base - $bh, 1) . '" width="' . $bw . '" height="' . $bh . '"><title>'
                . htmlspecialchars(river_admin_time($hour * 3600, 'M j, g A')) . ': ' . $m . ' weather</title></rect>';
        }
        $local = (new DateTimeImmutable('@' . ($hour * 3600)))->setTimezone(new DateTimeZone(RIVER_ADMIN_TZ));
        $label = $hours <= 24
            ? ((int) $local->format('G') % 3 === 0 ? $local->format('g A') : null)
            : ((int) $local->format('G') === 0 && (int) $local->format('j') % 2 === 0 ? $local->format('M j') : null);
        if ($label !== null) {
            $svg .= '<text class="axis" x="' . round($x + $barW / 2, 1) . '" y="' . ($h - 6) . '" text-anchor="middle">' . $label . '</text>';
        }
    }
    return $svg . '</svg>';
}
