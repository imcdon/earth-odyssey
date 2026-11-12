<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../includes/mailer.php';
$m = notify_config()['mail'] ?? [];
$from = (string) ($m['from'] ?? '');
echo json_encode([
    'configured' => mailer_configured(),
    'enabled' => !empty($m['enabled']),
    'from_ok' => $from !== '' && $from !== 'alerts@example.com',
    'from_name' => (string) ($m['from_name'] ?? ''),
    'smtp_host_set' => ((string) ($m['smtp_host'] ?? '')) !== '',
    'smtp_user_set' => ((string) ($m['smtp_user'] ?? '')) !== '',
    'smtp_pass_set' => ((string) ($m['smtp_pass'] ?? '')) !== '',
    'site_url_set' => ((string) ($m['site_url'] ?? '')) !== '',
], JSON_PRETTY_PRINT) . PHP_EOL;
