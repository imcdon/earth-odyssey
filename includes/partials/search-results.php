<?php
/*
 * partials/search-results.php - Unified sitewide search panels.
 * Expects $searchPanels: list of kind/title/description/url/meta/thumbnail.
 */
$searchPanels = $searchPanels ?? [];
?>
<?php if ($searchPanels !== []): ?>
    <div class="search-panels" role="list">
        <?php foreach ($searchPanels as $result): ?>
            <?php
            $panelUrl = url($result['url'] ?? '/');
            $kind = (string) ($result['kind'] ?? '');
            $meta = (string) ($result['meta'] ?? ucfirst($kind));
            $thumb = $result['thumbnail'] ?? null;
            ?>
            <article class="search-panel" role="listitem" data-kind="<?= htmlspecialchars($kind) ?>">
                <a href="<?= htmlspecialchars($panelUrl) ?>" class="search-panel-media">
                    <?php if (!empty($thumb)): ?>
                        <img
                            src="<?= htmlspecialchars(url($thumb)) ?>"<?= img_srcset($thumb, '(max-width: 720px) 100vw, 200px') ?>
                            alt=""
                            width="640"
                            height="400"
                            loading="lazy"
                            decoding="async"
                        >
                    <?php else: ?>
                        <span class="search-panel-placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
                <div class="search-panel-body">
                    <p class="search-panel-meta"><?= htmlspecialchars($meta) ?></p>
                    <h2 class="search-panel-title">
                        <a href="<?= htmlspecialchars($panelUrl) ?>"><?= htmlspecialchars($result['title'] ?? '') ?></a>
                    </h2>
                    <?php if (!empty($result['description'])): ?>
                        <p class="search-panel-description"><?= $result['description'] ?></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
