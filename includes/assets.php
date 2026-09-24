<?php
/*
 * assets.php - Versioned asset URLs, the auto-built CSS bundle, and responsive image helpers.
 */
require_once __DIR__ . '/paths.php';

const IMG_VARIANT_WIDTHS = [480, 960, 1600];

function asset_path(string $path): string
{
    return dirname(__DIR__) . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    $file = asset_path($path);
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/*
 * Concatenates and minifies the @import list in style.css into style.min.css.
 * Rebuilds only when a source file is newer than the bundle; falls back to style.css if the
 * bundle can't be written (e.g. read-only filesystem).
 */
function css_bundle_url(): string
{
    $dir = asset_path('assets/css');
    $manifest = $dir . '/style.css';
    $bundle = $dir . '/style.min.css';

    preg_match_all('/@import\s+url\([\'"]?([^\'")]+)[\'"]?\)\s*;/', (string) file_get_contents($manifest), $m);
    $sources = array_map(fn($rel) => $dir . '/' . $rel, $m[1]);

    $newest = filemtime($manifest);
    foreach ($sources as $src) {
        $newest = max($newest, (int) @filemtime($src));
    }

    if (!is_file($bundle) || filemtime($bundle) < $newest) {
        $css = '';
        foreach ($sources as $src) {
            $css .= (string) @file_get_contents($src) . "\n";
        }
        $tmp = $bundle . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, css_minify($css)) !== false) {
            @rename($tmp, $bundle);
        }
        @unlink($tmp);
        clearstatcache(true, $bundle);
    }

    return is_file($bundle) ? asset_url('assets/css/style.min.css') : asset_url('assets/css/style.css');
}

function css_minify(string $css): string
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{};,>])\s*/', '$1', $css);
    $css = preg_replace('/:\s+/', ':', $css);
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

/* Local .webp image under the project, or null for SVGs, external URLs, and missing files. */
function img_local_webp(?string $path): ?string
{
    if (!$path || preg_match('#^(https?:)?//#i', $path) || !preg_match('/\.webp$/i', $path)) {
        return null;
    }
    $file = asset_path($path);
    return is_file($file) ? $file : null;
}

/* [width => web path] for every existing variant plus the original, smallest first. */
function img_variants(?string $path): array
{
    static $cache = [];
    if ($path === null || $path === '') {
        return [];
    }
    if (isset($cache[$path])) {
        return $cache[$path];
    }

    $file = img_local_webp($path);
    if (!$file) {
        return $cache[$path] = [];
    }

    $set = [];
    foreach (IMG_VARIANT_WIDTHS as $w) {
        $variant = preg_replace('/\.webp$/i', "-{$w}.webp", $path);
        if (is_file(asset_path($variant))) {
            $set[$w] = $variant;
        }
    }
    $size = @getimagesize($file);
    if ($size) {
        $set[(int) $size[0]] = $path;
    }
    ksort($set);

    return $cache[$path] = $set;
}

/* ' srcset="..." sizes="..."' for a local webp with variants, otherwise ''. */
function img_srcset(?string $path, string $sizes): string
{
    $set = img_variants($path);
    if (count($set) < 2) {
        return '';
    }
    $parts = [];
    foreach ($set as $w => $p) {
        $parts[] = url($p) . " {$w}w";
    }
    return ' srcset="' . htmlspecialchars(implode(', ', $parts)) . '" sizes="' . htmlspecialchars($sizes) . '"';
}

/* URL of the smallest variant at least $minWidth wide (for CSS background images). */
function img_fit(?string $path, int $minWidth): string
{
    foreach (img_variants($path) as $w => $p) {
        if ($w >= $minWidth) {
            return url($p);
        }
    }
    return url((string) $path);
}
