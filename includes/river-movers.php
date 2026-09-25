<?php
/*
 * river-movers.php - Hourly "biggest changes" rankings per state, stored in river_movers and read by browse.php.
 * 24 hours: latest reading vs our own hourly snapshot from ~24 hours earlier.
 * 1 week:   latest reading vs the USGS daily mean from 7 days earlier.
 * USGS cost per state per run: 1 latest-values request, a daily-means request every 6 hours, and a one-time
 * 26-hour backfill whenever snapshot history is missing (first run, or after the cron was down).
 */
require_once __DIR__ . '/rivers.php';

/* How each measurement is ranked: percent change, or absolute change in native units. */
const RIVER_MOVER_PARAMS = ['00060' => 'pct', '00010' => 'abs', '00065' => 'abs'];
/* Smallest change worth ranking: 1% flow, 0.2 °C, 0.1 ft. Filters sensor noise and near-still lakes. */
const RIVER_MOVER_MIN_CHANGE = ['00060' => 1.0, '00010' => 0.2, '00065' => 0.1];
const RIVER_MOVER_WINDOWS = ['24h' => '24 hours', '1w' => '1 week'];
const RIVER_MOVER_LIMIT = 10;
const RIVER_MOVER_MIN_CFS = 50;
const RIVER_MOVER_FRESH = 10800;
const RIVER_MOVER_MATCH = 5400;
const RIVER_SNAPSHOT_KEEP_DAYS = 8;
const RIVER_PRELOAD_TOP = 10;

function river_movers_run(string $state): array
{
    $fips = STATE_FIPS[$state] ?? null;
    if (!$fips) {
        return ['ok' => false, 'state' => $state, 'error' => 'Unknown state code'];
    }

    $db = get_db();
    $stmt = $db->prepare('SELECT site_id FROM river_sites WHERE state = ?');
    $stmt->execute([$state]);
    $known = array_fill_keys($stmt->fetchAll(PDO::FETCH_COLUMN), true);
    if (!$known) {
        return ['ok' => false, 'state' => $state, 'error' => 'No gauges saved yet; run cron/river-sites-sync.php first'];
    }

    $stmt = $db->prepare(
        'SELECT MIN(s.observed_at) FROM river_snapshots s JOIN river_sites r ON r.site_id = s.site_id
         WHERE r.state = ? AND s.observed_at >= UTC_TIMESTAMP() - INTERVAL 30 HOUR'
    );
    $stmt->execute([$state]);
    $oldest = $stmt->fetchColumn();
    $backfill = !$oldest || strtotime($oldest . ' UTC') > time() - 23 * 3600;

    $codes = implode(',', array_keys(RIVER_MOVER_PARAMS));
    $fields = 'monitoring_location_id,parameter_code,time,value';
    $requests = [
        'latest' => ['collection' => 'latest-continuous', 'ttl' => 600, 'params' => [
            'state_code' => $fips, 'parameter_code' => $codes, 'properties' => $fields, 'limit' => 10000,
        ]],
        'daily' => ['collection' => 'daily', 'ttl' => 21600, 'params' => [
            'state_code' => $fips, 'parameter_code' => $codes, 'statistic_id' => '00003',
            'time' => gmdate('Y-m-d', strtotime('-8 days')) . '/' . gmdate('Y-m-d'),
            'properties' => $fields, 'limit' => 10000,
        ]],
    ];
    if ($backfill) {
        $requests['backfill'] = ['collection' => 'continuous', 'ttl' => 0, 'cache' => false, 'params' => [
            'state_code' => $fips, 'parameter_code' => $codes, 'time' => 'PT26H', 'properties' => $fields, 'limit' => 10000,
        ]];
    }
    $r = usgs_get_many($requests);

    if (!$r['latest']['ok'] || $r['latest']['stale']) {
        return ['ok' => false, 'state' => $state, 'error' => 'USGS latest values unavailable'];
    }

    $latest = river_movers_points($r['latest']['rows'], $known);
    $snapshots = [];
    foreach ($latest as $id => $byCode) {
        foreach ($byCode as $code => $pts) {
            $snapshots[] = [$id, $code, end($pts)];
        }
    }
    if ($backfill && $r['backfill']['ok']) {
        foreach (river_movers_points($r['backfill']['rows'], $known) as $id => $byCode) {
            foreach ($byCode as $code => $pts) {
                $hours = [];
                foreach ($pts as $p) {
                    $hours[intdiv($p[0], 3600)] ??= $p;
                }
                foreach ($hours as $p) {
                    $snapshots[] = [$id, $code, $p];
                }
            }
        }
    }
    $inserted = river_movers_save_snapshots($snapshots);

    $stmt = $db->prepare(
        'SELECT s.site_id, s.param, s.observed_at, s.value FROM river_snapshots s JOIN river_sites r ON r.site_id = s.site_id
         WHERE r.state = ? AND s.observed_at >= UTC_TIMESTAMP() - INTERVAL 27 HOUR ORDER BY s.observed_at'
    );
    $stmt->execute([$state]);
    $history = [];
    foreach ($stmt->fetchAll() as $row) {
        $history[$row['site_id']][$row['param']][] = [strtotime($row['observed_at'] . ' UTC'), (float) $row['value']];
    }

    $daily = [];
    if ($r['daily']['ok']) {
        foreach ($r['daily']['rows'] as $row) {
            $id = (string) ($row['monitoring_location_id'] ?? '');
            $code = (string) ($row['parameter_code'] ?? '');
            if (isset($known[$id], RIVER_MOVER_PARAMS[$code]) && is_numeric($row['value'] ?? null)) {
                $daily[$id][$code][substr((string) $row['time'], 0, 10)] = (float) $row['value'];
            }
        }
    }

    // For gauges without daily means (most gage heights): every-6-hour snapshots for the week, plus all of them near 7 days ago.
    $stmt = $db->prepare(
        'SELECT s.site_id, s.param, s.observed_at, s.value FROM river_snapshots s JOIN river_sites r ON r.site_id = s.site_id
         WHERE r.state = ? AND s.observed_at >= UTC_TIMESTAMP() - INTERVAL 171 HOUR
           AND (HOUR(s.observed_at) % 6 = 0 OR s.observed_at < UTC_TIMESTAMP() - INTERVAL 166 HOUR)
         ORDER BY s.observed_at'
    );
    $stmt->execute([$state]);
    $week = [];
    foreach ($stmt->fetchAll() as $row) {
        $week[$row['site_id']][$row['param']][] = [strtotime($row['observed_at'] . ' UTC'), (float) $row['value']];
    }

    $fresh = time() - RIVER_MOVER_FRESH;
    $candidates = ['24h' => [], '1w' => []];
    foreach ($latest as $id => $byCode) {
        foreach ($byCode as $code => $pts) {
            [$tNow, $vNow] = end($pts);
            if ($tNow < $fresh) {
                continue;
            }
            if ($c = river_movers_vs_snapshot($history[$id][$code] ?? [], $tNow, $vNow, 86400, 3600)) {
                $candidates['24h'][$code][] = ['site_id' => $id] + $c;
            }
            $c = river_movers_1w($daily[$id][$code] ?? [], $tNow, $vNow)
                ?? river_movers_vs_snapshot($week[$id][$code] ?? [], $tNow, $vNow, 7 * 86400, 21600);
            if ($c) {
                $candidates['1w'][$code][] = ['site_id' => $id] + $c;
            }
        }
    }

    $windows = $r['daily']['ok'] ? ['24h', '1w'] : ['24h'];
    $saved = river_movers_save($state, $windows, $candidates);

    $db->exec('DELETE FROM river_snapshots WHERE observed_at < UTC_TIMESTAMP() - INTERVAL ' . RIVER_SNAPSHOT_KEEP_DAYS . ' DAY');

    return [
        'ok' => true, 'state' => $state, 'snapshots' => $inserted, 'backfilled' => $backfill && $r['backfill']['ok'],
        'ranked' => $saved, 'weekly_skipped' => !$r['daily']['ok'], 'requests' => count($requests),
    ];
}

/* USGS rows -> [site_id][param] => [[unix, value], ...] in time order, known gauges only. */
function river_movers_points(array $rows, array $known): array
{
    $out = [];
    foreach ($rows as $row) {
        $id = (string) ($row['monitoring_location_id'] ?? '');
        $code = (string) ($row['parameter_code'] ?? '');
        $t = strtotime((string) ($row['time'] ?? ''));
        if ($t && isset($known[$id], RIVER_MOVER_PARAMS[$code]) && is_numeric($row['value'] ?? null)) {
            $out[$id][$code][] = [$t, (float) $row['value']];
        }
    }
    foreach ($out as $id => $byCode) {
        foreach ($byCode as $code => $pts) {
            usort($pts, fn($a, $b) => $a[0] <=> $b[0]);
            $out[$id][$code] = $pts;
        }
    }
    return $out;
}

function river_movers_save_snapshots(array $snapshots): int
{
    $inserted = 0;
    foreach (array_chunk($snapshots, 500) as $chunk) {
        $args = [];
        foreach ($chunk as [$id, $code, [$t, $v]]) {
            array_push($args, $id, $code, gmdate('Y-m-d H:i:s', $t), $v);
        }
        $stmt = get_db()->prepare(
            'INSERT IGNORE INTO river_snapshots (site_id, param, observed_at, value) VALUES '
            . implode(',', array_fill(0, count($chunk), '(?,?,?,?)'))
        );
        $stmt->execute($args);
        $inserted += $stmt->rowCount();
    }
    return $inserted;
}

/*
 * Now vs the snapshot closest to $ago seconds earlier (must be within 90 minutes of it).
 * Sparkline: the last snapshot in each $step-second bucket since then, plus the latest reading.
 */
function river_movers_vs_snapshot(array $history, int $tNow, float $vNow, int $ago, int $step): ?array
{
    $target = $tNow - $ago;
    $then = null;
    foreach ($history as $p) {
        if (abs($p[0] - $target) <= RIVER_MOVER_MATCH && (!$then || abs($p[0] - $target) < abs($then[0] - $target))) {
            $then = $p;
        }
    }
    if (!$then) {
        return null;
    }
    $buckets = [];
    foreach ($history as $p) {
        if ($p[0] >= $then[0] && $p[0] < $tNow) {
            $buckets[intdiv($p[0] - $then[0], $step)] = $p[1];
        }
    }
    ksort($buckets);
    $spark = array_values($buckets);
    $spark[] = $vNow;
    return river_movers_change($then[0], $then[1], $tNow, $vNow, $spark);
}

/* Now vs the daily mean from 7 days earlier; sparkline is the daily means since then plus the latest reading. */
function river_movers_1w(array $days, int $tNow, float $vNow): ?array
{
    $thenDay = gmdate('Y-m-d', $tNow - 7 * 86400);
    if (!isset($days[$thenDay])) {
        return null;
    }
    ksort($days);
    $spark = [];
    foreach ($days as $day => $v) {
        if ($day >= $thenDay && $day < gmdate('Y-m-d', $tNow)) {
            $spark[] = $v;
        }
    }
    $spark[] = $vNow;
    return river_movers_change(strtotime($thenDay . ' 12:00:00 UTC'), $days[$thenDay], $tNow, $vNow, $spark);
}

function river_movers_change(int $tThen, float $vThen, int $tNow, float $vNow, array $spark): array
{
    return [
        't_then' => $tThen, 'then' => $vThen, 't_now' => $tNow, 'now' => $vNow,
        'delta'  => $vNow - $vThen,
        'pct'    => $vThen != 0 ? ($vNow - $vThen) / abs($vThen) * 100 : null,
        'spark'  => array_map(fn($v) => round($v, 2), array_slice($spark, -30)),
    ];
}

/* Top rising and dropping gauges per window and measurement; replaces that state's rows for the given windows. */
function river_movers_save(string $state, array $windows, array $candidates): int
{
    $rows = [];
    foreach ($windows as $win) {
        foreach (RIVER_MOVER_PARAMS as $code => $mode) {
            $list = [];
            foreach ($candidates[$win][$code] ?? [] as $c) {
                if ($mode === 'pct' && ($c['pct'] === null || $c['then'] < RIVER_MOVER_MIN_CFS)) {
                    continue;
                }
                $c['score'] = $mode === 'pct' ? $c['pct'] : $c['delta'];
                if (abs($c['score']) >= RIVER_MOVER_MIN_CHANGE[$code]) {
                    $list[] = $c;
                }
            }
            usort($list, fn($a, $b) => $b['score'] <=> $a['score']);
            $up = array_slice(array_filter($list, fn($c) => $c['score'] > 0), 0, RIVER_MOVER_LIMIT);
            $down = array_slice(array_reverse(array_filter($list, fn($c) => $c['score'] < 0)), 0, RIVER_MOVER_LIMIT);
            foreach (['up' => $up, 'down' => $down] as $dir => $picked) {
                foreach (array_values($picked) as $i => $c) {
                    $rows[] = [$state, $win, $code, $dir, $i + 1, $c['site_id'], $c['then'], $c['now'], $c['delta'],
                               $c['pct'] === null ? null : round($c['pct'], 1),
                               gmdate('Y-m-d H:i:s', $c['t_then']), gmdate('Y-m-d H:i:s', $c['t_now']), json_encode($c['spark'])];
                }
            }
        }
    }

    $db = get_db();
    $db->beginTransaction();
    $del = $db->prepare('DELETE FROM river_movers WHERE state = ? AND win = ?');
    foreach ($windows as $win) {
        $del->execute([$state, $win]);
    }
    $ins = $db->prepare(
        'INSERT INTO river_movers (state, win, param, direction, rank_no, site_id, value_then, value_now, delta, pct,
                                   observed_then, observed_now, spark, computed_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    foreach ($rows as $row) {
        $ins->execute($row);
    }
    $db->commit();
    return count($rows);
}

/* Warms the cache for the most-viewed rivers of the past week so their pages load instantly. */
function river_preload_popular(): array
{
    $db = get_db();
    $ids = $db->query(
        'SELECT site_id FROM river_site_views WHERE viewed_on >= UTC_DATE() - INTERVAL 7 DAY
         GROUP BY site_id ORDER BY SUM(views) DESC LIMIT ' . RIVER_PRELOAD_TOP
    )->fetchAll(PDO::FETCH_COLUMN);

    $warmed = 0;
    foreach ($ids as $id) {
        $site = river_site($id);
        if ($site && in_array($site['state'], river_states(), true)) {
            usgs_latest_for_site($id);
            river_series($site, RIVER_DEFAULT_WINDOW);
            river_sync_daily($site);
            $warmed++;
        }
    }
    $db->exec('DELETE FROM river_site_views WHERE viewed_on < UTC_DATE() - INTERVAL 90 DAY');
    return ['warmed' => $warmed];
}

/* Page data: [win][param][direction] => rows (with gauge name and county), plus when the rankings were computed. */
function river_movers_for_state(string $state): array
{
    $stmt = get_db()->prepare(
        'SELECT m.*, s.display_name, s.county FROM river_movers m JOIN river_sites s ON s.site_id = m.site_id
         WHERE m.state = ? ORDER BY m.win, m.param, m.direction, m.rank_no'
    );
    $stmt->execute([$state]);
    $out = [];
    $computed = null;
    foreach ($stmt->fetchAll() as $row) {
        $row['spark'] = json_decode($row['spark'], true) ?: [];
        $out[$row['win']][$row['param']][$row['direction']][] = $row;
        $computed = max($computed ?? '', $row['computed_at']);
    }
    return ['movers' => $out, 'computed_at' => $computed];
}

/* Inline SVG sparkline; the line's shape doesn't depend on units, so it's drawn from native values. */
function river_sparkline(array $values): string
{
    $values = array_values(array_filter($values, 'is_numeric'));
    $n = count($values);
    if ($n < 2) {
        return '';
    }
    $min = min($values);
    $range = (max($values) - $min) ?: 1;
    $d = '';
    foreach ($values as $i => $v) {
        $d .= ($i ? 'L' : 'M') . round($i / ($n - 1) * 84) . ' ' . round(22 - ($v - $min) / $range * 20);
    }
    return '<svg class="river-spark" viewBox="0 0 84 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
}
