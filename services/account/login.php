<?php
/*
 * login.php - Public angler (and staff) login for the river tool.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

start_session();
if (current_user()) {
    header('Location: ' . login_next());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login_user($username, $password)) {
        header('Location: ' . login_next());
        exit;
    }
    $error = auth_login_error() ?: 'Invalid username/email or password.';
}

$page_theme = 'rivers';
$page_title = 'Sign in | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page">
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a></p>
            <h1 class="rivers-title">Sign in</h1>
            <p class="rivers-intro">Track saved rivers, fishability ranges, and alerts.</p>
        </div>
    </header>

    <section class="container admin-page">
        <?php if ($error): ?>
            <p class="form-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form class="admin-form" method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars((string) ($_POST['next'] ?? $_GET['next'] ?? '')) ?>">
            <label for="username">Username or email</label>
            <input type="text" id="username" name="username" required autocomplete="username" value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit" class="btn-primary">Sign in</button>
        </form>
        <p class="rivers-intro" style="margin-top:1rem">
            No account yet? <a href="<?= htmlspecialchars(url('services/account/register.php') . (isset($_GET['next']) ? '?next=' . rawurlencode((string) $_GET['next']) : '')) ?>">Create one</a>
        </p>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
