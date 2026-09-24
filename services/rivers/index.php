<?php
/*
 * index.php - River & stream gauges: type-ahead search, favorites, and the active gauges for a state.
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/rivers.php';

$states = river_states();
$state = strtoupper((string) ($_GET['state'] ?? ''));
if (!in_array($state, $states, true)) {
    $state = $states[0] ?? '';
}
$sites = $state ? river_sites_by_state($state) : [];

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
        </div>
    </header>

    <section class="container rivers-favs" aria-labelledby="favs-title" data-river-favs hidden>
        <h2 class="rivers-section-title" id="favs-title">Your favorites</h2>
        <ul class="rivers-fav-list"></ul>
    </section>

    <section class="container rivers-list-section" aria-labelledby="list-title">
        <div class="rivers-list-head">
            <h2 class="rivers-section-title" id="list-title">
                Active gauges<?= $state ? ' in ' . htmlspecialchars(RIVER_STATE_NAMES[$state] ?? $state) : '' ?>
            </h2>
            <?php if (count($states) > 1): ?>
                <form method="get" class="rivers-state-form">
                    <label for="river-state" class="visually-hidden">State</label>
                    <select id="river-state" name="state" onchange="this.form.submit()">
                        <?php foreach ($states as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"<?= $s === $state ? ' selected' : '' ?>><?= htmlspecialchars(RIVER_STATE_NAMES[$s] ?? $s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn-secondary">Go</button></noscript>
                </form>
            <?php endif; ?>
            <span class="rivers-count"><?= count($sites) ?> gauges</span>
        </div>

        <?php if ($sites): ?>
            <ul class="rivers-list">
                <?php foreach ($sites as $site): ?>
                    <li>
                        <a href="<?= htmlspecialchars(river_site_url($site['site_id'])) ?>"><?= htmlspecialchars($site['display_name']) ?></a>
                        <span class="rivers-list-meta">
                            <?= htmlspecialchars((string) $site['county']) ?>
                            <?php foreach (array_filter(explode(',', $site['params'])) as $code): ?>
                                <span class="rivers-chip"><?= htmlspecialchars(RIVER_PARAM_SHORT[$code] ?? $code) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="rivers-empty">The gauge list is being set up. Check back shortly.</p>
        <?php endif; ?>
    </section>

    <p class="container rivers-credit">Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised.</p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
