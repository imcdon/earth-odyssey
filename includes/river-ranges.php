<?php
/*
 * river-ranges.php - Fishability ranges per river and the badge they produce.
 * Ranges are stored in USGS units (cfs, ft). A blank limit means no limit on that side.
 * Badge: inside the ideal band = Prime; inside the fishable band = Fishable; below/above it = Too low / Blown out.
 */
require_once __DIR__ . '/rivers.php';

const RIVER_RANGE_PARAMS = ['00060' => 'Flow', '00065' => 'Gage height'];
const RIVER_RANGE_FIELDS = ['fish_low', 'ideal_low', 'ideal_high', 'fish_high'];
const RIVER_BADGES = ['prime' => 'Prime', 'fishable' => 'Fishable', 'low' => 'Too low', 'high' => 'Blown out'];
const RIVER_BADGE_MAX_AGE = 21600;
const RIVER_ADMIN_TZ = 'America/New_York';

/* UTC 'Y-m-d H:i:s' or unix time -> Eastern time for admin pages. */
function river_admin_time($utc, string $format = 'M j, g:i A'): string
{
    $t = is_int($utc) ? $utc : strtotime($utc . ' UTC');
    return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone(RIVER_ADMIN_TZ))->format($format);
}

/* [site_id => [param => range row]] */
function river_ranges_for_sites(array $siteIds): array
{
    $siteIds = array_values(array_unique(array_filter($siteIds, 'usgs_valid_site_id')));
    $out = [];
    foreach (array_chunk($siteIds, 500) as $chunk) {
        foreach (river_ranges_query('SELECT * FROM river_ranges WHERE site_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk) as $row) {
            $out[$row['site_id']][$row['param']] = $row;
        }
    }
    return $out;
}

function river_ranges_for_state(string $state): array
{
    $out = [];
    foreach (river_ranges_query('SELECT r.* FROM river_ranges r JOIN river_sites s ON s.site_id = r.site_id WHERE s.state = ?', [$state]) as $row) {
        $out[$row['site_id']][$row['param']] = $row;
    }
    return $out;
}

/* Public pages must keep working if migrate-ranges.sql hasn't been imported yet: no table means no badges. */
function river_ranges_query(string $sql, array $args): array
{
    try {
        $stmt = get_db()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('river_ranges query failed: ' . $e->getMessage());
        return [];
    }
}

function river_range_level(array $range, float $v): string
{
    $n = fn(string $k) => $range[$k] === null ? null : (float) $range[$k];
    if ($n('fish_low') !== null && $v < $n('fish_low')) {
        return 'low';
    }
    if ($n('fish_high') !== null && $v > $n('fish_high')) {
        return 'high';
    }
    $aboveLow = $n('ideal_low') === null || $v >= $n('ideal_low');
    $belowHigh = $n('ideal_high') === null || $v <= $n('ideal_high');
    return $aboveLow && $belowHigh ? 'prime' : 'fishable';
}

/*
 * $ranges: [param => range row]; $values: [param => ['v' => float, 't' => unix]].
 * Flow is used when it has a range and a recent reading; otherwise gage height.
 */
function river_badge(array $ranges, array $values): ?array
{
    foreach (array_keys(RIVER_RANGE_PARAMS) as $code) {
        if (isset($ranges[$code], $values[$code]) && time() - $values[$code]['t'] <= RIVER_BADGE_MAX_AGE) {
            $level = river_range_level($ranges[$code], $values[$code]['v']);
            return ['level' => $level, 'label' => RIVER_BADGES[$level], 'param' => $code,
                    'value' => $values[$code]['v'], 'time' => $values[$code]['t'], 'range' => $ranges[$code]];
        }
    }
    return null;
}

/* Badges from the hourly snapshots (MySQL only). $rangesBySite: from river_ranges_for_sites/state. */
function river_badges_from_snapshots(array $rangesBySite): array
{
    $values = [];
    foreach (array_chunk(array_keys($rangesBySite), 500) as $chunk) {
        $stmt = get_db()->prepare(
            'SELECT site_id, param, observed_at, value FROM river_snapshots
             WHERE site_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ') AND param IN (?, ?)
               AND observed_at >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
             ORDER BY observed_at'
        );
        $stmt->execute(array_merge($chunk, array_keys(RIVER_RANGE_PARAMS)));
        foreach ($stmt->fetchAll() as $row) {
            $values[$row['site_id']][$row['param']] = ['v' => (float) $row['value'], 't' => strtotime($row['observed_at'] . ' UTC')];
        }
    }
    $out = [];
    foreach ($rangesBySite as $id => $ranges) {
        if ($badge = river_badge($ranges, $values[$id] ?? [])) {
            $out[$id] = $badge;
        }
    }
    return $out;
}

/* "800–1,500 cfs", "up to 2,500 cfs", "600 cfs and up", or null when the band has no limits. */
function river_range_band(array $range, string $band, string $units): ?string
{
    $lo = $range[$band . '_low'];
    $hi = $range[$band . '_high'];
    $code = $range['param'];
    $f = fn($x) => river_format(river_convert($code, (float) $x, $units)[0]);
    $unit = river_convert($code, 0, $units)[1];
    return match (true) {
        $lo === null && $hi === null => null,
        $lo === null                 => 'up to ' . $f($hi) . ' ' . $unit,
        $hi === null                 => $f($lo) . ' ' . $unit . ' and up',
        default                      => $f($lo) . '–' . $f($hi) . ' ' . $unit,
    };
}

function river_badge_title(array $b, string $units): string
{
    [$v, $u] = river_convert($b['param'], $b['value'], $units);
    $ideal = river_range_band($b['range'], 'ideal', $units);
    return RIVER_RANGE_PARAMS[$b['param']] . ' ' . river_format($v) . ' ' . $u . ($ideal ? ' · ideal ' . $ideal : '');
}

function river_badge_html(?array $b, string $units): string
{
    if (!$b) {
        return '';
    }
    return '<span class="river-badge is-' . $b['level'] . '" title="' . htmlspecialchars(river_badge_title($b, $units)) . '">'
        . htmlspecialchars($b['label']) . '</span>';
}

/* One sentence explaining the badge, e.g. "Flow 1,200 cfs is in the ideal range (800–1,500 cfs)." */
function river_badge_sentence(array $b, string $units): string
{
    [$v, $u] = river_convert($b['param'], $b['value'], $units);
    $what = RIVER_RANGE_PARAMS[$b['param']] . ' ' . river_format($v) . ' ' . $u;
    $ideal = river_range_band($b['range'], 'ideal', $units);
    $fish = river_range_band($b['range'], 'fish', $units);
    return match ($b['level']) {
        'prime'    => "{$what} is in the ideal range" . ($ideal ? " ({$ideal})" : '') . '.',
        'fishable' => "{$what} is outside the ideal range" . ($ideal ? " ({$ideal})" : '') . ' but still fishable' . ($fish ? " ({$fish})" : '') . '.',
        'low'      => "{$what} is below the fishable range" . ($fish ? " ({$fish})" : '') . '.',
        default    => "{$what} is above the fishable range" . ($fish ? " ({$fish})" : '') . '.',
    };
}

/* Every river with a range, newest change first, with the gauge name and who changed it. */
function river_ranges_all(): array
{
    $rows = get_db()->query(
        'SELECT r.*, s.display_name, s.state, u.username FROM river_ranges r
         LEFT JOIN river_sites s ON s.site_id = r.site_id
         LEFT JOIN users u ON u.id = r.updated_by
         ORDER BY r.updated_at DESC'
    )->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[$row['site_id']] ??= ['site_id' => $row['site_id'], 'display_name' => $row['display_name'], 'state' => $row['state'],
                                   'ranges' => [], 'updated_at' => $row['updated_at'], 'username' => $row['username']];
        $out[$row['site_id']]['ranges'][$row['param']] = $row;
    }
    return $out;
}

/*
 * Saves both measurements for one site from the form ($post['r'][param][field], $post['units']).
 * A measurement with every box blank is removed. Returns a list of error messages (empty on success).
 */
function river_range_save(string $siteId, array $post, int $userId): array
{
    $metric = ($post['units'] ?? '') === 'metric';
    $errors = [];
    $save = [];
    foreach (RIVER_RANGE_PARAMS as $code => $label) {
        $vals = [];
        foreach (RIVER_RANGE_FIELDS as $field) {
            $raw = trim(str_replace(',', '', (string) ($post['r'][$code][$field] ?? '')));
            if ($raw === '') {
                $vals[$field] = null;
            } elseif (!is_numeric($raw)) {
                $errors[] = "{$label}: \"{$raw}\" isn't a number.";
                $vals[$field] = null;
            } else {
                $n = (float) $raw;
                $vals[$field] = $metric ? $n / ($code === '00060' ? 0.0283168 : 0.3048) : $n;
            }
        }
        $given = array_filter($vals, fn($v) => $v !== null);
        if (!$given) {
            $save[$code] = null;
            continue;
        }
        if ($vals['ideal_low'] === null && $vals['ideal_high'] === null) {
            $errors[] = "{$label}: enter at least one number for the ideal (Prime) range.";
        }
        if ($code === '00060' && min($given) < 0) {
            $errors[] = "{$label} can't be negative.";
        }
        $ordered = array_values($given);
        for ($i = 1; $i < count($ordered); $i++) {
            if ($ordered[$i] < $ordered[$i - 1]) {
                $errors[] = "{$label}: the numbers must go from low to high (too low, ideal low, ideal high, blown out).";
                break;
            }
        }
        $save[$code] = $vals;
    }
    if ($errors) {
        return $errors;
    }

    $db = get_db();
    $db->beginTransaction();
    foreach ($save as $code => $vals) {
        if ($vals === null) {
            $db->prepare('DELETE FROM river_ranges WHERE site_id = ? AND param = ?')->execute([$siteId, $code]);
            continue;
        }
        $db->prepare(
            'INSERT INTO river_ranges (site_id, param, fish_low, ideal_low, ideal_high, fish_high, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE fish_low = VALUES(fish_low), ideal_low = VALUES(ideal_low), ideal_high = VALUES(ideal_high),
                 fish_high = VALUES(fish_high), updated_by = VALUES(updated_by), updated_at = UTC_TIMESTAMP()'
        )->execute([$siteId, $code, $vals['fish_low'], $vals['ideal_low'], $vals['ideal_high'], $vals['fish_high'], $userId]);
    }
    $db->commit();
    return [];
}
