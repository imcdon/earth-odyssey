<?php
/*
 * river-auto-ranges.php - Weekly: recompute seasonal percentile fishability suggestions for saved/popular gauges.
 * cPanel example: 40 3 * * 1 php $HOME/public_html/cron/river-auto-ranges.php
 * Local: C:\xampp\php\php.exe cron\river-auto-ranges.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/river-auto-ranges.php';

$lock = fopen(usgs_storage_dir('locks') . '/river-auto-ranges.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Previous run still going; skipping.\n");
    exit(0);
}

$t = microtime(true);
$r = river_auto_ranges_run(200);
printf(
    "Auto ranges: %d sites (%d param rows) from %d candidates in %.1fs\n",
    (int) ($r['sites'] ?? 0),
    (int) ($r['params'] ?? 0),
    (int) ($r['candidates'] ?? 0),
    microtime(true) - $t
);

flock($lock, LOCK_UN);
exit(empty($r['ok']) ? 1 : 0);
