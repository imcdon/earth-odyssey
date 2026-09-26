<?php
/*
 * river-ranges.php - Every river with a fishability range, its badge right now, and a search to add another.
 * Editors and authors can set ranges.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-ranges.php';

$user = require_login();
$q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
$results = $q !== '' ? river_search($q, 20) : [];
$rivers = river_ranges_all();
$badges = river_badges_from_snapshots(array_map(fn($r) => $r['ranges'], $rivers));

$page_theme = 'admin';
$page_title = 'Fishability Ranges | ' . $site_name;
require __DIR__ . '/../includes/header.php';

function range_cell(?array $range): string
{
    if (!$range) {
        return '<span class="range-none">–</span>';
    }
    $ideal = river_range_band($range, 'ideal', 'us');
    $fish = river_range_band($range, 'fish', 'us');
    return '<span class="range-ideal">Prime ' . htmlspecialchars($ideal ?? 'any') . '</span>'
        . ($fish ? '<span class="range-fish">Fishable ' . htmlspecialchars($fish) . '</span>' : '');
}
?>

<section class="container admin-page">
    <h1 class="section-title">Fishability ranges</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>
    <p>Each river's badge compares its latest flow (or gage height, if it has no flow reading) with the range you set here: <span class="river-badge is-prime">Prime</span> <span class="river-badge is-fishable">Fishable</span> <span class="river-badge is-low">Too low</span> <span class="river-badge is-high">Blown out</span>. Rivers without a range show no badge.</p>

    <form class="range-search" method="get" action="">
        <label for="q">Add a river</label>
        <div class="range-search-row">
            <input type="search" id="q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="River, creek, or town (e.g. Kenai)" required minlength="2">
            <button type="submit" class="btn-secondary">Search</button>
        </div>
    </form>

    <?php if ($q !== ''): ?>
        <?php if ($results): ?>
            <ul class="admin-list range-results">
                <?php foreach ($results as $r): ?>
                    <li>
                        <a href="<?= htmlspecialchars(url('admin/river-range-edit.php') . '?site=' . rawurlencode($r['site_id'])) ?>"><?= htmlspecialchars($r['display_name']) ?></a>
                        <span class="article-meta"><?= htmlspecialchars(trim($r['county'] . ', ' . $r['state'], ', ')) ?><?= isset($rivers[$r['site_id']]) ? ' &middot; has a range' : '' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No gauges match "<?= htmlspecialchars($q) ?>".</p>
        <?php endif; ?>
    <?php endif; ?>

    <h2 class="section-title">Rivers with a range</h2>
    <?php if ($rivers): ?>
        <div class="range-table-wrap">
            <table class="admin-table range-table">
                <thead>
                    <tr><th scope="col">River</th><th scope="col">Now</th><th scope="col">Flow</th><th scope="col">Gage height</th><th scope="col">Last changed</th><th scope="col"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rivers as $id => $r): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars(river_site_url($id)) ?>"><?= htmlspecialchars($r['display_name'] ?? $id) ?></a>
                                <?php if ($r['state']): ?><span class="article-meta"><?= htmlspecialchars($r['state']) ?></span><?php endif; ?>
                            </td>
                            <td><?= isset($badges[$id]) ? river_badge_html($badges[$id], 'us') : '<span class="range-none" title="No recent reading from the hourly update">No reading</span>' ?></td>
                            <td><?= range_cell($r['ranges']['00060'] ?? null) ?></td>
                            <td><?= range_cell($r['ranges']['00065'] ?? null) ?></td>
                            <td><?= htmlspecialchars($r['username'] ?? 'Deleted user') ?><span class="article-meta"><?= htmlspecialchars(river_admin_time($r['updated_at'])) ?> ET</span></td>
                            <td><a href="<?= htmlspecialchars(url('admin/river-range-edit.php') . '?site=' . rawurlencode($id)) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No ranges yet. Search for a river above, or use "Set range" on any river page.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
