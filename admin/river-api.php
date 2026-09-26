<?php
/*
 * river-api.php - API usage panel (editors only): USGS quota, requests per hour, errors, weather calls,
 * cron job status, and the most-viewed rivers.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-admin.php';

require_role('editor');
$range = ($_GET['range'] ?? '') === '14d' ? '14d' : '24h';
$hours = $range === '14d' ? 336 : 24;
$p = river_api_panel($hours);
$t = $p['totals'];
$rangeLabel = $range === '14d' ? 'last 14 days' : 'last 24 hours';

$jobLabels = [
    'ok'      => ['OK', 'is-ok'],
    'failed'  => ['Failed', 'is-bad'],
    'running' => ['Running now', 'is-warn'],
    'stuck'   => ["Didn't finish", 'is-bad'],
    'overdue' => ['Overdue', 'is-bad'],
    'never'   => ['Never run', 'is-warn'],
];

$page_theme = 'admin';
$page_title = 'API Usage | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page api-panel">
    <h1 class="section-title">API usage</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a> &middot; Times are Eastern.</p>

    <?php if ($alert = river_api_alert()): ?>
        <p class="admin-alert"><?= htmlspecialchars($alert) ?></p>
    <?php endif; ?>

    <nav class="river-tabs api-tabs" aria-label="Time range">
        <a href="?range=24h"<?= $range === '24h' ? ' aria-current="true"' : '' ?>>24 hours</a>
        <a href="?range=14d"<?= $range === '14d' ? ' aria-current="true"' : '' ?>>14 days</a>
    </nav>

    <div class="api-cards">
        <div class="api-card">
            <span class="api-card-label">USGS requests left this hour</span>
            <?php if ($p['rate']): ?>
                <?php $share = $p['rate']['limit'] ? $p['rate']['remaining'] / $p['rate']['limit'] : 1; ?>
                <span class="api-card-value<?= $share < RIVER_API_LOW_SHARE ? ' is-bad' : '' ?>"><?= number_format($p['rate']['remaining']) ?></span>
                <span class="api-card-note">of <?= number_format($p['rate']['limit']) ?> &middot; reported <?= htmlspecialchars(river_time_ago(gmdate('c', (int) $p['rate']['at']))) ?></span>
            <?php else: ?>
                <span class="api-card-value">&ndash;</span>
                <span class="api-card-note">Not reported in the last hour. USGS only reports this for requests made with your API key.</span>
            <?php endif; ?>
        </div>
        <div class="api-card">
            <span class="api-card-label">USGS requests</span>
            <span class="api-card-value"><?= number_format($t['usgs'] ?? 0) ?></span>
            <span class="api-card-note"><?= htmlspecialchars($rangeLabel) ?> &middot; <?= number_format($t['usgs_errors'] ?? 0) ?> failed</span>
        </div>
        <div class="api-card">
            <span class="api-card-label">Weather calls (Open-Meteo)</span>
            <span class="api-card-value"><?= number_format($t['weather'] ?? 0) ?></span>
            <span class="api-card-note"><?= htmlspecialchars($rangeLabel) ?> &middot; <?= number_format($t['weather_errors'] ?? 0) ?> failed &middot; used when a report is saved</span>
        </div>
        <div class="api-card">
            <span class="api-card-label">Saved data shown instead</span>
            <span class="api-card-value<?= ($t['stale'] ?? 0) ? ' is-warn' : '' ?>"><?= number_format($t['stale'] ?? 0) ?></span>
            <span class="api-card-note">times USGS failed and visitors saw the last saved copy</span>
        </div>
    </div>

    <section class="api-section">
        <h2 class="api-h2">Requests per hour</h2>
        <p class="api-legend"><span class="swatch is-usgs"></span> USGS <span class="swatch is-weather"></span> Weather. Requests answered from the saved copy aren't counted.</p>
        <?= river_api_chart($p['hourly'], $hours) ?>
    </section>

    <section class="api-section">
        <h2 class="api-h2">Errors</h2>
        <?php if ($p['kinds']): ?>
            <table class="admin-table">
                <thead><tr><th scope="col">Type</th><th scope="col">Service</th><th scope="col">Count</th><th scope="col">Last seen</th></tr></thead>
                <tbody>
                    <?php foreach ($p['kinds'] as $k): ?>
                        <tr>
                            <td><?= htmlspecialchars(river_api_error_label($k['kind'])) ?></td>
                            <td><?= $k['weather'] ? 'Weather' : 'USGS' ?></td>
                            <td><?= number_format($k['n']) ?></td>
                            <td><?= htmlspecialchars(river_admin_time($k['last'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <h3 class="api-h3">Most recent</h3>
            <table class="admin-table api-recent">
                <thead><tr><th scope="col">When</th><th scope="col">Request</th><th scope="col">Status</th><th scope="col">Time taken</th><th scope="col">Visitors saw</th></tr></thead>
                <tbody>
                    <?php foreach ($p['recent'] as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars(river_admin_time((int) $r['t'])) ?></td>
                            <td><?= htmlspecialchars($r['endpoint']) ?></td>
                            <td><?= (int) $r['status'] ?: 'No response' ?></td>
                            <td><?= number_format((int) $r['ms'] / 1000, 1) ?> s</td>
                            <td><?= $r['stale'] ? 'Saved copy' : ($r['endpoint'] === 'open-meteo' ? 'Report saved without weather' : 'An error message') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="api-ok">No errors in the <?= htmlspecialchars($rangeLabel) ?>.</p>
        <?php endif; ?>
    </section>

    <section class="api-section">
        <h2 class="api-h2">Scheduled jobs</h2>
        <div class="api-jobs">
            <?php foreach ($p['jobs'] as $job): ?>
                <?php [$statusText, $statusClass] = $jobLabels[$job['status']]; $d = $job['data']; ?>
                <div class="api-job">
                    <div class="api-job-head">
                        <strong><?= htmlspecialchars($job['label']) ?></strong>
                        <span class="api-status <?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
                    </div>
                    <p class="api-card-note"><?= htmlspecialchars($job['note']) ?></p>
                    <?php if (!empty($d['started_at'])): ?>
                        <dl class="api-job-facts">
                            <dt>Last started</dt><dd><?= htmlspecialchars(river_admin_time((int) $d['started_at'])) ?></dd>
                            <?php if (!empty($d['finished_at'])): ?>
                                <dt>Last finished</dt><dd><?= htmlspecialchars(river_admin_time((int) $d['finished_at'])) ?><?= isset($d['seconds']) ? ' (' . (int) $d['seconds'] . ' s)' : '' ?></dd>
                                <dt>Result</dt><dd><?= htmlspecialchars((string) ($d['summary'] ?? '')) ?></dd>
                            <?php endif; ?>
                        </dl>
                    <?php else: ?>
                        <p class="api-card-note">No run recorded yet. Check the cron job in cPanel.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="api-section">
        <h2 class="api-h2">Most-viewed rivers</h2>
        <p class="api-legend">Views are counted per day (UTC): <?= $p['top_days'] === 1 ? 'today and yesterday' : 'the last ' . $p['top_days'] . ' days' ?>. The hourly update preloads the top 10.</p>
        <?php if ($p['top']): ?>
            <ol class="api-top">
                <?php foreach ($p['top'] as $r): ?>
                    <li>
                        <a href="<?= htmlspecialchars(river_site_url($r['site_id'])) ?>"><?= htmlspecialchars($r['display_name'] ?? $r['site_id']) ?></a>
                        <span class="article-meta"><?= htmlspecialchars((string) $r['state']) ?></span>
                        <span class="api-top-views"><?= number_format((int) $r['views']) ?> view<?= (int) $r['views'] === 1 ? '' : 's' ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p>No river views yet.</p>
        <?php endif; ?>
    </section>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
