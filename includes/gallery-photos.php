<?php
/*
 * gallery-photos.php - Admin helpers to save/delete album photos under assets/img/galleries/{slug}/.
 */
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/assets.php';

const GALLERY_PHOTO_MAX_BYTES = 12582912; // 12 MB per resized file (base or variant)
const GALLERY_PHOTO_MAX_DIM = 8000;
const GALLERY_PHOTO_UPLOAD_BATCH = 120; // base + variants for ~30 photos

function gallery_photos_dir(string $slug): ?string
{
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
        return null;
    }

    return dirname(__DIR__) . '/assets/img/galleries/' . $slug;
}

function gallery_photos_ensure_dir(string $slug): ?string
{
    $dir = gallery_photos_dir($slug);
    if ($dir === null) {
        return null;
    }
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return null;
    }
    if (!is_writable($dir)) {
        return null;
    }

    return $dir;
}

function gallery_photo_stem_from_name(string $name): ?string
{
    $base = strtolower(basename($name));
    if (!preg_match('/^([a-z0-9][a-z0-9_-]{0,78})(?:-(?:480|960|1600))?\.webp$/', $base, $m)) {
        return null;
    }

    return $m[1];
}

function gallery_photo_is_variant_name(string $name): bool
{
    return (bool) preg_match('/-(?:480|960|1600)\.webp$/i', basename($name));
}

function gallery_photo_unlink_set(string $dir, string $stem): void
{
    foreach ([$stem . '.webp', $stem . '-480.webp', $stem . '-960.webp', $stem . '-1600.webp', $stem . '.json'] as $name) {
        $path = $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function gallery_photo_delete(string $slug, string $basename): ?string
{
    $dir = gallery_photos_dir($slug);
    if ($dir === null || !is_dir($dir)) {
        return 'Gallery folder not found.';
    }

    $stem = gallery_photo_stem_from_name($basename);
    if ($stem === null || gallery_photo_is_variant_name($basename)) {
        return 'Invalid photo name.';
    }

    $base = $dir . '/' . $stem . '.webp';
    if (!is_file($base)) {
        return 'Photo not found.';
    }

    gallery_photo_unlink_set($dir, $stem);

    return null;
}

/**
 * Save browser-prepared WebP uploads (base + optional -480/-960/-1600 variants).
 * Expects $_FILES['photos'] multi-upload; filenames must be {stem}.webp or {stem}-{width}.webp.
 *
 * @return array{warnings: list<string>, saved: int, bases: int}
 */
function gallery_photo_save_uploads(string $slug, array $files): array
{
    $warnings = [];
    $dir = gallery_photos_ensure_dir($slug);
    if ($dir === null) {
        return [
            'warnings' => ['Could not create the gallery folder. Check permissions on assets/img/galleries/.'],
            'saved' => 0,
            'bases' => 0,
        ];
    }

    $uploads = $files['photos'] ?? null;
    if (!$uploads || !isset($uploads['name'])) {
        if (
            (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            && $_FILES === []
        ) {
            return [
                'warnings' => ['Upload was too large for the server (post_max_size is ' . ini_get('post_max_size') . '). Try fewer photos.'],
                'saved' => 0,
                'bases' => 0,
            ];
        }
        $maxFiles = (int) ini_get('max_file_uploads');
        return [
            'warnings' => ['No image files arrived. This server allows only ' . max(1, $maxFiles) . ' files per request — submit fewer photos, or upload again in smaller batches.'],
            'saved' => 0,
            'bases' => 0,
        ];
    }

    // Normalize single-file shape to a list.
    if (!is_array($uploads['name'])) {
        $uploads = [
            'name' => [$uploads['name']],
            'type' => [$uploads['type'] ?? ''],
            'tmp_name' => [$uploads['tmp_name'] ?? ''],
            'error' => [$uploads['error'] ?? UPLOAD_ERR_NO_FILE],
            'size' => [$uploads['size'] ?? 0],
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $accepted = 0;
    $bases = 0;

    foreach (array_keys($uploads['name']) as $i) {
        $err = $uploads['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $origName = (string) ($uploads['name'][$i] ?? '');
        $safeLabel = htmlspecialchars($origName !== '' ? $origName : 'photo');

        if ($accepted >= GALLERY_PHOTO_UPLOAD_BATCH) {
            $warnings[] = 'Only ' . GALLERY_PHOTO_UPLOAD_BATCH . ' files per upload; "' . $safeLabel . '" was skipped.';
            continue;
        }

        $tmp = (string) ($uploads['tmp_name'][$i] ?? '');
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            $code = (int) $err;
            $why = $code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE
                ? 'too large for PHP upload limits'
                : 'failed to upload (error ' . $code . ')';
            $warnings[] = '"' . $safeLabel . '" ' . $why . '.';
            continue;
        }

        $stem = gallery_photo_stem_from_name($origName);
        if ($stem === null) {
            $warnings[] = '"' . $safeLabel . '" has an invalid filename.';
            continue;
        }

        $size = (int) ($uploads['size'][$i] ?? 0);
        if ($size <= 0 || $size > GALLERY_PHOTO_MAX_BYTES) {
            $warnings[] = '"' . $safeLabel . '" is too large; it was skipped.';
            continue;
        }

        $mime = $finfo->file($tmp) ?: '';
        $info = @getimagesize($tmp);
        if ($mime !== 'image/webp' || !$info || (int) ($info[2] ?? 0) !== IMAGETYPE_WEBP) {
            $warnings[] = '"' . $safeLabel . '" must be a WebP image.';
            continue;
        }
        if (max((int) $info[0], (int) $info[1]) > GALLERY_PHOTO_MAX_DIM) {
            $warnings[] = '"' . $safeLabel . '" dimensions are too large; it was skipped.';
            continue;
        }

        $destName = strtolower(basename($origName));
        $dest = $dir . '/' . $destName;
        if (!move_uploaded_file($tmp, $dest)) {
            $warnings[] = '"' . $safeLabel . '" could not be saved.';
            continue;
        }
        @chmod($dest, 0644);
        $accepted++;
        if (!gallery_photo_is_variant_name($destName)) {
            $bases++;
        }
    }

    if ($accepted === 0 && $warnings === []) {
        $warnings[] = 'No photos were uploaded.';
    }

    return [
        'warnings' => $warnings,
        'saved' => $accepted,
        'bases' => $bases,
    ];
}

/**
 * Optional sidecar JSON from the browser (camera / capture time).
 * Expects POST photo_meta[stem] = JSON string with make/model/datetime_original.
 *
 * @return list<string> warnings
 */
function gallery_photo_save_meta(string $slug, array $metaByStem): array
{
    $warnings = [];
    $dir = gallery_photos_dir($slug);
    if ($dir === null || !is_dir($dir) || $metaByStem === []) {
        return $warnings;
    }

    foreach ($metaByStem as $stem => $raw) {
        $stem = strtolower(trim((string) $stem));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,78}$/', $stem)) {
            continue;
        }
        if (!is_file($dir . '/' . $stem . '.webp')) {
            continue;
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            $warnings[] = 'Ignored invalid metadata for ' . htmlspecialchars($stem) . '.';
            continue;
        }

        $payload = [
            'make' => isset($decoded['make']) ? mb_substr(trim((string) $decoded['make']), 0, 80) : null,
            'model' => isset($decoded['model']) ? mb_substr(trim((string) $decoded['model']), 0, 120) : null,
            'datetime_original' => isset($decoded['datetime_original'])
                ? mb_substr(trim((string) $decoded['datetime_original']), 0, 32)
                : null,
        ];
        foreach ($payload as $k => $v) {
            if ($v === '') {
                $payload[$k] = null;
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            continue;
        }
        @file_put_contents($dir . '/' . $stem . '.json', $json . "\n");
    }

    return $warnings;
}
