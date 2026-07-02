<?php
/*
 * index.php - Galleries coming soon placeholder.
 */
require __DIR__ . '/../includes/config.php';

$coming_soon_title = 'Galleries';
$coming_soon_message = 'Photo galleries from the trail, camp, and field are on the way.';
$coming_soon_image = '/assets/img/hero/autumn-camp.svg';
$page_title = $coming_soon_title . ' | ' . $site_name;

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/coming-soon.php';
require __DIR__ . '/../includes/footer.php';
