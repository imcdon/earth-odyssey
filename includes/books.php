<?php
/*
 * books.php - Public-domain outdoor library: curated shelf, Gutendex/Gutenberg search, hosted HTML.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ratelimit.php';

const BOOK_SOURCE_GUTENBERG = 'gutenberg';
const BOOK_SEARCH_TTL = 86400;
const BOOK_SEARCH_PER_PAGE = 32;
const BOOK_UA = 'EarthOdyssey-Books/1.0';
const GUTENDEX_BASE = 'https://gutendex.com/books';
const GUTENBERG_BASE = 'https://www.gutenberg.org';

const BOOK_SUBJECTS = [
    'fishing' => 'Fishing',
    'hunting' => 'Hunting',
    'camping' => 'Camping & woodcraft',
    'nature'  => 'Natural history',
    'travel'  => 'Travel & places',
];

const BOOK_SUBJECT_QUERIES = [
    'fishing' => 'fishing angling',
    'hunting' => 'hunting trapping',
    'camping' => 'camping woodcraft',
    'nature'  => 'natural history nature',
    'travel'  => 'travel wilderness mountains',
];

function book_subjects(): array
{
    return BOOK_SUBJECTS;
}

function book_subject_slug(?string $slug): ?string
{
    $slug = strtolower(trim((string) $slug));
    return $slug !== '' && isset(BOOK_SUBJECTS[$slug]) ? $slug : null;
}

function book_subject_labels(string $subjects): array
{
    $out = [];
    foreach (preg_split('/\s*,\s*/', $subjects, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $slug) {
        if (isset(BOOK_SUBJECTS[$slug])) {
            $out[$slug] = BOOK_SUBJECTS[$slug];
        }
    }
    return $out;
}

function book_format_author(string $name): string
{
    $name = trim($name);
    if (preg_match('/^([^,]+),\s*([^,]+)(?:,\s*[\d?.\-–\s]+)?$/', $name, $m) && !preg_match('/^\d/', trim($m[2]))) {
        return trim($m[2] . ' ' . $m[1]);
    }
    return $name;
}

function book_page_url(array $book): string
{
    $slug = trim((string) ($book['slug'] ?? ''));
    if ($slug !== '') {
        return url('books/' . rawurlencode($slug) . '/');
    }
    $gid = preg_replace('/\D+/', '', (string) ($book['source_id'] ?? ''));
    return $gid !== '' ? url('books/g/' . $gid . '/') : url('books/');
}

function book_read_url(array $book): ?string
{
    $slug = trim((string) ($book['slug'] ?? ''));
    if ($slug === '' || !book_is_hosted($book)) {
        return null;
    }
    return url('books/' . rawurlencode($slug) . '/read/');
}

function book_is_hosted(array $book): bool
{
    $path = book_hosted_path($book);
    return $path !== null && is_file($path);
}

function book_hosted_path(array $book): ?string
{
    $rel = str_replace('\\', '/', (string) ($book['html_path'] ?? ''));
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    $full = str_replace('\\', '/', dirname(__DIR__) . '/' . ltrim($rel, '/'));
    $root = str_replace('\\', '/', dirname(__DIR__) . '/media/books/');
    if (!str_starts_with($full, $root)) {
        return null;
    }
    return $full;
}

function book_cover_url(array $book): string
{
    $path = trim((string) ($book['cover_path'] ?? ''));
    if ($path !== '' && preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path !== '' && is_file(asset_path($path))) {
        return url($path);
    }
    $gid = preg_replace('/\D+/', '', (string) ($book['source_id'] ?? ''));
    if (($book['source'] ?? '') === BOOK_SOURCE_GUTENBERG && $gid !== '') {
        return GUTENBERG_BASE . '/cache/epub/' . $gid . '/pg' . $gid . '.cover.medium.jpg';
    }
    return '';
}

function book_gutenberg_url(array $book): string
{
    $url = trim((string) ($book['source_url'] ?? ''));
    if ($url !== '') {
        return $url;
    }
    $gid = preg_replace('/\D+/', '', (string) ($book['source_id'] ?? ''));
    return $gid !== '' ? GUTENBERG_BASE . '/ebooks/' . $gid : '';
}

function book_epub_url(array $book): string
{
    $path = trim((string) ($book['epub_path'] ?? ''));
    if ($path !== '' && preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path !== '' && is_file(asset_path($path))) {
        return url($path);
    }
    $gid = preg_replace('/\D+/', '', (string) ($book['source_id'] ?? ''));
    if (($book['source'] ?? '') === BOOK_SOURCE_GUTENBERG && $gid !== '') {
        return GUTENBERG_BASE . '/ebooks/' . $gid . '.epub.images';
    }
    return '';
}

function get_featured_books(?string $subject = null): array
{
    try {
        $pdo = get_db();
        $sql = 'SELECT * FROM books WHERE is_featured = 1';
        $params = [];
        if ($subject) {
            $sql .= ' AND FIND_IN_SET(?, REPLACE(subjects, " ", ""))';
            $params[] = $subject;
        }
        $sql .= ' ORDER BY sort_order ASC, title ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function get_book_by_slug(string $slug): ?array
{
    $slug = strtolower(trim($slug));
    if (!preg_match('/^[a-z0-9-]{1,120}$/', $slug)) {
        return null;
    }
    try {
        $stmt = get_db()->prepare('SELECT * FROM books WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function get_book_by_gutenberg_id(string $id): ?array
{
    $id = preg_replace('/\D+/', '', $id);
    if ($id === '') {
        return null;
    }
    try {
        $stmt = get_db()->prepare('SELECT * FROM books WHERE source = ? AND source_id = ? LIMIT 1');
        $stmt->execute([BOOK_SOURCE_GUTENBERG, $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function book_hub_url(?string $subject = null, ?string $q = null, int $page = 1): string
{
    $params = [];
    if ($q) {
        $params['q'] = $q;
    }
    if ($subject) {
        $params['subject'] = $subject;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }
    return url('books/') . ($params ? '?' . http_build_query($params) : '');
}

function book_catalog_search(string $query, ?string $subject, int $page): array
{
    $page = max(1, $page);
    $query = trim($query);
    $subjectQuery = $subject && isset(BOOK_SUBJECT_QUERIES[$subject]) ? BOOK_SUBJECT_QUERIES[$subject] : '';
    $search = trim($query . ' ' . $subjectQuery);
    if ($search === '') {
        return ['ok' => true, 'stale' => false, 'count' => 0, 'page' => $page, 'pages' => 1, 'results' => [], 'source' => ''];
    }

    $key = sha1(json_encode(['q' => $search, 'p' => $page], JSON_UNESCAPED_UNICODE));
    $cached = book_cache_get($key);
    if ($cached && (time() - $cached['fetched_at']) < BOOK_SEARCH_TTL) {
        $cached['payload']['stale'] = false;
        $cached['payload']['ok'] = true;
        return $cached['payload'];
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $allowLive = PHP_SAPI === 'cli' || rate_limit_allow('book-search:' . $ip, 40, 3600);

    $fresh = null;
    if ($allowLive) {
        if (!book_provider_skipped('gutendex')) {
            $fresh = book_search_gutendex($search, $page);
            if ($fresh === null) {
                book_provider_mark_down('gutendex');
            }
        }
        if ($fresh === null) {
            $fresh = book_search_gutenberg_opds($search, $page);
        }
    }

    if ($fresh !== null) {
        book_cache_put($key, $fresh);
        $fresh['stale'] = false;
        $fresh['ok'] = true;
        return $fresh;
    }

    if ($cached) {
        $cached['payload']['stale'] = true;
        $cached['payload']['ok'] = true;
        return $cached['payload'];
    }

    return [
        'ok' => false,
        'stale' => false,
        'count' => 0,
        'page' => $page,
        'pages' => 1,
        'results' => [],
        'source' => '',
        'error' => $allowLive
            ? 'The public-domain catalog is unavailable right now. Try again in a little while.'
            : 'Too many catalog searches. Please wait a minute and try again.',
    ];
}

function book_from_gutenberg(string $id): ?array
{
    $id = preg_replace('/\D+/', '', $id);
    if ($id === '') {
        return null;
    }

    $local = get_book_by_gutenberg_id($id);
    if ($local) {
        return $local;
    }

    $key = sha1('book:' . $id);
    $cached = book_cache_get($key);
    if ($cached && (time() - $cached['fetched_at']) < BOOK_SEARCH_TTL) {
        return $cached['payload']['book'] ?? null;
    }

    $book = book_fetch_gutendex_book($id);
    if (!$book) {
        $book = book_fetch_gutenberg_page($id);
    }
    if ($book) {
        book_cache_put($key, ['book' => $book]);
    } elseif ($cached) {
        return $cached['payload']['book'] ?? null;
    }
    return $book;
}

function book_search_gutendex(string $search, int $page): ?array
{
    $url = GUTENDEX_BASE . '?' . http_build_query([
        'copyright' => 'false',
        'languages' => 'en',
        'search'    => $search,
        'page'      => $page,
    ], '', '&', PHP_QUERY_RFC3986);

    $res = book_http_get($url, 6);
    if ($res['status'] !== 200) {
        return null;
    }
    $json = json_decode($res['body'], true);
    if (!is_array($json) || !isset($json['results']) || !is_array($json['results'])) {
        return null;
    }

    $count = (int) ($json['count'] ?? count($json['results']));
    $results = [];
    foreach ($json['results'] as $row) {
        $mapped = book_map_gutendex($row);
        if ($mapped) {
            $results[] = $mapped;
        }
    }

    return [
        'count'   => $count,
        'page'    => $page,
        'pages'   => max(1, (int) ceil($count / BOOK_SEARCH_PER_PAGE)),
        'results' => $results,
        'source'  => 'gutendex',
    ];
}

function book_fetch_gutendex_book(string $id): ?array
{
    $res = book_http_get(GUTENDEX_BASE . '/' . $id, 6);
    if ($res['status'] !== 200) {
        return null;
    }
    $json = json_decode($res['body'], true);
    return is_array($json) ? book_map_gutendex($json) : null;
}

function book_map_gutendex(array $row): ?array
{
    $id = (string) ($row['id'] ?? '');
    if ($id === '' || ($row['copyright'] ?? null) === true) {
        return null;
    }
    $langs = $row['languages'] ?? [];
    if (is_array($langs) && $langs && !in_array('en', $langs, true)) {
        return null;
    }

    $authors = [];
    foreach ($row['authors'] ?? [] as $author) {
        $name = book_format_author((string) ($author['name'] ?? ''));
        if ($name !== '') {
            $authors[] = $name;
        }
    }

    $formats = is_array($row['formats'] ?? null) ? $row['formats'] : [];
    $cover = (string) ($formats['image/jpeg'] ?? '');
    $html = (string) ($formats['text/html'] ?? $formats['text/html; charset=utf-8'] ?? '');
    $epub = (string) ($formats['application/epub+zip'] ?? '');
    $summaries = $row['summaries'] ?? [];
    $blurb = is_array($summaries) ? (string) ($summaries[0] ?? '') : '';
    if (mb_strlen($blurb) > 420) {
        $blurb = rtrim(mb_substr($blurb, 0, 417)) . '…';
    }

    $local = get_book_by_gutenberg_id($id);

    return [
        'id'         => $local['id'] ?? null,
        'source'     => BOOK_SOURCE_GUTENBERG,
        'source_id'  => $id,
        'slug'       => $local['slug'] ?? '',
        'title'      => (string) ($row['title'] ?? 'Untitled'),
        'author'     => $local['author'] ?? implode(', ', $authors),
        'year'       => $local['year'] ?? null,
        'subjects'   => $local['subjects'] ?? '',
        'blurb'      => $local['blurb'] ?? $blurb,
        'cover_path' => $local['cover_path'] ?? $cover,
        'html_path'  => $local['html_path'] ?? null,
        'epub_path'  => $local['epub_path'] ?? ($epub !== '' ? $epub : null),
        'source_url' => $local['source_url'] ?? (GUTENBERG_BASE . '/ebooks/' . $id),
        'is_featured'=> (int) ($local['is_featured'] ?? 0),
        'html_remote'=> $html,
    ];
}

function book_search_gutenberg_opds(string $search, int $page): ?array
{
    $start = ($page - 1) * 25 + 1;
    $url = GUTENBERG_BASE . '/ebooks/search.opds/?' . http_build_query([
        'query' => $search,
        'start_index' => $start,
    ], '', '&', PHP_QUERY_RFC3986);

    $res = book_http_get($url, 10);
    if ($res['status'] !== 200 || $res['body'] === '') {
        return null;
    }

    $results = [];
    if (preg_match_all('#<entry>(.*?)</entry>#s', $res['body'], $blocks)) {
        foreach ($blocks[1] as $block) {
            if (!preg_match('#<id>https?://www\.gutenberg\.org/ebooks/(\d+)\.opds</id>#i', $block, $im)) {
                continue;
            }
            $id = $im[1];
            $title = '';
            if (preg_match('#<title>([^<]+)</title>#', $block, $tm)) {
                $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
            }
            $author = '';
            if (preg_match('#<content[^>]*>([^<]+)</content>#', $block, $cm)) {
                $author = book_format_author(html_entity_decode(trim($cm[1]), ENT_QUOTES, 'UTF-8'));
            }
            $local = get_book_by_gutenberg_id($id);
            $results[] = [
                'id'         => $local['id'] ?? null,
                'source'     => BOOK_SOURCE_GUTENBERG,
                'source_id'  => $id,
                'slug'       => $local['slug'] ?? '',
                'title'      => $local['title'] ?? ($title !== '' ? $title : 'Untitled'),
                'author'     => $local['author'] ?? $author,
                'year'       => $local['year'] ?? null,
                'subjects'   => $local['subjects'] ?? '',
                'blurb'      => $local['blurb'] ?? '',
                'cover_path' => $local['cover_path'] ?? '',
                'html_path'  => $local['html_path'] ?? null,
                'epub_path'  => $local['epub_path'] ?? null,
                'source_url' => $local['source_url'] ?? (GUTENBERG_BASE . '/ebooks/' . $id),
                'is_featured'=> (int) ($local['is_featured'] ?? 0),
            ];
        }
    }

    if (!$results) {
        return null;
    }

    $hasNext = (bool) preg_match('#rel="next"#', $res['body']);
    $count = (($page - 1) * 25) + count($results) + ($hasNext ? 25 : 0);

    return [
        'count'   => $count,
        'page'    => $page,
        'pages'   => $hasNext ? $page + 1 : $page,
        'results' => $results,
        'source'  => 'gutenberg',
    ];
}

function book_fetch_gutenberg_page(string $id): ?array
{
    $res = book_http_get(GUTENBERG_BASE . '/ebooks/' . $id, 10);
    if ($res['status'] !== 200) {
        return null;
    }
    $title = '';
    if (preg_match('/<title>([^<]+)<\/title>/i', $res['body'], $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        $title = trim((string) preg_replace('/\s*\|\s*Project Gutenberg.*$/i', '', $title));
        $title = trim((string) preg_replace('/\s+by\s+.+$/i', '', $title));
    }
    $author = '';
    if (preg_match('/itemprop="creator"[^>]*>\s*([^<]+)/i', $res['body'], $m)) {
        $author = book_format_author(html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8'));
    } elseif (preg_match('/<title>[^<]*\sby\s([^|<]+)/i', $res['body'], $m)) {
        $author = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    if ($title === '') {
        return null;
    }
    return [
        'id'         => null,
        'source'     => BOOK_SOURCE_GUTENBERG,
        'source_id'  => $id,
        'slug'       => '',
        'title'      => $title,
        'author'     => $author,
        'year'       => null,
        'subjects'   => '',
        'blurb'      => '',
        'cover_path' => '',
        'html_path'  => null,
        'epub_path'  => null,
        'source_url' => GUTENBERG_BASE . '/ebooks/' . $id,
        'is_featured'=> 0,
    ];
}

function book_cache_get(string $key): ?array
{
    try {
        $stmt = get_db()->prepare('SELECT payload, fetched_at FROM book_search_cache WHERE cache_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    $payload = json_decode((string) $row['payload'], true);
    if (!is_array($payload)) {
        return null;
    }
    return [
        'payload'    => $payload,
        'fetched_at' => strtotime((string) $row['fetched_at']) ?: 0,
    ];
}

function book_cache_put(string $key, array $payload): void
{
    try {
        $stmt = get_db()->prepare(
            'INSERT INTO book_search_cache (cache_key, payload, fetched_at)
             VALUES (?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE payload = VALUES(payload), fetched_at = VALUES(fetched_at)'
        );
        $stmt->execute([$key, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    } catch (Throwable $e) {
        // Cache is optional.
    }
}

function book_provider_skipped(string $name): bool
{
    $file = dirname(__DIR__) . '/storage/books/' . $name . '.down';
    return is_file($file) && (time() - (int) filemtime($file) < 900);
}

function book_provider_mark_down(string $name): void
{
    $dir = dirname(__DIR__) . '/storage/books';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/' . $name . '.down', (string) time());
}

function book_http_get(string $url, int $timeout = 10): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => BOOK_UA,
        CURLOPT_HTTPHEADER     => ['Accept: application/atom+xml,application/json,text/html;q=0.9,*/*;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'body' => is_string($body) ? $body : ''];
}

function book_http_download(string $url, string $dest, int $timeout = 90): bool
{
    $dir = dirname($dest);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    $tmp = $dest . '.' . getmypid() . '.tmp';
    $fp = @fopen($tmp, 'wb');
    if (!$fp) {
        return false;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => BOOK_UA,
    ]);
    $ok = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    if ($ok && $status === 200 && filesize($tmp) > 64) {
        return @rename($tmp, $dest);
    }
    @unlink($tmp);
    return false;
}

function book_prepare_html(string $html, string $gutenbergId): string
{
    $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
    $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html) ?? $html;
    $html = preg_replace('#<object\b[^>]*>.*?</object>#is', '', $html) ?? $html;
    $html = preg_replace('#<form\b[^>]*>.*?</form>#is', '', $html) ?? $html;
    $html = preg_replace('#<link\b[^>]*>#is', '', $html) ?? $html;
    $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;

    if (preg_match('#<body[^>]*>(.*)</body>#is', $html, $m)) {
        $html = $m[1];
    }

    $base = GUTENBERG_BASE . '/cache/epub/' . $gutenbergId . '/';
    $html = preg_replace_callback(
        '#\s(href|src)=([\'"])([^\'"]+)\2#i',
        static function (array $m) use ($base): string {
            $url = book_absolutize_url($m[3], $base);
            if (preg_match('#^\s*javascript:#i', $url)) {
                return '';
            }
            return ' ' . $m[1] . '=' . $m[2] . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $m[2];
        },
        $html
    ) ?? $html;

    return preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
}

function book_absolutize_url(string $url, string $base): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if ($url === '' || isset($url[0]) && $url[0] === '#' || str_starts_with($url, 'mailto:') || str_starts_with($url, 'data:')) {
        return $url;
    }
    if (preg_match('#^(https?:)?//#i', $url)) {
        return $url;
    }
    if (isset($url[0]) && $url[0] === '/') {
        return GUTENBERG_BASE . $url;
    }
    return $base . ltrim($url, './');
}

function book_upsert(array $book): void
{
    $sql = 'INSERT INTO books (
                source, source_id, slug, title, author, year, subjects, blurb,
                cover_path, html_path, epub_path, source_url, is_featured, sort_order
            ) VALUES (
                :source, :source_id, :slug, :title, :author, :year, :subjects, :blurb,
                :cover_path, :html_path, :epub_path, :source_url, :is_featured, :sort_order
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                author = VALUES(author),
                year = VALUES(year),
                subjects = VALUES(subjects),
                blurb = VALUES(blurb),
                cover_path = VALUES(cover_path),
                html_path = VALUES(html_path),
                epub_path = VALUES(epub_path),
                source_url = VALUES(source_url),
                is_featured = VALUES(is_featured),
                sort_order = VALUES(sort_order)';
    get_db()->prepare($sql)->execute([
        ':source'      => $book['source'],
        ':source_id'   => $book['source_id'],
        ':slug'        => $book['slug'],
        ':title'       => $book['title'],
        ':author'      => $book['author'],
        ':year'        => $book['year'],
        ':subjects'    => $book['subjects'],
        ':blurb'       => $book['blurb'],
        ':cover_path'  => $book['cover_path'] ?? '',
        ':html_path'   => $book['html_path'] ?? null,
        ':epub_path'   => $book['epub_path'] ?? null,
        ':source_url'  => $book['source_url'],
        ':is_featured' => (int) ($book['is_featured'] ?? 0),
        ':sort_order'  => (int) ($book['sort_order'] ?? 0),
    ]);
}

function book_download_featured(array $book): array
{
    $gid = preg_replace('/\D+/', '', (string) $book['source_id']);
    $slug = $book['slug'];
    $dirRel = 'media/books/' . $slug;
    $dir = dirname(__DIR__) . '/' . $dirRel;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $coverRel = $dirRel . '/cover.jpg';
    $htmlRel = $dirRel . '/book.html';
    $coverFile = dirname(__DIR__) . '/' . $coverRel;
    $htmlFile = dirname(__DIR__) . '/' . $htmlRel;

    if (!is_file($coverFile)) {
        $coverUrls = [
            GUTENBERG_BASE . '/cache/epub/' . $gid . '/pg' . $gid . '.cover.medium.jpg',
            GUTENBERG_BASE . '/cache/epub/' . $gid . '/pg' . $gid . '.cover.small.jpg',
        ];
        foreach ($coverUrls as $url) {
            if (book_http_download($url, $coverFile, 30)) {
                $book['cover_path'] = $coverRel;
                break;
            }
        }
    } else {
        $book['cover_path'] = $coverRel;
    }

    if (!is_file($htmlFile) || filesize($htmlFile) < 200) {
        $htmlUrls = [
            GUTENBERG_BASE . '/cache/epub/' . $gid . '/pg' . $gid . '-images.html',
            GUTENBERG_BASE . '/cache/epub/' . $gid . '/pg' . $gid . '.html',
            GUTENBERG_BASE . '/files/' . $gid . '/' . $gid . '-h/' . $gid . '-h.htm',
        ];
        $raw = '';
        foreach ($htmlUrls as $url) {
            $res = book_http_get($url, 90);
            if ($res['status'] === 200 && strlen($res['body']) > 200) {
                $raw = $res['body'];
                break;
            }
        }
        if ($raw !== '') {
            $prepared = book_prepare_html($raw, $gid);
            if (@file_put_contents($htmlFile, $prepared) !== false) {
                $book['html_path'] = $htmlRel;
            }
        }
    } else {
        $book['html_path'] = $htmlRel;
    }

    return $book;
}
