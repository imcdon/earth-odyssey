<?php
/*
 * hero-carousel.php - Full-width rotating hero carousel for the home page.
 * Expects $hero_slides, $hero_cta_labels, and $hero_type_labels from config.php.
 */
?>
<section class="hero-carousel" aria-label="Featured content" aria-roledescription="carousel">
    <div class="hero-viewport">
        <div class="hero-track">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <a
                    href="<?= htmlspecialchars(url($slide['url'])) ?>"
                    class="hero-slide<?= $index === 0 ? ' is-active' : '' ?>"
                    aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
                >
                    <img
                        src="<?= htmlspecialchars(url($slide['image'])) ?>"
                        alt=""
                        width="1200"
                        height="675"
                        loading="<?= $index === 0 ? 'eager' : 'lazy' ?>"
                    >
                    <div class="hero-overlay">
                        <span class="hero-badge"><?= htmlspecialchars($hero_type_labels[$slide['type']]) ?></span>
                        <h2 class="hero-title"><?= htmlspecialchars($slide['title']) ?></h2>
                        <p class="hero-blurb"><?= htmlspecialchars($slide['blurb']) ?></p>
                        <span class="hero-cta"><?= htmlspecialchars($hero_cta_labels[$slide['type']]) ?> &rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <button type="button" class="hero-prev" aria-label="Previous slide">&lsaquo;</button>
        <button type="button" class="hero-next" aria-label="Next slide">&rsaquo;</button>
    </div>
    <div class="hero-dots" role="tablist" aria-label="Slide navigation">
        <?php foreach ($hero_slides as $index => $slide): ?>
            <button
                type="button"
                class="hero-dot<?= $index === 0 ? ' is-active' : '' ?>"
                aria-label="Go to slide <?= $index + 1 ?>: <?= htmlspecialchars($slide['title']) ?>"
                aria-current="<?= $index === 0 ? 'true' : 'false' ?>"
            ></button>
        <?php endforeach; ?>
    </div>
</section>
<script src="<?= htmlspecialchars(url('assets/js/carousel.js')) ?>" defer></script>
