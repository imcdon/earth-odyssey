<?php
/*
 * river-report-photo.php - Serves a report photo to staff who can see that report (photos live outside the web root).
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/river-reports.php';

$user = current_user();
$stmt = get_db()->prepare('SELECT p.file, p.mime, r.id AS report_id, r.author_id FROM river_report_photos p JOIN river_reports r ON r.id = p.report_id WHERE p.id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$photo = $stmt->fetch();

$path = $photo ? river_report_photo_dir((int) $photo['report_id'], false) . '/' . basename($photo['file']) : '';
if (!$user || !$photo || !river_report_can_edit($user, $photo) || !is_file($path)) {
    http_response_code(404);
    exit;
}

$etag = '"' . md5($photo['file']) . '"';
header('Cache-Control: private, max-age=604800');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Type: ' . $photo['mime']);
header('Content-Length: ' . filesize($path));
readfile($path);
