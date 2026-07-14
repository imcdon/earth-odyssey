<?php
/*
 * index.php - About Earth Odyssey: hero and editor.
 */
require __DIR__ . '/../includes/config.php';

$page_title = 'About | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="about-hero" aria-label="<?= htmlspecialchars($about_hero_title) ?>">
    <img
        class="about-hero-image"
        src="<?= htmlspecialchars(url($about_hero_image)) ?>"
        alt=""
        width="1600"
        height="500"
        decoding="async"
        fetchpriority="high"
    >
    <div class="about-hero-overlay">
        <div class="about-hero-copy">
            <p class="about-hero-role"><?= htmlspecialchars($about_hero_role) ?></p>
            <h1 class="about-hero-title"><?= htmlspecialchars($about_hero_title) ?></h1>
            <p class="about-intro"><?= htmlspecialchars($about_intro) ?></p>
        </div>
    </div>
</section>

<section class="container about-editor-section" id="editor">
    <div class="about-editor">
        <div class="about-editor-media">
            <img
                class="about-editor-image"
                src="<?= htmlspecialchars(url($about_editor['image'])) ?>"
                alt="<?= htmlspecialchars($about_editor['name']) ?>"
                width="480"
                height="640"
                loading="lazy"
            >
        </div>
        <div class="about-editor-body">
            <p class="about-editor-role"><?= htmlspecialchars($about_editor['role']) ?></p>
            <h2 class="about-editor-name"><?= htmlspecialchars($about_editor['name']) ?></h2>
            <p class="about-editor-bio"><?= htmlspecialchars($about_editor['bio']) ?></p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
