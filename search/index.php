<?php
/*
 * index.php - Global site search results.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/search.php';

$perPage = 20;
$query = normalize_search_query($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$search_query = $query ?? '';

$page_title = $query
    ? 'Search: ' . $query . ' | ' . $site_name
    : 'Search | ' . $site_name;

$searchResults = null;
if ($query !== null) {
    $searchResults = search_global($query, $page, $perPage, [
        'about_hero_title' => $about_hero_title,
        'about_intro'      => $about_intro,
        'about_hero_image' => $about_hero_image,
        'about_editor'     => $about_editor,
    ]);
    if ($page > $searchResults['total_pages']) {
        $page = $searchResults['total_pages'];
        $searchResults = search_global($query, $page, $perPage, [
            'about_hero_title' => $about_hero_title,
            'about_intro'      => $about_intro,
            'about_hero_image' => $about_hero_image,
            'about_editor'     => $about_editor,
        ]);
    }
}

function search_page_url(?string $q, int $pageNum = 1): string
{
    $params = [];
    if ($q !== null && $q !== '') {
        $params['q'] = $q;
    }
    if ($pageNum > 1) {
        $params['page'] = $pageNum;
    }
    $queryString = $params ? '?' . http_build_query($params) : '';

    return url('search/') . $queryString;
}

require __DIR__ . '/../includes/header.php';
?>

<section class="search-page">
    <div class="container search-page-header">
        <h1 class="section-title">Search</h1>
        <form class="search-form" action="<?= htmlspecialchars(url('search/')) ?>" method="get" role="search">
            <label class="visually-hidden" for="search-page-input">Search</label>
            <input
                type="search"
                id="search-page-input"
                name="q"
                value="<?= htmlspecialchars($query ?? '') ?>"
                placeholder="Search articles, categories, and more..."
                aria-label="Search"
            >
            <button type="submit" class="btn-primary">Search</button>
        </form>
    </div>

    <div class="container search-page-content">
        <?php if ($query === null): ?>
            <p class="search-empty">Enter a search term to find articles, categories, and About page content.</p>
        <?php elseif (!$searchResults['has_results']): ?>
            <p class="search-empty">No results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>.</p>
        <?php else: ?>
            <p class="search-results-summary">
                Results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>
            </p>
            <?php
            $searchCategories = $searchResults['categories'];
            $searchArticles = $searchResults['articles'];
            $searchAbout = $searchResults['about'];
            require __DIR__ . '/../includes/partials/search-results.php';
            ?>

            <?php if ($searchResults['total_pages'] > 1): ?>
                <nav class="pagination" aria-label="Search result pages">
                    <?php if ($page > 1): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $page - 1)) ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $searchResults['total_pages']; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pagination-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $i)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $searchResults['total_pages']): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $page + 1)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
