<?php
/*
 * read.php - In-site paper/serif reader for hosted Gutenberg HTML.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/books.php';

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
$book = $slug !== '' ? get_book_by_slug($slug) : null;
$html = null;

if ($book) {
    $path = book_hosted_path($book);
    if ($path && is_file($path)) {
        $raw = (string) file_get_contents($path);
        $html = book_prepare_html($raw, (string) $book['source_id']);
    }
}

if (!$book || $html === null || trim($html) === '') {
    http_response_code(404);
    $page_theme = 'books';
    $page_title = 'Reader not available | ' . $site_name;
    require __DIR__ . '/../includes/header.php';
    echo '<section class="container books-missing">';
    echo '<h1 class="section-title">Reader not available</h1>';
    echo '<p>This title is not hosted for in-site reading yet.</p>';
    if ($book) {
        echo '<p><a class="btn-ghost" href="' . htmlspecialchars(book_page_url($book)) . '">&larr; Back to ' . htmlspecialchars($book['title']) . '</a></p>';
    } else {
        echo '<p><a class="btn-ghost" href="' . htmlspecialchars(url('books/')) . '">&larr; All books</a></p>';
    }
    echo '</section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$page_theme = 'books';
$page_title = $book['title'] . ' | ' . $site_name;
$gutenbergUrl = book_gutenberg_url($book);
require __DIR__ . '/../includes/header.php';
?>

<article class="book-reader">
    <div class="container book-reader-bar">
        <a class="btn-ghost" href="<?= htmlspecialchars(book_page_url($book)) ?>">&larr; <?= htmlspecialchars($book['title']) ?></a>
    </div>
    <header class="container book-reader-head">
        <p class="kicker">Public domain</p>
        <h1 class="section-title"><?= htmlspecialchars($book['title']) ?></h1>
        <?php if ($book['author'] !== ''): ?>
            <p class="book-meta"><?= htmlspecialchars($book['author']) ?><?php if ($book['year']): ?> &middot; <?= (int) $book['year'] ?><?php endif; ?></p>
        <?php endif; ?>
    </header>
    <div class="container book-reader-body">
        <?= $html ?>
    </div>
    <footer class="container book-reader-credit">
        <p>
            This ebook is from <a href="<?= htmlspecialchars($gutenbergUrl ?: 'https://www.gutenberg.org/') ?>" rel="noopener noreferrer">Project Gutenberg</a>.
            Public domain in the United States. Earth Odyssey does not claim copyright in the text.
        </p>
        <p><a class="btn-ghost" href="<?= htmlspecialchars(book_page_url($book)) ?>">&larr; Back to the book</a></p>
    </footer>
</article>

<?php require __DIR__ . '/../includes/footer.php'; ?>
