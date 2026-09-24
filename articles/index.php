<?php
/*
 * index.php - Public list of published articles.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/categories.php';
require __DIR__ . '/../includes/articles.php';
require __DIR__ . '/../includes/search.php';

$page_theme = 'articles';
$page_title = 'Articles | ' . $site_name;
$perPage = 20;
$categorySlug = isset($_GET['category']) && $_GET['category'] !== '' ? trim($_GET['category']) : null;
$query = normalize_search_query($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$isSearch = $query !== null;

$categories = get_categories('article');

if ($isSearch) {
    $searchResults = search_articles_scope($query, $page, $perPage, $categorySlug);
    if ($page > $searchResults['total_pages']) {
        $page = $searchResults['total_pages'];
        $searchResults = search_articles_scope($query, $page, $perPage, $categorySlug);
    }
    $featured = null;
    $articles = [];
    $totalPages = $searchResults['total_pages'];
} else {
    $featured = get_featured_article();
    $excludeId = $featured ? (int) $featured['id'] : null;
    $total = count_published_articles($categorySlug, $excludeId);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $articles = get_published_articles_page($page, $perPage, $categorySlug, $excludeId);
}

function articles_page_url(?string $categorySlug, int $pageNum = 1, ?string $searchQuery = null): string
{
    $params = [];
    if ($searchQuery !== null && $searchQuery !== '') {
        $params['q'] = $searchQuery;
    }
    if ($categorySlug !== null && $categorySlug !== '') {
        $params['category'] = $categorySlug;
    }
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    $queryString = $params ? '?' . http_build_query($params) : '';

    return url('articles/') . $queryString;
}

require __DIR__ . '/../includes/header.php';
?>

<section class="articles-page">
    <div class="container articles-page-header">
        <h1 class="section-title articles-page-title">Articles</h1>
        <form class="articles-search" action="<?= htmlspecialchars(url('articles/')) ?>" method="get" role="search">
            <?php if ($categorySlug): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="articles-search">Search articles</label>
            <input
                type="search"
                id="articles-search"
                name="q"
                value="<?= htmlspecialchars($query ?? '') ?>"
                placeholder="Search articles..."
                aria-label="Search articles"
            >
            <button type="submit" class="btn-primary">Search</button>
        </form>
        <?php if ($categorySlug): ?>
            <p class="category-filter-clear">
                <a href="<?= htmlspecialchars(url('articles/')) ?>">Show all categories</a>
            </p>
        <?php endif; ?>
        <?php if ($isSearch): ?>
            <p class="search-results-summary">
                <?php if ($searchResults['has_results']): ?>
                    Article results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>
                <?php else: ?>
                    No article results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>
                <?php endif; ?>
                <?php if ($categorySlug): ?>
                    in this category
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="category-panel">
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a
                    href="<?= htmlspecialchars(articles_page_url($cat['slug'])) ?>"
                    class="category-tile<?= $categorySlug === $cat['slug'] ? ' is-active' : '' ?>"
                    style="background-image: url('<?= htmlspecialchars(img_fit($cat['image_path'], 960)) ?>')"
                >
                    <span class="category-tile-label"><?= htmlspecialchars($cat['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="container articles-page-content">
    <?php if ($isSearch): ?>
        <?php if ($searchResults['has_results']): ?>
            <?php
            $searchCategories = $searchResults['categories'];
            $searchArticles = $searchResults['articles'];
            $searchAbout = [];
            require __DIR__ . '/../includes/partials/search-results.php';
            ?>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Article search pages">
                    <?php if ($page > 1): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $page - 1, $query)) ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pagination-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $i, $query)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $page + 1, $query)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    <?php else: ?>
    <?php if ($featured): ?>
        <section class="featured-article" aria-label="Featured article">
            <a href="<?= htmlspecialchars(url($featured['url'])) ?>" class="featured-article-link">
                <div class="featured-article-media">
                    <img
                        src="<?= htmlspecialchars(url($featured['thumbnail'])) ?>"<?= img_srcset($featured['thumbnail'], '(max-width: 800px) 100vw, 50vw') ?>
                        alt=""
                        width="480"
                        height="270"
                        decoding="async"
                        fetchpriority="high"
                    >
                </div>
                <div class="featured-article-body">
                    <span class="featured-label">Featured</span>
                    <h2 class="featured-article-title"><?= htmlspecialchars($featured['title']) ?></h2>
                    <p class="featured-article-meta">
                        By <?= htmlspecialchars($featured['author_name']) ?>
                        <?php if ($featured['published_at']): ?>
                            &middot; <?= htmlspecialchars(date('F j, Y', strtotime($featured['published_at']))) ?>
                        <?php endif; ?>
                        <?php if (!empty($featured['category_name'])): ?>
                            &middot; <span class="category-badge"><?= htmlspecialchars($featured['category_name']) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="featured-article-blurb"><?= htmlspecialchars($featured['blurb']) ?></p>
                    <span class="featured-article-cta">Read article &rarr;</span>
                </div>
            </a>
        </section>
    <?php endif; ?>

    <section class="articles-list-section" aria-label="Article list">
        <?php if ($articles): ?>
            <div class="article-list">
                <?php foreach ($articles as $article): ?>
                    <article class="article-row">
                        <a href="<?= htmlspecialchars(url($article['url'])) ?>" class="article-row-thumb">
                            <img
                                src="<?= htmlspecialchars(url($article['thumbnail'])) ?>"<?= img_srcset($article['thumbnail'], '160px') ?>
                                alt=""
                                width="160"
                                height="90"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                        <div class="article-row-body">
                            <h2 class="article-row-title">
                                <a href="<?= htmlspecialchars(url($article['url'])) ?>"><?= htmlspecialchars($article['title']) ?></a>
                            </h2>
                            <p class="article-row-meta">
                                By <?= htmlspecialchars($article['author_name']) ?>
                                <?php if ($article['published_at']): ?>
                                    &middot; <?= htmlspecialchars(date('F j, Y', strtotime($article['published_at']))) ?>
                                <?php endif; ?>
                                <?php if (!empty($article['category_name'])): ?>
                                    &middot; <span class="category-badge"><?= htmlspecialchars($article['category_name']) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="article-row-blurb"><?= htmlspecialchars($article['blurb']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Article pages">
                    <?php if ($page > 1): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $page - 1)) ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pagination-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $i)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(articles_page_url($categorySlug, $page + 1)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <p class="articles-empty">No published articles<?= $categorySlug ? ' in this category' : '' ?> yet.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
