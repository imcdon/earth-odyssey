<?php
/*
 * site.php - One gauge: latest readings, window tabs, this-period-vs-last-year chart, and stats.
 * URL state: ?id=USGS-15266300&win=1W&p=00060&units=us
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/river-ranges.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/river-user.php';
require_once __DIR__ . '/../../includes/river-alerts.php';
require_once __DIR__ . '/../../includes/river-auto-ranges.php';

$id = (string) ($_GET['id'] ?? '');
$site = usgs_valid_site_id($id) ? river_site($id) : null;

$page_theme = 'rivers';

if (!$site) {
    http_response_code(404);
    $page_title = 'Gauge not found | ' . $site_name;
    require __DIR__ . '/../../includes/header.php';
    echo '<div class="rivers-page"><header class="rivers-head rivers-head--compact"><div class="container">'
        . '<p class="kicker"><a href="' . htmlspecialchars(url('services/rivers/')) . '">Stream gauges</a></p>'
        . '<h1 class="rivers-title">Gauge not found</h1>'
        . '<p class="rivers-intro">This gauge isn\'t in our list. It may have stopped reporting.</p>'
        . '<div class="rivers-head-tools"><a class="btn-secondary" href="' . htmlspecialchars(url('services/rivers/')) . '">Search gauges</a></div>'
        . '</div></header></div>';
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
$user = isset($_COOKIE[session_name()]) ? current_user() : null;
$canUseTools = $user && account_is_approved($user) && user_has_permission('tools', $user);
$staff = $user && is_staff($user);
$canOwnRanges = $canUseTools && user_has_permission('own_ranges', $user);
$latest = usgs_latest_for_site($site['site_id']);
$adminRanges = river_ranges_for_sites([$site['site_id']])[$site['site_id']] ?? [];
$effective = river_effective_ranges_for_user($user ? (int) $user['id'] : null, [$site['site_id']])[$site['site_id']] ?? [];
$badge = $effective
    ? river_badge($effective, array_map(fn($r) => ['v' => $r['value'], 't' => strtotime($r['time'])], $latest['readings']))
    : null;
$userRanges = $user ? (river_user_ranges_for_sites((int) $user['id'], [$site['site_id']])[$site['site_id']] ?? []) : [];
$autoRanges = river_auto_ranges_for_sites([$site['site_id']])[$site['site_id']] ?? [];
// Build suggestions on first visit if the weekly cron hasn't covered this gauge yet.
if ($canUseTools && !$autoRanges) {
    river_auto_compute_site($site['site_id']);
    $autoRanges = river_auto_ranges_for_sites([$site['site_id']])[$site['site_id']] ?? [];
}
$saved = $user ? river_user_has_site((int) $user['id'], $site['site_id']) : false;
$alertSub = $user ? river_alert_subscription((int) $user['id'], $site['site_id'], in_array('00060', $site['param_list'], true) ? '00060' : ($site['param_list'][0] ?? '00060')) : null;
$usgsNumber = preg_replace('/^[A-Z]+-/', '', $site['site_id']);
$badgeSource = $badge ? ($effective[$badge['param']]['_source'] ?? 'site') : null;

$page_title = $site['display_name'] . ' | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';

$rangeParams = [];
foreach (['00060' => 'Flow', '00065' => 'Gage height', '00010' => 'Water temperature'] as $code => $label) {
    if (in_array($code, $site['param_list'], true) || isset($userRanges[$code]) || isset($autoRanges[$code]) || isset($adminRanges[$code])) {
        $rangeParams[$code] = $label;
    }
}
$rangeFields = ['fish_low' => 'Too low below', 'ideal_low' => 'Prime from', 'ideal_high' => 'Prime up to', 'fish_high' => 'Blown out above'];
$fmtRange = static function (?float $v, string $code, string $units): string {
    if ($v === null) {
        return '';
    }
    $shown = river_convert($code, $v, $units)[0];
    return abs($shown) >= 100 ? number_format($shown, 0, '.', '') : rtrim(rtrim(number_format($shown, 2, '.', ''), '0'), '.');
};
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
    data-logged-in="<?= $canUseTools ? '1' : '0' ?>"
    data-csrf="<?= htmlspecialchars($canUseTools ? csrf_token() : '') ?>"
    data-saved="<?= $saved ? '1' : '0' ?>"
>
    <header class="rivers-head rivers-head--compact river-site-head">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a> &middot; <a href="<?= htmlspecialchars(url('services/rivers/') . '?explore=1&state=' . $site['state']) ?>"><?= htmlspecialchars(RIVER_STATE_NAMES[$site['state']] ?? $site['state']) ?></a></p>
            <h1 class="rivers-title"><?= htmlspecialchars($site['display_name']) ?><?= $badge ? ' ' . river_badge_html($badge, $units) : '' ?></h1>
            <p class="river-site-meta">
                <?= htmlspecialchars((string) $site['county']) ?>
                &middot; USGS <?= htmlspecialchars($usgsNumber) ?>
                &middot; <a href="https://waterdata.usgs.gov/monitoring-location/<?= htmlspecialchars(rawurlencode($usgsNumber)) ?>/" rel="noopener">USGS page</a>
                <?php if ($badgeSource): ?> &middot; Badge: <?= htmlspecialchars(['user' => 'Yours', 'site' => 'Site', 'auto' => 'Suggested'][$badgeSource] ?? $badgeSource) ?><?php endif; ?>
            </p>
            <div class="rivers-head-tools river-site-actions">
                <?php if ($canUseTools): ?>
                    <button type="button" class="btn-secondary" data-river-save aria-pressed="<?= $saved ? 'true' : 'false' ?>"><?= $saved ? '★ Saved to my rivers' : '☆ Save to my rivers' ?></button>
                <?php else: ?>
                    <button type="button" class="btn-secondary river-fav" aria-pressed="false" data-river-fav>&#9734; Save to favorites</button>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('services/account/login.php') . '?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '')) ?>">Sign in for saved rivers</a>
                <?php endif; ?>
                <?php if ($user && user_has_permission('fishing_reports', $user)): ?>
                    <a class="btn-primary" href="<?= htmlspecialchars(url('services/rivers/report-edit.php') . '?site=' . rawurlencode($site['site_id'])) ?>">Start a report</a>
                <?php endif; ?>
                <?php if ($staff): ?>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-range-edit.php') . '?site=' . rawurlencode($site['site_id'])) ?>"><?= $adminRanges ? 'Edit site range' : 'Set site range' ?></a>
                <?php endif; ?>
                <div class="river-units" role="group" aria-label="Units">
                    <button type="button" data-units-btn="us" aria-pressed="<?= $units === 'us' ? 'true' : 'false' ?>">US</button>
                    <button type="button" data-units-btn="metric" aria-pressed="<?= $units === 'metric' ? 'true' : 'false' ?>">Metric</button>
                </div>
                <a class="rivers-map-back" href="<?= htmlspecialchars(url('services/rivers/') . '?explore=1&state=' . rawurlencode($site['state'])) ?>" data-river-back>&larr; Back</a>
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

    <?php if ($canOwnRanges): ?>
    <section class="container river-user-tools" aria-labelledby="my-ranges-title">
        <h2 class="rivers-section-title" id="my-ranges-title">My fishability ranges</h2>
        <p class="rivers-intro">Your bands drive the badge on your dashboard and alerts. Suggested values come from seasonal percentiles of daily means.</p>
        <p class="form-success" data-range-status hidden></p>
        <p class="form-error" data-range-error hidden></p>
        <form class="admin-form river-user-range-form" data-user-range-form>
            <input type="hidden" name="units" value="<?= htmlspecialchars($units) ?>">
            <?php foreach ($rangeParams as $code => $label): ?>
                <?php
                $mine = $userRanges[$code] ?? null;
                $auto = $autoRanges[$code] ?? null;
                $stats = is_array($auto['stats'] ?? null) ? $auto['stats'] : (is_string($auto['stats'] ?? null) ? (json_decode($auto['stats'], true) ?: []) : []);
                ?>
                <fieldset data-range-param="<?= htmlspecialchars($code) ?>">
                    <legend><?= htmlspecialchars($label) ?></legend>
                    <?php if ($auto): ?>
                        <p class="report-help">
                            Suggested (n=<?= (int) $auto['n_sample'] ?><?= isset($stats['median']) ? ', typical ' . htmlspecialchars($fmtRange((float) $stats['median'], $code, $units)) . ' ' . htmlspecialchars(river_convert($code, 0, $units)[1]) : '' ?>).
                            <button type="button" class="btn-ghost" data-apply-auto="<?= htmlspecialchars($code) ?>">Apply suggested</button>
                        </p>
                    <?php else: ?>
                        <p class="report-help">No suggestion yet for this measurement — not enough seasonal daily history in the last few weeks of the year (need about a week of comparable days across years).</p>
                    <?php endif; ?>
                    <div class="river-range-grid">
                        <?php foreach ($rangeFields as $field => $flabel): ?>
                            <label>
                                <?= htmlspecialchars($flabel) ?>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    name="r[<?= htmlspecialchars($code) ?>][<?= htmlspecialchars($field) ?>]"
                                    value="<?= htmlspecialchars($fmtRange(isset($mine[$field]) && $mine[$field] !== null ? (float) $mine[$field] : null, $code, $units)) ?>"
                                    data-auto-value="<?= htmlspecialchars($fmtRange(isset($auto[$field]) && $auto[$field] !== null ? (float) $auto[$field] : null, $code, $units)) ?>"
                                >
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            <?php endforeach; ?>
            <button type="submit" class="btn-primary">Save my ranges</button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($canUseTools): ?>
    <section class="container river-user-tools" aria-labelledby="my-alerts-title">
        <h2 class="rivers-section-title" id="my-alerts-title">Alerts</h2>
        <p class="rivers-intro">Get notified when this gauge crosses your fishability bands. SMS needs a phone number on your <a href="<?= htmlspecialchars(url('services/account/')) ?>">account</a>.</p>
        <p class="form-success" data-alert-status hidden></p>
        <p class="form-error" data-alert-error hidden></p>
        <form class="admin-form river-alert-form" data-alert-form>
            <?php
            $alertParam = $alertSub['param'] ?? (in_array('00060', $site['param_list'], true) ? '00060' : ($site['param_list'][0] ?? '00060'));
            $on = static fn(string $k, $default = 1) => (int) ($alertSub[$k] ?? $default);
            ?>
            <label for="alert-param">Watch</label>
            <select id="alert-param" name="param">
                <?php foreach ($rangeParams as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>"<?= $alertParam === $code ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <fieldset>
                <legend>When</legend>
                <label class="checkbox-label"><input type="checkbox" name="trigger_enter_prime" value="1"<?= $on('trigger_enter_prime') ? ' checked' : '' ?>> Enters Prime</label>
                <label class="checkbox-label"><input type="checkbox" name="trigger_leave_fishable" value="1"<?= $on('trigger_leave_fishable') ? ' checked' : '' ?>> Leaves fishable range</label>
                <label class="checkbox-label"><input type="checkbox" name="trigger_below_low" value="1"<?= $on('trigger_below_low') ? ' checked' : '' ?>> Too low</label>
                <label class="checkbox-label"><input type="checkbox" name="trigger_above_high" value="1"<?= $on('trigger_above_high') ? ' checked' : '' ?>> Blown out / too high</label>
                <label class="checkbox-label"><input type="checkbox" name="trigger_unusual" value="1"<?= $on('trigger_unusual', 0) ? ' checked' : '' ?>> Unusual for this time of year</label>
            </fieldset>

            <fieldset>
                <legend>How</legend>
                <label class="checkbox-label"><input type="checkbox" name="channel_in_app" value="1"<?= $on('channel_in_app') ? ' checked' : '' ?>> In-app</label>
                <label class="checkbox-label"><input type="checkbox" name="channel_email" value="1"<?= $on('channel_email') ? ' checked' : '' ?>> Email</label>
                <label class="checkbox-label"><input type="checkbox" name="channel_sms" value="1"<?= $on('channel_sms', 0) ? ' checked' : '' ?>> SMS</label>
            </fieldset>

            <label for="cooldown_minutes">Cooldownest between alerts (minutes)</label>
            <input type="number" id="cooldown_minutes" name="cooldown_minutes" min="60" max="10080" value="<?= (int) ($alertSub['cooldown_minutes'] ?? 360) ?>">

            <div class="form-actions">
                <button type="submit" class="btn-primary">Save alerts</button>
                <?php if ($alertSub): ?>
                    <button type="button" class="btn-secondary" data-alert-delete>Remove alerts</button>
                <?php endif; ?>
            </div>
        </form>
    </section>
    <?php endif; ?>

    <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised. Daily windows use daily means; 3D and 1W use 15-minute readings.</p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/vendor/uplot/uPlot.iife.min.js')) ?>" defer></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
