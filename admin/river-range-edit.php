<?php
/*
 * river-range-edit.php - Set or clear the fishability range (flow and gage height) for one river.
 * Values can be entered in US or metric units; they are stored in cfs and feet.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-ranges.php';

$user = require_login();
$siteId = (string) ($_GET['site'] ?? '');
$site = usgs_valid_site_id($siteId) ? river_site($siteId) : null;
if (!$site) {
    http_response_code(404);
    exit('Gauge not found.');
}

$errors = [];
$units = ($_GET['units'] ?? '') === 'metric' ? 'metric' : 'us';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $post = $_POST;
    if (($post['action'] ?? '') === 'clear') {
        $post['r'] = [];
    }
    $errors = river_range_save($siteId, $post, (int) $user['id']);
    if (!$errors) {
        header('Location: ' . url('admin/river-range-edit.php') . '?' . http_build_query(['site' => $siteId, 'saved' => 1, 'units' => $post['units'] ?? 'us']));
        exit;
    }
    $units = ($post['units'] ?? '') === 'metric' ? 'metric' : 'us';
}

$ranges = river_ranges_for_sites([$siteId])[$siteId] ?? [];
$latest = usgs_latest_for_site($siteId);
$changed = null;
foreach ($ranges as $r) {
    if (!$changed || $r['updated_at'] > $changed['updated_at']) {
        $changed = $r;
    }
}
if ($changed && $changed['updated_by']) {
    $stmt = get_db()->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$changed['updated_by']]);
    $changed['username'] = $stmt->fetchColumn() ?: 'Deleted user';
}

/* Value shown in a box: what was just posted (on error), else the stored value in the chosen units. */
function range_value(string $code, string $field, array $ranges, string $units, array $errors): string
{
    if ($errors) {
        return (string) ($_POST['r'][$code][$field] ?? '');
    }
    $v = $ranges[$code][$field] ?? null;
    if ($v === null) {
        return '';
    }
    $shown = river_convert($code, (float) $v, $units)[0];
    return abs($shown) >= 100 ? number_format($shown, 0, '.', '') : rtrim(rtrim(number_format($shown, 2, '.', ''), '0'), '.');
}

$editable = array_filter(RIVER_RANGE_PARAMS, fn($c) => in_array($c, $site['param_list'], true) || isset($ranges[$c]), ARRAY_FILTER_USE_KEY);
$fields = ['fish_low' => 'Too low below', 'ideal_low' => 'Prime from', 'ideal_high' => 'Prime up to', 'fish_high' => 'Blown out above'];

$page_theme = 'admin';
$page_title = 'Fishability Range: ' . $site['display_name'] . ' | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title"><?= htmlspecialchars($site['display_name']) ?></h1>
    <p class="section-intro">
        <a href="<?= htmlspecialchars(url('admin/river-ranges.php')) ?>">&larr; All ranges</a> &middot;
        <a href="<?= htmlspecialchars(river_site_url($siteId)) ?>">River page</a>
    </p>

    <?php if (isset($_GET['saved'])): ?>
        <p class="form-success"><?= $ranges ? 'Range saved.' : 'Range cleared. This river no longer shows a badge.' ?></p>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
        <p class="form-error"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>

    <?php if ($latest['readings']): ?>
        <dl class="range-now">
            <?php foreach (array_intersect_key($latest['readings'], RIVER_RANGE_PARAMS) as $code => $r): ?>
                <div>
                    <dt><?= htmlspecialchars(RIVER_RANGE_PARAMS[$code]) ?> now</dt>
                    <?php foreach (['us', 'metric'] as $u): ?>
                        <?php [$v, $unit] = river_convert($code, $r['value'], $u); ?>
                        <dd data-units-only="<?= $u ?>"<?= $u === $units ? '' : ' hidden' ?>><?= river_format($v) ?> <?= htmlspecialchars($unit) ?></dd>
                    <?php endforeach; ?>
                    <dd class="article-meta"><?= htmlspecialchars(river_time_ago($r['time'])) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <?php if (!$editable): ?>
        <p>This gauge doesn't report flow or gage height, so it can't have a fishability badge.</p>
    <?php else: ?>
    <form class="admin-form range-form" method="post" action="" data-range-form>
        <?= csrf_field() ?>
        <p class="range-help">Fill in what you know; leave a box blank for "no limit". Flow is used for the badge; gage height is used only when the gauge has no flow reading. Clear every box for a measurement to remove it.</p>

        <div class="report-choice range-units" role="radiogroup" aria-label="Units">
            <label><input type="radio" name="units" value="us" <?= $units === 'us' ? 'checked' : '' ?>> US (cfs, feet)</label>
            <label><input type="radio" name="units" value="metric" <?= $units === 'metric' ? 'checked' : '' ?>> Metric (m³/s, meters)</label>
        </div>

        <?php foreach ($editable as $code => $label): ?>
            <fieldset class="range-fieldset">
                <legend><?= htmlspecialchars($label) ?> <small data-range-unit="<?= $code ?>"><?= htmlspecialchars(river_convert($code, 0, $units)[1]) ?></small></legend>
                <div class="range-row">
                    <?php foreach ($fields as $field => $fieldLabel): ?>
                        <div class="range-field is-<?= $field ?>">
                            <label for="r-<?= $code ?>-<?= $field ?>"><?= htmlspecialchars($fieldLabel) ?></label>
                            <input type="text" inputmode="decimal" id="r-<?= $code ?>-<?= $field ?>" name="r[<?= $code ?>][<?= $field ?>]"
                                value="<?= htmlspecialchars(range_value($code, $field, $ranges, $units, $errors)) ?>" data-range-code="<?= $code ?>" autocomplete="off">
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>

        <?php if ($changed): ?>
            <p class="range-help">Last changed by <?= htmlspecialchars($changed['username'] ?? 'Deleted user') ?> on <?= htmlspecialchars(river_admin_time($changed['updated_at'], 'M j, Y \a\t g:i A')) ?> ET.</p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" name="action" value="save" class="btn-primary">Save range</button>
            <?php if ($ranges): ?>
                <button type="submit" name="action" value="clear" class="btn-secondary" formnovalidate onclick="return confirm('Remove this river\'s range? Its badge will disappear.');">Clear range</button>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</section>

<script src="<?= htmlspecialchars(asset_url('assets/js/river-range.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
