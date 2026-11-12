<?php
/*
 * reports.php - Fishing reports list. Editors see every report; authors see their own.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/river-reports.php';

$user = require_permission('fishing_reports');
$reports = river_reports_for_user($user);

$page_theme = 'rivers';
$page_title = 'Fishing Reports | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page">
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a> &middot; Fishing reports</p>
            <h1 class="rivers-title">Fishing reports</h1>
            <p class="rivers-intro">Write up a trip. Gauge readings and weather are filled in when you save.</p>
            <div class="rivers-head-tools">
                <a class="btn-primary" href="<?= htmlspecialchars(url('services/rivers/report-edit.php')) ?>">New report</a>
                <a class="rivers-map-back" href="<?= htmlspecialchars(url('services/rivers/')) ?>" data-river-back>&larr; Back</a>
            </div>
        </div>
    </header>

    <section class="container admin-page">
        <?php if (isset($_GET['deleted'])): ?>
            <p class="form-success">Report deleted.</p>
        <?php endif; ?>

        <?php if ($reports): ?>
            <table class="admin-table">
                <thead>
                    <tr><th scope="col">Trip</th><th scope="col">Date</th><th scope="col">Gauges</th><th scope="col">Fish</th><?php if ($user['role'] === 'editor'): ?><th scope="col">By</th><?php endif; ?><th scope="col"></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><a href="<?= htmlspecialchars(url('services/rivers/report-edit.php') . '?id=' . (int) $r['id']) ?>"><?= htmlspecialchars($r['title']) ?></a></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($r['trip_date']))) ?></td>
                            <td><?= htmlspecialchars((string) $r['gauges']) ?></td>
                            <td><?= (int) $r['fish'] ?></td>
                            <?php if ($user['role'] === 'editor'): ?><td><?= htmlspecialchars($r['author']) ?></td><?php endif; ?>
                            <td><a href="<?= htmlspecialchars(url('services/rivers/report.php') . '?id=' . (int) $r['id']) ?>">View / print</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?= $user
                ? 'No reports yet. Start one here, or from any river page with "Start a report".'
                : 'You can write the report now. You\'ll sign in when you save.' ?></p>
        <?php endif; ?>
    </section>
</div>

<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
