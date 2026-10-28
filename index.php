<?php
/*
 * index.php - Home: AllTrails layout (search hero, feature cards, map, collections)
 * with a Patagonia field-journal theme (tools, then galleries, then stories).
 */
require __DIR__ . '/includes/config.php';

$page_theme = 'home';

$home_hero_image = '/assets/img/hero/autumn-camp.webp';
$home_hero_gallery_url = '/galleries/';
$gallery_band_image = '/assets/img/hero/autumn-camp.webp';
$gallery_categories = [];
$featured = null;
$latest_articles = [];
$rivers_ok = false;

try {
    require_once __DIR__ . '/includes/articles.php';
    require_once __DIR__ . '/includes/categories.php';

    $featured = get_featured_article();
    $excludeId = $featured ? (int) $featured['id'] : null;
    $latest_articles = get_published_articles_page(1, 3, null, $excludeId);

    foreach (get_categories('gallery') as $row) {
        $gallery_categories[] = enrich_gallery_category($row);
    }

    $gallery_for_hero = get_random_gallery_category();
    if ($gallery_for_hero) {
        $home_hero_image = $gallery_for_hero['image_path'];
        $gallery_band_image = $gallery_for_hero['image_path'];
    }
} catch (Throwable $e) {
    error_log('Home page content load failed: ' . $e->getMessage());
}

try {
    require_once __DIR__ . '/includes/rivers.php';
    $rivers_ok = true;
} catch (Throwable $e) {
    error_log('Home page river map load failed: ' . $e->getMessage());
}

$showMap = false;
if ($rivers_ok) {
    try {
        river_state_counts();
        $showMap = true;
    } catch (Throwable $e) {
        error_log('Home page river map render failed: ' . $e->getMessage());
    }
}

$photo_pool = array_values(array_filter(array_map(
    static fn($g) => $g['image_path'] ?? '',
    $gallery_categories
)));
if (!$photo_pool) {
    $photo_pool = [$home_hero_image];
}

$is_hero_photo = static function (string $path): bool {
    if ($path === '' || preg_match('/\.svg$/i', $path)) {
        return false;
    }

    return is_file(asset_path($path));
};

$photo_i = 0;
$next_hero_photo = static function () use (&$photo_i, $photo_pool, $home_hero_image): string {
    if (!$photo_pool) {
        return $home_hero_image;
    }
    $path = $photo_pool[$photo_i % count($photo_pool)];
    $photo_i++;

    return $path;
};

$pick_hero_image = static function (array $needles, string $fallback) use ($gallery_categories, $is_hero_photo, $next_hero_photo): string {
    foreach ($gallery_categories as $g) {
        $path = (string) ($g['image_path'] ?? '');
        if (!$is_hero_photo($path)) {
            continue;
        }
        $blob = strtolower(($g['slug'] ?? '') . ' ' . ($g['name'] ?? ''));
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($blob, $needle)) {
                return $path;
            }
        }
    }

    return $is_hero_photo($fallback) ? $fallback : $next_hero_photo();
};

$home_hero_rotations = [
    [
        'topic' => 'Rivers',
        'example' => 'ex. Kenai',
        'mode' => 'rivers',
        'image' => '/assets/img/galleries/alaska/img_3439.webp',
    ],
    [
        'topic' => 'Research Tools',
        'example' => 'ex. Stream Gauges',
        'mode' => 'search',
        'image' => '/assets/img/galleries/alaska/img_3416.webp',
    ],
    [
        'topic' => 'Hatches',
        'example' => 'ex. Green Drake',
        'mode' => 'search',
        'image' => '/assets/img/galleries/alaska/img_3543.webp',
    ],
    [
        'topic' => 'Fish',
        'example' => 'ex. King Salmon',
        'mode' => 'search',
        'image' => '/assets/img/categories/fish.webp',
    ],
    [
        'topic' => 'Animals',
        'example' => 'ex. Frogs',
        'mode' => 'search',
        'image' => '/assets/img/galleries/alaska/img_3426.webp',
    ],
    [
        'topic' => 'Places',
        'example' => 'ex. Alaska',
        'mode' => 'search',
        'image' => '/assets/img/galleries/alaska/img_3537.webp',
    ],
    [
        'topic' => 'Stories',
        'example' => 'ex. Bear stories',
        'mode' => 'search',
        'image' => '/assets/img/galleries/alaska/img_3529.webp',
    ],
    [
        'topic' => 'Books',
        'example' => 'ex. Compleat Angler',
        'mode' => 'books',
        'image' => '/assets/img/galleries/alaska/img_3393.webp',
    ],
];

$feature_cards = [
    [
        'kicker' => 'Library',
        'title' => 'Books',
        'blurb' => 'Public-domain outdoor writing — fishing, campcraft, and the field.',
        'url' => '/books/',
        'image' => '/assets/img/galleries/alaska/img_3447.webp',
    ],
    [
        'kicker' => 'Tools',
        'title' => 'Research Tools',
        'blurb' => 'Stream gauges, fishing reports, and field helpers before you go.',
        'url' => '/services/',
        'image' => $pick_hero_image(['water', 'river', 'tool'], '/assets/img/hero/services.webp'),
    ],
    [
        'kicker' => 'Photos',
        'title' => 'Gallery',
        'blurb' => 'Landscapes, wildlife, campsites, and water from the field.',
        'url' => '/galleries/',
        'image' => '/assets/img/galleries/alaska/img_3544.webp',
    ],
];

$page_title = $site_name . ' | ' . $site_tagline;
require __DIR__ . '/includes/header.php';
?>

<div
    class="home-app"
    <?php if ($rivers_ok): ?>
    data-river-page="home"
    data-api="<?= htmlspecialchars(url('services/rivers/api.php')) ?>"
    data-site-url="<?= htmlspecialchars(url('services/rivers/site.php')) ?>"
    <?php endif; ?>
>
<?php require __DIR__ . '/includes/home-hero.php'; ?>

<section class="home-features" aria-label="What you can do here" data-feature-scroller>
    <button type="button" class="home-features-arrow is-prev" data-feature-prev aria-label="Previous panel" hidden>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 5l-7 7 7 7"></path></svg>
    </button>
    <div class="home-features-grid">
        <?php foreach ($feature_cards as $card): ?>
            <a class="home-feature" href="<?= htmlspecialchars(url($card['url'])) ?>">
                <img
                    src="<?= htmlspecialchars(url($card['image'])) ?>"<?= img_srcset($card['image'], '(max-width: 800px) 100vw, 33vw') ?>
                    alt=""
                    width="640"
                    height="420"
                    loading="lazy"
                    decoding="async"
                >
                <div class="home-feature-copy">
                    <p class="home-feature-kicker"><?= htmlspecialchars($card['kicker']) ?></p>
                    <h2 class="home-feature-title"><?= htmlspecialchars($card['title']) ?></h2>
                    <p class="home-feature-blurb"><?= htmlspecialchars($card['blurb']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <button type="button" class="home-features-arrow is-next" data-feature-next aria-label="Next panel">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 5l7 7-7 7"></path></svg>
    </button>
</section>

<?php if ($showMap): ?>
<section class="home-gauges" aria-labelledby="home-gauges-title">
    <div class="container">
        <p class="kicker">Tools</p>
        <h2 class="section-title" id="home-gauges-title">Check the water anywhere</h2>
        <p class="home-gauges-intro">Pick a highlighted state for live USGS gauges, or search a river by name above.</p>
    </div>
    <?php
    $pickerAction = url('services/rivers/');
    $pickerQuery = [];
    require __DIR__ . '/includes/partials/river-state-picker.php';
    ?>
    <p class="container home-gauges-more">
        <a href="<?= htmlspecialchars(url('services/')) ?>">See all tools &rarr;</a>
    </p>
</section>
<?php else: ?>
<section class="home-gauges" aria-labelledby="home-gauges-title">
    <div class="container">
        <p class="kicker">Tools</p>
        <h2 class="section-title" id="home-gauges-title">Check the water anywhere</h2>
        <p class="home-gauges-intro">Live USGS flow, height, and temperature before you fish or ford a creek.</p>
        <p class="home-gauges-more">
            <a class="btn-primary" href="<?= htmlspecialchars(url('services/rivers/')) ?>">Open stream gauges</a>
            <a href="<?= htmlspecialchars(url('services/')) ?>">See all tools &rarr;</a>
        </p>
    </div>
</section>
<?php endif; ?>
</div>

<?php if ($gallery_categories): ?>
<section class="home-collections" aria-labelledby="home-collections-title">
    <div class="container-wide">
        <p class="kicker">Galleries</p>
        <h2 class="section-title" id="home-collections-title">From the trail, camp, and water</h2>
        <p class="home-collections-intro">Photo collections from the field.</p>
        <div class="home-collections-track">
            <?php foreach ($gallery_categories as $gallery): ?>
                <a class="home-collection-card" href="<?= htmlspecialchars(url($gallery['url'])) ?>">
                    <img
                        src="<?= htmlspecialchars(url($gallery['image_path'])) ?>"<?= img_srcset($gallery['image_path'], '(max-width: 700px) 80vw, 320px') ?>
                        alt=""
                        width="480"
                        height="320"
                        loading="lazy"
                        decoding="async"
                    >
                    <span class="home-collection-name"><?= htmlspecialchars($gallery['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="home-collections-more">
            <a href="<?= htmlspecialchars(url('galleries/')) ?>">Open galleries &rarr;</a>
        </p>
    </div>
</section>
<?php else: ?>
<section class="home-destinations" aria-label="Galleries">
    <article
        class="home-destination"
        style="background-image: url('<?= htmlspecialchars(img_fit($gallery_band_image, 1600)) ?>')"
    >
        <div class="home-destination-inner">
            <p class="home-destination-label">Galleries</p>
            <h2 class="home-destination-title">Places worth seeing</h2>
            <p class="home-destination-blurb">Landscapes, wildlife, campsites, and water — photos from the field.</p>
            <a class="btn-primary" href="<?= htmlspecialchars(url('galleries/')) ?>">Open galleries &rarr;</a>
        </div>
    </article>
</section>
<?php endif; ?>

<?php
$teaser_items = [];
if (!empty($featured)) {
    $teaser_items[] = $featured;
}
if (!empty($latest_articles)) {
    foreach ($latest_articles as $article) {
        if (count($teaser_items) >= 3) {
            break;
        }
        if (!empty($featured) && (int) $article['id'] === (int) $featured['id']) {
            continue;
        }
        $teaser_items[] = $article;
    }
}
?>

<?php if ($teaser_items): ?>
<section class="home-teaser" aria-label="Latest articles">
    <div class="container">
        <p class="kicker">Stories</p>
        <h2 class="section-title">From the field</h2>
        <p class="home-teaser-intro">Guides and notes worth packing.</p>
        <div class="home-teaser-grid">
            <?php foreach ($teaser_items as $article): ?>
                <a class="home-teaser-card" href="<?= htmlspecialchars(url($article['url'])) ?>">
                    <img
                        src="<?= htmlspecialchars(url($article['thumbnail'] ?? '/assets/img/hero/day-hike.webp')) ?>"<?= img_srcset($article['thumbnail'] ?? '/assets/img/hero/day-hike.webp', '(max-width: 700px) 100vw, 33vw') ?>
                        alt=""
                        width="480"
                        height="300"
                        loading="lazy"
                        decoding="async"
                    >
                    <div class="home-teaser-card-body">
                        <h3 class="home-teaser-card-title"><?= htmlspecialchars($article['title']) ?></h3>
                        <p class="home-teaser-card-blurb"><?= htmlspecialchars($article['blurb']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="home-teaser-more">
            <a class="btn-secondary" href="<?= htmlspecialchars(url('articles/')) ?>">View all articles</a>
        </p>
    </div>
</section>
<?php endif; ?>

<section
    class="home-mission"
    style="background-image: url('<?= htmlspecialchars(img_fit($about_hero_image, 1600)) ?>')"
    aria-labelledby="home-mission-title"
>
    <div class="home-mission-inner">
        <p class="kicker">What&rsquo;s the Point?</p>
        <h2 class="home-mission-title" id="home-mission-title">Conquer with Your Feet and Mind</h2>
        <p class="home-mission-blurb">In the era of pollution and perpetual war, the places on earth that we most cherish are falling into ruin. While we still have scenic views and wild game, we should seek to protect it.</p>
        <div class="home-mission-actions">
            <a class="btn-primary" href="<?= htmlspecialchars(url('advocacy/')) ?>">Advocacy</a>
            <a class="btn-primary" href="<?= htmlspecialchars(url('galleries/')) ?>">Galleries</a>
            <a class="btn-primary" href="<?= htmlspecialchars(url('about/')) ?>">About Earth Odyssey</a>
        </div>
    </div>
</section>

<script src="<?= htmlspecialchars(asset_url('assets/js/home-features.js')) ?>" defer></script>
<?php if ($rivers_ok): ?>
<script src="<?= htmlspecialchars(asset_url('assets/js/rivers.js')) ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
