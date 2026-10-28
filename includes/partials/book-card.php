<?php
/*
 * book-card.php - Cover tile for the Books hub and catalog results.
 * Expects $book array.
 */
$cover = book_cover_url($book);
$href = book_page_url($book);
$title = (string) ($book['title'] ?? '');
$author = (string) ($book['author'] ?? '');
?>
<a class="book-card" href="<?= htmlspecialchars($href) ?>">
    <span class="book-card-cover<?= $cover === '' ? ' is-empty' : '' ?>">
        <?php if ($cover !== ''): ?>
            <img
                src="<?= htmlspecialchars($cover) ?>"
                alt=""
                width="240"
                height="360"
                loading="lazy"
                decoding="async"
            >
        <?php endif; ?>
    </span>
    <span class="book-card-title"><?= htmlspecialchars($title) ?></span>
    <?php if ($author !== ''): ?>
        <span class="book-card-author"><?= htmlspecialchars($author) ?></span>
    <?php endif; ?>
</a>
