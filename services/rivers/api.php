<?php
/*
 * api.php - JSON proxy for the river tool. The browser never calls USGS directly.
 * Actions:
 *   search  ?action=search&q=kenai             gauge type-ahead (MySQL only, no USGS call)
 *   latest  ?action=latest&site=USGS-15266300  latest reading per measurement
 *   series  ?action=series&site=...&win=1W     this window vs the same window last year
 *   badges  ?action=badges&ids=A,B&units=us    fishability badges from the hourly snapshots (MySQL only)
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/river-ranges.php';
require __DIR__ . '/../../includes/ratelimit.php';

const API_RATE_LIMIT_PER_MINUTE = 60;

function api_send(int $status, array $payload, int $maxAge = 0): void
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($maxAge > 0 ? "public, max-age={$maxAge}" : 'no-store'));

    if ($status === 200) {
        $hash = md5($body);
        header('ETag: "' . $hash . '"');
        $sent = trim(str_replace(['W/', '"', '-gzip'], '', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($sent === $hash) {
            http_response_code(304);
            exit;
        }
    }

    http_response_code($status);
    echo $body;
    exit;
}

if (!rate_limit_allow('api:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), API_RATE_LIMIT_PER_MINUTE, 60)) {
    header('Retry-After: 60');
    api_send(429, ['error' => 'Too many requests. Try again in a minute.']);
}

switch ($_GET['action'] ?? '') {
    case 'search':
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $results = array_map(fn($r) => [
            'id'     => $r['site_id'],
            'name'   => $r['display_name'],
            'state'  => $r['state'],
            'county' => $r['county'],
        ], river_search($q, 10));
        api_send(200, ['q' => $q, 'results' => $results], 3600);

    case 'series':
        $site = (string) ($_GET['site'] ?? '');
        $win = strtoupper((string) ($_GET['win'] ?? RIVER_DEFAULT_WINDOW));
        if (!usgs_valid_site_id($site) || !isset(RIVER_WINDOWS[$win])) {
            api_send(400, ['error' => 'Invalid site or window.']);
        }
        $row = river_site($site);
        if (!$row) {
            api_send(404, ['error' => 'Gauge not found.']);
        }
        $series = river_series($row, $win);
        if (!$series['ok']) {
            api_send(502, ['error' => 'USGS is not responding. Try again shortly.']);
        }
        api_send(200, ['site' => $site] + $series, RIVER_WINDOWS[$win]['source'] === 'daily' ? 3600 : 300);

    case 'latest':
        $site = (string) ($_GET['site'] ?? '');
        if (!usgs_valid_site_id($site)) {
            api_send(400, ['error' => 'Invalid site id.']);
        }
        $latest = usgs_latest_for_site($site);
        if (!$latest['ok']) {
            api_send(502, ['error' => 'USGS is not responding. Try again shortly.']);
        }
        api_send(200, ['site' => $site] + $latest, 300);

    case 'badges':
        $ids = array_slice(array_filter(explode(',', (string) ($_GET['ids'] ?? '')), 'usgs_valid_site_id'), 0, 30);
        $units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
        $badges = [];
        foreach (river_badges_from_snapshots(river_ranges_for_sites($ids)) as $id => $b) {
            $badges[$id] = ['level' => $b['level'], 'label' => $b['label'], 'title' => river_badge_title($b, $units)];
        }
        api_send(200, ['badges' => (object) $badges], 300);

    default:
        api_send(400, ['error' => 'Unknown action.']);
}
