<?php
/*
 * report.php - Printable fishing report (staff only). Page 1: trip + conditions; then the write-up; then photos.
 * Everything comes from the snapshot saved with the report, so opening it never calls USGS or Open-Meteo.
 */
require __DIR__ . '/../../includes/config.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/river-reports.php';
require __DIR__ . '/../../includes/markdown.php';

$user = require_login();
$report = river_report_get((int) ($_GET['id'] ?? 0));
if (!$report || !river_report_can_edit($user, $report)) {
    http_response_code(404);
    echo 'Report not found.';
    exit;
}
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

$units = $report['measure_units'] === 'metric' ? 'metric' : 'us';
$cond = $report['conditions'] ?? [];
$weather = $cond['weather'] ?? null;
$offset = (int) ($cond['utc_offset'] ?? 0);
$trip = $weather ? (array_values(array_filter($weather['days'], fn($d) => $d['role'] === 'trip'))[0] ?? null) : null;

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
$lenUnit = $units === 'metric' ? 'cm' : 'in';
$wtUnit = $units === 'metric' ? 'kg' : 'lb';

$fmtTime = fn(?string $t) => $t ? date('g:i A', strtotime('2000-01-01 ' . $t)) : '';
$localTime = fn(string $iso) => gmdate('g:i A', strtotime($iso) + $offset);
$num = fn($v, int $dec = 0) => $v === null ? '–' : number_format((float) $v, $dec);

/*
 * Small print-friendly line chart. $points: [[x, y], ...] (x = unix seconds); $ticks: [[x, label]];
 * $marker: x to highlight (the trip time).
 */
function report_chart_svg(array $points, array $ticks, string $unit, ?int $marker = null): string
{
    $points = array_values(array_filter($points, fn($p) => $p[1] !== null));
    if (count($points) < 2) {
        return '';
    }
    $w = 640;
    $h = 170;
    $left = 56;
    $bottom = 22;
    $xs = array_column($points, 0);
    $ys = array_column($points, 1);
    [$x0, $x1] = [min($xs), max($xs)];
    [$y0, $y1] = [min($ys), max($ys)];
    $pad = ($y1 - $y0) * 0.1 ?: max(abs($y1) * 0.05, 0.5);
    $y0 -= $pad;
    $y1 += $pad;
    $sx = fn($x) => round($left + ($x - $x0) / (($x1 - $x0) ?: 1) * ($w - $left - 8), 1);
    $sy = fn($y) => round(8 + (1 - ($y - $y0) / ($y1 - $y0)) * ($h - $bottom - 8), 1);

    $svg = '<svg class="report-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Chart in ' . htmlspecialchars($unit) . '">';
    foreach ([0, 0.5, 1] as $f) {
        $yv = $y0 + ($y1 - $y0) * $f;
        $y = $sy($yv);
        $svg .= '<line class="grid" x1="' . $left . '" x2="' . ($w - 8) . '" y1="' . $y . '" y2="' . $y . '"/>'
            . '<text class="axis" x="' . ($left - 6) . '" y="' . ($y + 4) . '" text-anchor="end">' . htmlspecialchars(river_format($yv)) . '</text>';
    }
    foreach ($ticks as [$x, $label]) {
        if ($x >= $x0 && $x <= $x1) {
            $svg .= '<text class="axis" x="' . $sx($x) . '" y="' . ($h - 6) . '" text-anchor="middle">' . htmlspecialchars($label) . '</text>';
        }
    }
    if ($marker !== null && $marker >= $x0 && $marker <= $x1) {
        $svg .= '<line class="marker" x1="' . $sx($marker) . '" x2="' . $sx($marker) . '" y1="8" y2="' . ($h - $bottom) . '"/>';
    }
    $d = '';
    foreach ($points as $i => [$x, $y]) {
        $d .= ($i ? 'L' : 'M') . $sx($x) . ' ' . $sy($y);
    }
    return $svg . '<path class="line" d="' . $d . '"/><text class="axis unit" x="4" y="14">' . htmlspecialchars($unit) . '</text></svg>';
}

/* One tick per local midnight across the chart range, labelled "Mon 9/16". */
function report_day_ticks(int $from, int $to, int $offset): array
{
    $ticks = [];
    for ($t = $from; $t <= $to; $t += 86400) {
        $ticks[] = [$t + 43200, gmdate('D n/j', $t + 43200 + $offset)];
    }
    return $ticks;
}

$page_theme = 'report';
$page_title = $report['title'] . ' | Fishing Report';
require __DIR__ . '/../../includes/header.php';
?>

<article class="container report">
    <div class="report-actions">
        <button type="button" class="btn-primary" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-report-edit.php') . '?id=' . (int) $report['id']) ?>">Edit report</a>
        <a class="btn-ghost" href="<?= htmlspecialchars(url('admin/river-reports.php')) ?>">All reports</a>
    </div>

    <header class="report-head">
        <p class="kicker">Fishing report &middot; Earth Odyssey</p>
        <h1 class="report-title"><?= htmlspecialchars($report['title']) ?></h1>
        <p class="report-meta">
            <?= htmlspecialchars(date('l, F j, Y', strtotime($report['trip_date']))) ?>
            <?php if ($report['start_time']): ?>
                &middot; <?= htmlspecialchars($fmtTime($report['start_time'])) ?><?= $report['end_time'] ? '–' . htmlspecialchars($fmtTime($report['end_time'])) : '' ?>
            <?php endif; ?>
            <?php if ($report['angler'] !== ''): ?>&middot; <?= htmlspecialchars($report['angler']) ?><?php endif; ?>
        </p>
        <p class="report-meta">
            <?= htmlspecialchars(implode(' / ', array_map(fn($s) => $s['display_name'] ?? $s['site_id'], $report['sites']))) ?>
        </p>
    </header>

    <section class="report-summary" aria-label="Summary">
        <div><span class="report-stat-label">Fish landed</span><span class="report-stat"><?= $fish ?></span>
            <span class="report-stat-note"><?= htmlspecialchars(implode(', ', array_map(fn($s, $n) => "$n $s", array_keys($species), $species)) ?: 'No catches logged') ?></span></div>
        <?php if ($trip): ?>
            <div><span class="report-stat-label">Air temp</span><span class="report-stat"><?= $num($trip['tmax']) ?>° / <?= $num($trip['tmin']) ?>°</span>
                <span class="report-stat-note"><?= $num($trip['cloud']) ?>% cloud &middot; wind to <?= $num($trip['wind']) ?> <?= htmlspecialchars($weather['units']['wind']) ?> <?= htmlspecialchars((string) $trip['wind_dir']) ?></span></div>
            <div><span class="report-stat-label">Pressure</span><span class="report-stat"><?= htmlspecialchars((string) ($weather['pressure']['trend'] ?? '–')) ?></span>
                <span class="report-stat-note"><?= $weather['pressure']['at'] !== null ? $num($weather['pressure']['at'], $units === 'us' ? 2 : 1) . ' ' . htmlspecialchars($weather['units']['pressure']) : '' ?></span></div>
        <?php endif; ?>
        <?php if (!empty($cond['moon'])): ?>
            <div><span class="report-stat-label">Moon</span><span class="report-stat"><?= htmlspecialchars($cond['moon']['name']) ?></span>
                <span class="report-stat-note"><?= (int) $cond['moon']['illum'] ?>% lit<?= $trip ? ' &middot; sun ' . htmlspecialchars($trip['sunrise'] . '–' . $trip['sunset']) : '' ?></span></div>
        <?php endif; ?>
    </section>

    <?php if (!$cond): ?>
        <p class="report-note">Conditions haven't been loaded for this report yet. Open it in the editor and save with "Refresh conditions" ticked.</p>
    <?php endif; ?>

    <?php foreach ($cond['gauges'] ?? [] as $g): ?>
        <section class="report-section report-gauge-section">
            <h2 class="report-h2"><?= htmlspecialchars($g['name']) ?> <small>USGS <?= htmlspecialchars(preg_replace('/^[A-Z]+-/', '', $g['site_id'])) ?></small></h2>
            <?php if ($g['readings']): ?>
                <dl class="report-readings">
                    <?php foreach ($g['readings'] as $code => $r): ?>
                        <?php [$val, $unit] = river_convert($code, (float) $r['value'], $units); ?>
                        <div><dt><?= htmlspecialchars(USGS_PARAMS[$code]['label'] ?? $code) ?></dt><dd><?= river_format($val) ?> <small><?= htmlspecialchars($unit) ?></small></dd><dd class="report-when">at <?= htmlspecialchars($localTime($r['time'])) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <p class="report-note">No gauge readings near the trip time<?= $g['ok'] ? '' : ' (USGS was unavailable when this report was saved)' ?>.</p>
            <?php endif; ?>
            <?php if (!empty($g['chart']['points'])): ?>
                <?php
                $code = $g['chart']['code'];
                $pts = array_map(fn($p) => [$p[0], river_convert($code, (float) $p[1], $units)[0]], $g['chart']['points']);
                $from = strtotime($cond['range'][0]);
                ?>
                <h3 class="report-h3"><?= htmlspecialchars(USGS_PARAMS[$code]['label']) ?>, 7 days to the trip</h3>
                <?= report_chart_svg($pts, report_day_ticks($from, strtotime($cond['range'][1]), $offset), river_convert($code, 0, $units)[1], strtotime($cond['target'])) ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($weather): ?>
        <section class="report-section">
            <h2 class="report-h2">Weather</h2>
            <h3 class="report-h3">Trip day</h3>
            <table class="report-table">
                <thead><tr><th scope="col"></th><th scope="col">Temp</th><th scope="col">Cloud</th><th scope="col">Wind</th><th scope="col">Precip</th><th scope="col">Pressure</th></tr></thead>
                <tbody>
                    <?php foreach ($weather['periods'] as $p): ?>
                        <tr>
                            <th scope="row"><?= htmlspecialchars($p['label']) ?> <small><?= htmlspecialchars($p['hours']) ?></small></th>
                            <td><?= $num($p['temp']) ?><?= htmlspecialchars($weather['units']['temp']) ?></td>
                            <td><?= $num($p['cloud']) ?>%</td>
                            <td><?= $num($p['wind']) ?> <?= htmlspecialchars($weather['units']['wind'] . ' ' . $p['wind_dir']) ?></td>
                            <td><?= $num($p['precip'], $units === 'us' ? 2 : 1) ?> <?= htmlspecialchars($weather['units']['precip']) ?></td>
                            <td><?= $num($p['pressure'], $units === 'us' ? 2 : 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="report-h3">The week before <small>(the 3 days leading up are shaded)</small></h3>
            <table class="report-table">
                <thead><tr><th scope="col">Day</th><th scope="col">High / low</th><th scope="col">Precip</th><th scope="col">Wind max</th><th scope="col">Cloud</th><th scope="col">Pressure</th><th scope="col">Sun</th></tr></thead>
                <tbody>
                    <?php foreach ($weather['days'] as $d): ?>
                        <tr class="is-<?= htmlspecialchars($d['role']) ?>">
                            <th scope="row"><?= htmlspecialchars(date('D n/j', strtotime($d['date']))) ?><?= $d['role'] === 'trip' ? ' <small>trip</small>' : '' ?></th>
                            <td><?= $num($d['tmax']) ?>° / <?= $num($d['tmin']) ?>°</td>
                            <td><?= $num($d['precip'], $units === 'us' ? 2 : 1) ?> <?= htmlspecialchars($weather['units']['precip']) ?><?= $d['snow'] ? ' <small>(snow)</small>' : '' ?></td>
                            <td><?= $num($d['wind']) ?> <?= htmlspecialchars($weather['units']['wind'] . ' ' . $d['wind_dir']) ?></td>
                            <td><?= $num($d['cloud']) ?>%</td>
                            <td><?= $num($d['pressure'], $units === 'us' ? 2 : 1) ?></td>
                            <td><?= htmlspecialchars($d['sunrise'] . '–' . $d['sunset']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $pStart = strtotime($weather['pressure']['start'] . ':00 UTC') - $offset;
            $pPoints = [];
            foreach ($weather['pressure']['values'] as $i => $pv) {
                $pPoints[] = [$pStart + $i * 3600, $pv];
            }
            $dayStart = strtotime($report['trip_date'] . ' 00:00:00 UTC') - $offset;
            ?>
            <h3 class="report-h3">Barometric pressure
                <?php if ($weather['pressure']['trend']): ?>
                    <small><?= htmlspecialchars($weather['pressure']['trend']) ?> at <?= htmlspecialchars(date('g A', mktime($weather['pressure']['at_hour'], 0))) ?>
                        (<?= ($weather['pressure']['change_3h'] > 0 ? '+' : '') . $num($weather['pressure']['change_3h'], $units === 'us' ? 2 : 1) . ' ' . htmlspecialchars($weather['units']['pressure']) ?> over 3 hr,
                        <?= ($weather['pressure']['change_24h'] > 0 ? '+' : '') . $num($weather['pressure']['change_24h'], $units === 'us' ? 2 : 1) . ' ' . htmlspecialchars($weather['units']['pressure']) ?> over 24 hr)</small>
                <?php endif; ?>
            </h3>
            <?= report_chart_svg($pPoints, report_day_ticks($pStart, $pStart + 7 * 86400 - 3600, $offset), $weather['units']['pressure'],
                    $dayStart + (int) $weather['pressure']['at_hour'] * 3600) ?>
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
                            <td><?= $c['length'] !== null ? htmlspecialchars(rtrim(rtrim($c['length'], '0'), '.') . ' ' . $lenUnit) : '' ?></td>
                            <td><?= $c['weight'] !== null ? htmlspecialchars(rtrim(rtrim($c['weight'], '0'), '.') . ' ' . $wtUnit) : '' ?></td>
                            <td><?= $c['kept'] === null ? '' : ((int) $c['kept'] ? 'Kept' : 'Released') ?></td>
                            <td><?= htmlspecialchars($c['fly']) ?></td>
                            <td><?= htmlspecialchars($fmtTime($c['caught_at'])) ?></td>
                            <td><?= htmlspecialchars($c['note']) ?></td>
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

    <?php if ($report['photos']): ?>
        <section class="report-section report-photos-section">
            <h2 class="report-h2">Photos</h2>
            <div class="report-photo-grid">
                <?php foreach ($report['photos'] as $p): ?>
                    <figure>
                        <img src="<?= htmlspecialchars(url('admin/river-report-photo.php') . '?id=' . (int) $p['id']) ?>" alt="<?= htmlspecialchars($p['caption']) ?>" width="<?= (int) $p['width'] ?>" height="<?= (int) $p['height'] ?>">
                        <?php if ($p['caption'] !== ''): ?><figcaption><?= htmlspecialchars($p['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <footer class="report-foot">
        Gauge data: USGS Water Data (provisional). Weather: Open-Meteo<?= $weather ? ' (' . htmlspecialchars($weather['timezone']) . ')' : '' ?>.
        <?php if ($report['conditions_at']): ?>Conditions saved <?= htmlspecialchars(date('M j, Y', strtotime($report['conditions_at'] . ' UTC'))) ?>.<?php endif; ?>
        Report by <?= htmlspecialchars($report['author']) ?>.
    </footer>
</article>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
