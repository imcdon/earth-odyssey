<?php
/*
 * index.php - Advocacy directory: read and donate links for outside groups.
 */
require __DIR__ . '/../includes/config.php';

$page_theme = 'advocacy';
$page_title = 'Advocacy | ' . $site_name;

$advocacy_groups = $advocacy_groups ?? [];
$focus_order = ['Environmental', 'Climate', 'Human rights'];
$grouped = [];
foreach ($focus_order as $focus) {
    $grouped[$focus] = [];
}
foreach ($advocacy_groups as $group) {
    $focus = $group['focus'] ?? 'Environmental';
    if (!isset($grouped[$focus])) {
        $grouped[$focus] = [];
    }
    $grouped[$focus][] = $group;
}

require __DIR__ . '/../includes/header.php';
?>

<section class="advocacy-page">
    <div class="container advocacy-header">
        <p class="kicker">Advocacy</p>
        <h1 class="section-title">Read and support the work</h1>
        <p class="section-intro">Environmental, climate, and human rights groups. Earth Odyssey does not collect donations. Read and give on each organization’s own site.</p>
    </div>

    <?php if (!$advocacy_groups): ?>
        <div class="container">
            <p class="advocacy-empty">Groups will be listed here.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($grouped as $focus => $groups): ?>
        <?php if (!$groups) {
            continue;
        } ?>
        <section class="container advocacy-focus" aria-labelledby="advocacy-<?= htmlspecialchars(strtolower(preg_replace('/[^a-z]+/i', '-', $focus))) ?>">
            <h2 class="advocacy-focus-title" id="advocacy-<?= htmlspecialchars(strtolower(preg_replace('/[^a-z]+/i', '-', $focus))) ?>"><?= htmlspecialchars($focus) ?></h2>
            <div class="advocacy-list">
                <?php foreach ($groups as $group): ?>
                    <article class="advocacy-card">
                        <h3 class="advocacy-card-name"><?= htmlspecialchars($group['name']) ?></h3>
                        <p class="advocacy-card-summary"><?= htmlspecialchars($group['summary']) ?></p>
                        <p class="advocacy-card-links">
                            <a href="<?= htmlspecialchars($group['read_url']) ?>" target="_blank" rel="noopener noreferrer">Read</a>
                            <a href="<?= htmlspecialchars($group['donate_url']) ?>" target="_blank" rel="noopener noreferrer">Donate</a>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
