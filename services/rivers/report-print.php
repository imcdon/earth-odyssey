<?php
/*
 * report-print.php - Print / Save as PDF from the report form. Does not save. No login.
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/river-reports.php';
require __DIR__ . '/../../includes/markdown.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('services/rivers/report-edit.php'));
    exit;
}

$report = river_report_preview($_POST);
$units = $report['measure_units'] === 'metric' ? 'metric' : 'us';
$lenUnit = $units === 'metric' ? 'cm' : 'in';
$wtUnit = $units === 'metric' ? 'kg' : 'lb';

$fish = 0;
$kept = 0;
$released = 0;
$species = [];
foreach ($report['catches'] as $c) {
    $n = (int) ($c['fish_count'] ?? 0) ?: 1;
    $fish += $n;
    if ($c['kept'] !== null) {
        (int) $c['kept'] ? $kept += $n : $released += $n;
    }
    if ($c['species'] !== '') {
        $species[$c['species']] = ($species[$c['species']] ?? 0) + $n;
    }
}
arsort($species);

$fmtTime = fn(?string $t) => $t ? date('g:i A', strtotime('2000-01-01 ' . $t)) : '';
$tripOk = (bool) DateTimeImmutable::createFromFormat('!Y-m-d', (string) $report['trip_date']);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

$page_theme = 'report';
$page_title = $report['title'] . ' | Fishing Report';
require __DIR__ . '/../../includes/header.php';
?>

<article class="container report">
    <div class="report-actions">
        <button type="button" class="btn-primary" onclick="window.print()">Print to PDF</button>
        <button type="button" class="btn-secondary" onclick="window.close()">Back to editor</button>
    </div>

    <header class="report-head">
        <p class="kicker">Fishing report &middot; Earth Odyssey</p>
        <h1 class="report-title"><?= htmlspecialchars($report['title']) ?></h1>
        <p class="report-meta">
            <?php if ($tripOk): ?>
                <?= htmlspecialchars(date('l, F j, Y', strtotime($report['trip_date']))) ?>
            <?php endif; ?>
            <?php if ($report['start_time']): ?>
                &middot; <?= htmlspecialchars($fmtTime($report['start_time'])) ?><?= $report['end_time'] ? '–' . htmlspecialchars($fmtTime($report['end_time'])) : '' ?>
            <?php endif; ?>
            <?php if ($report['angler'] !== ''): ?>&middot; <?= htmlspecialchars($report['angler']) ?><?php endif; ?>
        </p>
        <?php if ($report['sites']): ?>
            <p class="report-meta"><?= htmlspecialchars(implode(' / ', array_map(fn($s) => $s['display_name'], $report['sites']))) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($fish): ?>
        <section class="report-summary" aria-label="Summary">
            <div>
                <span class="report-stat-label">Fish landed</span>
                <span class="report-stat"><?= $fish ?></span>
                <span class="report-stat-note"><?= htmlspecialchars(implode(', ', array_map(fn($s, $n) => "$n $s", array_keys($species), $species))) ?></span>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($report['clarity'] || $report['crowding'] || $report['access_point'] !== '' || trim((string) $report['hatch']) !== '' || trim((string) $report['tackle']) !== ''): ?>
        <section class="report-section">
            <h2 class="report-h2">On the water</h2>
            <dl class="report-facts">
                <?php if ($report['clarity']): ?><div><dt>Clarity</dt><dd><?= htmlspecialchars(RIVER_REPORT_CLARITY[$report['clarity']]) ?></dd></div><?php endif; ?>
                <?php if ($report['crowding']): ?><div><dt>Crowding</dt><dd><?= htmlspecialchars(RIVER_REPORT_CROWDING[$report['crowding']]) ?></dd></div><?php endif; ?>
                <?php if ($report['access_point'] !== ''): ?><div><dt>Access</dt><dd><?= htmlspecialchars($report['access_point']) ?></dd></div><?php endif; ?>
                <?php if (trim((string) $report['hatch']) !== ''): ?><div class="is-wide"><dt>Hatch</dt><dd><?= nl2br(htmlspecialchars($report['hatch'])) ?></dd></div><?php endif; ?>
                <?php if (trim((string) $report['tackle']) !== ''): ?><div class="is-wide"><dt>Flies, lures &amp; tackle</dt><dd><?= nl2br(htmlspecialchars($report['tackle'])) ?></dd></div><?php endif; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if ($report['catches']): ?>
        <section class="report-section">
            <h2 class="report-h2">Catch log <small><?= $fish ?> fish<?= $kept || $released ? " &middot; $released released, $kept kept" : '' ?></small></h2>
            <table class="report-table">
                <thead><tr><th scope="col">Species</th><th scope="col">#</th><th scope="col">Length</th><th scope="col">Weight</th><th scope="col">Kept?</th><th scope="col">Fly / lure</th><th scope="col">Time</th><th scope="col">Note</th></tr></thead>
                <tbody>
                    <?php foreach ($report['catches'] as $c): ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($c['species'] ?: '–') ?></th>
                            <td><?= (int) ($c['fish_count'] ?? 0) ?: 1 ?></td>
                            <td><?= $c['length'] !== null ? htmlspecialchars(rtrim(rtrim((string) $c['length'], '0'), '.') . ' ' . $lenUnit) : '' ?></td>
                            <td><?= $c['weight'] !== null ? htmlspecialchars(rtrim(rtrim((string) $c['weight'], '0'), '.') . ' ' . $wtUnit) : '' ?></td>
                            <td><?= $c['kept'] === null ? '' : ((int) $c['kept'] ? 'Kept' : 'Released') ?></td>
                            <td><?= htmlspecialchars((string) $c['fly']) ?></td>
                            <td><?= htmlspecialchars($fmtTime($c['caught_at'])) ?></td>
                            <td><?= htmlspecialchars((string) $c['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>

    <?php if (trim((string) $report['writeup']) !== ''): ?>
        <section class="report-section report-writeup">
            <h2 class="report-h2">The day</h2>
            <div class="report-prose"><?= render_markdown($report['writeup']) ?></div>
        </section>
    <?php endif; ?>

    <footer class="report-foot">
        This is a print from the report form. Sign in and save to keep it and add gauge readings and weather.
        <?php if ($report['author'] !== ''): ?>Report by <?= htmlspecialchars($report['author']) ?>.<?php endif; ?>
    </footer>
</article>

<script>
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
