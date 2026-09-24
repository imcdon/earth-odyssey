<?php
/*
 * account.php - Change the logged-in user's password.
 */
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = get_db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $hash = (string) $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 12) {
        $error = 'New password must be at least 12 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } elseif (password_verify($new, $hash)) {
        $error = 'New password must be different from the current one.';
    } else {
        get_db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        session_regenerate_id(true);
        $message = 'Password changed.';
    }
}

$page_theme = 'admin';
$page_title = 'Account | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <h1 class="section-title">Change Password</h1>
    <p class="section-intro"><a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a></p>

    <?php if ($error): ?>
        <p class="form-error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($message): ?>
        <p class="form-success"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form class="admin-form" method="post" action="">
        <?= csrf_field() ?>
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">

        <label for="new_password">New password (at least 12 characters)</label>
        <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">

        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="12" autocomplete="new-password">

        <button type="submit" class="btn-primary">Change password</button>
    </form>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
