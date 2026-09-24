<?php
/*
 * index.php - Home: cinematic hero, destination bands, article teasers, about.
 */
require __DIR__ . '/includes/config.php';

$page_theme = 'home';

try {
    require_once __DIR__ . '/includes/articles.php';
    require_once __DIR__ . '/includes/categories.php';
    $featured = get_featured_article();
    $excludeId = $featured ? (int) $featured['id'] : null;
    $latest_articles = get_published_articles_page(1, 3, null, $excludeId);

    $published_for_hero = get_published_articles(2);
    $hero_articles = [];
    foreach ($published_for_hero as $row) {
        $hero_articles[] = [
            'type'  => 'article',
            'title' => $row['title'],
            'blurb' => $row['blurb'],
            'url'   => $row['url'],
            'image' => $row['thumbnail'],
        ];
    }

    $gallery_for_hero = get_random_gallery_category();
    $hero_gallery = [];
    if ($gallery_for_hero) {
        $hero_gallery[] = [
            'type'  => 'gallery',
            'title' => $gallery_for_hero['name'],
            'blurb' => $gallery_for_hero['blurb'],
            'url'   => $gallery_for_hero['url'],
            'image' => $gallery_for_hero['image_path'],
        ];
    }

    $hero_slides = array_merge(
        $hero_articles,
        $hero_gallery,
        array_values(array_filter($hero_slides, function ($slide) {
            return $slide['type'] !== 'article' && $slide['type'] !== 'gallery';
        }))
    );

    // Prefer live gallery image on the Galleries destination band when available.
    if ($gallery_for_hero && !empty($home_sections[1])) {
        $home_sections[1]['image'] = $gallery_for_hero['image_path'];
    }
} catch (Throwable $e) {
    $featured = null;
}

$page_title = $site_name . ' | ' . $site_tagline;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero-carousel.php';
?>

<section class="home-destinations" aria-label="Explore Earth Odyssey">
    <?php foreach ($home_sections as $section): ?>
        <article
            class="home-destination"
            style="background-image: url('<?= htmlspecialchars(url($section['image'])) ?>')"
        >
            <div class="home-destination-inner">
                <p class="home-destination-label"><?= htmlspecialchars($section['label']) ?></p>
                <h2 class="home-destination-title"><?= htmlspecialchars($section['title']) ?></h2>
                <p class="home-destination-blurb"><?= htmlspecialchars($section['blurb']) ?></p>
                <a class="btn-primary" href="<?= htmlspecialchars(url($section['url'])) ?>"><?= htmlspecialchars($section['cta']) ?> &rarr;</a>
            </div>
        </article>
    <?php endforeach; ?>
</section>

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
        <h2 class="section-title">From the field</h2>
        <p class="home-teaser-intro">Recent stories and guides.</p>
        <div class="home-teaser-grid">
            <?php foreach ($teaser_items as $article): ?>
                <a class="home-teaser-card" href="<?= htmlspecialchars(url($article['url'])) ?>">
                    <img
                        src="<?= htmlspecialchars(url($article['thumbnail'] ?? '/assets/img/hero/day-hike.webp')) ?>"
                        alt=""
                        width="480"
                        height="300"
                        loading="lazy"
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

<section class="home-about container">
    <h2 class="section-title">About Earth Odyssey</h2>
    <p><?= htmlspecialchars($about_text) ?></p>
    <a class="btn-ghost" href="<?= htmlspecialchars(url('about/')) ?>">Meet the editor &rarr;</a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
