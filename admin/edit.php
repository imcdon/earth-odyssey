<?php
/*
 * edit.php - Create or edit an article (draft / submit for review / publish).
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/categories.php';
require __DIR__ . '/../includes/articles.php';

$user = require_login();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$article = $id ? get_article_by_id($id) : null;
$error = '';
$message = '';

if ($article && $user['role'] === 'author' && (int) $article['author_id'] !== (int) $user['id']) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

if ($article && $user['role'] === 'author' && $article['status'] === 'published') {
    http_response_code(403);
    echo 'Published articles can only be edited by an editor.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $blurb = trim($_POST['blurb'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $categoryId = ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null;
    $thumbnail = trim($_POST['thumbnail'] ?? '');
    $isFeatured = !empty($_POST['is_featured']);
    $action = $_POST['action'] ?? 'draft';

    if ($slug === '') {
        $slug = slugify($title);
    }

    if ($title === '' || $slug === '' || $blurb === '' || $body === '') {
        $error = 'Title, slug, blurb, and body are required.';
    } else {
        $status = 'draft';
        $editorId = null;
        $publishedAt = null;

        if ($action === 'pending') {
            $status = 'pending';
        } elseif ($action === 'publish' && $user['role'] === 'editor') {
            $status = 'published';
            $editorId = (int) $user['id'];
            $publishedAt = date('Y-m-d H:i:s');
        } elseif ($action === 'draft') {
            $status = 'draft';
        } elseif ($action === 'reject' && $user['role'] === 'editor') {
            $status = 'rejected';
            $editorId = (int) $user['id'];
        }

        $data = [
            'slug'         => $slug,
            'title'        => $title,
            'blurb'        => $blurb,
            'body'         => $body,
            'status'       => $status,
            'author_id'    => $article ? (int) $article['author_id'] : (int) $user['id'],
            'editor_id'    => $editorId,
            'published_at' => $publishedAt,
            'category_id'  => $categoryId,
            'thumbnail'    => $thumbnail !== '' ? $thumbnail : null,
        ];

        $savedId = save_article($data, $id ?: null);

        if ($user['role'] === 'editor' && $isFeatured) {
            set_article_featured($savedId);
        } elseif ($user['role'] === 'editor' && $id && !$isFeatured && !empty($article['is_featured'])) {
            clear_article_featured($savedId);
        }
        header('Location: ' . url('admin/edit.php') . '?id=' . $savedId . '&saved=1');
        exit;
    }
}

if (isset($_GET['saved'])) {
    $message = 'Article saved.';
}

$form = $article ?: [
    'title'       => '',
    'slug'        => '',
    'blurb'       => '',
    'body'        => '',
    'status'      => 'draft',
    'category_id' => null,
    'thumbnail'   => '',
    'is_featured' => 0,
];

$categories = get_categories('article');

$page_title = ($id ? 'Edit' : 'New') . ' Article | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title"><?= $id ? 'Edit Article' : 'New Article' ?></h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($message): ?>
        <p class="form-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($form['title']) ?>" required>

        <label for="slug">Slug (URL)</label>
        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($form['slug']) ?>" placeholder="auto-from-title">

        <label for="blurb">Blurb (short summary)</label>
        <input type="text" id="blurb" name="blurb" value="<?= htmlspecialchars($form['blurb']) ?>" required>

        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <option value="">— None —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"<?= (int) ($form['category_id'] ?? 0) === (int) $cat['id'] ? ' selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="thumbnail">Thumbnail (image path or URL)</label>
        <input type="text" id="thumbnail" name="thumbnail" value="<?= htmlspecialchars($form['thumbnail'] ?? '') ?>" placeholder="/assets/img/hero/day-hike.svg">

        <?php if ($user['role'] === 'editor'): ?>
            <label class="checkbox-label">
                <input type="checkbox" name="is_featured" value="1"<?= !empty($form['is_featured']) ? ' checked' : '' ?>>
                Featured article (shown at top of articles page)
            </label>
        <?php endif; ?>

        <label for="body">Body (Markdown or plain text)</label>
        <textarea id="body" name="body" rows="16" required><?= htmlspecialchars($form['body']) ?></textarea>

        <div class="form-actions">
            <button type="submit" name="action" value="draft" class="btn-secondary">Save draft</button>
            <button type="submit" name="action" value="pending" class="btn-primary">Submit for review</button>
            <?php if ($user['role'] === 'editor'): ?>
                <button type="submit" name="action" value="publish" class="btn-primary">Publish</button>
                <?php if ($id): ?>
                    <button type="submit" name="action" value="reject" class="btn-secondary">Reject</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
