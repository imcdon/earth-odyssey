<?php
/*
 * gallery-upload-api.php - JSON-only gallery photo upload (no HTML page wrapper).
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/gallery-photos.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function gallery_upload_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    gallery_upload_json(['ok' => false, 'error' => 'POST required.'], 405);
}

$user = current_user();
if (!$user) {
    gallery_upload_json(['ok' => false, 'error' => 'You are not logged in. Refresh and sign in again.'], 401);
}
if ($user['role'] !== 'editor') {
    gallery_upload_json(['ok' => false, 'error' => 'Editor access required.'], 403);
}

$sent = $_POST['csrf_token'] ?? '';
if (
    !is_string($sent)
    || !hash_equals(csrf_token(), $sent)
) {
    $msg = 'Your session expired. Refresh the page and try again.';
    if (
        (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
        && $_POST === []
        && $_FILES === []
    ) {
        $msg = 'Upload was too large for the server (post_max_size is ' . ini_get('post_max_size') . '). Try one photo at a time.';
    }
    gallery_upload_json(['ok' => false, 'error' => $msg], 400);
}

$categoryId = (int) ($_POST['category_id'] ?? 0);
$category = $categoryId ? get_category_by_id($categoryId) : null;
if (!$category || ($category['type'] ?? '') !== 'gallery') {
    gallery_upload_json(['ok' => false, 'error' => 'Choose a gallery category first.'], 400);
}

$before = count(get_gallery_photos($category['slug']));
$result = gallery_photo_save_uploads($category['slug'], $_FILES);
$warnings = array_merge(
    $result['warnings'],
    gallery_photo_save_meta($category['slug'], (array) ($_POST['photo_meta'] ?? []))
);
$after = count(get_gallery_photos($category['slug']));
$added = max(0, $after - $before);
$savedOk = $result['saved'] > 0 || $added > 0;

if (!$savedOk) {
    gallery_upload_json([
        'ok' => false,
        'error' => $warnings[0] ?? 'No photos were uploaded.',
        'warnings' => $warnings,
        'debug' => [
            'files_keys' => array_keys($_FILES),
            'photo_count' => isset($_FILES['photos']['name'])
                ? (is_array($_FILES['photos']['name']) ? count($_FILES['photos']['name']) : 1)
                : 0,
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
        ],
    ], 400);
}

gallery_upload_json([
    'ok' => true,
    'added' => $added,
    'saved' => $result['saved'],
    'bases' => $result['bases'],
    'category' => $category['name'],
    'warnings' => $warnings,
]);
