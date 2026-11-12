<?php
/*
 * mailer.php - Thin SMTP / mail() wrapper. Config: includes/notify.config.php (gitignored).
 */

function notify_config(): array
{
    static $cfg;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = __DIR__ . '/notify.config.php';
    $cfg = is_file($file) ? (require $file) : [];
    return is_array($cfg) ? $cfg : [];
}

function mailer_configured(): bool
{
    $c = notify_config();
    return !empty($c['mail']['enabled']) && !empty($c['mail']['from']);
}

/** Absolute URL for email bodies (optional notify.config mail.site_url, else current host). */
function mailer_absolute_url(string $path = ''): string
{
    $c = notify_config()['mail'] ?? [];
    $base = rtrim((string) ($c['site_url'] ?? ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    $path = (string) (function_exists('url') ? url($path) : '/' . ltrim($path, '/'));
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }
    return $base . ($path === '' ? '/' : $path);
}

function mailer_send(string $to, string $subject, string $body): bool
{
    $c = notify_config()['mail'] ?? [];
    if (empty($c['enabled']) || empty($c['from'])) {
        error_log('mailer_send: mail not configured');
        return false;
    }
    $from = (string) $c['from'];
    $fromName = (string) ($c['from_name'] ?? 'Earth Odyssey');
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $from),
        'Reply-To: ' . ($c['reply_to'] ?? $from),
        'X-Mailer: EarthOdyssey',
    ];

    if (!empty($c['smtp_host'])) {
        return mailer_smtp_send($to, $subject, $body, $c, $headers);
    }

    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
}

function mailer_smtp_send(string $to, string $subject, string $body, array $c, array $headers): bool
{
    $host = $c['smtp_host'];
    $port = (int) ($c['smtp_port'] ?? 587);
    $user = (string) ($c['smtp_user'] ?? '');
    $pass = (string) ($c['smtp_pass'] ?? '');
    $secure = (string) ($c['smtp_secure'] ?? 'tls'); // tls|ssl|none

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("mailer_smtp: connect failed {$errno} {$errstr}");
        return false;
    }
    stream_set_timeout($fp, 20);
    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $read();
    $write('EHLO earth-odyssey.local');
    $read();
    if ($secure === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (!str_starts_with($resp, '220')) {
            fclose($fp);
            return false;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return false;
        }
        $write('EHLO earth-odyssey.local');
        $read();
    }
    if ($user !== '') {
        $write('AUTH LOGIN');
        $read();
        $write(base64_encode($user));
        $read();
        $write(base64_encode($pass));
        $auth = $read();
        if (!str_starts_with($auth, '235')) {
            error_log('mailer_smtp: auth failed');
            fclose($fp);
            return false;
        }
    }
    $from = (string) $c['from'];
    $write('MAIL FROM:<' . $from . '>');
    $read();
    $write('RCPT TO:<' . $to . '>');
    $read();
    $write('DATA');
    $read();
    $payload = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
        . implode("\r\n", $headers) . "\r\n\r\n"
        . str_replace("\n.", "\n..", $body) . "\r\n.";
    $write($payload);
    $ok = str_starts_with($read(), '250');
    $write('QUIT');
    fclose($fp);
    return $ok;
}
