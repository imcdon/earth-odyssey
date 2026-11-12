<?php
/*
 * ringcentral.php - Send SMS via RingCentral REST API (JWT).
 * Config keys under notify.config.php → ringcentral.
 * Requires the from number to be SMS-enabled (SmsSender) on the JWT extension.
 */

function ringcentral_configured(): bool
{
    $c = notify_config()['ringcentral'] ?? [];
    return !empty($c['enabled'])
        && !empty($c['client_id'])
        && !empty($c['client_secret'])
        && !empty($c['jwt'])
        && !empty($c['from']);
}

function ringcentral_token(): ?string
{
    static $cached;
    if ($cached) {
        return $cached;
    }
    if (!ringcentral_configured()) {
        return null;
    }
    $c = notify_config()['ringcentral'];
    $server = rtrim((string) ($c['server'] ?? 'https://platform.ringcentral.com'), '/');
    $body = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $c['jwt'],
    ]);
    $ch = curl_init($server . '/restapi/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($c['client_id'] . ':' . $c['client_secret']),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300 || !$raw) {
        error_log('ringcentral_token: HTTP ' . $code . ' ' . (string) $raw);
        return null;
    }
    $json = json_decode($raw, true);
    $cached = $json['access_token'] ?? null;
    return $cached;
}

function ringcentral_send_sms(string $to, string $text): array
{
    if (!ringcentral_configured()) {
        return ['ok' => false, 'error' => 'RingCentral SMS is not configured.'];
    }
    $token = ringcentral_token();
    if (!$token) {
        return ['ok' => false, 'error' => 'Could not authenticate with RingCentral.'];
    }
    $c = notify_config()['ringcentral'];
    $server = rtrim((string) ($c['server'] ?? 'https://platform.ringcentral.com'), '/');
    $to = preg_replace('/[^\d+]/', '', $to) ?? $to;
    if ($to !== '' && $to[0] !== '+') {
        $to = '+1' . preg_replace('/\D/', '', $to);
    }
    $payload = json_encode([
        'from' => ['phoneNumber' => $c['from']],
        'to' => [['phoneNumber' => $to]],
        'text' => mb_substr($text, 0, 1000),
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($server . '/restapi/v1.0/account/~/extension/~/sms');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        error_log('ringcentral_send_sms: HTTP ' . $code . ' ' . (string) $raw);
        return ['ok' => false, 'error' => 'SMS send failed.', 'status' => $code];
    }
    return ['ok' => true, 'response' => json_decode((string) $raw, true)];
}
