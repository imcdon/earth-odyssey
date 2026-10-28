<?php
/*
 * index.php - Immersive photo-essay hub and cinematic album views.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/categories.php';

$categorySlug = trim((string) ($_GET['category'] ?? ''));
if ($categorySlug === '') {
    $request = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $home = rtrim(url(), '/') ?: '';
    if ($home !== '' && str_starts_with($request, $home)) {
        $request = substr($request, strlen($home)) ?: '/';
    }
    if (preg_match('#^/galleries/([a-z0-9-]+)/?$#', $request, $m)) {
        $categorySlug = $m[1];
    }
}

$selectedGallery = null;
if ($categorySlug !== '') {
    try {
        $selectedGallery = get_gallery_category_by_slug($categorySlug);
    } catch (Throwable $e) {
        $selectedGallery = null;
    }
}

if ($selectedGallery) {
    $photos = get_gallery_photos($selectedGallery['slug']);
    $page_theme = 'gallery';
    $page_title = $selectedGallery['name'] . ' | ' . $site_name;

    if (!$photos) {
        $coming_soon_title = $selectedGallery['name'];
        $coming_soon_message = $selectedGallery['blurb'];
        $coming_soon_image = $selectedGallery['image_path'];
        $coming_soon_cta_label = 'Back to galleries';
        $coming_soon_cta_url = '/galleries/';
        require __DIR__ . '/../includes/header.php';
        require __DIR__ . '/../includes/coming-soon.php';
        require __DIR__ . '/../includes/footer.php';
        return;
    }

    $photoTotal = count($photos);
    $viewerPhotos = [];
    foreach ($photos as $photo) {
        $srcsetParts = [];
        foreach (img_variants($photo['path']) as $w => $variantPath) {
            $srcsetParts[] = url($variantPath) . ' ' . $w . 'w';
        }
        $viewerPhotos[] = [
            'src'         => url($photo['path']),
            'srcset'      => implode(', ', $srcsetParts),
            'title'       => $photo['title'],
            'description' => gallery_photo_description($photo, $selectedGallery['name']),
            'camera'      => $photo['camera'] ?? null,
            'taken_at'    => $photo['taken_at_display'] ?? null,
            'width'       => (int) $photo['width'],
            'height'      => (int) $photo['height'],
        ];
    }

    $startIndex = max(0, min($photoTotal - 1, (int) ($_GET['i'] ?? 0)));
    $first = $viewerPhotos[$startIndex];
    $page_theme = 'gallery';
    $body_class = 'gallery-viewer-active';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section
        class="gallery-viewer"
        data-gallery-viewer
        data-index="<?= (int) $startIndex ?>"
        aria-label="<?= htmlspecialchars($selectedGallery['name']) ?> gallery"
    >
        <header class="gallery-viewer-top">
            <a class="gallery-viewer-back" href="<?= htmlspecialchars(url('galleries/')) ?>" aria-label="Back to galleries">&larr;</a>
            <h1 class="gallery-viewer-category"><?= htmlspecialchars($selectedGallery['name']) ?></h1>
            <p class="gallery-viewer-count" data-gallery-count><?= ($startIndex + 1) . ' / ' . $photoTotal ?></p>
        </header>

        <div class="gallery-viewer-stage">
            <button type="button" class="gallery-viewer-nav gallery-viewer-prev" data-gallery-prev aria-label="Previous photo">&larr;</button>
            <img
                class="gallery-viewer-image"
                src="<?= htmlspecialchars($first['src']) ?>"
                <?php if ($first['srcset'] !== ''): ?>
                srcset="<?= htmlspecialchars($first['srcset']) ?>"
                sizes="100vw"
                <?php endif; ?>
                alt="<?= htmlspecialchars($first['title']) ?>"
                width="<?= (int) $first['width'] ?>"
                height="<?= (int) $first['height'] ?>"
                data-gallery-image
                decoding="async"
                fetchpriority="high"
            >
            <button type="button" class="gallery-viewer-nav gallery-viewer-next" data-gallery-next aria-label="Next photo">&rarr;</button>
        </div>

        <footer class="gallery-viewer-bottom">
            <h2 class="gallery-viewer-title" data-gallery-title><?= htmlspecialchars($first['title']) ?></h2>
            <p class="gallery-viewer-desc" data-gallery-desc><?= htmlspecialchars($first['description']) ?></p>
        </footer>

        <script type="application/json" data-gallery-photos><?= json_encode($viewerPhotos, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </section>
    <script src="<?= htmlspecialchars(asset_url('assets/js/gallery.js')) ?>" defer></script>
    <?php
    require __DIR__ . '/../includes/footer.php';
    return;
}

$galleryCategories = [];
try {
    $raw = get_categories('gallery');
    foreach ($raw as $cat) {
        $galleryCategories[] = enrich_gallery_for_hub($cat);
    }
} catch (Throwable $e) {
    $galleryCategories = [];
}

$heroSlides = [];
$heroFeatured = null;
foreach ($galleryCategories as $cat) {
    if (empty($cat['cover'])) {
        continue;
    }
    $slide = [
        'name' => $cat['name'],
        'example' => 'Search ' . $cat['name'] . '...',
        'image' => $cat['cover'],
    ];
    if ($heroFeatured === null && !empty($cat['is_featured'])) {
        $heroFeatured = $slide;
        continue;
    }
    $heroSlides[] = $slide;
}
if ($heroFeatured !== null) {
    array_unshift($heroSlides, $heroFeatured);
}

$firstHero = $heroSlides[0] ?? null;

$page_theme = 'gallery';
$page_title = 'Galleries | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="galleries-experience">
    <header
        class="galleries-hero<?= $firstHero ? '' : ' galleries-hero--empty' ?>"
        <?php if (count($heroSlides) > 1): ?>
        data-galleries-hero-rotator
        data-hero-interval="4500"
        <?php endif; ?>
    >
        <?php if ($heroSlides): ?>
            <div class="galleries-hero-media" aria-hidden="true">
                <?php foreach ($heroSlides as $hi => $slide): ?>
                    <img
                        class="galleries-hero-image<?= $hi === 0 ? ' is-active' : '' ?>"
                        src="<?= htmlspecialchars(img_fit($slide['image'], 1600)) ?>"<?= img_srcset($slide['image'], '100vw') ?>
                        alt=""
                        width="1600"
                        height="1000"
                        <?= $hi === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                        decoding="async"
                        data-galleries-hero-image
                    >
                <?php endforeach; ?>
            </div>
            <div class="galleries-hero-veil" aria-hidden="true"></div>
        <?php endif; ?>
        <div class="galleries-hero-copy">
            <h1 class="galleries-hero-brand">Galleries</h1>
            <p class="galleries-hero-lede">View Collections of Photos</p>
            <form
                class="galleries-hero-search"
                action="<?= htmlspecialchars(url('search/')) ?>"
                method="get"
                role="search"
            >
                <input type="hidden" name="scope" value="galleries">
                <label class="visually-hidden" for="galleries-q">Search galleries</label>
                <span class="galleries-hero-search-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20l-3.5-3.5"></path>
                    </svg>
                </span>
                <?php if ($heroSlides): ?>
                    <div class="galleries-hero-search-terms" data-galleries-hero-terms aria-hidden="true">
                        <?php foreach ($heroSlides as $hi => $slide): ?>
                            <span
                                class="galleries-hero-search-term<?= $hi === 0 ? ' is-active' : '' ?>"
                                data-galleries-hero-term
                            ><?= htmlspecialchars($slide['example']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <input
                    id="galleries-q"
                    type="search"
                    name="q"
                    placeholder="<?= count($heroSlides) > 1 ? '' : htmlspecialchars($firstHero['example'] ?? 'Search galleries...') ?>"
                    autocomplete="off"
                    aria-label="Search galleries"
                    data-galleries-hero-input
                >
                <button type="submit" class="galleries-hero-search-submit">Search</button>
            </form>
            <?php if (count($heroSlides) > 1): ?>
                <script type="application/json" data-galleries-hero-slides><?= json_encode($heroSlides, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($galleryCategories): ?>
        <div class="galleries-panels" data-galleries-panels>
            <?php foreach ($galleryCategories as $cat): ?>
                <?php
                $previews = $cat['preview_photos'] ?? [];
                if ($previews === [] && !empty($cat['cover'])) {
                    $previews = [$cat['cover']];
                }
                $thumbs = array_slice($previews, 1, 6);
                ?>
                <a
                    class="galleries-panel"
                    href="<?= htmlspecialchars(url($cat['url'])) ?>"
                    data-gallery-panel
                >
                    <div class="galleries-panel-media" aria-hidden="true">
                        <?php if ($previews !== []): ?>
                            <?php foreach ($previews as $pi => $path): ?>
                                <img
                                    class="galleries-panel-slide<?= $pi === 0 ? ' is-active' : '' ?>"
                                    src="<?= htmlspecialchars(img_fit($path, 1600)) ?>"<?= img_srcset($path, '100vw') ?>
                                    alt=""
                                    width="1600"
                                    height="1000"
                                    loading="<?= $pi === 0 ? 'eager' : 'lazy' ?>"
                                    decoding="async"
                                    <?= $pi === 0 ? 'fetchpriority="high"' : '' ?>
                                >
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="galleries-panel-placeholder"></span>
                        <?php endif; ?>
                    </div>
                    <div class="galleries-panel-veil" aria-hidden="true"></div>
                    <div class="galleries-panel-copy">
                        <h2 class="galleries-panel-name"><?= htmlspecialchars($cat['name']) ?></h2>
                        <p class="galleries-panel-subtext"><?= htmlspecialchars($cat['blurb']) ?></p>
                        <?php if (!empty($cat['photo_count'])): ?>
                            <p class="galleries-panel-meta"><?= (int) $cat['photo_count'] ?> photos</p>
                        <?php endif; ?>
                        <?php if ($thumbs !== []): ?>
                            <div class="galleries-panel-thumbs" aria-hidden="true">
                                <?php foreach ($thumbs as $thumb): ?>
                                    <img
                                        src="<?= htmlspecialchars(img_fit($thumb, 480)) ?>"
                                        alt=""
                                        width="88"
                                        height="66"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <script src="<?= htmlspecialchars(asset_url('assets/js/galleries-hub.js')) ?>" defer></script>
    <?php else: ?>
        <div class="container galleries-empty">
            <p>Gallery categories are on the way.</p>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>