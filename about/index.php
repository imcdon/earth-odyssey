<?php
/*
 * index.php - About Earth Odyssey: hero and four pillars.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/categories.php';

$page_title = 'About | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section
    class="about-hero"
    style="background-image: url('<?= htmlspecialchars(url($about_hero_image)) ?>')"
    aria-label="<?= htmlspecialchars($about_hero_title) ?>"
>
    <div class="about-hero-overlay">
        <h1 class="about-hero-title"><?= htmlspecialchars($about_hero_title) ?></h1>
        <p class="about-intro"><?= htmlspecialchars($about_intro) ?></p>
    </div>
</section>

<section class="container about-pillars-section">
    <h2 class="section-title about-pillars-heading">The Pillars of Earth Odyssey</h2>
    <div class="about-pillars-grid">
        <?php foreach ($about_pillars as $pillar): ?>
            <article class="about-pillar" id="pillar-<?= htmlspecialchars(slugify_category($pillar['title'])) ?>">
                <img
                    src="<?= htmlspecialchars(url($pillar['image'])) ?>"
                    alt=""
                    width="400"
                    height="300"
                    loading="lazy"
                >
                <div class="about-pillar-body">
                    <h3 class="about-pillar-title"><?= htmlspecialchars($pillar['title']) ?></h3>
                    <p class="about-pillar-description"><?= htmlspecialchars($pillar['description']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
