<?php
/*
 * test-account-mail.php - CLI smoke test for account pending/approved emails.
 * Usage:
 *   C:\xampp\php\php.exe tools/test-account-mail.php you@example.com
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$to = trim((string) ($argv[1] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/test-account-mail.php you@example.com\n");
    exit(1);
}

$cfgFile = __DIR__ . '/../includes/notify.config.php';
echo 'config_file: ' . (is_file($cfgFile) ? 'found' : 'MISSING (copy notify.config.example.php)') . PHP_EOL;
echo 'mailer_configured: ' . (mailer_configured() ? 'yes' : 'no') . PHP_EOL;

$mail = notify_config()['mail'] ?? [];
echo 'enabled: ' . (!empty($mail['enabled']) ? 'yes' : 'no') . PHP_EOL;
echo 'from: ' . ((string) ($mail['from'] ?? '') !== '' ? 'set' : 'empty') . PHP_EOL;
echo 'smtp_host: ' . ((string) ($mail['smtp_host'] ?? '') !== '' ? 'set' : 'empty (will use PHP mail())') . PHP_EOL;
echo 'site_url: ' . ((string) ($mail['site_url'] ?? '') !== '' ? (string) $mail['site_url'] : '(auto from request host)') . PHP_EOL;
echo 'to: ' . $to . PHP_EOL;

if (!mailer_configured()) {
    fwrite(STDERR, "Mail is not configured. Copy includes/notify.config.example.php to includes/notify.config.php,\n"
        . "set mail.enabled=true, from, and SMTP (or rely on PHP mail()).\n");
    exit(2);
}

$pending = auth_email_registration_pending($to, 'Test User', 'testuser');
echo 'pending_email: ' . ($pending ? 'sent' : 'FAILED') . PHP_EOL;

$approved = auth_email_account_approved($to, 'Test User', 'testuser');
echo 'approved_email: ' . ($approved ? 'sent' : 'FAILED') . PHP_EOL;

exit(($pending && $approved) ? 0 : 3);
