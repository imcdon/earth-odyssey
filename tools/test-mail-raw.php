<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/mailer.php';

$to = trim((string) ($argv[1] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/test-mail-raw.php you@example.com\n");
    exit(1);
}

error_clear_last();
$ok = mailer_send($to, 'Earth Odyssey raw mail test', "Raw mailer_send test at " . gmdate('c') . " UTC\n");
$err = error_get_last();
echo 'mailer_send: ' . ($ok ? 'ok' : 'FAILED') . PHP_EOL;
if ($err) {
    echo 'last_error: ' . ($err['message'] ?? '') . PHP_EOL;
}
