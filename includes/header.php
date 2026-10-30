<?php
/*
 * header.php - Opens the HTML document, head, and site header with navigation.
 * Expects $site_name, $site_tagline, $nav_links from config.php.
 * Optional: $page_theme (home|articles|gallery|books|services|about|admin|search).
 */
$page_title = $page_title ?? $site_name;
$page_theme = preg_replace('/[^a-z]/', '', strtolower((string) ($page_theme ?? 'home'))) ?: 'home';

require_once __DIR__ . '/auth.php';
$header_user = null;
try {
    $header_user = current_user();
} catch (Throwable $e) {
    $header_user = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($site_tagline) ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(url('assets/img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="icon" href="<?= htmlspecialchars(url('assets/img/favicon-32.png')) ?>" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url('assets/img/favicon-180.png')) ?>">
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
<body class="theme-<?= htmlspecialchars($page_theme) ?><?= !empty($body_class) ? ' ' . htmlspecialchars((string) $body_class) : '' ?>">
    <header class="site-header">
        <div class="header-inner">
            <nav class="header-nav" aria-label="Main" data-nav>
                <button
                    type="button"
                    class="nav-explore"
                    aria-expanded="false"
                    aria-controls="nav-menu"
                    data-nav-toggle
                >
                    <span class="nav-explore-label">Explore</span>
                    <svg class="nav-explore-chevron" width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" focusable="false">
                        <path d="M2.2 4.2L6 8l3.8-3.8" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square"/>
                    </svg>
                </button>
                <ul class="nav-list" id="nav-menu" data-nav-menu hidden>
                    <?php foreach ($nav_links as $label => $navUrl): ?>
                        <li>
                            <a
                                href="<?= htmlspecialchars(url($navUrl)) ?>"
                                class="<?= nav_is_active($navUrl) ? 'is-active' : '' ?>"
                                <?= nav_is_active($navUrl) ? 'aria-current="page"' : '' ?>
                            ><?= htmlspecialchars($label) ?></a>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($header_user): ?>
                        <li class="nav-list-divider" aria-hidden="true"></li>
                        <li>
                            <a
                                href="<?= htmlspecialchars(url('admin/index.php')) ?>"
                                class="<?= nav_is_active('admin/index.php') || (nav_is_active('admin/') && !nav_is_active('admin/login.php')) ? 'is-active' : '' ?>"
                            >Admin</a>
                        </li>
                        <li>
                            <a href="<?= htmlspecialchars(url('admin/logout.php')) ?>">Log out</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-list-divider" aria-hidden="true"></li>
                        <li>
                            <a
                                href="<?= htmlspecialchars(url('admin/login.php')) ?>"
                                class="<?= nav_is_active('/admin/login.php') ? 'is-active' : '' ?>"
                            >Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <a class="logo header-logo" href="<?= htmlspecialchars(url()) ?>">EARTH-ODYSSEY.COM</a>
            <form class="header-search" action="<?= htmlspecialchars(url('search/')) ?>" method="get" role="search">
                <label class="visually-hidden" for="site-search">Search</label>
                <input type="search" id="site-search" name="q" placeholder="Search" aria-label="Search" value="<?= htmlspecialchars($search_query ?? '') ?>">
                <a class="header-search-icon" href="<?= htmlspecialchars(url('search/')) ?>" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="M20 20l-3.5-3.5"></path>
                    </svg>
                </a>
            </form>
        </div>
    </header>
    <script src="<?= htmlspecialchars(asset_url('assets/js/nav.js')) ?>" defer></script>
    <main>
