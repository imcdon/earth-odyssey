<?php
/*
 * river-snapshot.php - Hourly: save the latest readings, rank the biggest 24-hour and 1-week changes for each
 * state in usgs.config.php, and warm the cache for the most-viewed rivers.
 * cPanel cron (minute 17, away from the top-of-hour rush): 17 * * * * php $HOME/public_html/cron/river-snapshot.php
 * Local:                                                    C:\xampp\php\php.exe cron\river-snapshot.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/river-movers.php';

$lock = fopen(usgs_storage_dir('locks') . '/river-snapshot.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Previous run still going; skipping.\n");
    exit(0);
}

$states = river_states();
if (!$states) {
    fwrite(STDERR, "No valid river_states in includes/usgs.config.php\n");
    exit(1);
}

river_job_start('snapshot');
$failed = [];
$requests = 0;
foreach ($states as $state) {
    $t = microtime(true);
    $r = river_movers_run($state);
    if ($r['ok']) {
        $requests += $r['requests'];
        printf("%s: %d new snapshots%s, %d ranked rows%s, %d USGS requests, %.1fs\n",
            $state, $r['snapshots'], $r['backfilled'] ? ' (incl. 26h backfill)' : '', $r['ranked'],
            $r['weekly_skipped'] ? ' (1-week skipped: daily means unavailable)' : '', $r['requests'], microtime(true) - $t);
    } else {
        $failed[] = "{$state} ({$r['error']})";
        fwrite(STDERR, "{$state}: FAILED - {$r['error']}\n");
    }
}

$t = microtime(true);
$p = river_preload_popular();
printf("Preloaded %d popular rivers, %.1fs\n", $p['warmed'], microtime(true) - $t);

river_job_finish('snapshot', !$failed, sprintf('%d of %d states updated, %d USGS requests, %d popular rivers preloaded.',
    count($states) - count($failed), count($states), $requests, $p['warmed'])
    . ($failed ? ' Failed: ' . implode(', ', $failed) . '.' : ''));
flock($lock, LOCK_UN);
exit($failed ? 1 : 0);
