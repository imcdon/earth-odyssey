<?php
/*
 * api.php - JSON proxy for the river tool. The browser never calls USGS directly.
 * Actions: latest (?action=latest&site=USGS-15266300). search and series arrive with the river pages.
 */
require __DIR__ . '/../../includes/usgs.php';
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

    default:
        api_send(400, ['error' => 'Unknown action.']);
}
