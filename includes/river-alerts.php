<?php
/*
 * river-alerts.php - Alert subscriptions, in-app events, and delivery evaluation.
 */
require_once __DIR__ . '/river-user.php';
require_once __DIR__ . '/river-auto-ranges.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/ringcentral.php';

const RIVER_ALERT_PARAMS = ['00060' => 'Flow', '00065' => 'Gage height', '00010' => 'Water temperature'];

function river_alert_subscription(int $userId, string $siteId, string $param = '00060'): ?array
{
    try {
        $stmt = get_db()->prepare(
            'SELECT * FROM river_alert_subscriptions WHERE user_id = ? AND site_id = ? AND param = ?'
        );
        $stmt->execute([$userId, $siteId, $param]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function river_alert_subscriptions_for_user(int $userId, ?string $siteId = null): array
{
    try {
        if ($siteId) {
            $stmt = get_db()->prepare('SELECT * FROM river_alert_subscriptions WHERE user_id = ? AND site_id = ?');
            $stmt->execute([$userId, $siteId]);
        } else {
            $stmt = get_db()->prepare('SELECT * FROM river_alert_subscriptions WHERE user_id = ? ORDER BY site_id, param');
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function river_alert_save_subscription(int $userId, string $siteId, array $post): array
{
    if (!usgs_valid_site_id($siteId) || !river_site($siteId)) {
        return ['Gauge not found.'];
    }
    $param = (string) ($post['param'] ?? '00060');
    if (!isset(RIVER_ALERT_PARAMS[$param])) {
        return ['Invalid measurement.'];
    }

    $bool = static fn($k, $default = 0) => !empty($post[$k]) ? 1 : (int) $default;
    $email = $bool('channel_email', 1);
    $sms = $bool('channel_sms', 0);
    $inApp = $bool('channel_in_app', 1);
    if (!$email && !$sms && !$inApp) {
        $inApp = 1;
    }

    $user = current_user();
    if ($sms && (!$user || empty($user['phone']))) {
        return ['Add a phone number on your account before enabling SMS alerts.'];
    }
    if ($email && (!$user || empty($user['email']))) {
        return ['Add an email on your account before enabling email alerts.'];
    }

    $cooldown = max(60, min(10080, (int) ($post['cooldown_minutes'] ?? 360)));

    try {
        get_db()->prepare(
            'INSERT INTO river_alert_subscriptions (
                user_id, site_id, param,
                trigger_enter_prime, trigger_leave_fishable, trigger_above_high, trigger_below_low, trigger_unusual,
                channel_in_app, channel_email, channel_sms, cooldown_minutes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                trigger_enter_prime = VALUES(trigger_enter_prime),
                trigger_leave_fishable = VALUES(trigger_leave_fishable),
                trigger_above_high = VALUES(trigger_above_high),
                trigger_below_low = VALUES(trigger_below_low),
                trigger_unusual = VALUES(trigger_unusual),
                channel_in_app = VALUES(channel_in_app),
                channel_email = VALUES(channel_email),
                channel_sms = VALUES(channel_sms),
                cooldown_minutes = VALUES(cooldown_minutes)'
        )->execute([
            $userId, $siteId, $param,
            $bool('trigger_enter_prime', 1),
            $bool('trigger_leave_fishable', 1),
            $bool('trigger_above_high', 1),
            $bool('trigger_below_low', 1),
            $bool('trigger_unusual', 0),
            $inApp, $email, $sms, $cooldown,
        ]);
    } catch (PDOException $e) {
        error_log('river_alert_save_subscription: ' . $e->getMessage());
        return ['Could not save alerts.'];
    }
    return [];
}

function river_alert_delete_subscription(int $userId, string $siteId, string $param): void
{
    try {
        get_db()->prepare(
            'DELETE FROM river_alert_subscriptions WHERE user_id = ? AND site_id = ? AND param = ?'
        )->execute([$userId, $siteId, $param]);
    } catch (PDOException $e) {
        error_log('river_alert_delete_subscription: ' . $e->getMessage());
    }
}

function river_alert_unread_count(int $userId): int
{
    try {
        $stmt = get_db()->prepare('SELECT COUNT(*) FROM river_alert_events WHERE user_id = ? AND read_at IS NULL AND channel_in_app = 1');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function river_alert_events_for_user(int $userId, int $limit = 40): array
{
    try {
        $stmt = get_db()->prepare(
            'SELECT e.*, s.display_name, s.state FROM river_alert_events e
             LEFT JOIN river_sites s ON s.site_id = e.site_id
             WHERE e.user_id = ? AND e.channel_in_app = 1
             ORDER BY e.created_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

function river_alert_mark_read(int $userId, ?int $eventId = null): void
{
    try {
        if ($eventId) {
            get_db()->prepare(
                'UPDATE river_alert_events SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND id = ? AND read_at IS NULL'
            )->execute([$userId, $eventId]);
        } else {
            get_db()->prepare(
                'UPDATE river_alert_events SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL'
            )->execute([$userId]);
        }
    } catch (PDOException $e) {
        error_log('river_alert_mark_read: ' . $e->getMessage());
    }
}

function river_alert_insert_event(array $sub, string $triggerKey, ?string $level, ?float $value, string $message): int
{
    $db = get_db();
    $db->prepare(
        'INSERT INTO river_alert_events (
            user_id, site_id, param, trigger_key, level, value, message,
            channel_in_app, channel_email, channel_sms
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $sub['user_id'], $sub['site_id'], $sub['param'], $triggerKey, $level, $value, $message,
        (int) $sub['channel_in_app'], (int) $sub['channel_email'], (int) $sub['channel_sms'],
    ]);
    return (int) $db->lastInsertId();
}

/* Evaluate all subscriptions against latest snapshots. Returns counts. */
function river_alerts_run(): array
{
    $db = get_db();
    try {
        $subs = $db->query('SELECT * FROM river_alert_subscriptions')->fetchAll();
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'fired' => 0];
    }
    if (!$subs) {
        return ['ok' => true, 'fired' => 0, 'checked' => 0];
    }

    $siteIds = array_values(array_unique(array_column($subs, 'site_id')));
    $values = [];
    foreach (array_chunk($siteIds, 500) as $chunk) {
        $stmt = $db->prepare(
            'SELECT site_id, param, observed_at, value FROM river_snapshots
             WHERE site_id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')
               AND observed_at >= UTC_TIMESTAMP() - INTERVAL 6 HOUR
             ORDER BY observed_at'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $values[$row['site_id']][$row['param']] = [
                'v' => (float) $row['value'],
                't' => strtotime($row['observed_at'] . ' UTC'),
            ];
        }
    }

    $byUser = [];
    foreach ($subs as $sub) {
        $byUser[(int) $sub['user_id']][] = $sub;
    }

    $fired = 0;
    $users = [];
    $idList = array_keys($byUser);
    if ($idList) {
        $stmt = $db->prepare('SELECT id, email, phone, display_name, username FROM users WHERE id IN ('
            . implode(',', array_fill(0, count($idList), '?')) . ')');
        $stmt->execute($idList);
        foreach ($stmt->fetchAll() as $u) {
            $users[(int) $u['id']] = $u;
        }
    }

    foreach ($byUser as $userId => $userSubs) {
        $siteIdsUser = array_values(array_unique(array_column($userSubs, 'site_id')));
        $ranges = river_effective_ranges_for_user($userId, $siteIdsUser);
        foreach ($userSubs as $sub) {
            $siteId = $sub['site_id'];
            $param = $sub['param'];
            $reading = $values[$siteId][$param] ?? null;
            if (!$reading) {
                continue;
            }
            $range = $ranges[$siteId][$param] ?? null;
            if (!$range) {
                continue;
            }
            $level = river_range_level($range, $reading['v']);
            $prev = $sub['last_level'];
            $triggers = [];

            if ($prev !== $level) {
                if ($level === 'prime' && $sub['trigger_enter_prime']) {
                    $triggers[] = 'enter_prime';
                }
                if (in_array($level, ['low', 'high'], true) && $sub['trigger_leave_fishable']
                    && $prev && !in_array($prev, ['low', 'high'], true)) {
                    $triggers[] = 'leave_fishable';
                }
                if ($level === 'high' && $sub['trigger_above_high']) {
                    $triggers[] = 'above_high';
                }
                if ($level === 'low' && $sub['trigger_below_low']) {
                    $triggers[] = 'below_low';
                }
            }

            $unusual = false;
            if ($sub['trigger_unusual']) {
                $unusual = river_auto_is_unusual($siteId, $param, $reading['v']);
                if ($unusual) {
                    $triggers[] = 'unusual';
                }
            }

            if (!$triggers) {
                if ($prev !== $level) {
                    $db->prepare(
                        'UPDATE river_alert_subscriptions SET last_level = ? WHERE id = ?'
                    )->execute([$level, $sub['id']]);
                }
                continue;
            }

            if ($sub['last_fired_at']) {
                $elapsed = time() - strtotime($sub['last_fired_at'] . ' UTC');
                if ($elapsed < (int) $sub['cooldown_minutes'] * 60) {
                    if ($prev !== $level) {
                        $db->prepare(
                            'UPDATE river_alert_subscriptions SET last_level = ? WHERE id = ?'
                        )->execute([$level, $sub['id']]);
                    }
                    continue;
                }
            }

            $site = river_site($siteId);
            $name = $site['display_name'] ?? $siteId;
            $label = RIVER_ALERT_PARAMS[$param] ?? $param;
            [$shown, $unit] = river_convert($param, $reading['v'], 'us');
            $msgParts = [];
            foreach ($triggers as $t) {
                $msgParts[] = match ($t) {
                    'enter_prime' => "{$name}: {$label} is Prime (" . river_format($shown) . " {$unit})",
                    'leave_fishable' => "{$name}: {$label} left the fishable range (" . river_format($shown) . " {$unit})",
                    'above_high' => "{$name}: {$label} is blown out (" . river_format($shown) . " {$unit})",
                    'below_low' => "{$name}: {$label} is too low (" . river_format($shown) . " {$unit})",
                    'unusual' => "{$name}: {$label} is unusual for this time of year (" . river_format($shown) . " {$unit})",
                    default => "{$name}: {$label} update (" . river_format($shown) . " {$unit})",
                };
            }
            $message = implode(' · ', $msgParts);
            $triggerKey = $triggers[0];
            $eventId = river_alert_insert_event($sub, $triggerKey, $level, $reading['v'], $message);

            $u = $users[$userId] ?? null;
            if ($u && $sub['channel_email'] && !empty($u['email'])) {
                $sent = mailer_send(
                    $u['email'],
                    'River alert: ' . $name,
                    $message . "\n\nOpen: " . river_site_url($siteId) . "\n"
                );
                if ($sent) {
                    $db->prepare('UPDATE river_alert_events SET email_sent_at = UTC_TIMESTAMP() WHERE id = ?')
                        ->execute([$eventId]);
                }
            }
            if ($u && $sub['channel_sms'] && !empty($u['phone'])) {
                $sms = ringcentral_send_sms($u['phone'], $message);
                if (!empty($sms['ok'])) {
                    $db->prepare('UPDATE river_alert_events SET sms_sent_at = UTC_TIMESTAMP() WHERE id = ?')
                        ->execute([$eventId]);
                }
            }

            $db->prepare(
                'UPDATE river_alert_subscriptions SET last_level = ?, last_fired_at = UTC_TIMESTAMP() WHERE id = ?'
            )->execute([$level, $sub['id']]);
            $fired++;
        }
    }

    return ['ok' => true, 'fired' => $fired, 'checked' => count($subs)];
}
