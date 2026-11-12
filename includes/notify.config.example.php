<?php
/*
 * notify.config.example.php - Copy to notify.config.php (gitignored).
 * Used for account approval email, river alert email (SMTP or PHP mail), and RingCentral SMS.
 */
return [
    'mail' => [
        'enabled' => false,
        'from' => 'alerts@example.com',
        'from_name' => 'Earth Odyssey',
        'reply_to' => 'alerts@example.com',
        // Public site origin for links in emails (recommended on production).
        // 'site_url' => 'https://earth-odyssey.com',
        // Leave smtp_host empty to use PHP mail().
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_secure' => 'tls', // tls | ssl | none
        'smtp_user' => '',
        'smtp_pass' => '',
    ],
    'ringcentral' => [
        'enabled' => false,
        'server' => 'https://platform.ringcentral.com',
        'client_id' => '',
        'client_secret' => '',
        'jwt' => '',
        'from' => '+1XXXXXXXXXX', // SMS-enabled number on the JWT extension
    ],
];
