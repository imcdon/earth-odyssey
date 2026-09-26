<?php
/*
 * bootstrap.php - Local vs live: show errors on localhost, hide them live and write to a private log.
 */

function eo_is_local(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = (string) preg_replace('/:\d+$/', '', $host);
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function eo_home_dir(): string
{
    foreach (['HOME', 'USERPROFILE'] as $key) {
        $v = getenv($key);
        if (is_string($v) && $v !== '') {
            return rtrim(str_replace('\\', '/', $v), '/');
        }
    }
    $doc = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
    if ($doc !== '' && str_ends_with($doc, '/public_html')) {
        return dirname($doc);
    }
    return '';
}

function eo_error_log_path(): string
{
    $home = eo_home_dir();
    return $home === '' ? '' : $home . '/logs/earth-odyssey.log';
}

function eo_boot(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $local = eo_is_local();
    ini_set('display_errors', $local ? '1' : '0');
    ini_set('display_startup_errors', $local ? '1' : '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    if ($local) {
        return;
    }

    $log = eo_error_log_path();
    if ($log !== '') {
        $dir = dirname($log);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            ini_set('error_log', $log);
        }
    }

    set_exception_handler('eo_handle_exception');
    register_shutdown_function('eo_handle_shutdown');
}

function eo_wants_json(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#/(api|manifest)\.php$#', $script)) {
        return true;
    }
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
}

function eo_home_href(): string
{
    if (!function_exists('url')) {
        require_once __DIR__ . '/paths.php';
    }
    return function_exists('url') ? url() : '/';
}

function eo_fail(): void
{
    static $sent = false;
    if ($sent || PHP_SAPI === 'cli' || eo_is_local()) {
        return;
    }
    $sent = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    if (eo_wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo '{"error":"Something went wrong."}';
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    $home = htmlspecialchars(eo_home_href(), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Something went wrong</title><style>';
    echo 'html,body{margin:0;min-height:100%;background:#f4efe6;color:#1c1a17;';
    echo 'font-family:Georgia,"Times New Roman",serif}';
    echo 'main{max-width:28rem;margin:0 auto;padding:4rem 1.5rem;text-align:center}';
    echo 'h1{font-size:1.75rem;font-weight:400;margin:0 0 .75rem}';
    echo 'p{margin:0 0 1.5rem;line-height:1.5}';
    echo 'a{color:#3e5638}';
    echo '</style></head><body><main>';
    echo '<h1>Something went wrong.</h1>';
    echo '<p>Please try again in a moment.</p>';
    echo '<p><a href="' . $home . '">Back to home</a></p>';
    echo '</main></body></html>';
}

function eo_handle_exception(Throwable $e): void
{
    error_log(sprintf('Uncaught %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    error_log($e->getTraceAsString());
    if (PHP_SAPI === 'cli') {
        exit(1);
    }
    eo_fail();
    exit(1);
}

function eo_handle_shutdown(): void
{
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatal, true)) {
        return;
    }
    error_log(sprintf('Fatal: %s in %s:%d', $err['message'], $err['file'], $err['line']));
    if (PHP_SAPI === 'cli') {
        return;
    }
    eo_fail();
}

eo_boot();
