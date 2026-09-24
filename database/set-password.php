<?php
/*
 * set-password.php - CLI: set or reset an admin account's password.
 * Run: php database/set-password.php <username>
 * The password is typed at the prompt (never passed as an argument, so it stays out of shell history).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/db.php';

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, "Usage: php database/set-password.php <username>\n");
    exit(1);
}

$stmt = get_db()->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if (!$stmt->fetch()) {
    fwrite(STDERR, "No user named \"{$username}\".\n");
    exit(1);
}

echo "New password for {$username} (min 12 characters; input is visible): ";
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

get_db()->prepare('UPDATE users SET password_hash = ? WHERE username = ?')
    ->execute([password_hash($password, PASSWORD_DEFAULT), $username]);

echo "Password updated for {$username}.\n";
