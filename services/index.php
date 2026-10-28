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
        style="background-image: url('<?= htmlspecialchars(img_fit('/assets/img/galleries/alaska/img_3535.webp', 1600)) ?>')"
    >
        <div class="services-hero-inner">
            <h1>Tools &amp; Services</h1>
            <p>Stream gauges and fishing reports for the water.</p>
        </div>
    </div>

    <div class="container-wide services-body">
        <?php foreach ($service_tool_groups as $group): ?>
            <section class="services-group" aria-label="<?= htmlspecialchars($group['title']) ?>">
                <h2 class="services-group-title"><?= htmlspecialchars($group['title']) ?></h2>
                <div class="services-grid">
                    <?php foreach ($group['tools'] as $tool): ?>
                        <?php
                        $status = (string) ($tool['status'] ?? '');
                        $isOpen = in_array($status, ['live', 'beta'], true) && !empty($tool['url']);
                        $image = (string) ($tool['image'] ?? '');
                        $toolUrl = $isOpen ? url($tool['url']) : '';
                        ?>
                        <article class="service-card">
                            <?php if ($image !== ''): ?>
                                <?php if ($isOpen): ?>
                                    <a class="service-card-media" href="<?= htmlspecialchars($toolUrl) ?>" tabindex="-1" aria-hidden="true">
                                <?php else: ?>
                                    <div class="service-card-media">
                                <?php endif; ?>
                                    <img
                                        src="<?= htmlspecialchars(img_fit($image, 640)) ?>"
                                        <?= img_srcset($image, '(min-width: 800px) 38vw, 100vw') ?>
                                        alt=""
                                    >
                                <?php if ($isOpen): ?>
                                    </a>
                                <?php else: ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            <div class="service-card-body">
                                <h3 class="service-card-title">
                                    <?php if ($isOpen): ?>
                                        <a href="<?= htmlspecialchars($toolUrl) ?>"><?= htmlspecialchars($tool['title']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($tool['title']) ?>
                                    <?php endif; ?>
                                </h3>
                                <?php if ($status === 'beta'): ?>
                                    <span class="service-status is-beta">Beta</span>
                                <?php elseif ($status === 'live'): ?>
                                    <span class="service-status is-live">Live</span>
                                <?php else: ?>
                                    <span class="service-status is-soon">Coming soon</span>
                                <?php endif; ?>
                                <p class="service-card-desc"><?= htmlspecialchars($tool['description']) ?></p>
                                <?php if ($isOpen): ?>
                                    <a class="btn-primary" href="<?= htmlspecialchars($toolUrl) ?>">Open tool</a>
                                <?php else: ?>
                                    <span class="btn-secondary" aria-disabled="true">Coming soon</span>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
