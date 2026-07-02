<?php
/*
 * login.php - Admin login page.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';

start_session();
if (current_user()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login_user($username, $password)) {
        header('Location: ' . url('admin/index.php'));
        exit;
    }
    $error = 'Invalid username or password.';
}

$page_title = 'Admin Login | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title">Admin Login</h1>
    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form class="admin-form" method="post" action="">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autocomplete="username">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="btn-primary">Log in</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
