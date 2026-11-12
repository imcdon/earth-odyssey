<?php
/*
 * river-state-map.php - State gauge map: sites with coordinates, 24h flow trends from snapshots,
 * and fishability badges for the Leaflet map on /services/rivers/?state=XX.
 */
require_once __DIR__ . '/river-movers.php';
require_once __DIR__ . '/river-ranges.php';

/* Gauges in a state that have lat/lon for the map (MySQL only). */
function river_sites_for_map(string $state): array
{
    $stmt = get_db()->prepare(
        'SELECT site_id, display_name, county, lat, lon, params FROM river_sites
         WHERE state = ? AND lat IS NOT NULL AND lon IS NOT NULL
         ORDER BY display_name'
    );
    $stmt->execute([$state]);
    return $stmt->fetchAll();
}

/*
 * [site_id => trend] over ~24 hours from river_snapshots for one USGS parameter.
 * Trend fields: now, then, delta, pct, spark, observed_at (ISO UTC). Missing history => absent key.
 */
function river_param_trends_for_sites(array $siteIds, string $param = '00060'): array
{
    $siteIds = array_values(array_unique(array_filter($siteIds, 'usgs_valid_site_id')));
    $out = [];
    if (!$siteIds || !isset(USGS_PARAMS[$param])) {
        return $out;
    }

    foreach (array_chunk($siteIds, 500) as $chunk) {
        /* No wall-clock cutoff: use each site's newest reading as "now" so a stale cron still yields a 24h trend. */
        $stmt = get_db()->prepare(
            'SELECT site_id, observed_at, value FROM river_snapshots
             WHERE site_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')
               AND param = ?
             ORDER BY observed_at'
        );
        $stmt->execute([...$chunk, $param]);

        $history = [];
        foreach ($stmt->fetchAll() as $row) {
            $history[$row['site_id']][] = [strtotime($row['observed_at'] . ' UTC'), (float) $row['value']];
        }

        foreach ($history as $id => $points) {
            if (count($points) < 2) {
                continue;
            }
            $last = $points[array_key_last($points)];
            $cutoff = $last[0] - 26 * 3600;
            $window = [];
            foreach ($points as $p) {
                if ($p[0] >= $cutoff) {
                    $window[] = $p;
                }
            }
            if (count($window) < 2) {
                continue;
            }
            $trend = river_movers_vs_snapshot($window, $last[0], $last[1], 86400, 3600);
            if (!$trend) {
                continue;
            }
            $out[$id] = [
                'now'         => $trend['now'],
                'then'        => $trend['then'],
                'delta'       => $trend['delta'],
                'pct'         => $trend['pct'],
                'spark'       => $trend['spark'],
                'observed_at' => gmdate('c', $trend['t_now']),
            ];
        }
    }

    return $out;
}

function river_flow_trends_for_sites(array $siteIds): array
{
    return river_param_trends_for_sites($siteIds, '00060');
}

/* Convert a native trend row to display units for API/UI. */
function river_trend_public(array $t, string $param, string $units): array
{
    [$nowV, $unit] = river_convert($param, $t['now'], $units);
    [$deltaV] = river_convert($param, $t['delta'], $units);
    return [
        'param'       => $param,
        'now'         => round($nowV, abs($nowV) >= 100 ? 0 : (abs($nowV) >= 10 ? 1 : 2)),
        'delta'       => round($deltaV, abs($deltaV) >= 100 ? 0 : (abs($deltaV) >= 10 ? 1 : 2)),
        'pct'         => $t['pct'] === null ? null : round($t['pct'], abs($t['pct']) >= 100 ? 0 : 1),
        'unit'        => $unit,
        'spark'       => $t['spark'],
        'observed_at' => $t['observed_at'],
    ];
}

/* One panel row for the state dropdowns. */
function river_panel_row(string $id, string $name, ?string $county, string $stat, ?array $trend, string $param, string $units, ?int $views = null): array
{
    $row = [
        'id'     => $id,
        'name'   => $name,
        'county' => $county,
        'stat'   => $stat,
        'spark'  => $trend['spark'] ?? [],
    ];
    if ($trend) {
        $row['trend'] = river_trend_public($trend, $param, $units);
    }
    if ($views !== null) {
        $row['views'] = $views;
    }
    return $row;
}

/* JSON payload for ?action=statemap — flow values converted to $units; spark stays native for shape. */
function river_statemap_payload(string $state, string $units = 'us'): array
{
    $countStmt = get_db()->prepare('SELECT COUNT(*) FROM river_sites WHERE state = ?');
    $countStmt->execute([$state]);
    $total = (int) $countStmt->fetchColumn();

    $sites = river_sites_for_map($state);
    $ids = array_column($sites, 'site_id');
    $trends = river_flow_trends_for_sites($ids);
    $badges = river_badges_from_snapshots(river_ranges_for_state($state));

    $out = [];
    foreach ($sites as $s) {
        $id = $s['site_id'];
        $item = [
            'id'     => $id,
            'name'   => $s['display_name'],
            'siteNo' => preg_replace('/^[A-Z]+-/', '', $id) ?: $id,
            'lat'    => (float) $s['lat'],
            'lon'    => (float) $s['lon'],
            'county' => $s['county'],
        ];

        if (isset($trends[$id])) {
            $item['trend'] = river_trend_public($trends[$id], '00060', $units);
        }

        if (isset($badges[$id])) {
            $b = $badges[$id];
            $item['badge'] = [
                'level' => $b['level'],
                'label' => $b['label'],
                'title' => river_badge_title($b, $units),
            ];
        }

        $out[] = $item;
    }

    return [
        'state'          => $state,
        'stateName'      => RIVER_STATE_NAMES[$state] ?? $state,
        'sites'          => $out,
        'missingCoords'  => max(0, $total - count($sites)),
    ];
}

/*
 * Dropdown panels under the state map: all A–Z, top views, biggest flow risers/falls, coldest/warmest.
 * MySQL only (sites, snapshots, movers, views).
 */
function river_state_panels_payload(string $state, string $units = 'us'): array
{
    $stmt = get_db()->prepare(
        'SELECT site_id, display_name, county, params FROM river_sites WHERE state = ? ORDER BY display_name'
    );
    $stmt->execute([$state]);
    $sites = $stmt->fetchAll();
    $ids = array_column($sites, 'site_id');
    $byId = [];
    foreach ($sites as $s) {
        $byId[$s['site_id']] = $s;
    }

    $flow = river_param_trends_for_sites($ids, '00060');
    $water = river_param_trends_for_sites($ids, '00010');
    $air = river_param_trends_for_sites($ids, '00020');

    $all = [];
    foreach ($sites as $s) {
        $id = $s['site_id'];
        $bits = [];
        if (isset($flow[$id])) {
            $pub = river_trend_public($flow[$id], '00060', $units);
            $bits[] = river_format($pub['now']) . ' ' . $pub['unit'];
            if ($pub['pct'] !== null) {
                $bits[] = ($pub['pct'] > 0 ? '+' : '') . river_format($pub['pct']) . '% 24h';
            }
        }
        if (isset($water[$id])) {
            $pubT = river_trend_public($water[$id], '00010', $units);
            $bits[] = 'water ' . river_format($pubT['now']) . ' ' . $pubT['unit'];
        }
        if (isset($air[$id])) {
            $pubA = river_trend_public($air[$id], '00020', $units);
            $bits[] = 'air ' . river_format($pubA['now']) . ' ' . $pubA['unit'];
        }
        if ($s['county']) {
            $bits[] = (string) $s['county'];
        }
        $sparkTrend = $flow[$id] ?? $water[$id] ?? $air[$id] ?? null;
        $sparkParam = isset($flow[$id]) ? '00060' : (isset($water[$id]) ? '00010' : '00020');
        $all[] = river_panel_row(
            $id,
            $s['display_name'],
            $s['county'],
            $bits ? implode(' · ', $bits) : 'No recent readings',
            $sparkTrend,
            $sparkParam,
            $units
        );
    }

    $popular = [];
    if ($ids) {
        $viewStmt = get_db()->prepare(
            'SELECT v.site_id, SUM(v.views) AS views FROM river_site_views v
             WHERE v.site_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
               AND v.viewed_on >= UTC_DATE() - INTERVAL 90 DAY
             GROUP BY v.site_id
             ORDER BY views DESC
             LIMIT 10'
        );
        $viewStmt->execute($ids);
        foreach ($viewStmt->fetchAll() as $row) {
            $id = $row['site_id'];
            if (!isset($byId[$id])) {
                continue;
            }
            $s = $byId[$id];
            $views = (int) $row['views'];
            $bits = [$views . ' view' . ($views === 1 ? '' : 's')];
            if (isset($flow[$id])) {
                $pub = river_trend_public($flow[$id], '00060', $units);
                $bits[] = river_format($pub['now']) . ' ' . $pub['unit'];
            }
            $popular[] = river_panel_row($id, $s['display_name'], $s['county'], implode(' · ', $bits), $flow[$id] ?? null, '00060', $units, $views);
        }
    }

    $movers = river_movers_for_state($state);
    $risers = [];
    $falls = [];
    foreach (($movers['movers']['24h']['00060']['up'] ?? []) as $m) {
        $trend = [
            'now' => (float) $m['value_now'], 'then' => (float) $m['value_then'],
            'delta' => (float) $m['delta'], 'pct' => $m['pct'] !== null ? (float) $m['pct'] : null,
            'spark' => $m['spark'], 'observed_at' => gmdate('c', strtotime($m['observed_now'] . ' UTC')),
        ];
        $pub = river_trend_public($trend, '00060', $units);
        $pct = $pub['pct'] !== null ? (($pub['pct'] > 0 ? '+' : '') . river_format($pub['pct']) . '%') : '—';
        $stat = $pct . ' · ' . river_format($pub['now']) . ' ' . $pub['unit'];
        $risers[] = river_panel_row($m['site_id'], $m['display_name'], $m['county'], $stat, $trend, '00060', $units);
    }
    foreach (($movers['movers']['24h']['00060']['down'] ?? []) as $m) {
        $trend = [
            'now' => (float) $m['value_now'], 'then' => (float) $m['value_then'],
            'delta' => (float) $m['delta'], 'pct' => $m['pct'] !== null ? (float) $m['pct'] : null,
            'spark' => $m['spark'], 'observed_at' => gmdate('c', strtotime($m['observed_now'] . ' UTC')),
        ];
        $pub = river_trend_public($trend, '00060', $units);
        $pct = $pub['pct'] !== null ? (($pub['pct'] > 0 ? '+' : '') . river_format($pub['pct']) . '%') : '—';
        $stat = $pct . ' · ' . river_format($pub['now']) . ' ' . $pub['unit'];
        $falls[] = river_panel_row($m['site_id'], $m['display_name'], $m['county'], $stat, $trend, '00060', $units);
    }

    [$coldestWater, $warmestWater] = river_panel_temp_extremes($water, $byId, '00010', $units);
    [$coldestAir, $warmestAir] = river_panel_temp_extremes($air, $byId, '00020', $units);

    return [
        'state'     => $state,
        'stateName' => RIVER_STATE_NAMES[$state] ?? $state,
        'panels'    => [
            'all'           => ['title' => 'All streams A–Z', 'empty' => 'No gauges in this state yet.', 'rows' => $all],
            'popular'       => ['title' => 'Most popular', 'empty' => 'No views recorded yet.', 'rows' => $popular],
            'risers'        => ['title' => 'Biggest risers %', 'empty' => 'Rankings appear after the next hourly update.', 'rows' => $risers],
            'falls'         => ['title' => 'Biggest falls %', 'empty' => 'Rankings appear after the next hourly update.', 'rows' => $falls],
            'coldest_water' => ['title' => 'Coldest water', 'empty' => 'No water-temperature readings yet.', 'rows' => $coldestWater],
            'warmest_water' => ['title' => 'Warmest water', 'empty' => 'No water-temperature readings yet.', 'rows' => $warmestWater],
            'coldest_air'   => ['title' => 'Coldest air', 'empty' => 'No air-temperature readings yet. They appear after the next hourly update.', 'rows' => $coldestAir],
            'warmest_air'   => ['title' => 'Warmest air', 'empty' => 'No air-temperature readings yet. They appear after the next hourly update.', 'rows' => $warmestAir],
        ],
    ];
}

/* Top 10 coldest and warmest for a temperature parameter. Returns [coldestRows, warmestRows]. */
function river_panel_temp_extremes(array $trends, array $byId, string $param, string $units): array
{
    $rows = [];
    foreach ($trends as $id => $t) {
        if (!isset($byId[$id])) {
            continue;
        }
        $rows[] = ['id' => $id, 'trend' => $t, 'site' => $byId[$id]];
    }
    usort($rows, fn($a, $b) => $a['trend']['now'] <=> $b['trend']['now']);

    $make = function (array $slice) use ($param, $units): array {
        $out = [];
        foreach ($slice as $row) {
            $pub = river_trend_public($row['trend'], $param, $units);
            $stat = river_format($pub['now']) . ' ' . $pub['unit'];
            if ($pub['delta'] != 0) {
                $stat .= ' · 24h ' . ($pub['delta'] > 0 ? '+' : '') . river_format($pub['delta']) . ' ' . $pub['unit'];
            }
            $out[] = river_panel_row($row['id'], $row['site']['display_name'], $row['site']['county'], $stat, $row['trend'], $param, $units);
        }
        return $out;
    };

    return [
        $make(array_slice($rows, 0, 10)),
        $make(array_slice(array_reverse($rows), 0, 10)),
    ];
}
