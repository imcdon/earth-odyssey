<?php
/*
 * index.php - Services / tools hub (calm, scannable).
 */
require __DIR__ . '/../includes/config.php';

$page_theme = 'services';
$page_title = 'Services | ' . $site_name;

require __DIR__ . '/../includes/header.php';
?>

<section class="services-page">
    <div
        class="services-hero"
        style="background-image: url('<?= htmlspecialchars(url('/assets/img/hero/services.webp')) ?>')"
    >
        <div class="services-hero-inner">
            <h1>Tools &amp; Services</h1>
            <p>Free outdoor tech helpers for weather, water, and trip planning. More tools as we build them.</p>
        </div>
    </div>

    <div class="container services-body">
        <?php foreach ($service_tool_groups as $group): ?>
            <section class="services-group" aria-label="<?= htmlspecialchars($group['title']) ?>">
                <h2 class="services-group-title"><?= htmlspecialchars($group['title']) ?></h2>
                <div class="services-grid">
                    <?php foreach ($group['tools'] as $tool): ?>
                        <?php $isLive = ($tool['status'] ?? '') === 'live' && !empty($tool['url']); ?>
                        <article class="service-card">
                            <span class="service-status <?= $isLive ? 'is-live' : 'is-soon' ?>">
                                <?= $isLive ? 'Live' : 'Coming soon' ?>
                            </span>
                            <h3 class="service-card-title"><?= htmlspecialchars($tool['title']) ?></h3>
                            <p class="service-card-desc"><?= htmlspecialchars($tool['description']) ?></p>
                            <?php if ($isLive): ?>
                                <a class="btn-primary" href="<?= htmlspecialchars(url($tool['url'])) ?>">Open tool</a>
                            <?php else: ?>
                                <span class="btn-secondary" aria-disabled="true">Coming soon</span>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
