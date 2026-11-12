<?php
/*
 * api.php - JSON proxy for the river tool. The browser never calls USGS directly.
 * Actions:
 *   search    ?action=search&q=kenai             gauge type-ahead (MySQL only, no USGS call)
 *   latest    ?action=latest&site=USGS-15266300  latest reading per measurement
 *   series    ?action=series&site=...&win=1W     this window vs the same window last year
 *   badges    ?action=badges&ids=A,B&units=us    fishability badges from the hourly snapshots (MySQL only)
 *   statemap     ?action=statemap&state=AK&units=us     pins + 24h flow trends for the state map (MySQL only)
 *   statepanels  ?action=statepanels&state=AK&units=us  dropdown rankings under the state map (MySQL only)
 *   mysites / alerts / autorange (GET, auth) and save/unsave/importfavs/userranges/alertsave/alertdelete/alertsread (POST)
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/river-ranges.php';
require_once __DIR__ . '/../../includes/river-state-map.php';
require_once __DIR__ . '/../../includes/river-user.php';
require_once __DIR__ . '/../../includes/river-alerts.php';
require_once __DIR__ . '/../../includes/river-auto-ranges.php';
require __DIR__ . '/../../includes/ratelimit.php';

const API_RATE_LIMIT_PER_MINUTE = 60;

function api_require_user(?string $permission = 'tools'): array
{
    $user = current_user();
    if (!$user) {
        api_send(401, ['error' => 'Sign in required.']);
    }
    if (!account_is_approved($user)) {
        api_send(403, ['error' => 'Your account is awaiting admin approval.']);
    }
    if ($permission !== null && !user_has_permission($permission, $user)) {
        api_send(403, ['error' => 'You do not have permission for that.']);
    }
    return $user;
}

function api_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

function api_csrf_ok(array $body): bool
{
    start_session();
    $sent = (string) ($body['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $sent !== '' && hash_equals(csrf_token(), $sent);
}

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

    case 'statemap':
        $state = strtoupper((string) ($_GET['state'] ?? ''));
        if (!in_array($state, river_states(), true)) {
            api_send(400, ['error' => 'Invalid or unsupported state.']);
        }
        $units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
        api_send(200, river_statemap_payload($state, $units), 300);

    case 'statepanels':
        $state = strtoupper((string) ($_GET['state'] ?? ''));
        if (!in_array($state, river_states(), true)) {
            api_send(400, ['error' => 'Invalid or unsupported state.']);
        }
        $units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
        api_send(200, river_state_panels_payload($state, $units), 300);

    case 'mysites':
        $user = api_require_user();
        $units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
        api_send(200, river_user_dashboard((int) $user['id'], $units));

    case 'alerts':
        $user = api_require_user();
        api_send(200, [
            'unread' => river_alert_unread_count((int) $user['id']),
            'events' => river_alert_events_for_user((int) $user['id'], 40),
        ]);

    case 'autorange':
        $site = (string) ($_GET['site'] ?? '');
        if (!usgs_valid_site_id($site)) {
            api_send(400, ['error' => 'Invalid site id.']);
        }
        $rows = river_auto_ranges_for_sites([$site])[$site] ?? [];
        if (!$rows) {
            river_auto_compute_site($site);
            $rows = river_auto_ranges_for_sites([$site])[$site] ?? [];
        }
        api_send(200, ['site' => $site, 'ranges' => (object) $rows], 600);

    case 'save':
    case 'unsave':
    case 'importfavs':
    case 'userranges':
    case 'alertsave':
    case 'alertdelete':
    case 'alertsread':
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            api_send(405, ['error' => 'POST required.']);
        }
        $body = api_read_json_body();
        if (!api_csrf_ok($body)) {
            api_send(400, ['error' => 'Invalid CSRF token. Refresh and try again.']);
        }
        $user = api_require_user();
        $uid = (int) $user['id'];
        $action = (string) ($_GET['action'] ?? '');

        if ($action === 'save') {
            $errors = river_user_save_site($uid, (string) ($body['site'] ?? ''));
            api_send($errors ? 400 : 200, $errors ? ['error' => $errors[0]] : ['ok' => true]);
        }
        if ($action === 'unsave') {
            river_user_remove_site($uid, (string) ($body['site'] ?? ''));
            api_send(200, ['ok' => true]);
        }
        if ($action === 'importfavs') {
            $n = river_user_import_favorites($uid, (array) ($body['favorites'] ?? []));
            api_send(200, ['ok' => true, 'added' => $n]);
        }
        if ($action === 'userranges') {
            if (!user_has_permission('own_ranges', $user)) {
                api_send(403, ['error' => 'You do not have permission to set personal ranges.']);
            }
            $errors = river_user_range_save($uid, (string) ($body['site'] ?? ''), $body);
            api_send($errors ? 400 : 200, $errors ? ['error' => $errors[0], 'errors' => $errors] : ['ok' => true]);
        }
        if ($action === 'alertsave') {
            $errors = river_alert_save_subscription($uid, (string) ($body['site'] ?? ''), $body);
            api_send($errors ? 400 : 200, $errors ? ['error' => $errors[0]] : ['ok' => true]);
        }
        if ($action === 'alertdelete') {
            river_alert_delete_subscription($uid, (string) ($body['site'] ?? ''), (string) ($body['param'] ?? '00060'));
            api_send(200, ['ok' => true]);
        }
        if ($action === 'alertsread') {
            $eid = isset($body['id']) ? (int) $body['id'] : null;
            river_alert_mark_read($uid, $eid ?: null);
            api_send(200, ['ok' => true]);
        }
        api_send(400, ['error' => 'Unknown action.']);

    default:
        api_send(400, ['error' => 'Unknown action.']);
}
