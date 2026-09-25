<?php
/*
 * river-reports.php - Fishing reports list. Editors see every report; authors see their own.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-reports.php';

$user = require_login();
$reports = river_reports_for_user($user);

$page_theme = 'admin';
$page_title = 'Fishing Reports | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title">Fishing reports</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>

    <?php if (isset($_GET['deleted'])): ?>
        <p class="form-success">Report deleted.</p>
    <?php endif; ?>

    <div class="admin-actions">
        <a class="btn-primary" href="<?= htmlspecialchars(url('admin/river-report-edit.php')) ?>">New report</a>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('services/rivers/')) ?>">Find a river</a>
    </div>

    <?php if ($reports): ?>
        <table class="admin-table">
            <thead>
                <tr><th scope="col">Trip</th><th scope="col">Date</th><th scope="col">Gauges</th><th scope="col">Fish</th><?php if ($user['role'] === 'editor'): ?><th scope="col">By</th><?php endif; ?><th scope="col"></th></tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td><a href="<?= htmlspecialchars(url('admin/river-report-edit.php') . '?id=' . (int) $r['id']) ?>"><?= htmlspecialchars($r['title']) ?></a></td>
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
        <p>No reports yet. Start one here, or from any river page with "Start a report".</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
