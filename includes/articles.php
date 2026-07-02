<?php
/*
 * articles.php - Load and query articles from MySQL.
 */
require_once __DIR__ . '/db.php';

const DEFAULT_ARTICLE_THUMBNAIL = '/assets/img/hero/day-hike.svg';

function article_thumbnail(?string $path): string
{
    return ($path !== null && $path !== '') ? $path : DEFAULT_ARTICLE_THUMBNAIL;
}

function enrich_article_row(array &$row): void
{
    $row['url'] = '/articles/' . $row['slug'] . '/';
    $row['thumbnail'] = article_thumbnail($row['thumbnail'] ?? null);
}

function get_featured_article(): ?array
{
    $stmt = get_db()->query(
        'SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug
         FROM articles a
         JOIN users u ON u.id = a.author_id
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.status = \'published\' AND a.is_featured = 1
         ORDER BY a.published_at DESC
         LIMIT 1'
    );
    $article = $stmt->fetch();

    if (!$article) {
        return null;
    }

    enrich_article_row($article);

    return $article;
}

function published_articles_where(?string $categorySlug, ?int $excludeId): array
{
    $conditions = ['a.status = \'published\''];
    $params = [];

    if ($categorySlug !== null && $categorySlug !== '') {
        $conditions[] = 'c.slug = ?';
        $params[] = $categorySlug;
    }

    if ($excludeId !== null && $excludeId > 0) {
        $conditions[] = 'a.id != ?';
        $params[] = $excludeId;
    }

    return [$conditions, $params];
}

function count_published_articles(?string $categorySlug = null, ?int $excludeId = null): int
{
    [$conditions, $params] = published_articles_where($categorySlug, $excludeId);
    $join = ($categorySlug !== null && $categorySlug !== '')
        ? 'LEFT JOIN categories c ON c.id = a.category_id'
        : 'LEFT JOIN categories c ON c.id = a.category_id';

    $sql = 'SELECT COUNT(*) FROM articles a ' . $join . ' WHERE ' . implode(' AND ', $conditions);
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function get_published_articles_page(
    int $page,
    int $perPage,
    ?string $categorySlug = null,
    ?int $excludeId = null
): array {
    [$conditions, $params] = published_articles_where($categorySlug, $excludeId);
    $offset = max(0, ($page - 1) * $perPage);

    $sql = 'SELECT a.slug, a.title, a.blurb, a.published_at, a.thumbnail,
                   u.username AS author_name, c.name AS category_name, c.slug AS category_slug
            FROM articles a
            JOIN users u ON u.id = a.author_id
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY a.published_at DESC
            LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;

    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        enrich_article_row($row);
    }

    return $rows;
}

function get_published_articles(int $limit = 0): array
{
    $sql = 'SELECT a.slug, a.title, a.blurb, a.published_at, a.thumbnail,
                   u.username AS author_name, c.name AS category_name, c.slug AS category_slug
            FROM articles a
            JOIN users u ON u.id = a.author_id
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.status = \'published\'
            ORDER BY a.published_at DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $stmt = get_db()->query($sql);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        enrich_article_row($row);
    }

    return $rows;
}

function get_article_by_slug(string $slug): ?array
{
    $stmt = get_db()->prepare(
        'SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug
         FROM articles a
         JOIN users u ON u.id = a.author_id
         LEFT JOIN categories c ON c.id = a.category_id
         WHERE a.slug = ? AND a.status = \'published\''
    );
    $stmt->execute([$slug]);
    $article = $stmt->fetch();

    if ($article) {
        enrich_article_row($article);
    }

    return $article ?: null;
}

function get_article_by_id(int $id): ?array
{
    $stmt = get_db()->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $article = $stmt->fetch();

    return $article ?: null;
}

function get_pending_articles(): array
{
    $stmt = get_db()->query(
        'SELECT a.*, u.username AS author_name
         FROM articles a
         JOIN users u ON u.id = a.author_id
         WHERE a.status = \'pending\'
         ORDER BY a.updated_at ASC'
    );

    return $stmt->fetchAll();
}

function get_articles_for_user(int $userId, string $role): array
{
    if ($role === 'editor') {
        $stmt = get_db()->query(
            'SELECT a.*, u.username AS author_name
             FROM articles a
             JOIN users u ON u.id = a.author_id
             ORDER BY a.updated_at DESC'
        );
        return $stmt->fetchAll();
    }

    $stmt = get_db()->prepare(
        'SELECT * FROM articles WHERE author_id = ? ORDER BY updated_at DESC'
    );
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function set_article_featured(int $articleId): void
{
    $db = get_db();
    $db->exec('UPDATE articles SET is_featured = 0');
    $stmt = $db->prepare('UPDATE articles SET is_featured = 1 WHERE id = ?');
    $stmt->execute([$articleId]);
}

function clear_article_featured(int $articleId): void
{
    $stmt = get_db()->prepare('UPDATE articles SET is_featured = 0 WHERE id = ?');
    $stmt->execute([$articleId]);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);

    return trim($text, '-');
}

function save_article(array $data, ?int $id = null): int
{
    if ($id) {
        $stmt = get_db()->prepare(
            'UPDATE articles
             SET slug = ?, title = ?, blurb = ?, body = ?, status = ?, editor_id = ?,
                 published_at = ?, category_id = ?, thumbnail = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['slug'],
            $data['title'],
            $data['blurb'],
            $data['body'],
            $data['status'],
            $data['editor_id'],
            $data['published_at'],
            $data['category_id'],
            $data['thumbnail'],
            $id,
        ]);

        return $id;
    }

    $stmt = get_db()->prepare(
        'INSERT INTO articles (slug, title, blurb, body, status, author_id, editor_id,
                               published_at, category_id, thumbnail)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['slug'],
        $data['title'],
        $data['blurb'],
        $data['body'],
        $data['status'],
        $data['author_id'],
        $data['editor_id'],
        $data['published_at'],
        $data['category_id'],
        $data['thumbnail'],
    ]);

    return (int) get_db()->lastInsertId();
}
