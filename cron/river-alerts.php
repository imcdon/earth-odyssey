<?php
/*
 * river-alerts.php - Evaluate alert subscriptions against recent snapshots; deliver in-app / email / SMS.
 * Run after river-snapshot.php, e.g. minute 20: 20 * * * * php $HOME/public_html/cron/river-alerts.php
 * Local: C:\xampp\php\php.exe cron\river-alerts.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/river-alerts.php';

$lock = fopen(usgs_storage_dir('locks') . '/river-alerts.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Previous run still going; skipping.\n");
    exit(0);
}

$r = river_alerts_run();
if (empty($r['ok'])) {
    fwrite(STDERR, 'Alerts failed: ' . ($r['error'] ?? 'unknown') . "\n");
    flock($lock, LOCK_UN);
    exit(1);
}

printf("Checked %d subscriptions, fired %d alerts.\n", (int) ($r['checked'] ?? 0), (int) ($r['fired'] ?? 0));
flock($lock, LOCK_UN);
exit(0);
