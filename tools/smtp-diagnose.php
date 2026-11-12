<?php
/*
 * smtp-diagnose.php - Show where SMTP handshake fails (no secrets printed).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/mailer.php';

$mail = notify_config()['mail'] ?? [];
$host = (string) ($mail['smtp_host'] ?? '');
$port = (int) ($mail['smtp_port'] ?? 587);
$user = (string) ($mail['smtp_user'] ?? '');
$pass = (string) ($mail['smtp_pass'] ?? '');
$secure = (string) ($mail['smtp_secure'] ?? 'tls');
$from = (string) ($mail['from'] ?? '');
$to = trim((string) ($argv[1] ?? $from));

$hosts = array_values(array_unique(array_filter([
    $host,
    'smtp.gmail.com',
    'mail.webstarbusinessservices.com',
    'smtp.webstarbusinessservices.com',
])));

function smtp_try(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to): void
{
    echo "=== {$host}:{$port} ({$secure}) ===" . PHP_EOL;
    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
    if (!$fp) {
        echo "connect: FAIL {$errno} {$errstr}" . PHP_EOL . PHP_EOL;
        return;
    }
    stream_set_timeout($fp, 15);
    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($data);
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    echo 'banner: ' . $read() . PHP_EOL;
    $write('EHLO earth-odyssey.local');
    echo 'ehlo: ' . strtok($read(), "\n") . PHP_EOL;

    if ($secure === 'tls') {
        $write('STARTTLS');
        $tls = $read();
        echo 'starttls: ' . $tls . PHP_EOL;
        if (!str_starts_with($tls, '220')) {
            fclose($fp);
            echo PHP_EOL;
            return;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            echo 'tls_upgrade: FAIL' . PHP_EOL . PHP_EOL;
            fclose($fp);
            return;
        }
        echo 'tls_upgrade: ok' . PHP_EOL;
        $write('EHLO earth-odyssey.local');
        echo 'ehlo2: ' . strtok($read(), "\n") . PHP_EOL;
    }

    if ($user !== '') {
        $write('AUTH LOGIN');
        echo 'auth_login: ' . $read() . PHP_EOL;
        $write(base64_encode($user));
        echo 'auth_user: ' . $read() . PHP_EOL;
        $write(base64_encode($pass));
        $auth = $read();
        echo 'auth_pass: ' . $auth . PHP_EOL;
        if (!str_starts_with($auth, '235')) {
            fclose($fp);
            echo PHP_EOL;
            return;
        }
    }

    $write('MAIL FROM:<' . $from . '>');
    echo 'mail_from: ' . $read() . PHP_EOL;
    $write('RCPT TO:<' . $to . '>');
    echo 'rcpt_to: ' . $read() . PHP_EOL;
    $write('QUIT');
    $read();
    fclose($fp);
    echo 'result: auth/envelope ok' . PHP_EOL . PHP_EOL;
}

echo 'configured_host: ' . ($host !== '' ? $host : '(empty)') . PHP_EOL;
echo 'user_set: ' . ($user !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'pass_set: ' . ($pass !== '' ? 'yes' : 'no') . PHP_EOL;
echo 'from: ' . $from . PHP_EOL;
echo 'to: ' . $to . PHP_EOL . PHP_EOL;

foreach ($hosts as $h) {
    smtp_try($h, $port, $secure, $user, $pass, $from, $to);
}
