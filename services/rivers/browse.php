<?php
/*
 * browse.php - Biggest 24-hour and 1-week changes per state (rising and dropping), precomputed hourly by
 * cron/river-snapshot.php. Reads MySQL only; never calls USGS.
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/river-movers.php';
require __DIR__ . '/../../includes/river-ranges.php';

$states = river_states();
$state = strtoupper((string) ($_GET['state'] ?? ''));
if (!in_array($state, $states, true)) {
    $state = '';
}
$win = (string) ($_GET['win'] ?? '24h');
if (!isset(RIVER_MOVER_WINDOWS[$win])) {
    $win = '24h';
}
$units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';

$data = $state ? river_movers_for_state($state) : ['movers' => [], 'computed_at' => null];
$stateName = RIVER_STATE_NAMES[$state] ?? $state;
$badges = $state ? river_badges_from_snapshots(river_ranges_for_state($state)) : [];

$sections = [
    '00060' => ['title' => 'Flow', 'note' => 'Ranked by percent change. Gauges under ' . RIVER_MOVER_MIN_CFS . ' cfs are left out.'],
    '00010' => ['title' => 'Water temperature', 'note' => 'Ranked by degrees of change.'],
    '00065' => ['title' => 'Gage height', 'note' => 'Ranked by change in water level.'],
];

/*
 * One ranked row, written compactly (the page can hold 60 of these). Values are rendered in the reader's units;
 * data-code/data-a/data-b let rivers.js re-render them when the units toggle changes.
 */
function mover_row(array $m, string $code, string $win, string $units, array $badges): string
{
    $href = river_site_url($m['site_id'], ['win' => $win === '24h' ? '3D' : '1W', 'p' => $code, 'units' => $units]);
    $then = (float) $m['value_then'];
    $now = (float) $m['value_now'];
    if (RIVER_MOVER_PARAMS[$code] === 'pct' && $m['pct'] !== null) {
        $pct = (float) $m['pct'];
        $change = '<b>' . ($pct > 0 ? '+' : '') . number_format($pct, abs($pct) >= 100 ? 0 : 1) . '%</b>';
    } else {
        $change = '<b data-code="' . $code . '" data-delta="' . ($now - $then) . '">' . htmlspecialchars(mover_delta_text($code, $now - $then, $units)) . '</b>';
    }
    [$a, $unit] = river_convert($code, $then, $units);
    [$b] = river_convert($code, $now, $units);

    return '<li class="river-mover"><div class="river-mover-main"><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($m['display_name']) . '</a>'
        . '<span class="river-mover-meta">' . river_badge_html($badges[$m['site_id']] ?? null, $units) . htmlspecialchars((string) $m['county']) . ' &middot; ' . htmlspecialchars(river_time_ago($m['observed_now'] . ' UTC')) . '</span></div>'
        . river_sparkline($m['spark'])
        . '<div class="river-mover-values">' . $change
        . '<span class="river-mover-range" data-code="' . $code . '" data-a="' . $then . '" data-b="' . $now . '">'
        . river_format($a) . ' &rarr; ' . river_format($b) . ' ' . htmlspecialchars($unit) . '</span></div></li>';
}

function mover_delta_text(string $code, float $delta, string $units): string
{
    $shown = $code === '00010' ? ($units === 'us' ? $delta * 9 / 5 : $delta) : river_convert($code, $delta, $units)[0];
    return ($shown > 0 ? '+' : '') . river_format($shown) . ' ' . river_convert($code, 0, $units)[1];
}

$page_theme = 'rivers';
$page_title = 'Biggest River Changes' . ($state ? ' in ' . $stateName : '') . ' | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page river-browse" data-river-page="browse" data-units="<?= htmlspecialchars($units) ?>">
    <header class="rivers-head">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/') . ($state ? '?state=' . $state : '')) ?>">Stream gauges</a><?= $state ? ' &middot; ' . htmlspecialchars($stateName) : '' ?></p>
            <h1 class="rivers-title">Biggest changes</h1>
            <p class="rivers-intro">Which rivers are rising, dropping, warming, or cooling fastest right now. Rankings update every hour.</p>
            <?php if ($state): ?>
            <div class="river-site-actions">
                <a class="rivers-change-state" href="<?= htmlspecialchars(url('services/rivers/browse.php')) ?>">Change state</a>
                <div class="river-units" role="group" aria-label="Units">
                    <button type="button" data-units-btn="us" aria-pressed="<?= $units === 'us' ? 'true' : 'false' ?>">US</button>
                    <button type="button" data-units-btn="metric" aria-pressed="<?= $units === 'metric' ? 'true' : 'false' ?>">Metric</button>
                </div>
                <?php if ($data['computed_at']): ?>
                    <span class="rivers-count">Updated <?= htmlspecialchars(river_time_ago($data['computed_at'] . ' UTC')) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$state): ?>
        <?php $pickerAction = url('services/rivers/browse.php'); $pickerQuery = ['win' => $win]; require __DIR__ . '/../../includes/partials/river-state-picker.php'; ?>
    <?php else: ?>
    <section class="container river-compare">
        <nav class="river-tabs" aria-label="Time window">
            <?php foreach (RIVER_MOVER_WINDOWS as $key => $label): ?>
                <a href="?<?= htmlspecialchars(http_build_query(['state' => $state, 'win' => $key, 'units' => $units])) ?>"<?= $key === $win ? ' aria-current="true"' : '' ?>><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if (empty($data['movers'][$win])): ?>
            <p class="rivers-empty">The <?= htmlspecialchars(RIVER_MOVER_WINDOWS[$win]) ?> rankings will appear after the next hourly update.</p>
        <?php endif; ?>

        <?php foreach ($sections as $code => $section): ?>
            <?php $lists = $data['movers'][$win][$code] ?? null; ?>
            <?php if (!$lists) continue; ?>
            <section class="river-movers-section" aria-labelledby="m-<?= $code ?>">
                <div class="river-movers-head">
                    <h2 class="river-movers-title" id="m-<?= $code ?>"><?= htmlspecialchars($section['title']) ?></h2>
                    <p class="river-stats-note"><?= htmlspecialchars($section['note']) ?></p>
                </div>
                <div class="river-movers-cols">
                    <?php foreach (['up' => 'Rising', 'down' => 'Dropping'] as $dir => $dirLabel): ?>
                        <div class="river-movers-col is-<?= $dir ?>">
                            <h3 class="rivers-section-title"><?= $dir === 'up' ? '&#9650;' : '&#9660;' ?> <?= $dirLabel ?></h3>
                            <?php if (empty($lists[$dir])): ?>
                                <p class="rivers-empty">None <?= $dir === 'up' ? 'rising' : 'dropping' ?> enough to rank.</p>
                            <?php else: ?>
                                <ol class="river-movers-list"><?php foreach ($lists[$dir] as $m) echo mover_row($m, $code, $win, $units, $badges); ?></ol>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <p class="container rivers-credit">
        Data: <a href="https://waterdata.usgs.gov/" rel="noopener">USGS Water Data</a>. Provisional data may be revised.
        24-hour changes compare the latest reading with the reading closest to 24 hours earlier. 1-week changes compare
        it with the daily average from 7 days earlier (or the reading from 7 days earlier where USGS has no daily average).
    </p>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
