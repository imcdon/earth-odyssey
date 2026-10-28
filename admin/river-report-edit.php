<?php
/*
 * river-report-edit.php - Old admin URL. Reports now live under Services.
 */
require __DIR__ . '/../includes/config.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('services/rivers/report-edit.php') . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
