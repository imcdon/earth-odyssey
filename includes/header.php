<?php
/*
 * header.php - Opens the HTML document, head, and site header with navigation.
 * Expects $site_name, $site_tagline, $nav_links from config.php.
 * Optional: $page_theme (home|articles|gallery|services|about|admin|search).
 */
$page_title = $page_title ?? $site_name;
$page_theme = preg_replace('/[^a-z]/', '', strtolower((string) ($page_theme ?? 'home'))) ?: 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($site_tagline) ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <?php if ($page_theme === 'rivers'): ?>
    <link rel="manifest" href="<?= htmlspecialchars(url('services/rivers/manifest.php')) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url('assets/img/app/apple-touch-icon.png')) ?>">
    <meta name="theme-color" content="#1f3f2b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="EO Rivers">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;600;700&family=Libre+Caslon+Text:ital,wght@0,400;0,700;1,400&family=Newsreader:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(css_bundle_url()) ?>">
</head>
<body class="theme-<?= htmlspecialchars($page_theme) ?>">
    <header class="site-header">
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
                    <?php foreach ($nav_links as $label => $navUrl): ?>
                        <li>
                            <a
                                href="<?= htmlspecialchars(url($navUrl)) ?>"
                                class="<?= nav_is_active($navUrl) ? 'is-active' : '' ?>"
                                <?= nav_is_active($navUrl) ? 'aria-current="page"' : '' ?>
                            ><?= htmlspecialchars($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <a class="logo header-logo" href="<?= htmlspecialchars(url()) ?>">EARTH-ODYSSEY.COM</a>
            <form class="header-search" action="<?= htmlspecialchars(url('search/')) ?>" method="get" role="search">
                <label class="visually-hidden" for="site-search">Search</label>
                <input type="search" id="site-search" name="q" placeholder="Search..." aria-label="Search" value="<?= htmlspecialchars($search_query ?? '') ?>">
            </form>
        </div>
    </header>
    <script src="<?= htmlspecialchars(asset_url('assets/js/nav.js')) ?>" defer></script>
    <main>
