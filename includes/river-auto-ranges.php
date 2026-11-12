<?php
/*
 * river-auto-ranges.php - Seasonal percentile fishability suggestions from river_daily.
 * v1 applies percentile edges; also stores median, mode, mean, log_std for later method switches.
 */
require_once __DIR__ . '/rivers.php';

const RIVER_AUTO_PARAMS = ['00060', '00065', '00010'];
const RIVER_AUTO_DOY_RADIUS = 21;
const RIVER_AUTO_MIN_SAMPLE = 8;
const RIVER_AUTO_METHOD = 'percentile';
const RIVER_AUTO_METHOD_VERSION = 'doy21_p20_35_65_85_v1';

/* Coldwater absolute clamps for water temp (°C, USGS native). */
const RIVER_AUTO_TEMP_FLOOR_C = 4.44;   // ~40°F
const RIVER_AUTO_TEMP_CEIL_C = 20.0;    // ~68°F
const RIVER_AUTO_TEMP_IDEAL_LO_C = 10.0; // ~50°F
const RIVER_AUTO_TEMP_IDEAL_HI_C = 15.56; // ~60°F

function river_auto_ranges_for_sites(array $siteIds): array
{
    $siteIds = array_values(array_unique(array_filter($siteIds, 'usgs_valid_site_id')));
    $out = [];
    if (!$siteIds) {
        return $out;
    }
    try {
        foreach (array_chunk($siteIds, 500) as $chunk) {
            $sql = 'SELECT * FROM river_auto_ranges WHERE site_id IN ('
                . implode(',', array_fill(0, count($chunk), '?')) . ')';
            $stmt = get_db()->prepare($sql);
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $row) {
                if (is_string($row['stats'] ?? null)) {
                    $row['stats'] = json_decode($row['stats'], true) ?: [];
                }
                $out[$row['site_id']][$row['param']] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log('river_auto_ranges_for_sites: ' . $e->getMessage());
    }
    return $out;
}

function river_auto_percentile(array $sorted, float $p): ?float
{
    $n = count($sorted);
    if ($n === 0) {
        return null;
    }
    if ($n === 1) {
        return $sorted[0];
    }
    $p = max(0.0, min(100.0, $p));
    $idx = ($p / 100) * ($n - 1);
    $lo = (int) floor($idx);
    $hi = (int) ceil($idx);
    if ($lo === $hi) {
        return $sorted[$lo];
    }
    $w = $idx - $lo;
    return $sorted[$lo] * (1 - $w) + $sorted[$hi] * $w;
}

/* Histogram mode on log1p scale for positive skewed series; linear bins for temp. */
function river_auto_mode(array $values, string $param): ?float
{
    $n = count($values);
    if ($n < 5) {
        return null;
    }
    $useLog = $param === '00060' || $param === '00065';
    $transformed = [];
    foreach ($values as $v) {
        if ($useLog) {
            if ($v <= 0) {
                continue;
            }
            $transformed[] = log($v);
        } else {
            $transformed[] = $v;
        }
    }
    if (count($transformed) < 5) {
        return null;
    }
    $min = min($transformed);
    $max = max($transformed);
    if ($max <= $min) {
        return $useLog ? exp($min) : $min;
    }
    $bins = max(8, min(24, (int) round(sqrt(count($transformed)))));
    $width = ($max - $min) / $bins;
    $counts = array_fill(0, $bins, 0);
    foreach ($transformed as $t) {
        $i = (int) floor(($t - $min) / $width);
        if ($i >= $bins) {
            $i = $bins - 1;
        }
        $counts[$i]++;
    }
    $peak = array_keys($counts, max($counts))[0];
    $center = $min + ($peak + 0.5) * $width;
    return $useLog ? exp($center) : $center;
}

function river_auto_stats(array $values, string $param): array
{
    sort($values, SORT_NUMERIC);
    $n = count($values);
    $mean = array_sum($values) / $n;
    $median = river_auto_percentile($values, 50);
    $mode = river_auto_mode($values, $param);
    $logStd = null;
    if ($param === '00060' || $param === '00065') {
        $logs = [];
        foreach ($values as $v) {
            if ($v > 0) {
                $logs[] = log($v);
            }
        }
        if (count($logs) > 1) {
            $lm = array_sum($logs) / count($logs);
            $var = 0.0;
            foreach ($logs as $l) {
                $var += ($l - $lm) ** 2;
            }
            $logStd = sqrt($var / (count($logs) - 1));
        }
    } else {
        if ($n > 1) {
            $var = 0.0;
            foreach ($values as $v) {
                $var += ($v - $mean) ** 2;
            }
            $logStd = sqrt($var / ($n - 1));
        }
    }
    $pcts = [];
    foreach ([5, 10, 15, 20, 25, 30, 35, 50, 65, 70, 75, 80, 85, 90, 95] as $p) {
        $pcts['p' . $p] = river_auto_percentile($values, $p);
    }
    return [
        'n_sample' => $n,
        'median' => $median,
        'mode' => $mode,
        'mean' => $mean,
        'log_std' => $logStd,
        'percentiles' => $pcts,
    ];
}

function river_auto_bands_from_stats(array $stats, string $param): array
{
    $p = $stats['percentiles'];
    $fishLow = $p['p20'] ?? null;
    $idealLow = $p['p35'] ?? null;
    $idealHigh = $p['p65'] ?? null;
    $fishHigh = $p['p85'] ?? null;

    if ($param === '00010') {
        $fishLow = max($fishLow ?? RIVER_AUTO_TEMP_FLOOR_C, RIVER_AUTO_TEMP_FLOOR_C);
        $fishHigh = min($fishHigh ?? RIVER_AUTO_TEMP_CEIL_C, RIVER_AUTO_TEMP_CEIL_C);
        $idealLow = max($idealLow ?? RIVER_AUTO_TEMP_IDEAL_LO_C, RIVER_AUTO_TEMP_IDEAL_LO_C);
        $idealHigh = min($idealHigh ?? RIVER_AUTO_TEMP_IDEAL_HI_C, RIVER_AUTO_TEMP_IDEAL_HI_C);
        if ($idealLow > $idealHigh) {
            $idealLow = RIVER_AUTO_TEMP_IDEAL_LO_C;
            $idealHigh = RIVER_AUTO_TEMP_IDEAL_HI_C;
        }
        if ($fishLow > $idealLow) {
            $fishLow = min($fishLow, $idealLow);
        }
        if ($fishHigh < $idealHigh) {
            $fishHigh = max($fishHigh, $idealHigh);
        }
    }

    return [
        'fish_low' => $fishLow,
        'ideal_low' => $idealLow,
        'ideal_high' => $idealHigh,
        'fish_high' => $fishHigh,
    ];
}

function river_auto_seasonal_values(string $siteId, string $param, ?DateTimeInterface $asOf = null): array
{
    $asOf ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $doy = (int) $asOf->format('z') + 1;
    $radius = RIVER_AUTO_DOY_RADIUS;
    $stmt = get_db()->prepare(
        'SELECT value, DAYOFYEAR(day) AS doy FROM river_daily WHERE site_id = ? AND param = ?'
    );
    $stmt->execute([$siteId, $param]);
    $values = [];
    foreach ($stmt->fetchAll() as $row) {
        $d = (int) $row['doy'];
        $dist = min(abs($d - $doy), 366 - abs($d - $doy));
        if ($dist <= $radius) {
            $values[] = (float) $row['value'];
        }
    }
    return $values;
}

function river_auto_compute_site(string $siteId, ?DateTimeInterface $asOf = null): array
{
    $site = river_site($siteId);
    if (!$site) {
        return ['ok' => false, 'error' => 'missing site'];
    }
    // Ensure some daily history exists.
    if (empty($site['daily_synced_at']) || strtotime($site['daily_synced_at'] . ' UTC') < time() - 86400 * 7) {
        river_sync_daily($site);
        $site = river_site($siteId) ?? $site;
    }

    $computed = 0;
    $db = get_db();
    foreach (RIVER_AUTO_PARAMS as $param) {
        if ($param !== '00010' && !in_array($param, $site['param_list'] ?? [], true)) {
            // Still try if daily rows exist.
        }
        $values = river_auto_seasonal_values($siteId, $param, $asOf);
        if (count($values) < RIVER_AUTO_MIN_SAMPLE) {
            continue;
        }
        $stats = river_auto_stats($values, $param);
        $bands = river_auto_bands_from_stats($stats, $param);
        $db->prepare(
            'INSERT INTO river_auto_ranges (
                site_id, param, fish_low, ideal_low, ideal_high, fish_high,
                method, method_version, n_sample, stats, computed_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                fish_low = VALUES(fish_low), ideal_low = VALUES(ideal_low),
                ideal_high = VALUES(ideal_high), fish_high = VALUES(fish_high),
                method = VALUES(method), method_version = VALUES(method_version),
                n_sample = VALUES(n_sample), stats = VALUES(stats), computed_at = UTC_TIMESTAMP()'
        )->execute([
            $siteId, $param,
            $bands['fish_low'], $bands['ideal_low'], $bands['ideal_high'], $bands['fish_high'],
            RIVER_AUTO_METHOD, RIVER_AUTO_METHOD_VERSION, $stats['n_sample'],
            json_encode($stats, JSON_UNESCAPED_SLASHES),
        ]);
        $computed++;
    }
    return ['ok' => true, 'computed' => $computed];
}

function river_auto_is_unusual(string $siteId, string $param, float $value): bool
{
    $row = river_auto_ranges_for_sites([$siteId])[$siteId][$param] ?? null;
    if (!$row) {
        return false;
    }
    $stats = $row['stats'] ?? [];
    if (is_string($stats)) {
        $stats = json_decode($stats, true) ?: [];
    }
    $p = $stats['percentiles'] ?? [];
    $p5 = $p['p5'] ?? null;
    $p95 = $p['p95'] ?? null;
    if ($p5 !== null && $value < $p5) {
        return true;
    }
    if ($p95 !== null && $value > $p95) {
        return true;
    }
    $logStd = $stats['log_std'] ?? null;
    $median = $stats['median'] ?? null;
    if ($logStd && $median && ($param === '00060' || $param === '00065') && $value > 0 && $median > 0) {
        $z = abs(log($value) - log($median)) / $logStd;
        if ($z > 2) {
            return true;
        }
    }
    return false;
}

/* Recompute for saved + popular sites. */
function river_auto_ranges_run(int $limit = 200): array
{
    $db = get_db();
    $ids = [];
    try {
        foreach ($db->query('SELECT DISTINCT site_id FROM river_user_sites LIMIT 500')->fetchAll() as $r) {
            $ids[$r['site_id']] = true;
        }
    } catch (PDOException $e) {
        // table may not exist yet
    }
    try {
        foreach ($db->query(
            'SELECT site_id FROM river_site_views
             WHERE viewed_on >= UTC_DATE() - INTERVAL 90 DAY
             GROUP BY site_id ORDER BY SUM(views) DESC LIMIT 150'
        )->fetchAll() as $r) {
            $ids[$r['site_id']] = true;
        }
    } catch (PDOException $e) {
        // ignore
    }

    $list = array_slice(array_keys($ids), 0, $limit);
    $ok = 0;
    $params = 0;
    foreach ($list as $siteId) {
        $r = river_auto_compute_site($siteId);
        if (!empty($r['ok'])) {
            $ok++;
            $params += (int) ($r['computed'] ?? 0);
        }
    }
    return ['ok' => true, 'sites' => $ok, 'params' => $params, 'candidates' => count($list)];
}
