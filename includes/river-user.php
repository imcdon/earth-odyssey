<?php
/*
 * river-user.php - Saved rivers and per-user fishability ranges for logged-in anglers.
 */
require_once __DIR__ . '/rivers.php';
require_once __DIR__ . '/river-ranges.php';

const RIVER_USER_SITE_MAX = 40;

function river_user_sites(int $userId): array
{
    try {
        $stmt = get_db()->prepare(
            'SELECT u.site_id, u.sort_order, u.created_at, s.display_name, s.state, s.county, s.params, s.lat, s.lon
             FROM river_user_sites u
             JOIN river_sites s ON s.site_id = u.site_id
             WHERE u.user_id = ?
             ORDER BY u.sort_order ASC, u.created_at ASC'
        );
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('river_user_sites: ' . $e->getMessage());
        return [];
    }
    foreach ($rows as &$row) {
        $row['param_list'] = array_values(array_filter(explode(',', (string) $row['params'])));
    }
    unset($row);
    return $rows;
}

function river_user_has_site(int $userId, string $siteId): bool
{
    try {
        $stmt = get_db()->prepare('SELECT 1 FROM river_user_sites WHERE user_id = ? AND site_id = ?');
        $stmt->execute([$userId, $siteId]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function river_user_save_site(int $userId, string $siteId): array
{
    if (!usgs_valid_site_id($siteId) || !river_site($siteId)) {
        return ['Gauge not found.'];
    }
    $sites = river_user_sites($userId);
    foreach ($sites as $s) {
        if ($s['site_id'] === $siteId) {
            return [];
        }
    }
    if (count($sites) >= RIVER_USER_SITE_MAX) {
        return ['You can save up to ' . RIVER_USER_SITE_MAX . ' rivers.'];
    }
    $order = $sites ? ((int) end($sites)['sort_order'] + 1) : 0;
    try {
        get_db()->prepare(
            'INSERT INTO river_user_sites (user_id, site_id, sort_order) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE sort_order = sort_order'
        )->execute([$userId, $siteId, $order]);
    } catch (PDOException $e) {
        error_log('river_user_save_site: ' . $e->getMessage());
        return ['Could not save this river.'];
    }
    return [];
}

function river_user_remove_site(int $userId, string $siteId): void
{
    try {
        get_db()->prepare('DELETE FROM river_user_sites WHERE user_id = ? AND site_id = ?')->execute([$userId, $siteId]);
    } catch (PDOException $e) {
        error_log('river_user_remove_site: ' . $e->getMessage());
    }
}

/* Import [{id, name}, …] from browser favorites; returns how many were added. */
function river_user_import_favorites(int $userId, array $favs): int
{
    $added = 0;
    foreach (array_slice($favs, 0, RIVER_USER_SITE_MAX) as $fav) {
        $id = (string) ($fav['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if (!river_user_save_site($userId, $id)) {
            $added++;
        }
    }
    return $added;
}

function river_user_ranges_for_sites(int $userId, array $siteIds): array
{
    $siteIds = array_values(array_unique(array_filter($siteIds, 'usgs_valid_site_id')));
    if (!$siteIds) {
        return [];
    }
    $out = [];
    try {
        foreach (array_chunk($siteIds, 500) as $chunk) {
            $sql = 'SELECT * FROM river_user_ranges WHERE user_id = ? AND site_id IN ('
                . implode(',', array_fill(0, count($chunk), '?')) . ')';
            $stmt = get_db()->prepare($sql);
            $stmt->execute(array_merge([$userId], $chunk));
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['site_id']][$row['param']] = $row;
            }
        }
    } catch (PDOException $e) {
        error_log('river_user_ranges_for_sites: ' . $e->getMessage());
    }
    return $out;
}

/*
 * Resolve bands for badge: user → admin → auto.
 * Returns [site_id => [param => range row]] with optional '_source' on each param row.
 */
function river_effective_ranges_for_user(?int $userId, array $siteIds): array
{
    $admin = river_ranges_for_sites($siteIds);
    $auto = function_exists('river_auto_ranges_for_sites') ? river_auto_ranges_for_sites($siteIds) : [];
    $user = $userId ? river_user_ranges_for_sites($userId, $siteIds) : [];
    $out = [];
    foreach ($siteIds as $id) {
        $params = array_unique(array_merge(
            array_keys($user[$id] ?? []),
            array_keys($admin[$id] ?? []),
            array_keys($auto[$id] ?? [])
        ));
        foreach ($params as $param) {
            if (isset($user[$id][$param])) {
                $row = $user[$id][$param];
                $row['_source'] = 'user';
            } elseif (isset($admin[$id][$param])) {
                $row = $admin[$id][$param];
                $row['_source'] = 'site';
            } elseif (isset($auto[$id][$param])) {
                $row = $auto[$id][$param];
                $row['_source'] = 'auto';
            } else {
                continue;
            }
            $out[$id][$param] = $row;
        }
    }
    return $out;
}

function river_user_range_save(int $userId, string $siteId, array $post): array
{
    if (!usgs_valid_site_id($siteId) || !river_site($siteId)) {
        return ['Gauge not found.'];
    }
    $metric = ($post['units'] ?? '') === 'metric';
    $errors = [];
    $save = [];
    $params = RIVER_RANGE_PARAMS + ['00010' => 'Water temperature'];
    foreach ($params as $code => $label) {
        if (!isset($post['r'][$code])) {
            continue;
        }
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
                if ($metric) {
                    $n = match ($code) {
                        '00060' => $n / 0.0283168,
                        '00065' => $n / 0.3048,
                        '00010' => ($n - 32) * 5 / 9,
                        default => $n,
                    };
                }
                $vals[$field] = $n;
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
                $errors[] = "{$label}: the numbers must go from low to high.";
                break;
            }
        }
        $save[$code] = $vals;
    }
    if ($errors) {
        return $errors;
    }
    if (!$save) {
        return ['Nothing to save.'];
    }

    $db = get_db();
    $db->beginTransaction();
    try {
        foreach ($save as $code => $vals) {
            if ($vals === null) {
                $db->prepare('DELETE FROM river_user_ranges WHERE user_id = ? AND site_id = ? AND param = ?')
                    ->execute([$userId, $siteId, $code]);
                continue;
            }
            $db->prepare(
                'INSERT INTO river_user_ranges (user_id, site_id, param, fish_low, ideal_low, ideal_high, fish_high, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE fish_low = VALUES(fish_low), ideal_low = VALUES(ideal_low),
                     ideal_high = VALUES(ideal_high), fish_high = VALUES(fish_high), updated_at = UTC_TIMESTAMP()'
            )->execute([$userId, $siteId, $code, $vals['fish_low'], $vals['ideal_low'], $vals['ideal_high'], $vals['fish_high']]);
        }
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('river_user_range_save: ' . $e->getMessage());
        return ['Could not save ranges.'];
    }
    return [];
}

/* Dashboard payload: saved sites + readings + badges + alert subscription flags. */
function river_user_dashboard(int $userId, string $units = 'us'): array
{
    require_once __DIR__ . '/river-auto-ranges.php';
    require_once __DIR__ . '/river-alerts.php';
    $sites = river_user_sites($userId);
    $ids = array_column($sites, 'site_id');
    $ranges = river_effective_ranges_for_user($userId, $ids);
    $alertSubs = [];
    foreach (river_alert_subscriptions_for_user($userId) as $sub) {
        $alertSubs[$sub['site_id']][] = $sub;
    }
    $panels = [];
    foreach ($sites as $site) {
        $id = $site['site_id'];
        $latest = usgs_latest_for_site($id);
        $readings = [];
        $values = [];
        foreach ($latest['readings'] ?? [] as $code => $r) {
            [$v, $u] = river_convert($code, $r['value'], $units);
            $readings[] = [
                'code' => $code,
                'label' => $r['label'],
                'value' => $v,
                'unit' => $u,
                'raw' => $r['value'],
                'time' => $r['time'],
                'ago' => river_time_ago($r['time']),
            ];
            $values[$code] = ['v' => (float) $r['value'], 't' => strtotime($r['time'])];
        }
        // Prefer live latest for the dashboard badge; fall back to snapshots if needed.
        $badge = !empty($ranges[$id]) ? river_badge($ranges[$id], $values) : null;
        if (!$badge && !empty($ranges[$id])) {
            $badge = river_badges_from_snapshots([$id => $ranges[$id]])[$id] ?? null;
        }
        $subs = $alertSubs[$id] ?? [];
        $panels[] = [
            'site_id' => $id,
            'display_name' => $site['display_name'],
            'state' => $site['state'],
            'state_name' => RIVER_STATE_NAMES[$site['state']] ?? $site['state'],
            'county' => $site['county'],
            'usgs_number' => preg_replace('/^[A-Z]+-/', '', $id),
            'href' => river_site_url($id, ['units' => $units]),
            'readings' => $readings,
            'badge' => $badge ? [
                'level' => $badge['level'],
                'label' => $badge['label'],
                'title' => river_badge_title($badge, $units),
                'sentence' => river_badge_sentence($badge, $units),
                'source' => $ranges[$id][$badge['param']]['_source'] ?? 'site',
            ] : null,
            'has_range' => !empty($ranges[$id]),
            'alerts_on' => $subs !== [],
            'alert_channels' => $subs ? [
                'in_app' => (bool) max(array_column($subs, 'channel_in_app')),
                'email' => (bool) max(array_column($subs, 'channel_email')),
                'sms' => (bool) max(array_column($subs, 'channel_sms')),
            ] : null,
            'param_list' => $site['param_list'],
        ];
    }
    return ['sites' => $panels, 'units' => $units, 'count' => count($panels)];
}
