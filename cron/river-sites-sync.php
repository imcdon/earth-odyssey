<?php
/*
 * river-sites-sync.php - Weekly: refresh the list of active gauges for each state in usgs.config.php.
 * cPanel cron (Sundays 03:23): 23 3 * * 0 php $HOME/public_html/cron/river-sites-sync.php
 * Local:                       C:\xampp\php\php.exe cron\river-sites-sync.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/rivers.php';

$states = river_states();
if (!$states) {
    fwrite(STDERR, "No valid river_states in includes/usgs.config.php\n");
    exit(1);
}

river_job_start('sites-sync');
$failed = [];
$saved = 0;
foreach ($states as $state) {
    $t = microtime(true);
    $r = river_sync_state($state);
    if ($r['ok']) {
        $saved += $r['saved'];
        printf("%s: %d active, %d saved, %d removed, %d USGS requests, %.1fs\n",
            $state, $r['active'], $r['saved'], $r['removed'], $r['requests'], microtime(true) - $t);
    } else {
        $failed[] = "{$state} ({$r['error']})";
        fwrite(STDERR, "{$state}: FAILED - {$r['error']}\n");
    }
}
river_job_finish('sites-sync', !$failed, sprintf('%d of %d states synced, %s gauges saved.',
    count($states) - count($failed), count($states), number_format($saved))
    . ($failed ? ' Failed: ' . implode(', ', $failed) . '.' : ''));
exit($failed ? 1 : 0);
