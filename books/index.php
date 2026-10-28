<?php
/*
 * index.php - Books hub: featured public-domain shelf, subject filters, catalog search.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/books.php';
require __DIR__ . '/../includes/search.php';

$page_theme = 'books';
$page_title = 'Books | ' . $site_name;
$subject = book_subject_slug($_GET['subject'] ?? null);
$query = normalize_search_query($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$isSearch = $query !== null || $subject !== null;

$featured = [];
$catalog = null;
$books_ok = true;

try {
    $featured = get_featured_books($isSearch && $query === null ? $subject : null);
} catch (Throwable $e) {
    $featured = [];
    $books_ok = false;
}

if ($query !== null) {
    try {
        $catalog = book_catalog_search($query ?? '', $subject, $page);
        if ($catalog['ok'] && $page > $catalog['pages']) {
            $page = $catalog['pages'];
            $catalog = book_catalog_search($query ?? '', $subject, $page);
        }
    } catch (Throwable $e) {
        $catalog = [
            'ok' => false,
            'stale' => false,
            'count' => 0,
            'page' => 1,
            'pages' => 1,
            'results' => [],
            'error' => 'The public-domain catalog is unavailable right now.',
        ];
    }
}

require __DIR__ . '/../includes/header.php';
?>

<section class="books-page">
    <div class="container books-page-header">
        <h1 class="section-title books-page-title">Books</h1>
        <p class="section-intro">A shelf of public-domain outdoor writing — fishing, hunting, campcraft, and the field. Search the catalog for more.</p>
        <form class="articles-search books-search" action="<?= htmlspecialchars(url('books/')) ?>" method="get" role="search">
            <?php if ($subject): ?>
                <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="books-search">Search public-domain books</label>
            <input
                type="search"
                id="books-search"
                name="q"
                value="<?= htmlspecialchars($query ?? '') ?>"
                placeholder="Search fishing, camping, Muir…"
                aria-label="Search public-domain books"
            >
            <button type="submit" class="btn-primary">Search</button>
        </form>
        <nav class="book-subjects" aria-label="Book subjects">
            <a
                href="<?= htmlspecialchars(book_hub_url(null, $query)) ?>"
                class="<?= $subject === null ? 'is-active' : '' ?>"
            >All</a>
            <?php foreach (book_subjects() as $slug => $label): ?>
                <a
                    href="<?= htmlspecialchars(book_hub_url($slug, $query)) ?>"
                    class="<?= $subject === $slug ? 'is-active' : '' ?>"
                ><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($catalog && !empty($catalog['stale'])): ?>
            <p class="books-note">Showing a saved catalog result while the live catalog is slow.</p>
        <?php endif; ?>
    </div>

    <?php if (!$isSearch || $query === null): ?>
        <div class="container books-shelf">
            <h2 class="books-shelf-title">
                <?= $subject ? htmlspecialchars(BOOK_SUBJECTS[$subject]) : 'Featured shelf' ?>
            </h2>
            <?php if ($featured): ?>
                <div class="book-grid">
                    <?php foreach ($featured as $book): ?>
                        <?php require __DIR__ . '/../includes/partials/book-card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!$books_ok): ?>
                <p class="articles-empty">The books library is being set up.</p>
            <?php else: ?>
                <p class="articles-empty">No featured titles in this subject yet. Search the catalog below.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($query !== null): ?>
        <div class="container books-catalog">
            <h2 class="books-shelf-title">Catalog</h2>
            <?php if ($catalog && $catalog['ok'] && $catalog['results']): ?>
                <p class="search-results-summary">
                    Public-domain results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>
                    <?php if ($subject): ?>
                        in <?= htmlspecialchars(BOOK_SUBJECTS[$subject]) ?>
                    <?php endif; ?>
                </p>
                <div class="book-grid">
                    <?php foreach ($catalog['results'] as $book): ?>
                        <?php require __DIR__ . '/../includes/partials/book-card.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($catalog['pages'] > 1): ?>
                    <nav class="pagination" aria-label="Catalog pages">
                        <?php if ($page > 1): ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(book_hub_url($subject, $query, $page - 1)) ?>">&larr; Prev</a>
                        <?php endif; ?>
                        <?php
                        $from = max(1, $page - 3);
                        $to = min($catalog['pages'], $page + 3);
                        for ($i = $from; $i <= $to; $i++):
                        ?>
                            <?php if ($i === $page): ?>
                                <span class="pagination-current" aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a class="pagination-link" href="<?= htmlspecialchars(book_hub_url($subject, $query, $i)) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $catalog['pages']): ?>
                            <a class="pagination-link" href="<?= htmlspecialchars(book_hub_url($subject, $query, $page + 1)) ?>">Next &rarr;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php elseif ($catalog && !empty($catalog['error'])): ?>
                <p class="articles-empty"><?= htmlspecialchars($catalog['error']) ?></p>
            <?php else: ?>
                <p class="articles-empty">
                    No catalog results for <strong class="search-query-display"><?= htmlspecialchars($query) ?></strong>.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
