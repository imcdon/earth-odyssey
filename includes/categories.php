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
    $row['url'] = '/galleries/?category=' . rawurlencode($row['slug']);
    $row['blurb'] = 'Photos from the trail, camp, and field — ' . $row['name'] . '.';

    return $row;
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
