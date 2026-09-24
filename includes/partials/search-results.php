<?php
/*
 * partials/search-results.php - Render grouped search result sections.
 * Expects $searchArticles, $searchCategories; optional $searchAbout.
 */
?>
<?php if (!empty($searchCategories)): ?>
    <section class="search-results-section" aria-label="Category results">
        <h2 class="search-results-heading">Categories</h2>
        <div class="search-results-list">
            <?php foreach ($searchCategories as $result): ?>
                <article class="search-result-row">
                    <?php if (!empty($result['thumbnail'])): ?>
                        <a href="<?= htmlspecialchars(url($result['url'])) ?>" class="search-result-thumb">
                            <img
                                src="<?= htmlspecialchars(url($result['thumbnail'])) ?>"<?= img_srcset($result['thumbnail'], '120px') ?>
                                alt=""
                                width="120"
                                height="68"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                    <?php endif; ?>
                    <div class="search-result-body">
                        <p class="search-result-meta"><?= htmlspecialchars($result['meta'] ?? '') ?></p>
                        <h3 class="search-result-title">
                            <a href="<?= htmlspecialchars(url($result['url'])) ?>"><?= htmlspecialchars($result['title']) ?></a>
                        </h3>
                        <p class="search-result-description"><?= $result['description'] ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($searchArticles)): ?>
    <section class="search-results-section" aria-label="Article results">
        <h2 class="search-results-heading">Articles</h2>
        <div class="article-list">
            <?php foreach ($searchArticles as $result): ?>
                <article class="article-row">
                    <?php if (!empty($result['thumbnail'])): ?>
                        <a href="<?= htmlspecialchars(url($result['url'])) ?>" class="article-row-thumb">
                            <img
                                src="<?= htmlspecialchars(url($result['thumbnail'])) ?>"<?= img_srcset($result['thumbnail'], '160px') ?>
                                alt=""
                                width="160"
                                height="90"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                    <?php endif; ?>
                    <div class="article-row-body">
                        <h3 class="article-row-title">
                            <a href="<?= htmlspecialchars(url($result['url'])) ?>"><?= htmlspecialchars($result['title']) ?></a>
                        </h3>
                        <?php if (!empty($result['author_name'])): ?>
                            <p class="article-row-meta">
                                By <?= htmlspecialchars($result['author_name']) ?>
                                <?php if (!empty($result['published_at'])): ?>
                                    &middot; <?= htmlspecialchars(date('F j, Y', strtotime($result['published_at']))) ?>
                                <?php endif; ?>
                                <?php if (!empty($result['category_name'])): ?>
                                    &middot; <span class="category-badge"><?= htmlspecialchars($result['category_name']) ?></span>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <p class="article-row-blurb search-excerpt"><?= $result['description'] ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($searchAbout)): ?>
    <section class="search-results-section" aria-label="About results">
        <h2 class="search-results-heading">About</h2>
        <div class="search-results-list">
            <?php foreach ($searchAbout as $result): ?>
                <article class="search-result-row">
                    <?php if (!empty($result['thumbnail'])): ?>
                        <a href="<?= htmlspecialchars(url($result['url'])) ?>" class="search-result-thumb">
                            <img
                                src="<?= htmlspecialchars(url($result['thumbnail'])) ?>"<?= img_srcset($result['thumbnail'], '120px') ?>
                                alt=""
                                width="120"
                                height="68"
                                loading="lazy"
                                decoding="async"
                            >
                        </a>
                    <?php endif; ?>
                    <div class="search-result-body">
                        <p class="search-result-meta"><?= htmlspecialchars($result['meta'] ?? '') ?></p>
                        <h3 class="search-result-title">
                            <a href="<?= htmlspecialchars(url($result['url'])) ?>"><?= htmlspecialchars($result['title']) ?></a>
                        </h3>
                        <p class="search-result-description"><?= $result['description'] ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
