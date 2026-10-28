<?php
/*
 * index.php - Global site search results (optional scope=galleries).
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/search.php';

$perPage = 20;
$query = normalize_search_query($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$search_query = $query ?? '';
$scope = strtolower(trim((string) ($_GET['scope'] ?? '')));
if ($scope !== 'galleries') {
    $scope = '';
}

$page_theme = 'search';
$page_title = $query
    ? 'Search: ' . $query . ' | ' . $site_name
    : 'Search | ' . $site_name;

$searchOptions = [
    'service_tool_groups' => $service_tool_groups,
];
if ($scope !== '') {
    $searchOptions['scope'] = $scope;
}

$searchResults = null;
if ($query !== null) {
    $searchResults = search_global($query, $page, $perPage, $searchOptions);
    if ($page > $searchResults['total_pages']) {
        $page = $searchResults['total_pages'];
        $searchResults = search_global($query, $page, $perPage, $searchOptions);
    }
}

function search_page_url(?string $q, int $pageNum = 1, string $scope = ''): string
{
    $params = [];
    if ($q !== null && $q !== '') {
        $params['q'] = $q;
    }
    if ($scope !== '') {
        $params['scope'] = $scope;
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
        <h1 class="section-title"><?= $scope === 'galleries' ? 'Search galleries' : 'Search' ?></h1>
        <form class="search-form" action="<?= htmlspecialchars(url('search/')) ?>" method="get" role="search">
            <?php if ($scope !== ''): ?>
                <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="search-page-input">Search</label>
            <input
                type="search"
                id="search-page-input"
                name="q"
                value="<?= htmlspecialchars($query ?? '') ?>"
                placeholder="<?= $scope === 'galleries'
                    ? 'Search galleries and photos...'
                    : 'Search articles, galleries, photos, tools, rivers...' ?>"
                aria-label="Search"
            >
            <button type="submit" class="btn-primary">Search</button>
        </form>
    </div>

    <div class="container search-page-content">
        <?php if ($query === null): ?>
            <p class="search-empty">
                <?= $scope === 'galleries'
                    ? 'Enter a search term to find galleries and photos.'
                    : 'Enter a search term to find articles, galleries, photos, tools, and rivers.' ?>
            </p>
        <?php elseif (!$searchResults['has_results']): ?>
            <p class="search-empty">No results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>.</p>
        <?php else: ?>
            <p class="search-results-summary">
                <?= (int) $searchResults['total'] ?> result<?= $searchResults['total'] === 1 ? '' : 's' ?>
                for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>
            </p>
            <?php
            $searchPanels = $searchResults['results'];
            require __DIR__ . '/../includes/partials/search-results.php';
            ?>

            <?php if ($searchResults['total_pages'] > 1): ?>
                <nav class="pagination" aria-label="Search result pages">
                    <?php if ($page > 1): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $page - 1, $scope)) ?>">&larr; Prev</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $searchResults['total_pages']; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="pagination-current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $i, $scope)) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $searchResults['total_pages']): ?>
                        <a class="pagination-link" href="<?= htmlspecialchars(search_page_url($query, $page + 1, $scope)) ?>">Next &rarr;</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
