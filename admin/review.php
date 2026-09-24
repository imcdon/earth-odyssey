<?php
/*
 * review.php - Editor queue for pending articles.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/articles.php';

require_role('editor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $article = $id ? get_article_by_id($id) : null;
    $user = current_user();

    if ($article && $article['status'] === 'pending') {
        if ($action === 'publish') {
            save_article([
                'slug'         => $article['slug'],
                'title'        => $article['title'],
                'blurb'        => $article['blurb'],
                'body'         => $article['body'],
                'status'       => 'published',
                'author_id'    => (int) $article['author_id'],
                'editor_id'    => (int) $user['id'],
                'published_at' => date('Y-m-d H:i:s'),
                'category_id'  => $article['category_id'] !== null ? (int) $article['category_id'] : null,
                'thumbnail'    => $article['thumbnail'] ?? null,
            ], $id);
        } elseif ($action === 'reject') {
            save_article([
                'slug'         => $article['slug'],
                'title'        => $article['title'],
                'blurb'        => $article['blurb'],
                'body'         => $article['body'],
                'status'       => 'rejected',
                'author_id'    => (int) $article['author_id'],
                'editor_id'    => (int) $user['id'],
                'published_at' => null,
                'category_id'  => $article['category_id'] !== null ? (int) $article['category_id'] : null,
                'thumbnail'    => $article['thumbnail'] ?? null,
            ], $id);
        } elseif ($action === 'delete') {
            delete_article($id);
        }
    }

    header('Location: ' . url('admin/review.php'));
    exit;
}

$pending = get_pending_articles();
$page_theme = 'admin';
$page_title = 'Review Queue | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title">Review Queue</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>

    <?php if ($pending): ?>
        <ul class="admin-list review-list">
            <?php foreach ($pending as $article): ?>
                <li>
                    <div>
                        <strong><?= htmlspecialchars($article['title']) ?></strong>
                        <span class="article-meta">by <?= htmlspecialchars($article['author_name']) ?></span>
                        <p><?= htmlspecialchars($article['blurb']) ?></p>
                        <a href="<?= htmlspecialchars(url('admin/edit.php')) ?>?id=<?= (int) $article['id'] ?>">Edit before publishing</a>
                    </div>
                    <form method="post" action="" class="review-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $article['id'] ?>">
                        <button type="submit" name="action" value="publish" class="btn-primary">Publish</button>
                        <button type="submit" name="action" value="reject" class="btn-secondary">Reject</button>
                        <button type="submit" name="action" value="delete" class="btn-secondary" onclick="return confirm('Permanently delete this article?');">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No articles waiting for review.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
