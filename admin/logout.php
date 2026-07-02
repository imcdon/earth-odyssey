<?php
/*
 * logout.php - Destroy admin session and redirect to login.
 */
require __DIR__ . '/../includes/auth.php';

logout_user();
header('Location: ' . url('admin/login.php'));
exit;
