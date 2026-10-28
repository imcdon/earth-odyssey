<?php
/*
 * river-report-photo.php - Old admin URL. Report photos now live under Services.
 */
require __DIR__ . '/../includes/config.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('services/rivers/report-photo.php') . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
