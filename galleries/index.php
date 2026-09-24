<?php
/*
 * index.php - Gallery categories hub, or a single category coming-soon via ?category=slug.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/categories.php';

$categorySlug = trim((string) ($_GET['category'] ?? ''));
$selectedGallery = null;

if ($categorySlug !== '') {
    try {
        $selectedGallery = get_gallery_category_by_slug($categorySlug);
    } catch (Throwable $e) {
        $selectedGallery = null;
    }
}

if ($selectedGallery) {
    $coming_soon_title = $selectedGallery['name'];
    $coming_soon_message = $selectedGallery['blurb'];
    $coming_soon_image = $selectedGallery['image_path'];
    $coming_soon_cta_label = 'See the gallery';
    $coming_soon_cta_url = '/galleries/';
    $page_theme = 'gallery';
    $page_title = $coming_soon_title . ' | ' . $site_name;

    require __DIR__ . '/../includes/header.php';
    require __DIR__ . '/../includes/coming-soon.php';
    require __DIR__ . '/../includes/footer.php';
    return;
}

$galleryCategories = [];
try {
    $galleryCategories = get_categories('gallery');
} catch (Throwable $e) {
    $galleryCategories = [];
}

$page_theme = 'gallery';
$page_title = 'Galleries | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="galleries-page">
    <div class="container articles-page-header">
        <h1 class="section-title articles-page-title">Galleries</h1>
        <p class="section-intro">Browse photo galleries by place and subject.</p>
    </div>

    <div class="category-panel">
        <?php if ($galleryCategories): ?>
            <div class="category-grid">
                <?php foreach ($galleryCategories as $cat): ?>
                    <a
                        href="<?= htmlspecialchars(url('/galleries/?category=' . rawurlencode($cat['slug']))) ?>"
                        class="category-tile"
                        style="background-image: url('<?= htmlspecialchars(url($cat['image_path'])) ?>')"
                    >
                        <span class="category-tile-label"><?= htmlspecialchars($cat['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="container">
                <p class="articles-empty">Gallery categories are on the way.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
