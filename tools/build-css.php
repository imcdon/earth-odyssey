<?php
/*
 * build-css.php - Rebuild assets/css/style.min.css from the @import list in style.css.
 * cPanel deploy (.cpanel.yml) runs this after rsync. Local: C:\xampp\php\php.exe tools\build-css.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/assets.php';

$path = css_build_bundle(true);
if ($path === '') {
    fwrite(STDERR, "Could not write assets/css/style.min.css\n");
    exit(1);
}

fwrite(STDOUT, 'Wrote ' . $path . ' (' . filesize($path) . " bytes)\n");
