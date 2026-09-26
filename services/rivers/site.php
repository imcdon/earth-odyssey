<?php
/*
 * site.php - One gauge: latest readings, window tabs, this-period-vs-last-year chart, and stats.
 * URL state: ?id=USGS-15266300&win=1W&p=00060&units=us
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/river-ranges.php';
require __DIR__ . '/../../includes/auth.php';

$id = (string) ($_GET['id'] ?? '');
$site = usgs_valid_site_id($id) ? river_site($id) : null;

$page_theme = 'rivers';

if (!$site) {
    http_response_code(404);
    $page_title = 'Gauge not found | ' . $site_name;
    require __DIR__ . '/../../includes/header.php';
    echo '<section class="container rivers-missing"><h1 class="section-title">Gauge not found</h1>'
        . '<p>This gauge isn\'t in our list. It may have stopped reporting.</p>'
        . '<p><a class="btn-secondary" href="' . htmlspecialchars(url('services/rivers/')) . '">Search gauges</a></p></section>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

$win = strtoupper((string) ($_GET['win'] ?? RIVER_DEFAULT_WINDOW));
if (!isset(RIVER_WINDOWS[$win])) {
    $win = RIVER_DEFAULT_WINDOW;
}
$units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
$param = (string) ($_GET['p'] ?? '');
if (!in_array($param, $site['param_list'], true)) {
    $param = $site['param_list'][0] ?? '00060';
}

if (empty($_SERVER['HTTP_X_EO_OFFLINE_REFRESH'])) {
    river_record_view($site['site_id']);
}
// Only look up staff when a session cookie exists, so ordinary visitors never get a session.
$staff = isset($_COOKIE[session_name()]) ? current_user() : null;
$latest = usgs_latest_for_site($site['site_id']);
$ranges = river_ranges_for_sites([$site['site_id']])[$site['site_id']] ?? [];
$badge = $ranges ? river_badge($ranges, array_map(fn($r) => ['v' => $r['value'], 't' => strtotime($r['time'])], $latest['readings'])) : null;
$usgsNumber = preg_replace('/^[A-Z]+-/', '', $site['site_id']);

$page_title = $site['display_name'] . ' | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/vendor/uplot/uPlot.min.css')) ?>">

<div
    class="rivers-page river-site"
    data-river-page="site"
    data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
    data-site="<?= htmlspecialchars($site['site_id']) ?>"
    data-name="<?= htmlspecialchars($site['display_name']) ?>"
    data-win="<?= htmlspecialchars($win) ?>"
    data-param="<?= htmlspecialchars($param) ?>"
    data-units="<?= htmlspecialchars($units) ?>"
    data-rendered="<?= gmdate('c') ?>"
>
    <header class="rivers-head river-site-head">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a> &middot; <a href="<?= htmlspecialchars(url('services/rivers/browse.php') . '?state=' . $site['state']) ?>"><?= htmlspecialchars(RIVER_STATE_NAMES[$site['state']] ?? $site['state']) ?></a></p>
            <h1 class="rivers-title"><?= htmlspecialchars($site['display_name']) ?><?= $badge ? ' ' . river_badge_html($badge, $units) : '' ?></h1>
            <p class="river-site-meta">
                <?= htmlspecialchars((string) $site['county']) ?>
                &middot; USGS <?= htmlspecialchars($usgsNumber) ?>
                &middot; <a href="https://waterdata.usgs.gov/monitoring-location/<?= htmlspecialchars(rawurlencode($usgsNumber)) ?>/" rel="noopener">USGS page</a>
            </p>
            <div class="river-site-actions">
                <button type="button" class="btn-secondary river-fav" aria-pressed="false" data-river-fav>&#9734; Save to favorites</button>
                <?php if ($staff): ?>
                    <a class="btn-primary" href="<?= htmlspecialchars(url('admin/river-report-edit.php') . '?site=' . rawurlencode($site['site_id'])) ?>">Start a report</a>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-range-edit.php') . '?site=' . rawurlencode($site['site_id'])) ?>"><?= $ranges ? 'Edit range' : 'Set range' ?></a>
                <?php endif; ?>
                <div class="river-units" role="group" aria-label="Units">
                    <button type="button" data-units-btn="us" aria-pressed="<?= $units === 'us' ? 'true' : 'false' ?>">US</button>
                    <button type="button" data-units-btn="metric" aria-pressed="<?= $units === 'metric' ? 'true' : 'false' ?>">Metric</button>
                </div>
            </div>
        </div>
    </header>

    <section class="container river-now" aria-labelledby="now-title">
        <h2 class="rivers-section-title" id="now-title">Right now</h2>
        <?php if ($badge): ?>
            <p class="river-fishability">
                <?= river_badge_html($badge, $units) ?>
                <?php foreach (['us', 'metric'] as $u): ?>
                    <span data-units-only="<?= $u ?>"<?= $u === $units ? '' : ' hidden' ?>><?= htmlspecialchars(river_badge_sentence($badge, $u)) ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
        <?php if ($latest['readings']): ?>
            <div class="river-cards">
                <?php foreach ($latest['readings'] as $code => $r): ?>
                    <?php [$v, $u] = river_convert($code, $r['value'], $units); ?>
                    <div class="river-card" data-reading data-code="<?= htmlspecialchars($code) ?>" data-value="<?= htmlspecialchars((string) $r['value']) ?>">
                        <span class="river-card-label"><?= htmlspecialchars($r['label']) ?></span>
                        <span class="river-card-value"><span data-reading-value><?= river_format($v) ?></span> <small data-reading-unit><?= htmlspecialchars($u) ?></small></span>
                        <span class="river-card-time" data-time="<?= htmlspecialchars($r['time']) ?>"><?= htmlspecialchars(river_time_ago($r['time'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($latest['stale'] && $latest['as_of']): ?>
                <p class="river-stale">USGS isn't responding right now. Showing data as of <?= htmlspecialchars(gmdate('M j, g:i A', strtotime($latest['as_of']))) ?> UTC.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="river-stale">No current readings from USGS for this gauge.</p>
        <?php endif; ?>
    </section>

    <section class="container river-compare" aria-labelledby="compare-title">
        <h2 class="rivers-section-title" id="compare-title">This period vs. last year</h2>

        <nav class="river-tabs" aria-label="Time window">
            <?php foreach (RIVER_WINDOWS as $key => $cfg): ?>
                <a
                    href="<?= htmlspecialchars(river_site_url($site['site_id'], ['win' => $key, 'p' => $param, 'units' => $units])) ?>"
                    data-win-tab="<?= htmlspecialchars($key) ?>"
                    title="<?= htmlspecialchars($cfg['label']) ?>"
                    <?= $key === $win ? 'aria-current="true"' : '' ?>
                ><?= htmlspecialchars($key) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (count($site['param_list']) > 1): ?>
            <div class="river-params" role="group" aria-label="Measurement">
                <?php foreach ($site['param_list'] as $code): ?>
                    <button type="button" data-param-btn="<?= htmlspecialchars($code) ?>" aria-pressed="<?= $code === $param ? 'true' : 'false' ?>">
                        <?= htmlspecialchars(USGS_PARAMS[$code]['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="river-chart" data-river-chart aria-live="polite">
            <div class="river-chart-skeleton">Loading chart&hellip;</div>
        </div>
        <noscript><p class="river-stale">Turn on JavaScript to see the comparison chart.</p></noscript>

        <div class="river-stats-wrap">
            <table class="river-stats" data-river-stats hidden>
                <thead>
                    <tr><th scope="col">Measurement</th><th scope="col">This period</th><th scope="col">Last year</th><th scope="col">Change</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <p class="river-stats-note" data-river-note></p>
    </section>

    <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised. Daily windows use daily means; 3D and 1W use 15-minute readings.</p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/vendor/uplot/uPlot.iife.min.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
