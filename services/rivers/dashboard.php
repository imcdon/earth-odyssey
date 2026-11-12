<?php
/*
 * dashboard.php - Logged-in angler home: large panels for saved rivers.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/river-user.php';
require_once __DIR__ . '/../../includes/river-alerts.php';
require_once __DIR__ . '/../../includes/river-auto-ranges.php';

$user = require_permission('tools');
$units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
$dash = river_user_dashboard((int) $user['id'], $units);
$unread = river_alert_unread_count((int) $user['id']);
$events = river_alert_events_for_user((int) $user['id'], 12);
$exploreUrl = url('services/rivers/') . '?explore=1';

/* Live High/Low conditions for saved rivers — shown even before the alert cron fires. */
$liveAlerts = [];
foreach ($dash['sites'] as $panel) {
    $badge = $panel['badge'] ?? null;
    if (!$badge || !in_array($badge['level'], ['low', 'high'], true)) {
        continue;
    }
    $liveAlerts[] = [
        'site_id' => $panel['site_id'],
        'href' => $panel['href'],
        'level' => $badge['level'],
        'label' => $badge['label'],
        'message' => $panel['display_name'] . ': ' . $badge['sentence'],
        'when' => 'Now',
    ];
}
$fishOk = [];
foreach ($dash['sites'] as $panel) {
    $badge = $panel['badge'] ?? null;
    if (!$badge || !in_array($badge['level'], ['prime', 'fishable'], true)) {
        continue;
    }
    $fishOk[] = $panel;
}

$page_theme = 'rivers';
$page_title = 'My rivers | ' . $site_name;
$extra_head_html = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/vendor/uplot/uPlot.min.css')) . '">' . "\n";
require __DIR__ . '/../../includes/header.php';
?>

<div
    class="rivers-page river-dashboard"
    data-river-page="dashboard"
    data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
    data-units="<?= htmlspecialchars($units) ?>"
    data-csrf="<?= htmlspecialchars(csrf_token()) ?>"
    data-logged-in="1"
>
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker">Tools &middot; Water &middot; My rivers</p>
            <h1 class="rivers-title">My rivers</h1>
            <p class="rivers-intro">
                <?= (int) $dash['count'] ?> saved
                &middot; <a href="#river-alerts"><?= $unread ? (int) $unread . ' new alert' . ($unread === 1 ? '' : 's') : 'Alerts' ?></a>
            </p>
            <div class="rivers-head-tools">
                <a class="btn-primary" href="<?= htmlspecialchars($exploreUrl) ?>">Search for a new river</a>
                <div class="river-units" role="group" aria-label="Units">
                    <a href="?units=us" aria-current="<?= $units === 'us' ? 'true' : 'false' ?>">US</a>
                    <a href="?units=metric" aria-current="<?= $units === 'metric' ? 'true' : 'false' ?>">Metric</a>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('services/account/')) ?>">Account</a>
                <?php if (is_staff($user)): ?>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/')) ?>">Admin</a>
                <?php endif; ?>
                <a class="rivers-map-back" href="<?= htmlspecialchars(url('services/account/logout.php')) ?>">Sign out</a>
            </div>
        </div>
    </header>

    <section class="container river-alerts-strip" id="river-alerts" aria-label="Alerts">
        <div class="rivers-list-head">
            <h2 class="rivers-section-title">
                Alerts
                <?php if ($liveAlerts): ?> · <?= count($liveAlerts) ?> now<?php endif; ?>
                <?php if ($unread): ?> · <?= (int) $unread ?> new<?php endif; ?>
            </h2>
            <?php if ($events): ?>
                <button type="button" class="btn-ghost" data-alerts-mark-read>Mark all read</button>
            <?php endif; ?>
        </div>

        <?php if ($liveAlerts): ?>
            <h3 class="river-alerts-subhead">Out of range now</h3>
            <ul class="river-alert-list">
                <?php foreach ($liveAlerts as $ev): ?>
                    <li class="river-alert-item is-live">
                        <span class="river-badge is-<?= htmlspecialchars($ev['level']) ?> is-alert">
                            <span class="river-badge-mark" aria-hidden="true"></span><?= htmlspecialchars($ev['label']) ?>
                        </span>
                        <a href="<?= htmlspecialchars($ev['href']) ?>"><?= htmlspecialchars($ev['message']) ?></a>
                        <time><?= htmlspecialchars($ev['when']) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php elseif ($fishOk): ?>
            <p class="river-alerts-ok">
                <span class="river-badge is-prime is-fish"><span class="river-badge-mark" aria-hidden="true"></span>Fish</span>
                All <?= count($fishOk) ?> ranged river<?= count($fishOk) === 1 ? '' : 's' ?> look fishable right now.
            </p>
        <?php elseif ($dash['sites']): ?>
            <p class="rivers-empty">Set fishability ranges on a saved river to see High / Low alerts here.</p>
        <?php else: ?>
            <p class="rivers-empty">Save a river to track fishability alerts.</p>
        <?php endif; ?>

        <?php if ($events): ?>
            <h3 class="river-alerts-subhead">Recent notifications</h3>
            <ul class="river-alert-list">
                <?php foreach ($events as $ev): ?>
                    <li class="river-alert-item<?= empty($ev['read_at']) ? ' is-unread' : '' ?>">
                        <?php
                        $lvl = (string) ($ev['level'] ?? '');
                        $kind = in_array($lvl, ['prime', 'fishable'], true) ? 'fish' : (in_array($lvl, ['low', 'high'], true) ? 'alert' : '');
                        ?>
                        <?php if ($lvl !== '' && isset(RIVER_BADGES[$lvl])): ?>
                            <span class="river-badge is-<?= htmlspecialchars($lvl) ?><?= $kind ? ' is-' . $kind : '' ?>">
                                <span class="river-badge-mark" aria-hidden="true"></span><?= htmlspecialchars(RIVER_BADGES[$lvl]) ?>
                            </span>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars(river_site_url($ev['site_id'], ['units' => $units])) ?>"><?= htmlspecialchars($ev['message']) ?></a>
                        <time datetime="<?= htmlspecialchars($ev['created_at']) ?>"><?= htmlspecialchars(river_time_ago($ev['created_at'] . ' UTC')) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="container river-dash-panels" aria-label="Saved rivers">
        <?php if (!$dash['sites']): ?>
            <p class="rivers-empty">No rivers saved yet. <a href="<?= htmlspecialchars($exploreUrl) ?>">Search the map</a> and save gauges you care about.</p>
        <?php else: ?>
            <?php foreach ($dash['sites'] as $panel): ?>
                <article class="river-dash-panel" data-dash-site="<?= htmlspecialchars($panel['site_id']) ?>">
                    <header class="river-dash-panel-head">
                        <div>
                            <p class="kicker"><?= htmlspecialchars($panel['state_name']) ?><?= $panel['county'] ? ' &middot; ' . htmlspecialchars((string) $panel['county']) : '' ?></p>
                            <h2 class="river-dash-title">
                                <a href="<?= htmlspecialchars($panel['href']) ?>"><?= htmlspecialchars($panel['display_name']) ?></a>
                                <?php if ($panel['badge']): ?>
                                    <?php
                                    $bl = $panel['badge']['level'];
                                    $bk = in_array($bl, ['prime', 'fishable'], true) ? 'fish' : 'alert';
                                    ?>
                                    <span class="river-badge is-<?= htmlspecialchars($bl) ?> is-<?= $bk ?>" title="<?= htmlspecialchars($panel['badge']['title']) ?>"><span class="river-badge-mark" aria-hidden="true"></span><?= htmlspecialchars($panel['badge']['label']) ?></span>
                                <?php elseif (!$panel['has_range']): ?>
                                    <span class="river-dash-range-missing">No range set</span>
                                <?php endif; ?>
                            </h2>
                            <p class="river-site-meta">USGS <?= htmlspecialchars($panel['usgs_number']) ?></p>
                            <?php if ($panel['badge']): ?>
                                <p class="river-dash-fishability"><?= htmlspecialchars($panel['badge']['sentence']) ?></p>
                            <?php endif; ?>
                            <p class="river-dash-alert-status">
                                <?php if ($panel['alerts_on']): ?>
                                    Alerts on
                                    <?php
                                    $ch = [];
                                    if (!empty($panel['alert_channels']['in_app'])) {
                                        $ch[] = 'in-app';
                                    }
                                    if (!empty($panel['alert_channels']['email'])) {
                                        $ch[] = 'email';
                                    }
                                    if (!empty($panel['alert_channels']['sms'])) {
                                        $ch[] = 'SMS';
                                    }
                                    echo $ch ? ' · ' . htmlspecialchars(implode(', ', $ch)) : '';
                                    ?>
                                    · <a href="<?= htmlspecialchars($panel['href']) ?>#my-alerts-title">Edit</a>
                                <?php else: ?>
                                    Alerts off · <a href="<?= htmlspecialchars($panel['href']) ?>#my-alerts-title">Set alerts</a>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="river-dash-panel-actions">
                            <a class="btn-secondary" href="<?= htmlspecialchars($panel['href']) ?>">Open</a>
                            <button type="button" class="btn-ghost" data-unsave-site="<?= htmlspecialchars($panel['site_id']) ?>">Remove</button>
                        </div>
                    </header>
                    <?php if ($panel['readings']): ?>
                        <div class="river-cards river-dash-cards">
                            <?php foreach ($panel['readings'] as $r): ?>
                                <div class="river-card">
                                    <span class="river-card-label"><?= htmlspecialchars($r['label']) ?></span>
                                    <span class="river-card-value"><?= river_format($r['value']) ?> <small><?= htmlspecialchars($r['unit']) ?></small></span>
                                    <span class="river-card-time"><?= htmlspecialchars($r['ago']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="river-dash-chart" data-dash-chart data-site="<?= htmlspecialchars($panel['site_id']) ?>" data-param="<?= htmlspecialchars($panel['param_list'][0] ?? '00060') ?>">
                        <div class="river-chart-skeleton">Loading chart&hellip;</div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised.</p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/vendor/uplot/uPlot.iife.min.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
