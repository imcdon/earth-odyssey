<?php
/*
 * river-reports.php - Old admin URL. Reports now live under Services.
 */
require __DIR__ . '/../includes/config.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . url('services/rivers/reports.php') . ($q !== '' ? '?' . $q : ''), true, 302);
exit;
