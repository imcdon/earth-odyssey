<?php
/*
 * index.php - River & stream gauges hub / state map.
 * First screen: compact title + search under the nav, map fills the rest of the viewport (no scroll to see it).
 * Below the fold: favorites, rankings, credit.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/rivers.php';

$viewer = require_permission('tools');
$explore = isset($_GET['explore']) || isset($_GET['state']);
if (!$explore) {
    require __DIR__ . '/dashboard.php';
    exit;
}

$states = river_states();
$state = strtoupper((string) ($_GET['state'] ?? ''));
if (!in_array($state, $states, true)) {
    $state = '';
}
$sites = $state ? river_sites_by_state($state) : [];
$riversBase = url('services/rivers/');
// US-map / "change state" needs explore=1 so logged-in users are not sent back to the dashboard.
$hubUrl = $riversBase . ($viewer ? '?explore=1' : '');
$units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
$stateName = $state ? (RIVER_STATE_NAMES[$state] ?? $state) : '';

$page_theme = 'rivers';
$page_title = ($state ? 'Gauges in ' . $stateName . ' | ' : 'River & Stream Gauges | ') . $site_name;
$body_class = 'rivers-mapfill';

if ($state) {
    $extra_head_html = '<link rel="stylesheet" href="' . htmlspecialchars(asset_url('assets/vendor/leaflet/leaflet.css')) . '">' . "\n";
}

require __DIR__ . '/../../includes/header.php';
?>

<div
    class="rivers-page"
    data-river-page="index"
    data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
    data-site-url="<?= htmlspecialchars(url('services/rivers/site.php')) ?>"
    <?php if ($state): ?>data-state="<?= htmlspecialchars($state) ?>" data-units="<?= htmlspecialchars($units) ?>"<?php endif; ?>
>
    <div class="rivers-viewport">
        <header class="rivers-head rivers-head--compact">
            <div class="container rivers-head-bar">
                <div class="rivers-head-copy">
                    <p class="kicker">Tools &middot; Water<?= $state ? ' &middot; ' . htmlspecialchars($stateName) : '' ?></p>
                    <h1 class="rivers-title"><?= $state ? htmlspecialchars($stateName) . ' gauges' : 'River &amp; stream gauges' ?></h1>
                </div>
                <div class="rivers-search">
                    <label for="river-q" class="visually-hidden">Search gauges</label>
                    <input
                        id="river-q"
                        type="search"
                        placeholder="Search a river, creek, or town (e.g. Kenai)"
                        autocomplete="off"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="false"
                        aria-controls="river-results"
                        data-river-search
                    >
                    <ul id="river-results" class="rivers-results" role="listbox" hidden></ul>
                </div>
            </div>
            <div class="container rivers-head-tools">
                <?php if ($state): ?>
                    <a class="rivers-change-state" href="<?= htmlspecialchars($hubUrl) ?>">Change state</a>
                    <div class="river-units" role="group" aria-label="Units">
                        <button type="button" data-units-btn="us" aria-pressed="<?= $units === 'us' ? 'true' : 'false' ?>">US</button>
                        <button type="button" data-units-btn="metric" aria-pressed="<?= $units === 'metric' ? 'true' : 'false' ?>">Metric</button>
                    </div>
                    <span class="rivers-count"><?= count($sites) ?> gauges</span>
                <?php else: ?>
                    <p class="rivers-head-hint">Pick a highlighted state, or search a river by name.</p>
                <?php endif; ?>
                <?php if ($viewer): ?>
                    <a class="btn-primary rivers-dash-cta" href="<?= htmlspecialchars(url('services/rivers/')) ?>">See dashboard</a>
                <?php else: ?>
                    <a class="btn-primary rivers-dash-cta" href="<?= htmlspecialchars(url('services/account/login.php') . '?next=' . rawurlencode(url('services/rivers/'))) ?>">Sign in to see dashboard</a>
                <?php endif; ?>
                <a class="rivers-browse-link" href="<?= htmlspecialchars(url('services/rivers/browse.php') . ($state ? '?state=' . $state : '')) ?>">Biggest changes today &rarr;</a>
            </div>
        </header>

        <div class="rivers-map-stage">
            <?php if (!$state): ?>
                <?php
                // Base path only — picker appends ?state=XX (state alone keeps logged-in users in explore mode).
                $pickerAction = $riversBase;
                $pickerCompact = true;
                require __DIR__ . '/../../includes/partials/river-state-picker.php';
                ?>
            <?php else: ?>
                <section class="river-state-map-section" aria-label="Map of <?= htmlspecialchars($stateName) ?>" data-river-state-map>
                    <div class="river-state-map-wrap">
                        <div id="river-state-map" class="river-state-map" role="application" aria-label="Map of gauges in <?= htmlspecialchars($stateName) ?>"></div>
                        <p class="river-state-map-status" data-map-status>Loading gauges…</p>
                        <p class="river-map-caption" data-map-caption hidden></p>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <div class="rivers-after">
        <section class="container rivers-favs" aria-labelledby="favs-title" data-river-favs hidden>
            <h2 class="rivers-section-title" id="favs-title">Your favorites</h2>
            <ul class="rivers-fav-list"></ul>
        </section>

        <?php if ($state): ?>
        <section class="container river-state-panels" aria-label="Gauge rankings for <?= htmlspecialchars($stateName) ?>" data-river-state-panels>
            <div class="rivers-list-head">
                <h2 class="rivers-section-title" id="panels-title">Browse gauges</h2>
                <span class="rivers-count"><?= count($sites) ?> gauges</span>
            </div>
            <?php
            $panelDefs = [
                'all'           => 'All streams A–Z',
                'popular'       => 'Most popular',
                'risers'        => 'Biggest risers %',
                'falls'         => 'Biggest falls %',
                'coldest_water' => 'Coldest water',
                'warmest_water' => 'Warmest water',
                'coldest_air'   => 'Coldest air',
                'warmest_air'   => 'Warmest air',
            ];
            foreach ($panelDefs as $key => $label):
            ?>
            <details class="river-panel" data-panel="<?= htmlspecialchars($key) ?>"<?= $key === 'all' ? ' open' : '' ?>>
                <summary class="river-panel-summary">
                    <span class="river-panel-summary-label"><?= htmlspecialchars($label) ?></span>
                    <span class="river-panel-summary-count" data-panel-count></span>
                </summary>
                <div class="river-panel-body">
                    <p class="river-panel-status" data-panel-status>Loading…</p>
                    <ul class="river-panel-list" data-panel-list hidden></ul>
                </div>
            </details>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised.<?= $state ? ' Map tiles &copy; Esri.' : '' ?></p>
    </div>
</div>

<?php if ($state): ?>
<script src="<?= htmlspecialchars(asset_url('assets/vendor/leaflet/leaflet.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
