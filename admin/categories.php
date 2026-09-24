<?php
/*
 * categories.php - Editor: manage article and gallery categories.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/categories.php';

require_role('editor');

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_check();
    $deleteError = delete_category((int) $_POST['delete_id']);
    if ($deleteError) {
        $error = $deleteError;
    } else {
        $message = 'Category deleted.';
    }
}

$articleCategories = get_categories('article');
$galleryCategories = get_categories('gallery');

$page_theme = 'admin';
$page_title = 'Manage Categories | ' . $site_name;
require __DIR__ . '/../includes/header.php';

function render_category_list(array $categories): void
{
    if (!$categories) {
        echo '<p>No categories yet.</p>';
        return;
    }
    ?>
    <ul class="admin-list category-admin-list">
        <?php foreach ($categories as $cat): ?>
            <li>
                <div class="category-admin-info">
                    <strong><?= htmlspecialchars($cat['name']) ?></strong>
                    <span class="category-admin-meta">
                        <?= htmlspecialchars($cat['slug']) ?>
                        &middot; order <?= (int) $cat['sort_order'] ?>
                        <?php if (!empty($cat['is_featured'])): ?>
                            &middot; Featured
                        <?php endif; ?>
                        &middot; <?= htmlspecialchars($cat['image_path']) ?>
                    </span>
                </div>
                <div class="category-admin-actions">
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/category-edit.php')) ?>?id=<?= (int) $cat['id'] ?>">Edit</a>
                    <form method="post" action="" class="category-delete-form" onsubmit="return confirm('Delete this category?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_id" value="<?= (int) $cat['id'] ?>">
                        <button type="submit" class="btn-secondary">Delete</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
}
?>

<section class="container admin-page">
    <h1 class="section-title">Manage Categories</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($message): ?>
        <p class="form-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <section class="category-admin-section">
        <div class="category-admin-header">
            <h2 class="section-title">Article Categories</h2>
            <a class="btn-primary" href="<?= htmlspecialchars(url('admin/category-edit.php')) ?>?type=article">Add article category</a>
        </div>
        <?php render_category_list($articleCategories); ?>
    </section>

    <section class="category-admin-section">
        <div class="category-admin-header">
            <h2 class="section-title">Gallery Categories</h2>
            <a class="btn-primary" href="<?= htmlspecialchars(url('admin/category-edit.php')) ?>?type=gallery">Add gallery category</a>
        </div>
        <?php render_category_list($galleryCategories); ?>
    </section>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
