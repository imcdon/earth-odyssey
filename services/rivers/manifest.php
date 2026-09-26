<?php
/*
 * manifest.php - Web app manifest for the river tool ("EO Rivers" on the home screen).
 * PHP so the paths follow the install folder (/ on the live site, /earth-odyssey/ locally).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/paths.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'id'               => url('services/rivers/'),
    'name'             => 'Earth Odyssey River Gauges',
    'short_name'       => 'EO Rivers',
    'description'      => 'Live USGS river flow, height, and water temperature, saved for use at the river.',
    'start_url'        => url('services/rivers/?app=1'),
    'scope'            => url('services/rivers/'),
    'display'          => 'standalone',
    'background_color' => '#f4efe6',
    'theme_color'      => '#1f3f2b',
    'icons'            => [
        ['src' => url('assets/img/app/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('assets/img/app/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => url('assets/img/app/maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
