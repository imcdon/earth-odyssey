<?php
/*
 * book.php - Single book: cover, metadata, attribution, read / Gutenberg / EPUB.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/books.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$gutenbergId = preg_replace('/\D+/', '', (string) ($_GET['gutenberg'] ?? $_GET['id'] ?? ''));

$book = null;
$books_ok = true;
try {
    if ($slug !== '') {
        $book = get_book_by_slug($slug);
    } elseif ($gutenbergId !== '') {
        $book = get_book_by_gutenberg_id($gutenbergId) ?? book_from_gutenberg($gutenbergId);
    }
} catch (Throwable $e) {
    $books_ok = false;
}

if (!$book) {
    http_response_code(404);
    $page_theme = 'books';
    $page_title = 'Book not found | ' . $site_name;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="container books-missing">';
    echo '<h1 class="section-title">Book not found</h1>';
    echo '<p>' . ($books_ok ? 'That title is not on the shelf.' : 'The books library is being set up.') . '</p>';
    echo '<p><a class="btn-ghost" href="' . htmlspecialchars(url('books/')) . '">&larr; All books</a></p>';
    echo '</section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$cover = book_cover_url($book);
$readUrl = book_read_url($book);
$gutenbergUrl = book_gutenberg_url($book);
$epubUrl = book_epub_url($book);
$labels = book_subject_labels((string) ($book['subjects'] ?? ''));
$year = $book['year'] ? (string) $book['year'] : '';

$page_theme = 'books';
$page_title = $book['title'] . ' | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<article class="container book-page">
    <p class="book-back"><a class="btn-ghost" href="<?= htmlspecialchars(url('books/')) ?>">&larr; All books</a></p>
    <div class="book-hero">
        <div class="book-hero-cover<?= $cover === '' ? ' is-empty' : '' ?>">
            <?php if ($cover !== ''): ?>
                <img
                    src="<?= htmlspecialchars($cover) ?>"
                    alt=""
                    width="320"
                    height="480"
                    decoding="async"
                    fetchpriority="high"
                >
            <?php endif; ?>
        </div>
        <div class="book-hero-copy">
            <p class="kicker">Public domain</p>
            <h1 class="section-title book-title"><?= htmlspecialchars($book['title']) ?></h1>
            <p class="book-meta">
                <?php if ($book['author'] !== ''): ?>
                    <?= htmlspecialchars($book['author']) ?>
                <?php endif; ?>
                <?php if ($year !== ''): ?>
                    <?php if ($book['author'] !== ''): ?> &middot; <?php endif; ?>
                    <?= htmlspecialchars($year) ?>
                <?php endif; ?>
            </p>
            <?php if ($labels): ?>
                <p class="book-tags">
                    <?php foreach ($labels as $slug => $label): ?>
                        <a href="<?= htmlspecialchars(book_hub_url($slug)) ?>"><?= htmlspecialchars($label) ?></a>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php if (($book['blurb'] ?? '') !== ''): ?>
                <p class="book-blurb"><?= htmlspecialchars($book['blurb']) ?></p>
            <?php endif; ?>
            <div class="book-actions">
                <?php if ($readUrl): ?>
                    <a class="btn-primary" href="<?= htmlspecialchars($readUrl) ?>">Read here</a>
                <?php endif; ?>
                <?php if ($gutenbergUrl): ?>
                    <a class="btn-secondary" href="<?= htmlspecialchars($gutenbergUrl) ?>" rel="noopener noreferrer">Read on Gutenberg</a>
                <?php endif; ?>
                <?php if ($epubUrl): ?>
                    <a class="btn-ghost" href="<?= htmlspecialchars($epubUrl) ?>" rel="noopener noreferrer">Download EPUB</a>
                <?php endif; ?>
            </div>
            <p class="book-credit">
                This work is in the public domain in the United States.
                <?php if (($book['source'] ?? '') === BOOK_SOURCE_GUTENBERG): ?>
                    Text and cover from <a href="<?= htmlspecialchars($gutenbergUrl ?: 'https://www.gutenberg.org/') ?>" rel="noopener noreferrer">Project Gutenberg</a>.
                <?php endif; ?>
            </p>
        </div>
    </div>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
