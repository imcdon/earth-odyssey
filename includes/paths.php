<?php
/*
 * paths.php - Base path detection for subdirectory installs (e.g. /earth-odyssey/).
 */
if (!function_exists('url')) {
    $doc_root = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $project_root = str_replace('\\', '/', dirname(__DIR__));
    $base_path = '';

    if ($doc_root !== '' && str_starts_with($project_root, $doc_root)) {
        $relative = substr($project_root, strlen($doc_root));
        $base_path = '/' . trim($relative, '/');
        if ($base_path === '/') {
            $base_path = '';
        }
    }

    function url(string $path = ''): string
    {
        global $base_path;
        $path = ltrim($path, '/');
        if ($path === '') {
            return $base_path === '' ? '/' : $base_path . '/';
        }
        return $base_path . '/' . $path;
    }
}
