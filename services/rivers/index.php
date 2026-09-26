<?php
/*
 * index.php - River & stream gauges: type-ahead search, favorites, and the active gauges for a state.
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/river-ranges.php';

$states = river_states();
$state = strtoupper((string) ($_GET['state'] ?? ''));
if (!in_array($state, $states, true)) {
    $state = '';
}
$sites = $state ? river_sites_by_state($state) : [];
$badges = $state ? river_badges_from_snapshots(river_ranges_for_state($state)) : [];
$hubUrl = url('services/rivers/');

$page_theme = 'rivers';
$page_title = 'River & Stream Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div
    class="rivers-page"
    data-river-page="index"
    data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
    data-site-url="<?= htmlspecialchars(url('services/rivers/site.php')) ?>"
>
    <header class="rivers-head">
        <div class="container">
            <p class="kicker">Tools &middot; Water</p>
            <h1 class="rivers-title">River &amp; stream gauges</h1>
            <p class="rivers-intro">Live USGS flow, height, and water temperature. Compare this week, this month, or this year against the same stretch last year.</p>

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
            <a class="rivers-browse-link" href="<?= htmlspecialchars(url('services/rivers/browse.php') . ($state ? '?state=' . $state : '')) ?>">Biggest changes today &rarr;</a>
        </div>
    </header>

    <section class="container rivers-favs" aria-labelledby="favs-title" data-river-favs hidden>
        <h2 class="rivers-section-title" id="favs-title">Your favorites</h2>
        <ul class="rivers-fav-list"></ul>
    </section>

    <?php if (!$state): ?>
        <?php $pickerAction = $hubUrl; require __DIR__ . '/../../includes/partials/river-state-picker.php'; ?>
    <?php else: ?>
    <section class="container rivers-list-section" aria-labelledby="list-title">
        <div class="rivers-list-head">
            <h2 class="rivers-section-title" id="list-title">
                Active gauges in <?= htmlspecialchars(RIVER_STATE_NAMES[$state] ?? $state) ?>
            </h2>
            <a class="rivers-change-state" href="<?= htmlspecialchars($hubUrl) ?>">Change state</a>
            <span class="rivers-count"><?= count($sites) ?> gauges</span>
        </div>

        <?php if ($sites): ?>
            <ul class="rivers-list"><?php
                foreach ($sites as $site) {
                    $chips = '';
                    foreach (array_filter(explode(',', $site['params'])) as $code) {
                        $chips .= '<span class="rivers-chip">' . htmlspecialchars(RIVER_PARAM_SHORT[$code] ?? $code) . '</span>';
                    }
                    echo '<li><a href="', htmlspecialchars(river_site_url($site['site_id'])), '">', htmlspecialchars($site['display_name']), '</a>',
                        '<span class="rivers-list-meta">', river_badge_html($badges[$site['site_id']] ?? null, 'us'), htmlspecialchars((string) $site['county']), ' ', $chips, '</span></li>';
                }
            ?></ul>
        <?php else: ?>
            <p class="rivers-empty">The gauge list is being set up. Check back shortly.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised.</p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
