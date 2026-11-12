<?php
/*
 * auth.php - Session login helpers for admin and public river accounts.
 */
if (defined('EO_AUTH_LOADED')) {
    return;
}
define('EO_AUTH_LOADED', true);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/mailer.php';

const AUTH_STAFF_ROLES = ['author', 'editor'];
const AUTH_MEMBER_ROLE = 'member';
const AUTH_STATUS_PENDING = 'pending';
const AUTH_STATUS_APPROVED = 'approved';
const AUTH_STATUS_DENIED = 'denied';

/** @var array<string, string> permission key => users column */
const AUTH_PERMISSIONS = [
    'library' => 'perm_library',
    'tools' => 'perm_tools',
    'own_ranges' => 'perm_own_ranges',
    'fishing_reports' => 'perm_fishing_reports',
];

/** Customer level: minimum approved-member access. */
const AUTH_CUSTOMER_PERMISSIONS = ['library', 'tools', 'own_ranges', 'fishing_reports'];

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_login_error(?string $message = null): ?string
{
    static $error = null;
    if ($message !== null) {
        $error = $message;
    }
    return $error;
}

function current_user(bool $refresh = false): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null && !$refresh) {
        return $user;
    }

    $stmt = get_db()->prepare(
        'SELECT id, username, email, display_name, phone, role, account_status,
                perm_library, perm_tools, perm_own_ranges, perm_fishing_reports,
                email_verified_at, phone_verified_at, reviewed_at, reviewed_by
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if ($user && ($user['account_status'] ?? '') === AUTH_STATUS_DENIED) {
        logout_user();
        $user = null;
    }

    return $user;
}

function is_staff(?array $user = null): bool
{
    $user ??= current_user();
    return $user && in_array($user['role'], AUTH_STAFF_ROLES, true);
}

function is_member(?array $user = null): bool
{
    $user ??= current_user();
    return $user && $user['role'] === AUTH_MEMBER_ROLE;
}

function account_status(?array $user = null): string
{
    $user ??= current_user();
    if (!$user) {
        return AUTH_STATUS_PENDING;
    }
    $status = (string) ($user['account_status'] ?? AUTH_STATUS_APPROVED);
    return in_array($status, [AUTH_STATUS_PENDING, AUTH_STATUS_APPROVED, AUTH_STATUS_DENIED], true)
        ? $status
        : AUTH_STATUS_APPROVED;
}

function account_is_approved(?array $user = null): bool
{
    $user ??= current_user();
    return $user && account_status($user) === AUTH_STATUS_APPROVED;
}

function user_has_permission(string $permission, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || !account_is_approved($user)) {
        return false;
    }
    if (is_staff($user)) {
        return true;
    }
    $col = AUTH_PERMISSIONS[$permission] ?? null;
    if ($col === null) {
        return false;
    }
    return !empty($user[$col]);
}

function login_user(string $username, string $password): bool
{
    auth_login_error('');
    $stmt = get_db()->prepare(
        'SELECT id, username, password_hash, role, account_status
         FROM users WHERE username = ? OR email = ?'
    );
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        auth_login_error('Invalid username/email or password.');
        return false;
    }
    if (($user['account_status'] ?? '') === AUTH_STATUS_DENIED) {
        auth_login_error('This account was denied. Contact the site admin.');
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    return true;
}

function register_member(string $username, string $email, string $password, string $displayName = ''): array
{
    $username = trim($username);
    $email = strtolower(trim($email));
    $displayName = trim($displayName);
    $errors = [];

    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3–50 letters, numbers, or underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($displayName !== '' && mb_strlen($displayName) > 100) {
        $errors[] = 'Display name is too long.';
    }
    if ($errors) {
        return $errors;
    }

    $db = get_db();
    $check = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        return ['That username or email is already registered.'];
    }

    $name = $displayName !== '' ? $displayName : $username;
    try {
        $db->prepare(
            'INSERT INTO users (
                username, email, display_name, password_hash, role, account_status,
                perm_library, perm_tools, perm_own_ranges, perm_fishing_reports
             ) VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0)'
        )->execute([
            $username,
            $email,
            $name,
            password_hash($password, PASSWORD_DEFAULT),
            AUTH_MEMBER_ROLE,
            AUTH_STATUS_PENDING,
        ]);
    } catch (PDOException $e) {
        error_log('register_member: ' . $e->getMessage());
        return ['Could not create the account. Try again.'];
    }

    auth_email_registration_pending($email, $name, $username);

    return [];
}

function auth_email_registration_pending(string $email, string $displayName, string $username): bool
{
    if ($email === '' || !mailer_configured()) {
        return false;
    }
    $login = mailer_absolute_url('services/account/login.php');
    $body = "Hi {$displayName},\n\n"
        . "Thanks for creating an Earth Odyssey account ({$username}).\n\n"
        . "An admin still needs to approve your account before you can use the library, tools, "
        . "personal fishability ranges, and fishing reports. We will email you again when it is ready.\n\n"
        . "You can sign in anytime to check status:\n{$login}\n\n"
        . "— Earth Odyssey\n";
    return mailer_send($email, 'Earth Odyssey account received — awaiting approval', $body);
}

function auth_email_account_approved(string $email, string $displayName, string $username): bool
{
    if ($email === '' || !mailer_configured()) {
        return false;
    }
    $login = mailer_absolute_url('services/account/login.php');
    $body = "Hi {$displayName},\n\n"
        . "Good news — your Earth Odyssey account ({$username}) is approved and ready to use.\n\n"
        . "Sign in here:\n{$login}\n\n"
        . "— Earth Odyssey\n";
    return mailer_send($email, 'Earth Odyssey account approved — ready to use', $body);
}

function auth_permission_labels(): array
{
    return [
        'library' => 'Library',
        'tools' => 'Tools',
        'own_ranges' => 'Own fishability ranges',
        'fishing_reports' => 'Fishing reports',
    ];
}

function auth_list_accounts(): array
{
    return get_db()->query(
        'SELECT id, username, email, display_name, role, account_status,
                perm_library, perm_tools, perm_own_ranges, perm_fishing_reports,
                reviewed_at, created_at
         FROM users
         ORDER BY
           FIELD(account_status, \'pending\', \'approved\', \'denied\'),
           created_at DESC'
    )->fetchAll();
}

function auth_pending_count(): int
{
    return (int) get_db()->query(
        "SELECT COUNT(*) FROM users WHERE account_status = 'pending'"
    )->fetchColumn();
}

/**
 * Approve, deny, or update permissions for an account.
 *
 * @param array<string, bool|int> $perms keyed by AUTH_PERMISSIONS keys
 */
function auth_review_account(int $accountId, string $status, array $perms, int $reviewerId, ?string $role = null): array
{
    if (!in_array($status, [AUTH_STATUS_PENDING, AUTH_STATUS_APPROVED, AUTH_STATUS_DENIED], true)) {
        return ['Invalid account status.'];
    }

    $stmt = get_db()->prepare(
        'SELECT id, username, email, display_name, role, account_status FROM users WHERE id = ?'
    );
    $stmt->execute([$accountId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['Account not found.'];
    }
    if ((int) $row['id'] === $reviewerId && $status === AUTH_STATUS_DENIED) {
        return ['You cannot deny your own account.'];
    }

    $previousStatus = (string) ($row['account_status'] ?? AUTH_STATUS_PENDING);
    $newRole = $row['role'];
    if ($role !== null) {
        if (!in_array($role, ['author', 'editor', AUTH_MEMBER_ROLE], true)) {
            return ['Invalid role.'];
        }
        if ((int) $row['id'] === $reviewerId && $role !== 'editor') {
            return ['You cannot remove editor access from your own account.'];
        }
        $newRole = $role;
    }

    $flags = [];
    foreach (AUTH_PERMISSIONS as $key => $col) {
        $flags[$col] = !empty($perms[$key]) ? 1 : 0;
    }
    if ($status === AUTH_STATUS_DENIED) {
        foreach ($flags as $col => $_) {
            $flags[$col] = 0;
        }
    } elseif ($status === AUTH_STATUS_APPROVED && in_array($newRole, AUTH_STAFF_ROLES, true)) {
        foreach ($flags as $col => $_) {
            $flags[$col] = 1;
        }
    }

    try {
        get_db()->prepare(
            'UPDATE users SET
                role = ?,
                account_status = ?,
                perm_library = ?,
                perm_tools = ?,
                perm_own_ranges = ?,
                perm_fishing_reports = ?,
                reviewed_at = CURRENT_TIMESTAMP,
                reviewed_by = ?
             WHERE id = ?'
        )->execute([
            $newRole,
            $status,
            $flags['perm_library'],
            $flags['perm_tools'],
            $flags['perm_own_ranges'],
            $flags['perm_fishing_reports'],
            $reviewerId,
            $accountId,
        ]);
    } catch (PDOException $e) {
        error_log('auth_review_account: ' . $e->getMessage());
        return ['Could not update the account.'];
    }

    if (
        $status === AUTH_STATUS_APPROVED
        && $previousStatus !== AUTH_STATUS_APPROVED
        && !empty($row['email'])
    ) {
        auth_email_account_approved(
            (string) $row['email'],
            (string) ($row['display_name'] ?: $row['username']),
            (string) $row['username']
        );
    }

    return [];
}

function auth_update_contact(int $userId, ?string $email = null, ?string $phone = null, ?string $displayName = null): array
{
    $errors = [];
    $sets = [];
    $args = [];

    if ($email !== null) {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Enter a valid email address.';
        } else {
            $sets[] = 'email = ?';
            $args[] = $email;
            $sets[] = 'email_verified_at = NULL';
        }
    }
    if ($phone !== null) {
        $phone = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($phone === '') {
            $sets[] = 'phone = NULL';
            $sets[] = 'phone_verified_at = NULL';
        } elseif (!preg_match('/^\+?[1-9]\d{9,14}$/', $phone)) {
            $errors[] = 'Phone must be in E.164 form, e.g. +15551234567.';
        } else {
            if ($phone[0] !== '+') {
                $phone = '+1' . preg_replace('/\D/', '', $phone);
            }
            $sets[] = 'phone = ?';
            $args[] = $phone;
            $sets[] = 'phone_verified_at = NULL';
        }
    }
    if ($displayName !== null) {
        $displayName = trim($displayName);
        if (mb_strlen($displayName) > 100) {
            $errors[] = 'Display name is too long.';
        } else {
            $sets[] = 'display_name = ?';
            $args[] = $displayName;
        }
    }
    if ($errors || !$sets) {
        return $errors;
    }

    $args[] = $userId;
    try {
        get_db()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($args);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'unique')) {
            return ['That email is already in use.'];
        }
        error_log('auth_update_contact: ' . $e->getMessage());
        return ['Could not save. Try again.'];
    }
    return [];
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        $msg = 'Your session expired. Go back, refresh the page, and try again.';
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
            && $_POST === []
            && $_FILES === []
        ) {
            $msg = 'Upload was too large for the server (post_max_size is ' . ini_get('post_max_size') . '). Try fewer photos at once.';
        }
        if (str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo $msg;
        exit;
    }
}

function logout_user(): void
{
    start_session();
    $_SESSION = [];
    session_destroy();
}

function login_next(?string $next = null): string
{
    $raw = $next ?? (string) ($_POST['next'] ?? $_GET['next'] ?? '');
    $path = parse_url($raw, PHP_URL_PATH) ?: '';
    $query = parse_url($raw, PHP_URL_QUERY);
    $user = current_user();
    if ($user && !account_is_approved($user) && !is_staff($user)) {
        return url('services/account/pending.php');
    }
    if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) || str_starts_with($raw, '//')) {
        if ($user && !is_staff($user)) {
            return url('services/rivers/');
        }
        return url('admin/index.php');
    }
    foreach (['admin/', 'services/rivers/', 'services/account/', 'books/'] as $prefix) {
        $base = rtrim(parse_url(url($prefix), PHP_URL_PATH) ?: '', '/') . '/';
        if (str_starts_with($path, $base) || $path === rtrim($base, '/')) {
            return $path . ($query ? '?' . $query : '');
        }
    }
    return url('admin/index.php');
}

function auth_login_url(?string $next = null): string
{
    $raw = $next ?? (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($raw, PHP_URL_PATH) ?: '';
    $adminBase = rtrim(parse_url(url('admin/'), PHP_URL_PATH) ?: '', '/') . '/';
    $login = str_starts_with($path, $adminBase)
        ? url('admin/login.php')
        : url('services/account/login.php');
    return $login . ($raw !== '' ? '?next=' . rawurlencode($raw) : '');
}

function wants_json_response(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (wants_json_response()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'You are not logged in. Refresh and sign in again.']);
            exit;
        }
        header('Location: ' . auth_login_url());
        exit;
    }

    return $user;
}

function require_approved(): array
{
    $user = require_login();
    if (account_is_approved($user)) {
        return $user;
    }
    if (wants_json_response()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Your account is awaiting admin approval.']);
        exit;
    }
    header('Location: ' . url('services/account/pending.php'));
    exit;
}

function require_permission(string $permission): array
{
    $user = require_approved();
    if (user_has_permission($permission, $user)) {
        return $user;
    }
    if (wants_json_response()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'You do not have permission for that.']);
        exit;
    }
    http_response_code(403);
    $labels = auth_permission_labels();
    $label = $labels[$permission] ?? $permission;
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Access denied</title></head><body style="font-family:system-ui,sans-serif;max-width:36rem;margin:3rem auto;padding:0 1rem">'
        . '<h1>Access denied</h1>'
        . '<p>Your account does not include <strong>' . htmlspecialchars($label) . '</strong>. Ask an admin to update your permissions.</p>'
        . '<p><a href="' . htmlspecialchars(url()) . '">Home</a></p></body></html>';
    exit;
}

function require_staff(): array
{
    $user = require_login();
    if (!is_staff($user) || !account_is_approved($user)) {
        http_response_code(403);
        if (wants_json_response()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Access denied.']);
            exit;
        }
        header('Location: ' . url('services/rivers/'));
        exit;
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_staff();
    if ($user['role'] !== $role) {
        http_response_code(403);
        if (wants_json_response()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Access denied.']);
            exit;
        }
        echo 'Access denied.';
        exit;
    }

    return $user;
}
