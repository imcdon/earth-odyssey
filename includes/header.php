<?php
/*
 * header.php - Opens the HTML document, head, and site header with navigation.
 * Expects $site_name, $site_tagline, $nav_links, and $header_bg_image from config.php.
 */
$page_title = $page_title ?? $site_name;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($site_tagline) ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Source+Sans+3:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/style.css')) ?>">
</head>
<body>
    <header class="site-header" style="--header-bg-image: url('<?= htmlspecialchars(url($header_bg_image)) ?>')">
        <div class="header-inner">
            <nav class="header-nav" aria-label="Main">
                <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="nav-menu">
                    <span class="nav-toggle-bars" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                    <span class="visually-hidden">Menu</span>
                </button>
                <ul class="nav-list" id="nav-menu">
                    <?php foreach ($nav_links as $label => $url): ?>
                        <li><a href="<?= htmlspecialchars(url($url)) ?>"><?= htmlspecialchars($label) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <a class="logo header-logo" href="<?= htmlspecialchars(url()) ?>">
                <img src="<?= htmlspecialchars(url('assets/img/logo.svg')) ?>" alt="<?= htmlspecialchars($site_name) ?> logo" width="40" height="40">
                <span class="logo-text">
                    <strong><?= htmlspecialchars($site_name) ?></strong>
                    <small><?= htmlspecialchars($site_tagline) ?></small>
                </span>
            </a>
            <form class="header-search" action="<?= htmlspecialchars(url('search/')) ?>" method="get" role="search">
                <label class="visually-hidden" for="site-search">Search</label>
                <input type="search" id="site-search" name="q" placeholder="Search..." aria-label="Search" value="<?= htmlspecialchars($search_query ?? '') ?>">
            </form>
        </div>
    </header>
    <script src="<?= htmlspecialchars(url('assets/js/nav.js')) ?>" defer></script>
    <main>
