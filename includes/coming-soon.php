<?php
/*
 * coming-soon.php - Placeholder section for pages not yet built.
 * Expects $coming_soon_title; optional $coming_soon_message, $coming_soon_image.
 */
$coming_soon_message = $coming_soon_message ?? 'This section is under construction. Check back soon.';
$coming_soon_image = $coming_soon_image ?? '/assets/img/header-bg.svg';
?>
<section
    class="coming-soon-hero"
    style="background-image: url('<?= htmlspecialchars(url($coming_soon_image)) ?>')"
    aria-label="<?= htmlspecialchars($coming_soon_title) ?>"
>
    <div class="coming-soon-overlay">
        <p class="coming-soon-badge">Coming Soon</p>
        <h1 class="coming-soon-title"><?= htmlspecialchars($coming_soon_title) ?></h1>
        <p class="coming-soon-message"><?= htmlspecialchars($coming_soon_message) ?></p>
        <a class="btn-primary coming-soon-home" href="<?= htmlspecialchars(url()) ?>">Back to home</a>
    </div>
</section>
