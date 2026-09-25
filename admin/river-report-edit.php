<?php
/*
 * river-report-edit.php - Create or edit a fishing report. ?site=USGS-... pre-fills the first gauge (from a river page).
 * Photos are shrunk in the browser (assets/js/river-report.js) before upload; the server only validates and stores them.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-reports.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);
$report = $id ? river_report_get($id) : null;
if ($id && !$report) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}
if ($report && !river_report_can_edit($user, $report)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'delete' && $report) {
        river_report_delete($report);
        header('Location: ' . url('admin/river-reports.php') . '?deleted=1');
        exit;
    }
    $result = river_report_save($user, $report, $_POST, $_FILES);
    if ($result['id']) {
        $_SESSION['report_flash'] = $result['warnings'];
        header('Location: ' . url('admin/river-report-edit.php') . '?id=' . $result['id'] . '&saved=1');
        exit;
    }
    $errors = $result['errors'];
}

$warnings = [];
if (isset($_GET['saved'])) {
    $warnings = (array) ($_SESSION['report_flash'] ?? []);
    unset($_SESSION['report_flash']);
}

/* Form values: what was just posted (after a validation error), the saved report, or a blank report. */
if ($errors) {
    $form = $_POST;
    $gauges = [];
    foreach ((array) ($_POST['sites'] ?? []) as $i => $siteId) {
        $gauges[] = ['site_id' => (string) $siteId, 'label' => (string) ($_POST['site_label'][$i] ?? '')];
    }
    $catches = [];
    foreach (array_keys((array) ($_POST['catch_species'] ?? [])) as $i) {
        $catches[] = [
            'species' => $_POST['catch_species'][$i] ?? '', 'count' => $_POST['catch_count'][$i] ?? '',
            'length' => $_POST['catch_length'][$i] ?? '', 'weight' => $_POST['catch_weight'][$i] ?? '',
            'kept' => $_POST['catch_kept'][$i] ?? '', 'fly' => $_POST['catch_fly'][$i] ?? '',
            'time' => $_POST['catch_time'][$i] ?? '', 'note' => $_POST['catch_note'][$i] ?? '',
        ];
    }
} elseif ($report) {
    $form = $report;
    $form['start_time'] = $report['start_time'] ? substr($report['start_time'], 0, 5) : '';
    $form['end_time'] = $report['end_time'] ? substr($report['end_time'], 0, 5) : '';
    $gauges = array_map(fn($s) => ['site_id' => $s['site_id'], 'label' => ($s['display_name'] ?? $s['site_id']) . ' (' . $s['site_id'] . ')'], $report['sites']);
    $catches = array_map(fn($c) => [
        'species' => $c['species'], 'count' => $c['fish_count'] ?? '',
        'length' => $c['length'] === null ? '' : rtrim(rtrim($c['length'], '0'), '.'),
        'weight' => $c['weight'] === null ? '' : rtrim(rtrim($c['weight'], '0'), '.'),
        'kept' => $c['kept'] === null ? '' : ((int) $c['kept'] ? 'kept' : 'released'),
        'fly' => $c['fly'], 'time' => $c['caught_at'] ? substr($c['caught_at'], 0, 5) : '', 'note' => $c['note'],
    ], $report['catches']);
} else {
    $form = ['title' => '', 'trip_date' => date('Y-m-d'), 'start_time' => '', 'end_time' => '', 'angler' => $user['username'],
             'measure_units' => 'us', 'clarity' => '', 'crowding' => '', 'access_point' => '', 'hatch' => '', 'tackle' => '', 'writeup' => ''];
    $gauges = [];
    $prefill = (string) ($_GET['site'] ?? '');
    if (usgs_valid_site_id($prefill) && ($site = river_site($prefill))) {
        $gauges[] = ['site_id' => $site['site_id'], 'label' => $site['display_name'] . ' (' . $site['site_id'] . ')'];
    }
    $catches = [];
}
while (count($gauges) < RIVER_REPORT_MAX_SITES) {
    $gauges[] = ['site_id' => '', 'label' => ''];
}
$catches[] = ['species' => '', 'count' => '', 'length' => '', 'weight' => '', 'kept' => '', 'fly' => '', 'time' => '', 'note' => ''];

$v = fn(string $k) => htmlspecialchars((string) ($form[$k] ?? ''));
$metric = ($form['measure_units'] ?? 'us') === 'metric';
$photos = $report['photos'] ?? [];

$page_theme = 'admin';
$page_title = ($report ? 'Edit' : 'New') . ' Fishing Report | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page report-editor">
    <h1 class="section-title"><?= $report ? 'Edit fishing report' : 'New fishing report' ?></h1>
    <p class="section-intro">
        <a href="<?= htmlspecialchars(url('admin/river-reports.php')) ?>">&larr; All reports</a>
        <?php if ($report): ?>
            &middot; <a href="<?= htmlspecialchars(url('services/rivers/report.php') . '?id=' . (int) $report['id']) ?>">View / print</a>
        <?php endif; ?>
    </p>

    <?php foreach ($errors as $e): ?>
        <p class="form-error"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
    <?php if (isset($_GET['saved'])): ?>
        <p class="form-success">Report saved.</p>
    <?php endif; ?>
    <?php foreach ($warnings as $w): ?>
        <p class="form-error"><?= htmlspecialchars($w) ?></p>
    <?php endforeach; ?>

    <div class="report-draft" data-report-draft hidden>
        <span data-report-draft-text>You have unsaved changes from earlier.</span>
        <button type="button" class="btn-secondary" data-report-draft-restore>Restore them</button>
        <button type="button" class="btn-ghost" data-report-draft-discard>Discard</button>
    </div>

    <form
        class="admin-form report-form"
        method="post"
        enctype="multipart/form-data"
        action=""
        data-report-form
        data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
        data-draft-key="eo-report-draft-<?= $report ? (int) $report['id'] : 'new' ?>"
        data-updated="<?= $report ? strtotime($report['updated_at']) * 1000 : 0 ?>"
        data-saved="<?= isset($_GET['saved']) ? '1' : '0' ?>"
        data-max-photos="<?= RIVER_REPORT_MAX_PHOTOS ?>"
        data-photo-count="<?= count($photos) ?>"
    >
        <?= csrf_field() ?>

        <fieldset>
            <legend>The trip</legend>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= $v('title') ?>" maxlength="200" required>

            <div class="report-row">
                <div>
                    <label for="trip_date">Date</label>
                    <input type="date" id="trip_date" name="trip_date" value="<?= $v('trip_date') ?>" max="<?= date('Y-m-d') ?>" required>
                </div>
                <div>
                    <label for="start_time">Start</label>
                    <input type="time" id="start_time" name="start_time" value="<?= $v('start_time') ?>">
                </div>
                <div>
                    <label for="end_time">End</label>
                    <input type="time" id="end_time" name="end_time" value="<?= $v('end_time') ?>">
                </div>
            </div>

            <label for="angler">Angler(s)</label>
            <input type="text" id="angler" name="angler" value="<?= $v('angler') ?>" maxlength="120">

            <span class="report-label">Units for fish length and weight</span>
            <div class="report-choice">
                <label><input type="radio" name="measure_units" value="us"<?= $metric ? '' : ' checked' ?> data-units-radio> Inches &amp; pounds</label>
                <label><input type="radio" name="measure_units" value="metric"<?= $metric ? ' checked' : '' ?> data-units-radio> Centimeters &amp; kilograms</label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Gauges (up to <?= RIVER_REPORT_MAX_SITES ?>)</legend>
            <p class="report-help">The first gauge is used for the weather. Search by river, creek, or town.</p>
            <?php foreach ($gauges as $i => $g): ?>
                <div class="report-gauge" data-gauge-slot>
                    <input type="hidden" name="sites[]" value="<?= htmlspecialchars($g['site_id']) ?>" data-gauge-id>
                    <label class="visually-hidden" for="gauge-<?= $i ?>">Gauge <?= $i + 1 ?></label>
                    <input type="text" id="gauge-<?= $i ?>" name="site_label[]" value="<?= htmlspecialchars($g['label']) ?>"
                           placeholder="<?= $i === 0 ? 'e.g. Kenai River at Soldotna' : 'Another gauge (optional)' ?>" autocomplete="off" data-gauge-search>
                    <ul class="rivers-results" hidden></ul>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <fieldset>
            <legend>On the water</legend>
            <div class="report-row">
                <div>
                    <label for="clarity">Water clarity</label>
                    <select id="clarity" name="clarity">
                        <option value="">&mdash;</option>
                        <?php foreach (RIVER_REPORT_CLARITY as $key => $label): ?>
                            <option value="<?= $key ?>"<?= ($form['clarity'] ?? '') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="crowding">Crowding / pressure</label>
                    <select id="crowding" name="crowding">
                        <option value="">&mdash;</option>
                        <?php foreach (RIVER_REPORT_CROWDING as $key => $label): ?>
                            <option value="<?= $key ?>"<?= ($form['crowding'] ?? '') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label for="access_point">Access point / put-in</label>
            <input type="text" id="access_point" name="access_point" value="<?= $v('access_point') ?>" maxlength="200">
            <label for="hatch">Hatch / bug activity</label>
            <textarea id="hatch" name="hatch" rows="3"><?= $v('hatch') ?></textarea>
        </fieldset>

        <fieldset>
            <legend>Catch log</legend>
            <div class="report-catch-wrap">
                <table class="report-catch" data-catch-table>
                    <thead>
                        <tr>
                            <th scope="col">Species</th><th scope="col">Count</th>
                            <th scope="col">Length (<span data-unit-length><?= $metric ? 'cm' : 'in' ?></span>)</th>
                            <th scope="col">Weight (<span data-unit-weight><?= $metric ? 'kg' : 'lb' ?></span>)</th>
                            <th scope="col">Kept?</th><th scope="col">Fly / lure</th><th scope="col">Time</th><th scope="col">Note</th><th scope="col"><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catches as $c): ?>
                            <tr data-catch-row>
                                <td><input type="text" name="catch_species[]" value="<?= htmlspecialchars((string) $c['species']) ?>" list="species-list" aria-label="Species" maxlength="80"></td>
                                <td><input type="number" name="catch_count[]" value="<?= htmlspecialchars((string) $c['count']) ?>" min="1" max="9999" aria-label="Count" class="is-narrow"></td>
                                <td><input type="number" name="catch_length[]" value="<?= htmlspecialchars((string) $c['length']) ?>" min="0" step="0.25" aria-label="Length" class="is-narrow"></td>
                                <td><input type="number" name="catch_weight[]" value="<?= htmlspecialchars((string) $c['weight']) ?>" min="0" step="0.05" aria-label="Weight" class="is-narrow"></td>
                                <td>
                                    <select name="catch_kept[]" aria-label="Kept or released">
                                        <option value="">&mdash;</option>
                                        <option value="released"<?= $c['kept'] === 'released' ? ' selected' : '' ?>>Released</option>
                                        <option value="kept"<?= $c['kept'] === 'kept' ? ' selected' : '' ?>>Kept</option>
                                    </select>
                                </td>
                                <td><input type="text" name="catch_fly[]" value="<?= htmlspecialchars((string) $c['fly']) ?>" aria-label="Fly or lure" maxlength="120"></td>
                                <td><input type="time" name="catch_time[]" value="<?= htmlspecialchars((string) $c['time']) ?>" aria-label="Time caught"></td>
                                <td><input type="text" name="catch_note[]" value="<?= htmlspecialchars((string) $c['note']) ?>" aria-label="Note" maxlength="255"></td>
                                <td><button type="button" class="report-remove" data-catch-remove aria-label="Remove this fish">&times;</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-secondary" data-catch-add>+ Add fish</button>
            <datalist id="species-list">
                <?php foreach (RIVER_REPORT_SPECIES as $species): ?>
                    <option value="<?= htmlspecialchars($species) ?>">
                <?php endforeach; ?>
            </datalist>
        </fieldset>

        <fieldset>
            <legend>Flies, lures &amp; tackle</legend>
            <label for="tackle" class="visually-hidden">Flies, lures and tackle</label>
            <textarea id="tackle" name="tackle" rows="4" placeholder="Rod, line, leader, what worked and what didn't"><?= $v('tackle') ?></textarea>
        </fieldset>

        <fieldset>
            <legend>Write-up</legend>
            <p class="report-help">Blank lines start new paragraphs. <code>### Heading</code> and <code>- list item</code> work too.</p>
            <label for="writeup" class="visually-hidden">Write-up</label>
            <textarea id="writeup" name="writeup" rows="18"><?= $v('writeup') ?></textarea>
        </fieldset>

        <fieldset>
            <legend>Photos (up to <?= RIVER_REPORT_MAX_PHOTOS ?>)</legend>
            <?php if ($photos): ?>
                <ul class="report-photos">
                    <?php foreach ($photos as $p): ?>
                        <li>
                            <img src="<?= htmlspecialchars(url('admin/river-report-photo.php') . '?id=' . (int) $p['id']) ?>" alt="" loading="lazy" width="<?= (int) $p['width'] ?>" height="<?= (int) $p['height'] ?>">
                            <input type="text" name="photo_caption[<?= (int) $p['id'] ?>]" value="<?= htmlspecialchars($p['caption']) ?>" placeholder="Caption" aria-label="Caption" maxlength="255">
                            <label class="checkbox-label"><input type="checkbox" name="photo_delete[]" value="<?= (int) $p['id'] ?>" data-photo-delete> Remove</label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <label for="photos">Add photos</label>
            <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp,image/heic" multiple data-photo-input>
            <ul class="report-photos is-new" data-photo-previews></ul>
            <p class="report-help" data-photo-status>Photos are resized to 1600px before uploading.</p>
        </fieldset>

        <fieldset>
            <legend>Conditions</legend>
            <?php if ($report && $report['conditions_at']): ?>
                <p class="report-help">River and weather conditions were saved <?= htmlspecialchars(date('M j, Y g:i A', strtotime($report['conditions_at'] . ' UTC'))) ?>. They update automatically when you change the gauges, date, times, or units.</p>
                <label class="checkbox-label"><input type="checkbox" name="refresh_conditions" value="1"> Refresh conditions on save</label>
            <?php else: ?>
                <p class="report-help">Gauge readings, a 7-day flow chart, weather, and moon phase are added automatically when you save.</p>
            <?php endif; ?>
        </fieldset>

        <div class="form-actions">
            <button type="submit" name="action" value="save" class="btn-primary" data-report-submit>Save report</button>
            <?php if ($report): ?>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('services/rivers/report.php') . '?id=' . (int) $report['id']) ?>">View / print</a>
                <button type="submit" name="action" value="delete" class="btn-secondary" formnovalidate onclick="return confirm('Permanently delete this report and its photos?');">Delete</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<script src="<?= htmlspecialchars(asset_url('assets/js/river-report.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
