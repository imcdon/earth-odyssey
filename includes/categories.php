<?php
/*
 * categories.php - Category CRUD for articles and galleries.
 */
require_once __DIR__ . '/db.php';

function slugify_category(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}

function get_categories(?string $type = null): array
{
    if ($type !== null) {
        $stmt = get_db()->prepare(
            'SELECT id, type, slug, name, image_path, sort_order, is_featured
             FROM categories
             WHERE type = ?
             ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$type]);

        return $stmt->fetchAll();
    }

    $stmt = get_db()->query(
        'SELECT id, type, slug, name, image_path, sort_order, is_featured
         FROM categories
         ORDER BY type ASC, sort_order ASC, name ASC'
    );

    return $stmt->fetchAll();
}

function get_featured_gallery(): ?array
{
    $stmt = get_db()->query(
        "SELECT id, type, slug, name, image_path, sort_order, is_featured
         FROM categories
         WHERE type = 'gallery' AND is_featured = 1
         ORDER BY sort_order ASC, name ASC
         LIMIT 1"
    );
    $row = $stmt->fetch();

    return $row ? enrich_gallery_category($row) : null;
}

function get_random_gallery_category(): ?array
{
    $stmt = get_db()->query(
        "SELECT id, type, slug, name, image_path, sort_order, is_featured
         FROM categories
         WHERE type = 'gallery'
         ORDER BY RAND()
         LIMIT 1"
    );
    $row = $stmt->fetch();

    return $row ? enrich_gallery_category($row) : null;
}

function enrich_gallery_category(array $row): array
{
    $row['url'] = '/galleries/' . rawurlencode($row['slug']) . '/';
    $row['blurb'] = 'Photos from the trail, camp, and field — ' . $row['name'] . '.';

    return $row;
}

function gallery_photo_title(string $path): string
{
    $base = pathinfo($path, PATHINFO_FILENAME);
    $label = str_replace(['-', '_'], ' ', $base);

    return ucwords(trim($label));
}

function gallery_photo_sidecar_path(string $webPath): string
{
    $relative = ltrim(str_replace('\\', '/', $webPath), '/');
    $relative = preg_replace('/\.webp$/i', '.json', $relative) ?: $relative;

    return dirname(__DIR__) . '/' . $relative;
}

function gallery_photo_load_meta(string $webPath): array
{
    $file = gallery_photo_sidecar_path($webPath);
    if (!is_file($file)) {
        return [
            'make' => null,
            'model' => null,
            'datetime_original' => null,
            'camera' => null,
            'taken_at_display' => null,
        ];
    }

    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw)) {
        return [
            'make' => null,
            'model' => null,
            'datetime_original' => null,
            'camera' => null,
            'taken_at_display' => null,
        ];
    }

    $make = isset($raw['make']) ? trim((string) $raw['make']) : '';
    $model = isset($raw['model']) ? trim((string) $raw['model']) : '';
    $datetime = isset($raw['datetime_original']) ? trim((string) $raw['datetime_original']) : '';
    $make = $make !== '' ? $make : null;
    $model = $model !== '' ? $model : null;
    $datetime = $datetime !== '' ? $datetime : null;

    $camera = null;
    if ($make !== null && $model !== null) {
        $camera = str_starts_with(mb_strtolower($model), mb_strtolower($make))
            ? $model
            : $make . ' ' . $model;
    } elseif ($model !== null) {
        $camera = $model;
    } elseif ($make !== null) {
        $camera = $make;
    }

    $takenDisplay = null;
    if ($datetime !== null) {
        $ts = strtotime(str_replace('T', ' ', $datetime));
        if ($ts !== false) {
            $takenDisplay = date('F j, Y · g:i A', $ts);
        }
    }

    return [
        'make' => $make,
        'model' => $model,
        'datetime_original' => $datetime,
        'camera' => $camera,
        'taken_at_display' => $takenDisplay,
    ];
}

function gallery_photo_description(array $photo, string $collectionName): string
{
    $bits = [];
    if (!empty($photo['camera'])) {
        $bits[] = $photo['camera'];
    }
    if (!empty($photo['taken_at_display'])) {
        $bits[] = $photo['taken_at_display'];
    }
    if ($bits !== []) {
        return implode(' · ', $bits);
    }

    return 'From the ' . $collectionName . ' collection.';
}

function enrich_gallery_for_hub(array $row, int $previewLimit = 10): array
{
    $row = enrich_gallery_category($row);
    $photos = get_gallery_photos($row['slug']);
    $row['photo_count'] = count($photos);
    $row['preview_photos'] = [];
    foreach (array_slice($photos, 0, max(1, $previewLimit)) as $photo) {
        $row['preview_photos'][] = $photo['path'];
    }
    if ($row['preview_photos'] !== []) {
        $row['cover'] = $row['preview_photos'][0];
    } elseif (!empty($row['image_path'])) {
        $row['cover'] = $row['image_path'];
        $row['preview_photos'] = [$row['image_path']];
    } else {
        $row['cover'] = null;
    }

    return $row;
}

function get_gallery_photos(string $slug): array
{
    if (!preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
        return [];
    }

    $dir = dirname(__DIR__) . '/assets/img/galleries/' . $slug;
    if (!is_dir($dir)) {
        return [];
    }

    $photos = [];
    foreach (glob($dir . '/*.webp') ?: [] as $file) {
        $name = basename($file);
        if (preg_match('/-\d+\.webp$/i', $name)) {
            continue;
        }
        $size = @getimagesize($file) ?: [1600, 1067, 'mime' => 'image/webp'];
        $path = '/assets/img/galleries/' . $slug . '/' . $name;
        $meta = gallery_photo_load_meta($path);
        $photos[] = [
            'path'              => $path,
            'title'             => gallery_photo_title($path),
            'width'             => (int) $size[0],
            'height'            => (int) $size[1],
            'make'              => $meta['make'],
            'model'             => $meta['model'],
            'datetime_original' => $meta['datetime_original'],
            'camera'            => $meta['camera'],
            'taken_at_display'  => $meta['taken_at_display'],
        ];
    }

    usort($photos, static fn($a, $b) => strnatcasecmp($a['path'], $b['path']));

    return $photos;
}

function get_gallery_category_by_slug(string $slug): ?array
{
    $stmt = get_db()->prepare(
        "SELECT id, type, slug, name, image_path, sort_order, is_featured
         FROM categories
         WHERE type = 'gallery' AND slug = ?
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    return $row ? enrich_gallery_category($row) : null;
}

function set_gallery_featured(int $categoryId): void
{
    $db = get_db();
    $db->exec("UPDATE categories SET is_featured = 0 WHERE type = 'gallery'");
    $stmt = $db->prepare("UPDATE categories SET is_featured = 1 WHERE id = ? AND type = 'gallery'");
    $stmt->execute([$categoryId]);
}

function clear_gallery_featured(int $categoryId): void
{
    $stmt = get_db()->prepare("UPDATE categories SET is_featured = 0 WHERE id = ? AND type = 'gallery'");
    $stmt->execute([$categoryId]);
}

function get_category_by_id(int $id): ?array
{
    $stmt = get_db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    return $category ?: null;
}

function save_category(array $data, ?int $id = null): int
{
    if ($id) {
        $stmt = get_db()->prepare(
            'UPDATE categories
             SET type = ?, slug = ?, name = ?, image_path = ?, sort_order = ?, is_featured = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['type'],
            $data['slug'],
            $data['name'],
            $data['image_path'],
            $data['sort_order'],
            (int) ($data['is_featured'] ?? 0),
            $id,
        ]);

        return $id;
    }

    $stmt = get_db()->prepare(
        'INSERT INTO categories (type, slug, name, image_path, sort_order, is_featured)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['type'],
        $data['slug'],
        $data['name'],
        $data['image_path'],
        $data['sort_order'],
        (int) ($data['is_featured'] ?? 0),
    ]);

    return (int) get_db()->lastInsertId();
}

function delete_category(int $id): ?string
{
    $category = get_category_by_id($id);
    if (!$category) {
        return 'Category not found.';
    }

    if ($category['type'] === 'article') {
        $stmt = get_db()->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return 'Cannot delete: articles are assigned to this category.';
        }
    }

    $stmt = get_db()->prepare('DELETE FROM categories WHERE id = ?');
    $stmt->execute([$id]);

    return null;
}

function category_type_label(string $type): string
{
    return $type === 'gallery' ? 'Gallery' : 'Article';
}
