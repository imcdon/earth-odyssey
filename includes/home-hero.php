<?php
/*
 * home-hero.php - Rotating "Search for..." hero: topic, photo, and example prompt.
 * Expects: $home_hero_rotations, $home_hero_gallery_url, $rivers_ok (optional).
 */
$home_hero_rotations = $home_hero_rotations ?? [];
if (!$home_hero_rotations) {
    $home_hero_rotations = [[
        'topic' => 'Rivers',
        'example' => 'ex. Kenai',
        'mode' => 'rivers',
        'image' => $home_hero_image ?? '/assets/img/hero/autumn-camp.webp',
    ]];
}
$home_hero_gallery_url = $home_hero_gallery_url ?? '/galleries/';
$rivers_ok = !empty($rivers_ok);
$first = $home_hero_rotations[0];
?>
<section
    class="home-hero"
    aria-label="Search Earth Odyssey"
    data-hero-rotator
    data-hero-interval="4500"
>
    <div class="home-hero-viewport">
        <div class="home-hero-media">
            <?php foreach ($home_hero_rotations as $index => $slide): ?>
                <img
                    class="home-hero-image<?= $index === 0 ? ' is-active' : '' ?>"
                    src="<?= htmlspecialchars(url($slide['image'])) ?>"<?= img_srcset($slide['image'], '100vw') ?>
                    alt=""
                    width="1600"
                    height="900"
                    decoding="async"
                    <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>
                    data-hero-image
                >
            <?php endforeach; ?>
        </div>
        <div class="home-hero-overlay">
            <h1 class="home-hero-title">
                <span class="home-hero-prompt">Search for&hellip;</span>
                <span class="home-hero-topics" data-hero-topics>
                    <?php foreach ($home_hero_rotations as $index => $slide): ?>
                        <span
                            class="home-hero-topic<?= $index === 0 ? ' is-active' : '' ?>"
                            data-hero-topic
                            <?= $index === 0 ? '' : 'aria-hidden="true"' ?>
                        ><?= htmlspecialchars($slide['topic']) ?></span>
                    <?php endforeach; ?>
                </span>
            </h1>
            <form
                class="home-hero-search"
                action="<?= htmlspecialchars(url('search/')) ?>"
                method="get"
                role="search"
                data-hero-search
                data-search-action="<?= htmlspecialchars(url('search/')) ?>"
            >
                <label for="home-q" class="visually-hidden">Search</label>
                <span class="home-hero-search-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20l-3.5-3.5"></path>
                    </svg>
                </span>
                <input
                    id="home-q"
                    name="q"
                    type="search"
                    placeholder="<?= htmlspecialchars($first['example']) ?>"
                    autocomplete="off"
                    <?php if ($rivers_ok && ($first['mode'] ?? '') === 'rivers'): ?>
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="false"
                    aria-controls="river-results"
                    <?php endif; ?>
                    data-hero-input
                    data-hero-mode="<?= htmlspecialchars($first['mode']) ?>"
                >
                <button type="submit" class="home-hero-submit">Search</button>
                <?php if ($rivers_ok): ?>
                <ul id="river-results" class="rivers-results" role="listbox" hidden></ul>
                <?php endif; ?>
            </form>
            <nav class="home-hero-pills" aria-label="Start here">
                <a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a>
                <a href="<?= htmlspecialchars(url('services/rivers/reports.php')) ?>">Fishing reports</a>
                <a href="<?= htmlspecialchars(url($home_hero_gallery_url)) ?>">Galleries</a>
                <a href="<?= htmlspecialchars(url('books/')) ?>">Books</a>
            </nav>
        </div>
    </div>
    <script type="application/json" data-hero-slides><?= json_encode(
        array_map(static fn($s) => [
            'topic' => $s['topic'],
            'example' => $s['example'],
            'mode' => $s['mode'],
        ], $home_hero_rotations),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?></script>
</section>
<script src="<?= htmlspecialchars(asset_url('assets/js/home-hero.js')) ?>" defer></script>
