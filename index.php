<?php
/*
 * index.php - Home page: hero carousel, feature blocks, latest articles, and about blurb.
 */
require __DIR__ . '/includes/config.php';

try {
    require_once __DIR__ . '/includes/articles.php';
    $featured = get_featured_article();
    $excludeId = $featured ? (int) $featured['id'] : null;
    $latest_articles = get_published_articles_page(1, 3, null, $excludeId);

    $published_for_hero = get_published_articles(2);
    $hero_articles = [];
    $hero_images = ['/assets/img/hero/day-hike.svg', '/assets/img/hero/weather.svg'];
    foreach ($published_for_hero as $i => $row) {
        $hero_articles[] = [
            'type'  => 'article',
            'title' => $row['title'],
            'blurb' => $row['blurb'],
            'url'   => $row['url'],
            'image' => $hero_images[$i] ?? '/assets/img/hero/day-hike.svg',
        ];
    }
    $hero_slides = array_merge(
        $hero_articles,
        array_values(array_filter($hero_slides, function ($slide) {
            return $slide['type'] !== 'article';
        }))
    );
} catch (Throwable $e) {
    // Fallback to config placeholders if database is unavailable.
    $featured = null;
}

$page_title = $site_name . ' | ' . $site_tagline;
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/hero-carousel.php';
?>

<section class="container">
    <div class="feature-grid">
        <?php foreach ($features as $feature): ?>
            <article class="card">
                <h2><?= htmlspecialchars($feature['title']) ?></h2>
                <p><?= htmlspecialchars($feature['description']) ?></p>
                <a class="card-link" href="<?= htmlspecialchars(url($feature['url'])) ?>">View <?= htmlspecialchars($feature['title']) ?> &rarr;</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="container home-articles">
    <h2 class="section-title">Latest Articles</h2>

    <?php if (!empty($featured)): ?>
        <section class="featured-article" aria-label="Featured article">
            <a href="<?= htmlspecialchars(url($featured['url'])) ?>" class="featured-article-link">
                <div class="featured-article-media">
                    <img
                        src="<?= htmlspecialchars(url($featured['thumbnail'])) ?>"
                        alt=""
                        width="480"
                        height="270"
                        loading="eager"
                    >
                </div>
                <div class="featured-article-body">
                    <span class="featured-label">Featured</span>
                    <h3 class="featured-article-title"><?= htmlspecialchars($featured['title']) ?></h3>
                    <p class="featured-article-meta">
                        By <?= htmlspecialchars($featured['author_name']) ?>
                        <?php if ($featured['published_at']): ?>
                            &middot; <?= htmlspecialchars(date('F j, Y', strtotime($featured['published_at']))) ?>
                        <?php endif; ?>
                        <?php if (!empty($featured['category_name'])): ?>
                            &middot; <span class="category-badge"><?= htmlspecialchars($featured['category_name']) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="featured-article-blurb"><?= htmlspecialchars($featured['blurb']) ?></p>
                    <span class="featured-article-cta">Read article &rarr;</span>
                </div>
            </a>
        </section>
    <?php endif; ?>

    <?php if (!empty($latest_articles)): ?>
        <div class="article-list">
            <?php foreach ($latest_articles as $article): ?>
                <article class="article-row">
                    <a href="<?= htmlspecialchars(url($article['url'])) ?>" class="article-row-thumb">
                        <img
                            src="<?= htmlspecialchars(url($article['thumbnail'] ?? '/assets/img/hero/day-hike.svg')) ?>"
                            alt=""
                            width="160"
                            height="90"
                            loading="lazy"
                        >
                    </a>
                    <div class="article-row-body">
                        <h3 class="article-row-title">
                            <a href="<?= htmlspecialchars(url($article['url'])) ?>"><?= htmlspecialchars($article['title']) ?></a>
                        </h3>
                        <?php if (!empty($article['author_name'])): ?>
                            <p class="article-row-meta">
                                By <?= htmlspecialchars($article['author_name']) ?>
                                <?php if (!empty($article['published_at'])): ?>
                                    &middot; <?= htmlspecialchars(date('F j, Y', strtotime($article['published_at']))) ?>
                                <?php endif; ?>
                                <?php if (!empty($article['category_name'])): ?>
                                    &middot; <span class="category-badge"><?= htmlspecialchars($article['category_name']) ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <p class="article-row-blurb"><?= htmlspecialchars($article['blurb']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="home-articles-more"><a href="<?= htmlspecialchars(url('articles/')) ?>">View all articles &rarr;</a></p>
    <?php endif; ?>
</section>

<section class="container about-section">
    <h2 class="section-title">About Earth Odyssey</h2>
    <p><?= htmlspecialchars($about_text) ?></p>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
