<?php
/*
 * search.php - Sitewide and scoped search (articles, galleries, photos, tools, rivers).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/articles.php';
require_once __DIR__ . '/categories.php';
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/rivers.php';

const SEARCH_MAX_QUERY_LENGTH = 200;
const SEARCH_EXCERPT_RADIUS = 80;
const SEARCH_PHOTO_CAP = 40;
const SEARCH_RIVER_CAP = 25;
const SEARCH_ARTICLE_CAP = 100;
const SEARCH_TOOL_THUMB_CANDIDATES = [
    '/assets/img/hero/services.webp',
    '/assets/img/hero/services.svg',
];

function normalize_search_query(string $q): ?string
{
    $q = trim(preg_replace('/\s+/', ' ', $q));
    if ($q === '') {
        return null;
    }

    if (mb_strlen($q) > SEARCH_MAX_QUERY_LENGTH) {
        $q = mb_substr($q, 0, SEARCH_MAX_QUERY_LENGTH);
    }

    return $q;
}

function search_query_terms(string $q): array
{
    $q = mb_strtolower($q);
    $q = preg_replace('/[^\p{L}\p{N}\s-]+/u', ' ', $q);
    $parts = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);

    return array_values(array_unique($parts ?: []));
}

function fulltext_boolean_query(string $q): string
{
    $terms = search_query_terms($q);
    $booleanTerms = [];

    foreach ($terms as $term) {
        $term = preg_replace('/[+\-><()~*"@]+/', '', $term);
        if ($term === '' || mb_strlen($term) < 4) {
            continue;
        }
        $booleanTerms[] = $term . '*';
    }

    return implode(' ', $booleanTerms);
}

function should_use_like_search(string $q): bool
{
    $boolean = fulltext_boolean_query($q);

    return $boolean === '';
}

function query_matches_text(string $text, array $terms): bool
{
    if ($terms === []) {
        return false;
    }

    $haystack = mb_strtolower($text);
    foreach ($terms as $term) {
        if ($term !== '' && mb_strpos($haystack, $term) !== false) {
            return true;
        }
    }

    return false;
}

function highlight_search_terms(string $text, array $terms): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    foreach ($terms as $term) {
        if ($term === '') {
            continue;
        }
        $pattern = '/' . preg_quote($term, '/') . '/iu';
        $escaped = preg_replace($pattern, '<mark class="search-highlight">$0</mark>', $escaped);
    }

    return $escaped;
}

function build_search_excerpt(string $text, array $terms, int $radius = SEARCH_EXCERPT_RADIUS): string
{
    $text = preg_replace('/\s+/', ' ', trim($text));
    if ($text === '') {
        return '';
    }

    $lower = mb_strtolower($text);
    $matchPos = false;
    $matchLen = 0;

    foreach ($terms as $term) {
        if ($term === '') {
            continue;
        }
        $pos = mb_strpos($lower, $term);
        if ($pos !== false && ($matchPos === false || $pos < $matchPos)) {
            $matchPos = $pos;
            $matchLen = mb_strlen($term);
        }
    }

    if ($matchPos === false) {
        $snippet = mb_substr($text, 0, $radius * 2);

        return highlight_search_terms($snippet, $terms) . (mb_strlen($text) > $radius * 2 ? '…' : '');
    }

    $start = max(0, $matchPos - $radius);
    $length = ($radius * 2) + $matchLen;
    $snippet = mb_substr($text, $start, $length);

    if ($start > 0) {
        $snippet = '…' . ltrim($snippet);
    }
    if ($start + $length < mb_strlen($text)) {
        $snippet = rtrim($snippet) . '…';
    }

    return highlight_search_terms($snippet, $terms);
}

function article_search_description(array $article, string $query): string
{
    $terms = search_query_terms($query);
    $plainBody = markdown_to_plain_text($article['body'] ?? '');

    if (query_matches_text($plainBody, $terms)) {
        return build_search_excerpt($plainBody, $terms);
    }

    return htmlspecialchars($article['blurb'] ?? '', ENT_QUOTES, 'UTF-8');
}

function map_article_to_search_result(array $article, string $query): array
{
    $hasThumb = !empty($article['thumbnail']);
    enrich_article_row($article);

    return [
        'kind'          => 'article',
        'title'         => $article['title'],
        'description'   => article_search_description($article, $query),
        'url'           => $article['url'],
        'meta'          => 'Article',
        'thumbnail'     => $hasThumb ? $article['thumbnail'] : null,
        'score'         => (float) ($article['score'] ?? 1),
        'author_name'   => $article['author_name'] ?? null,
        'published_at'  => $article['published_at'] ?? null,
        'category_name' => $article['category_name'] ?? null,
    ];
}

function search_asset_exists(string $webPath): bool
{
    $relative = ltrim(str_replace('\\', '/', $webPath), '/');
    if ($relative === '' || str_contains($relative, '..')) {
        return false;
    }

    return is_file(dirname(__DIR__) . '/' . $relative);
}

function search_tool_thumbnail(): ?string
{
    foreach (SEARCH_TOOL_THUMB_CANDIDATES as $path) {
        if (search_asset_exists($path)) {
            return $path;
        }
    }

    return null;
}

function search_photo_title(string $path): string
{
    $base = pathinfo($path, PATHINFO_FILENAME);
    $label = str_replace(['-', '_'], ' ', $base);

    return ucwords(trim($label));
}

function search_result_sort(array $a, array $b): int
{
    $scoreCmp = ($b['score'] <=> $a['score']);
    if ($scoreCmp !== 0) {
        return $scoreCmp;
    }

    return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
}

function search_articles_base_sql(?string $categorySlug): array
{
    $conditions = ['a.status = \'published\''];
    $params = [];

    if ($categorySlug !== null && $categorySlug !== '') {
        $conditions[] = 'c.slug = ?';
        $params[] = $categorySlug;
    }

    $join = 'LEFT JOIN categories c ON c.id = a.category_id';

    return [$conditions, $params, $join];
}

function search_articles_fulltext(
    string $query,
    int $limit,
    int $offset,
    ?string $categorySlug = null
): array {
    $boolean = fulltext_boolean_query($query);
    if ($boolean === '') {
        return [];
    }

    [$conditions, $params, $join] = search_articles_base_sql($categorySlug);
    $conditions[] = 'MATCH(a.title, a.blurb, a.body) AGAINST (? IN BOOLEAN MODE)';

    $sql = 'SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug,
                   MATCH(a.title, a.blurb, a.body) AGAINST (? IN BOOLEAN MODE) AS score
            FROM articles a
            JOIN users u ON u.id = a.author_id
            ' . $join . '
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY score DESC, a.published_at DESC
            LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $stmt = get_db()->prepare($sql);
    $stmt->execute(array_merge([$boolean, $boolean], $params));

    return $stmt->fetchAll();
}

function search_articles_like(
    string $query,
    int $limit,
    int $offset,
    ?string $categorySlug = null
): array {
    [$conditions, $params, $join] = search_articles_base_sql($categorySlug);
    $like = '%' . $query . '%';
    $conditions[] = '(a.title LIKE ? OR a.blurb LIKE ? OR a.body LIKE ?)';
    $params = array_merge($params, [$like, $like, $like]);

    $sql = 'SELECT a.*, u.username AS author_name, c.name AS category_name, c.slug AS category_slug,
                   1 AS score
            FROM articles a
            JOIN users u ON u.id = a.author_id
            ' . $join . '
            WHERE ' . implode(' AND ', $conditions) . '
            ORDER BY a.published_at DESC
            LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function count_search_articles_fulltext(string $query, ?string $categorySlug = null): int
{
    $boolean = fulltext_boolean_query($query);
    if ($boolean === '') {
        return 0;
    }

    [$conditions, $params, $join] = search_articles_base_sql($categorySlug);
    $conditions[] = 'MATCH(a.title, a.blurb, a.body) AGAINST (? IN BOOLEAN MODE)';

    $sql = 'SELECT COUNT(*)
            FROM articles a
            JOIN users u ON u.id = a.author_id
            ' . $join . '
            WHERE ' . implode(' AND ', $conditions);

    $stmt = get_db()->prepare($sql);
    $stmt->execute(array_merge([$boolean], $params));

    return (int) $stmt->fetchColumn();
}

function count_search_articles_like(string $query, ?string $categorySlug = null): int
{
    [$conditions, $params, $join] = search_articles_base_sql($categorySlug);
    $like = '%' . $query . '%';
    $conditions[] = '(a.title LIKE ? OR a.blurb LIKE ? OR a.body LIKE ?)';
    $params = array_merge($params, [$like, $like, $like]);

    $sql = 'SELECT COUNT(*)
            FROM articles a
            JOIN users u ON u.id = a.author_id
            ' . $join . '
            WHERE ' . implode(' AND ', $conditions);

    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function search_articles(
    string $query,
    int $limit,
    int $offset,
    ?string $categorySlug = null
): array {
    if (should_use_like_search($query)) {
        return search_articles_like($query, $limit, $offset, $categorySlug);
    }

    $rows = search_articles_fulltext($query, $limit, $offset, $categorySlug);
    if ($rows !== []) {
        return $rows;
    }

    return search_articles_like($query, $limit, $offset, $categorySlug);
}

function count_search_articles(string $query, ?string $categorySlug = null): int
{
    if (should_use_like_search($query)) {
        return count_search_articles_like($query, $categorySlug);
    }

    $total = count_search_articles_fulltext($query, $categorySlug);
    if ($total > 0) {
        return $total;
    }

    return count_search_articles_like($query, $categorySlug);
}

function search_categories(string $query, array $types, int $limit = 20): array
{
    if ($types === []) {
        return [];
    }

    $like = '%' . $query . '%';
    $placeholders = implode(', ', array_fill(0, count($types), '?'));

    $sql = 'SELECT id, type, slug, name, image_path
            FROM categories
            WHERE type IN (' . $placeholders . ')
              AND (name LIKE ? OR slug LIKE ?)
            ORDER BY sort_order ASC, name ASC
            LIMIT ' . (int) $limit;

    $stmt = get_db()->prepare($sql);
    $stmt->execute(array_merge($types, [$like, $like]));
    $rows = $stmt->fetchAll();
    $terms = search_query_terms($query);
    $results = [];

    foreach ($rows as $row) {
        $isArticle = $row['type'] === 'article';
        $thumb = !empty($row['image_path']) ? $row['image_path'] : null;
        $results[] = [
            'kind'        => $isArticle ? 'category' : 'gallery',
            'title'       => $row['name'],
            'description' => $isArticle
                ? 'Browse articles in ' . $row['name']
                : 'Photo collection from the field — ' . $row['name'] . '.',
            'url'         => $isArticle
                ? '/articles/?category=' . rawurlencode($row['slug'])
                : '/galleries/' . rawurlencode($row['slug']) . '/',
            'meta'        => $isArticle ? 'Article' : 'Gallery',
            'thumbnail'   => $thumb,
            'score'       => query_matches_text($row['name'], $terms) ? 2.0 : 1.5,
        ];
    }

    return $results;
}

function search_galleries(string $query, int $limit = 20): array
{
    return search_categories($query, ['gallery'], $limit);
}

function search_tools(string $query, array $toolGroups): array
{
    $terms = search_query_terms($query);
    if ($terms === []) {
        return [];
    }

    $thumb = search_tool_thumbnail();
    $results = [];

    foreach ($toolGroups as $group) {
        $groupTitle = (string) ($group['title'] ?? '');
        foreach ($group['tools'] ?? [] as $tool) {
            $title = (string) ($tool['title'] ?? '');
            $description = (string) ($tool['description'] ?? '');
            $url = $tool['url'] ?? null;
            $image = (string) ($tool['image'] ?? '');
            if ($url === null || $url === '') {
                continue;
            }
            if (!query_matches_text($title . ' ' . $description . ' ' . $groupTitle, $terms)) {
                continue;
            }

            $results[] = [
                'kind'        => 'tool',
                'title'       => $title,
                'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
                'url'         => $url,
                'meta'        => 'Tool',
                'thumbnail'   => ($image !== '' && search_asset_exists($image)) ? $image : $thumb,
                'score'       => query_matches_text($title, $terms) ? 2.2 : 1.4,
            ];
        }
    }

    return $results;
}

function search_rivers_global(string $query, int $limit = SEARCH_RIVER_CAP): array
{
    $rows = river_search($query, $limit);
    $terms = search_query_terms($query);
    $results = [];

    foreach ($rows as $row) {
        $name = (string) ($row['display_name'] ?? '');
        $state = (string) ($row['state'] ?? '');
        $county = (string) ($row['county'] ?? '');
        $bits = array_filter([$state, $county]);
        $description = 'Stream gauge' . ($bits !== [] ? ' · ' . implode(' · ', $bits) : '');

        $results[] = [
            'kind'        => 'river',
            'title'       => $name,
            'description' => htmlspecialchars($description, ENT_QUOTES, 'UTF-8'),
            'url'         => '/services/rivers/site.php?id=' . rawurlencode((string) $row['site_id']),
            'meta'        => 'River',
            'thumbnail'   => null,
            'score'       => query_matches_text($name, $terms) ? 2.0 : 1.3,
        ];
    }

    return $results;
}

function search_gallery_photos(string $query, int $limit = SEARCH_PHOTO_CAP): array
{
    $terms = search_query_terms($query);
    if ($terms === []) {
        return [];
    }

    $albums = get_categories('gallery');
    $results = [];

    foreach ($albums as $album) {
        $slug = (string) ($album['slug'] ?? '');
        $albumName = (string) ($album['name'] ?? $slug);
        foreach (get_gallery_photos($slug) as $photo) {
            $path = (string) ($photo['path'] ?? '');
            $title = search_photo_title($path);
            $haystack = $title . ' ' . str_replace(['-', '_'], ' ', pathinfo($path, PATHINFO_FILENAME));
            if (!query_matches_text($haystack, $terms)) {
                continue;
            }

            $results[] = [
                'kind'        => 'photo',
                'title'       => $title,
                'description' => htmlspecialchars('From the ' . $albumName . ' gallery.', ENT_QUOTES, 'UTF-8'),
                'url'         => '/galleries/' . rawurlencode($slug) . '/',
                'meta'        => 'Photo',
                'thumbnail'   => $path !== '' ? $path : null,
                'score'       => query_matches_text($title, $terms) ? 1.8 : 1.2,
            ];

            if (count($results) >= $limit) {
                return $results;
            }
        }
    }

    return $results;
}

function search_global(string $query, int $page, int $perPage, array $options = []): array
{
    $toolGroups = $options['service_tool_groups'] ?? [];
    $scope = strtolower(trim((string) ($options['scope'] ?? '')));
    $all = [];

    if ($scope === 'galleries') {
        $all = array_merge(
            search_galleries($query),
            search_gallery_photos($query)
        );
    } else {
        foreach (search_articles($query, SEARCH_ARTICLE_CAP, 0) as $row) {
            $all[] = map_article_to_search_result($row, $query);
        }

        $all = array_merge(
            $all,
            search_galleries($query),
            search_gallery_photos($query),
            search_tools($query, is_array($toolGroups) ? $toolGroups : []),
            search_rivers_global($query)
        );
    }

    usort($all, 'search_result_sort');

    $total = count($all);
    $totalPages = max(1, (int) ceil(max(1, $total) / $perPage));
    if ($total === 0) {
        $totalPages = 1;
    }
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;
    $results = array_slice($all, $offset, $perPage);

    return [
        'results'     => $results,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => $totalPages,
        'has_results' => $total > 0,
    ];
}

function search_articles_scope(
    string $query,
    int $page,
    int $perPage,
    ?string $categorySlug = null
): array {
    $offset = max(0, ($page - 1) * $perPage);
    $articleRows = search_articles($query, $perPage, $offset, $categorySlug);
    $totalArticles = count_search_articles($query, $categorySlug);
    $totalPages = max(1, (int) ceil($totalArticles / $perPage));

    $articles = [];
    foreach ($articleRows as $row) {
        $articles[] = map_article_to_search_result($row, $query);
    }

    $categories = search_categories($query, ['article']);

    return [
        'articles'       => $articles,
        'categories'     => $categories,
        'total_articles' => $totalArticles,
        'page'           => min($page, $totalPages),
        'total_pages'    => $totalPages,
        'has_results'    => $articles !== [] || $categories !== [],
    ];
}
