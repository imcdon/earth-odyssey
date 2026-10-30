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
        $msg = 'Your session expired. Go back, refresh the page, and try again.';
        // Oversized uploads empty $_POST (including the CSRF field) — say so when likely.
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
