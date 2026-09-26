<?php
/*
 * index.php - Admin dashboard with role-based links.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/river-admin.php';

$user = require_login();
$apiAlert = $user['role'] === 'editor' ? river_api_alert() : null;
$articles = get_articles_for_user((int) $user['id'], $user['role']);

$page_theme = 'admin';
$page_title = 'Admin | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title">Admin</h1>
    <p class="section-intro">Logged in as <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>).</p>

    <?php if ($apiAlert): ?>
        <p class="admin-alert"><?= htmlspecialchars($apiAlert) ?> <a href="<?= htmlspecialchars(url('admin/river-api.php')) ?>">View API usage</a></p>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <p class="form-success">Article deleted.</p>
    <?php endif; ?>

    <div class="admin-actions">
        <a class="btn-primary" href="<?= htmlspecialchars(url('admin/edit.php')) ?>">New article</a>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-reports.php')) ?>">Fishing reports</a>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-ranges.php')) ?>">Fishability ranges</a>
        <?php if ($user['role'] === 'editor'): ?>
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-api.php')) ?>">API usage</a>
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/review.php')) ?>">Review queue</a>
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/categories.php')) ?>">Manage categories</a>
        <?php endif; ?>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/account.php')) ?>">Change password</a>
        <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/logout.php')) ?>">Log out</a>
    </div>

    <h2 class="section-title">Articles</h2>
    <?php if ($articles): ?>
        <ul class="admin-list">
            <?php foreach ($articles as $article): ?>
                <li>
                    <a href="<?= htmlspecialchars(url('admin/edit.php')) ?>?id=<?= (int) $article['id'] ?>"><?= htmlspecialchars($article['title']) ?></a>
                    <span class="status-badge status-<?= htmlspecialchars($article['status']) ?>"><?= htmlspecialchars($article['status']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No articles yet.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
