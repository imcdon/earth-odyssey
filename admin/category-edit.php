<?php
/*
 * category-edit.php - Editor: create or edit a category.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/categories.php';

require_role('editor');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$category = $id ? get_category_by_id($id) : null;
$error = '';

if ($category) {
    $type = $category['type'];
} else {
    $type = $_GET['type'] ?? 'article';
    if (!in_array($type, ['article', 'gallery'], true)) {
        $type = 'article';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $imagePath = trim($_POST['image_path'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $type = $_POST['type'] ?? $type;
    $isFeatured = $type === 'gallery' && !empty($_POST['is_featured']);

    if (!in_array($type, ['article', 'gallery'], true)) {
        $error = 'Invalid category type.';
    } elseif ($name === '') {
        $error = 'Name is required.';
    } else {
        if ($slug === '') {
            $slug = slugify_category($name);
        }

        if ($slug === '') {
            $error = 'Slug is required.';
        } elseif ($imagePath === '') {
            $error = 'Image path is required.';
        } else {
            try {
                $savedId = save_category([
                    'type'        => $type,
                    'slug'        => $slug,
                    'name'        => $name,
                    'image_path'  => $imagePath,
                    'sort_order'  => $sortOrder,
                    'is_featured' => $isFeatured ? 1 : 0,
                ], $id ?: null);

                if ($type === 'gallery') {
                    if ($isFeatured) {
                        set_gallery_featured($savedId);
                    } elseif ($id) {
                        clear_gallery_featured($savedId);
                    }
                }

                header('Location: ' . url('admin/categories.php') . '?saved=1');
                exit;
            } catch (PDOException $e) {
                if ((int) $e->errorInfo[1] === 1062) {
                    $error = 'A category with that slug already exists for this type.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$form = $category ?: [
    'type'        => $type,
    'name'        => '',
    'slug'        => '',
    'image_path'  => '',
    'sort_order'  => 0,
    'is_featured' => 0,
];

$page_theme = 'admin';
$page_title = ($id ? 'Edit' : 'New') . ' ' . category_type_label($form['type']) . ' Category | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title"><?= $id ? 'Edit' : 'New' ?> <?= htmlspecialchars(category_type_label($form['type'])) ?> Category</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/categories.php')) ?>">&larr; Back to categories</a></p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($form['type']) ?>">

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($form['name']) ?>" required>

        <label for="slug">Slug (URL)</label>
        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($form['slug']) ?>" placeholder="auto-from-name">

        <label for="image_path">Image path</label>
        <input type="text" id="image_path" name="image_path" value="<?= htmlspecialchars($form['image_path']) ?>" placeholder="/assets/img/categories/hiking.webp" required>

        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?= (int) $form['sort_order'] ?>" min="0">

        <?php if ($form['type'] === 'gallery'): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="is_featured" value="1"<?= !empty($form['is_featured']) ? ' checked' : '' ?>>
                Featured gallery (home carousel + galleries page image)
            </label>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn-primary">Save category</button>
            <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/categories.php')) ?>">Cancel</a>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
