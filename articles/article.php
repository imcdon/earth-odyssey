<?php
/*
 * article.php - Single published article view (Markdown body).
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/markdown.php';

$slug = $_GET['slug'] ?? '';
$article = $slug ? get_article_by_slug($slug) : null;

if (!$article) {
    http_response_code(404);
    $page_theme = 'articles';
    $page_title = 'Article not found | ' . $site_name;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="container"><h1 class="section-title">Article not found</h1><p><a href="' . htmlspecialchars(url('articles/')) . '">Back to articles</a></p></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_theme = 'articles';
$page_title = htmlspecialchars($article['title']) . ' | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<article class="container article-page">
    <p class="article-back article-back-top"><a class="btn-ghost" href="<?= htmlspecialchars(url('articles/')) ?>">&larr; All articles</a></p>
    <header class="article-header">
        <h1 class="section-title"><?= htmlspecialchars($article['title']) ?></h1>
        <p class="article-meta">
            By <?= htmlspecialchars($article['author_name']) ?>
            <?php if ($article['published_at']): ?>
                &middot; <?= htmlspecialchars(date('F j, Y', strtotime($article['published_at']))) ?>
            <?php endif; ?>
        </p>
    </header>
    <div class="article-body article-prose">
        <?= render_markdown($article['body']) ?>
    </div>
    <p class="article-back"><a class="btn-ghost" href="<?= htmlspecialchars(url('articles/')) ?>">&larr; All articles</a></p>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
