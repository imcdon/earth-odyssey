<?php
/*
 * gallery-photos.php - Editor: upload and manage photos inside a gallery category.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/gallery-photos.php';

require_role('editor');

$error = '';
$message = '';
$warnings = [];

$categoryId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['category_id'] ?? 0);
$category = $categoryId ? get_category_by_id($categoryId) : null;

if ($category && ($category['type'] ?? '') !== 'gallery') {
    $category = null;
}

if (isset($_GET['saved']) && $category) {
    $message = 'Photos uploaded.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $category) {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'upload');

    if ($action === 'delete') {
        $basename = (string) ($_POST['basename'] ?? '');
        $deleteError = gallery_photo_delete($category['slug'], $basename);
        if ($deleteError) {
            $error = $deleteError;
        } else {
            $message = 'Photo deleted.';
        }
    } elseif ($action === 'upload') {
        $before = count(get_gallery_photos($category['slug']));
        $warnings = gallery_photo_save_uploads($category['slug'], $_FILES);
        $warnings = array_merge(
            $warnings,
            gallery_photo_save_meta($category['slug'], (array) ($_POST['photo_meta'] ?? []))
        );
        $after = count(get_gallery_photos($category['slug']));
        $added = max(0, $after - $before);
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        if ($added > 0) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'added' => $added, 'warnings' => $warnings], JSON_UNESCAPED_SLASHES);
                exit;
            }
            $message = $added === 1 ? '1 photo uploaded.' : $added . ' photos uploaded.';
        } else {
            $fail = $warnings[0] ?? 'No photos were uploaded.';
            if ($wantsJson) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => $fail, 'warnings' => $warnings], JSON_UNESCAPED_SLASHES);
                exit;
            }
            $error = $fail;
            $warnings = array_slice($warnings, 1);
        }
    }
}

$galleryCategories = get_categories('gallery');
$photos = $category ? get_gallery_photos($category['slug']) : [];

$page_theme = 'admin';
$page_title = ($category ? $category['name'] . ' photos' : 'Gallery photos') . ' | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title"><?= $category ? htmlspecialchars($category['name']) . ' photos' : 'Gallery photos' ?></h1>
    <p class="section-intro">
        <a href="<?= htmlspecialchars(url('admin/categories.php')) ?>">&larr; Back to categories</a>
        <?php if ($category): ?>
            · <a href="<?= htmlspecialchars(url('galleries/' . $category['slug'] . '/')) ?>" target="_blank" rel="noopener">View album</a>
        <?php endif; ?>
    </p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($message): ?>
        <p class="form-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
    <?php foreach ($warnings as $warning): ?>
        <p class="form-error"><?= htmlspecialchars($warning) ?></p>
    <?php endforeach; ?>

    <?php if (!$category): ?>
        <p class="section-intro">Choose a gallery category to upload photos into. Create categories first if you need a new album.</p>
        <?php if (!$galleryCategories): ?>
            <p>No gallery categories yet. <a href="<?= htmlspecialchars(url('admin/category-edit.php')) ?>?type=gallery">Add one</a>.</p>
        <?php else: ?>
            <ul class="admin-list category-admin-list">
                <?php foreach ($galleryCategories as $cat): ?>
                    <?php $count = count(get_gallery_photos($cat['slug'])); ?>
                    <li>
                        <div class="category-admin-info">
                            <strong><?= htmlspecialchars($cat['name']) ?></strong>
                            <span class="category-admin-meta">
                                <?= htmlspecialchars($cat['slug']) ?>
                                &middot; <?= (int) $count ?> photo<?= $count === 1 ? '' : 's' ?>
                            </span>
                        </div>
                        <div class="category-admin-actions">
                            <a class="btn-primary" href="<?= htmlspecialchars(url('admin/gallery-photos.php')) ?>?id=<?= (int) $cat['id'] ?>">Manage photos</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php else: ?>
        <form
            class="admin-form gallery-upload-form"
            method="post"
            action="<?= htmlspecialchars(url('admin/gallery-photos.php')) ?>?id=<?= (int) $category['id'] ?>"
            enctype="multipart/form-data"
            data-gallery-upload
            data-max-edge="2400"
            data-variant-widths="480,960,1600"
        >
            <?= csrf_field() ?>
            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
            <input type="hidden" name="action" value="upload">

            <p class="range-help">
                Photos are resized in your browser to WebP (max 2400px) with 480 / 960 / 1600 variants,
                then saved to <code>/assets/img/galleries/<?= htmlspecialchars($category['slug']) ?>/</code>.
            </p>

            <label for="gallery-photos">Add photos</label>
            <input
                type="file"
                id="gallery-photos"
                accept="image/jpeg,image/png,image/webp,image/*"
                multiple
                data-photo-input
            >

            <p class="gallery-upload-status" data-photo-status>Choose photos to resize and upload.</p>
            <ul class="gallery-upload-previews" data-photo-previews></ul>

            <div class="form-actions">
                <button type="submit" class="btn-primary" data-upload-submit disabled>Upload photos</button>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/gallery-photos.php')) ?>">All galleries</a>
            </div>
        </form>

        <h2 class="section-title gallery-photos-heading">
            <?= count($photos) ?> photo<?= count($photos) === 1 ? '' : 's' ?> in this album
        </h2>

        <?php if (!$photos): ?>
            <p>No photos yet. Upload above to fill this gallery.</p>
        <?php else: ?>
            <ul class="gallery-admin-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php
                    $basename = basename($photo['path']);
                    $thumb = img_fit($photo['path'], 480);
                    ?>
                    <li class="gallery-admin-card">
                        <img
                            src="<?= htmlspecialchars(url($thumb)) ?>"
                            alt=""
                            width="480"
                            height="320"
                            loading="lazy"
                            decoding="async"
                        >
                        <div class="gallery-admin-card-body">
                            <strong><?= htmlspecialchars($photo['title']) ?></strong>
                            <span class="category-admin-meta">
                                <?= (int) $photo['width'] ?>&times;<?= (int) $photo['height'] ?>
                                <?php if (!empty($photo['camera'])): ?>
                                    &middot; <?= htmlspecialchars($photo['camera']) ?>
                                <?php endif; ?>
                            </span>
                            <form
                                method="post"
                                action="<?= htmlspecialchars(url('admin/gallery-photos.php')) ?>?id=<?= (int) $category['id'] ?>"
                                onsubmit="return confirm('Delete this photo and its resized versions?');"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="basename" value="<?= htmlspecialchars($basename) ?>">
                                <button type="submit" class="btn-secondary">Delete</button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if ($category): ?>
    <script src="<?= htmlspecialchars(asset_url('assets/js/gallery-admin.js')) ?>" defer></script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
