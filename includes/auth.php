<?php
/*
 * auth.php - Session login helpers for admin area.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';

function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function current_user(): ?array
{
    start_session();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = get_db()->prepare('SELECT id, username, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

function login_user(string $username, string $password): bool
{
    $stmt = get_db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    return true;
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
        echo 'Your session expired. Go back, refresh the page, and try again.';
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
    if ($path === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $raw) || str_starts_with($raw, '//')) {
        return url('admin/index.php');
    }
    foreach (['admin/', 'services/rivers/'] as $prefix) {
        $base = rtrim(parse_url(url($prefix), PHP_URL_PATH) ?: '', '/') . '/';
        if (str_starts_with($path, $base) || $path === rtrim($base, '/')) {
            return $path . ($query ? '?' . $query : '');
        }
    }
    return url('admin/index.php');
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $here = (string) ($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . url('admin/login.php') . ($here !== '' ? '?next=' . rawurlencode($here) : ''));
        exit;
    }

    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    return $user;
}
