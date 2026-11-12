<?php
/*
 * index.php - Staff admin dashboard: articles, gallery, and a door into the angler river dashboard.
 */
require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/river-admin.php';

$user = require_staff();
$isEditor = $user['role'] === 'editor';
$apiAlert = $isEditor ? river_api_alert() : null;
$articles = get_articles_for_user((int) $user['id'], $user['role']);
$articleCount = count($articles);
$pending = array_values(array_filter($articles, static fn($a) => ($a['status'] ?? '') === 'pending'));
$pendingAccounts = $isEditor ? auth_pending_count() : 0;

$page_theme = 'admin';
$page_title = 'Admin dashboard | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page admin-dashboard">
    <header class="admin-dash-head">
        <div>
            <p class="kicker">Staff</p>
            <h1 class="section-title">Admin dashboard</h1>
            <p class="section-intro">Signed in as <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['role']) ?>).</p>
        </div>
        <div class="admin-dash-head-actions">
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/account.php')) ?>">Account</a>
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/logout.php')) ?>">Log out</a>
        </div>
    </header>

    <?php if ($apiAlert): ?>
        <p class="admin-alert"><?= htmlspecialchars($apiAlert) ?> <a href="<?= htmlspecialchars(url('admin/river-api.php')) ?>">View API usage</a></p>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <p class="form-success">Article deleted.</p>
    <?php endif; ?>

    <div class="admin-dash-grid">
        <?php if ($isEditor): ?>
        <article class="admin-dash-panel">
            <p class="kicker">People</p>
            <h2 class="admin-dash-title">Accounts</h2>
            <p class="admin-dash-copy">
                Approve or deny signups and set permissions.
                <?= $pendingAccounts ? htmlspecialchars((string) $pendingAccounts) . ' waiting for review' : 'No pending requests' ?>.
            </p>
            <div class="admin-dash-actions">
                <a class="btn-primary" href="<?= htmlspecialchars(url('admin/users.php')) ?>">Manage accounts</a>
            </div>
        </article>
        <?php endif; ?>

        <article class="admin-dash-panel">
            <p class="kicker">Content</p>
            <h2 class="admin-dash-title">Articles</h2>
            <p class="admin-dash-copy">
                Write and edit field stories.
                <?= $articleCount ? htmlspecialchars((string) $articleCount) . ' in your list' : 'None yet' ?><?= $pending ? ' · ' . count($pending) . ' pending review' : '' ?>.
            </p>
            <div class="admin-dash-actions">
                <a class="btn-primary" href="<?= htmlspecialchars(url('admin/edit.php')) ?>">New article</a>
                <?php if ($isEditor): ?>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/review.php')) ?>">Review queue</a>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/categories.php')) ?>">Categories</a>
                <?php endif; ?>
            </div>
            <?php if ($articles): ?>
                <ul class="admin-list admin-dash-list">
                    <?php foreach (array_slice($articles, 0, 6) as $article): ?>
                        <li>
                            <a href="<?= htmlspecialchars(url('admin/edit.php')) ?>?id=<?= (int) $article['id'] ?>"><?= htmlspecialchars($article['title']) ?></a>
                            <span class="status-badge status-<?= htmlspecialchars($article['status']) ?>"><?= htmlspecialchars($article['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($articleCount > 6): ?>
                    <p class="admin-dash-more"><a href="<?= htmlspecialchars(url('admin/edit.php')) ?>">Open article editor</a></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="admin-dash-empty">No articles yet. Start one with New article.</p>
            <?php endif; ?>
        </article>

        <article class="admin-dash-panel">
            <p class="kicker">Media</p>
            <h2 class="admin-dash-title">Gallery</h2>
            <p class="admin-dash-copy">Manage gallery categories and upload photos for the public galleries.</p>
            <div class="admin-dash-actions">
                <?php if ($isEditor): ?>
                    <a class="btn-primary" href="<?= htmlspecialchars(url('admin/gallery-photos.php')) ?>">Gallery photos</a>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/categories.php')) ?>">Categories</a>
                <?php else: ?>
                    <p class="admin-dash-empty">Gallery tools are available to editors.</p>
                <?php endif; ?>
            </div>
            <p class="admin-dash-more"><a href="<?= htmlspecialchars(url('galleries/')) ?>">View public galleries</a></p>
        </article>

        <article class="admin-dash-panel">
            <p class="kicker">Water</p>
            <h2 class="admin-dash-title">Stream gauges</h2>
            <p class="admin-dash-copy">Open your angler dashboard of saved rivers, or manage site-wide fishability ranges and API usage.</p>
            <div class="admin-dash-actions">
                <a class="btn-primary" href="<?= htmlspecialchars(url('services/rivers/')) ?>">Account stream gauge dashboard</a>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-ranges.php')) ?>">Fishability ranges</a>
                <?php if ($isEditor): ?>
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/river-api.php')) ?>">API usage</a>
                <?php endif; ?>
            </div>
            <p class="admin-dash-more"><a href="<?= htmlspecialchars(url('services/rivers/') . '?explore=1') ?>">Open map explorer</a></p>
        </article>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
