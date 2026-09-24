<?php
/*
 * usgs.php - USGS Water Data (OGC API v1) client for the river tool.
 * Parallel fetches (curl_multi), a file cache that serves stale data when USGS fails, and a usage log.
 */
require_once __DIR__ . '/db.php';

const USGS_BASE = 'https://api.waterdata.usgs.gov/ogcapi/v1/collections/';
const USGS_MAX_PAGES = 10;

const USGS_PARAMS = [
    '00060' => ['label' => 'Discharge', 'unit' => 'cfs'],
    '00065' => ['label' => 'Gage height', 'unit' => 'ft'],
    '00010' => ['label' => 'Water temperature', 'unit' => 'degC'],
    '63680' => ['label' => 'Turbidity', 'unit' => 'FNU'],
    '00300' => ['label' => 'Dissolved oxygen', 'unit' => 'mg/L'],
];

function usgs_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/usgs.config.php';
        $cfg = (is_file($file) ? require $file : []) + ['api_key' => '', 'river_states' => []];
    }
    return $cfg;
}

function usgs_storage_dir(string $sub): string
{
    $dir = dirname(__DIR__) . '/storage/' . $sub;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function usgs_valid_site_id(string $id): bool
{
    return (bool) preg_match('/^[A-Z]{2,10}-[0-9A-Za-z]{4,20}$/', $id);
}

function usgs_url(string $collection, array $params): string
{
    $params += ['f' => 'json', 'skipGeometry' => 'true'];
    ksort($params);
    return USGS_BASE . $collection . '/items?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/*
 * Fetch several collections at once. $requests: [key => ['collection' => , 'params' => [], 'ttl' => seconds]].
 * Returns [key => ['ok' => bool, 'stale' => bool, 'fetched_at' => ?int, 'rows' => array]].
 * Fresh cache is served without a network call; on USGS failure the last cached copy is served as stale.
 */
function usgs_get_many(array $requests): array
{
    $results = [];
    $pending = [];
    $log = [];

    foreach ($requests as $key => $req) {
        $url = usgs_url($req['collection'], $req['params'] ?? []);
        $cached = usgs_cache_read($url);
        if ($cached && time() - $cached['fetched_at'] < ($req['ttl'] ?? 900)) {
            $results[$key] = ['ok' => true, 'stale' => false, 'fetched_at' => $cached['fetched_at'], 'rows' => $cached['rows']];
            $log[] = [$req['collection'], 0, 1, 0, 0, 0];
            continue;
        }
        $pending[$key] = ['url' => $url, 'collection' => $req['collection'], 'cached' => $cached];
    }

    $responses = $pending ? usgs_http_parallel(array_map(fn($p) => $p['url'], $pending)) : [];

    foreach ($pending as $key => $p) {
        $res = $responses[$key];
        $rows = null;
        $ms = $res['ms'];
        $bytes = $res['bytes'];
        $status = $res['status'];

        $json = $status === 200 ? json_decode($res['body'], true) : null;
        if (is_array($json)) {
            $rows = usgs_rows($json, $p['collection']);
            $next = usgs_next_link($json);
            for ($page = 1; $next && $page < USGS_MAX_PAGES; $page++) {
                $more = usgs_http_parallel([$next])[0];
                $ms += $more['ms'];
                $bytes += $more['bytes'];
                $moreJson = $more['status'] === 200 ? json_decode($more['body'], true) : null;
                if (!is_array($moreJson)) {
                    $status = $more['status'];
                    $rows = null;
                    break;
                }
                array_push($rows, ...usgs_rows($moreJson, $p['collection']));
                $next = usgs_next_link($moreJson);
            }
        }

        if ($rows !== null) {
            usgs_cache_write($p['url'], $rows);
            $results[$key] = ['ok' => true, 'stale' => false, 'fetched_at' => time(), 'rows' => $rows];
            $log[] = [$p['collection'], $status, 0, 0, $ms, $bytes];
        } elseif ($p['cached']) {
            $results[$key] = ['ok' => true, 'stale' => true, 'fetched_at' => $p['cached']['fetched_at'], 'rows' => $p['cached']['rows']];
            $log[] = [$p['collection'], $status, 0, 1, $ms, $bytes];
        } else {
            $results[$key] = ['ok' => false, 'stale' => false, 'fetched_at' => null, 'rows' => []];
            $log[] = [$p['collection'], $status, 0, 0, $ms, $bytes];
        }
    }

    usgs_log($log);
    return $results;
}

function usgs_get(string $collection, array $params, int $ttl): array
{
    return usgs_get_many(['r' => ['collection' => $collection, 'params' => $params, 'ttl' => $ttl]])['r'];
}

/* [key => url] -> [key => ['status', 'body', 'ms', 'bytes']], all requests in flight at once. */
function usgs_http_parallel(array $urls): array
{
    $apiKey = (string) usgs_config()['api_key'];
    $headers = ['Accept: application/geo+json'];
    if ($apiKey !== '') {
        $headers[] = 'X-Api-Key: ' . $apiKey;
    }

    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $k => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'EarthOdyssey-RiverTool/1.0',
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$k] = $ch;
    }

    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    $out = [];
    foreach ($handles as $k => $ch) {
        $out[$k] = [
            'status' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body'   => (string) curl_multi_getcontent($ch),
            'ms'     => (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
            'bytes'  => (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);

    return $out;
}

/* Flatten GeoJSON features to property rows; feature ids are kept only where they mean something. */
function usgs_rows(array $json, string $collection): array
{
    $keepId = in_array($collection, ['monitoring-locations', 'time-series-metadata'], true);
    $rows = [];
    foreach ($json['features'] ?? [] as $f) {
        $row = $f['properties'] ?? [];
        if ($keepId) {
            $row['_id'] = $f['id'] ?? null;
        }
        if (($f['geometry']['type'] ?? '') === 'Point') {
            [$row['_lon'], $row['_lat']] = $f['geometry']['coordinates'];
        }
        $rows[] = $row;
    }
    return $rows;
}

function usgs_next_link(array $json): ?string
{
    foreach ($json['links'] ?? [] as $link) {
        $href = (string) ($link['href'] ?? '');
        if (($link['rel'] ?? '') === 'next' && str_starts_with($href, USGS_BASE)) {
            return $href;
        }
    }
    return null;
}

function usgs_cache_file(string $url): string
{
    return usgs_storage_dir('cache/usgs') . '/' . sha1($url) . '.json';
}

function usgs_cache_read(string $url): ?array
{
    $file = usgs_cache_file($url);
    if (!is_file($file)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) && isset($data['fetched_at'], $data['rows']) ? $data : null;
}

/* Written atomically and never deleted on expiry, so a stale copy is always there as a fallback. */
function usgs_cache_write(string $url, array $rows): void
{
    $file = usgs_cache_file($url);
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode(['fetched_at' => time(), 'rows' => $rows])) !== false) {
        @rename($tmp, $file);
    }
    @unlink($tmp);
}

/* $entries: [[endpoint, status, cache_hit, stale, ms, bytes], ...]. Logging must never break a page. */
function usgs_log(array $entries): void
{
    if (!$entries) {
        return;
    }
    try {
        $db = get_db();
        $sql = 'INSERT INTO river_api_log (endpoint, status, cache_hit, stale, ms, bytes) VALUES '
            . implode(',', array_fill(0, count($entries), '(?,?,?,?,?,?)'));
        $db->prepare($sql)->execute(array_merge(...$entries));
        if (random_int(1, 200) === 1) {
            $db->exec('DELETE FROM river_api_log WHERE created_at < NOW() - INTERVAL 14 DAY');
        }
    } catch (Throwable $e) {
        error_log('river_api_log insert failed: ' . $e->getMessage());
    }
}

/* Latest reading per tracked measurement for one site (15-minute cache). */
function usgs_latest_for_site(string $siteId): array
{
    $r = usgs_get('latest-continuous', [
        'monitoring_location_id' => $siteId,
        'parameter_code'         => implode(',', array_keys(USGS_PARAMS)),
        'properties'             => 'parameter_code,time,value,unit_of_measure',
        'limit'                  => 50,
    ], 900);

    $found = [];
    foreach ($r['rows'] as $row) {
        $code = (string) ($row['parameter_code'] ?? '');
        if (!isset(USGS_PARAMS[$code]) || !is_numeric($row['value'] ?? null)) {
            continue;
        }
        if (isset($found[$code]) && strcmp($found[$code]['time'], (string) $row['time']) >= 0) {
            continue;
        }
        $found[$code] = [
            'label' => USGS_PARAMS[$code]['label'],
            'value' => (float) $row['value'],
            'unit'  => USGS_PARAMS[$code]['unit'],
            'time'  => (string) $row['time'],
        ];
    }

    $readings = [];
    foreach (array_keys(USGS_PARAMS) as $code) {
        if (isset($found[$code])) {
            $readings[$code] = $found[$code];
        }
    }

    return [
        'ok'       => $r['ok'],
        'stale'    => $r['stale'],
        'as_of'    => $r['fetched_at'] ? gmdate('c', $r['fetched_at']) : null,
        'readings' => $readings,
    ];
}
