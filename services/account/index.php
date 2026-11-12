<?php
/*
 * index.php - Account settings: email, phone (for SMS alerts), display name.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = require_login();
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $errors = auth_update_contact(
        (int) $user['id'],
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['phone'] ?? ''),
        (string) ($_POST['display_name'] ?? '')
    );
    if (!$errors) {
        $saved = true;
        $user = current_user(true);
    }
}

$page_theme = 'rivers';
$page_title = 'Account | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page">
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a> &middot; Account</p>
            <h1 class="rivers-title">Your account</h1>
            <p class="rivers-intro">Email and phone are used for river alerts.</p>
            <div class="rivers-head-tools">
                <a class="btn-secondary" href="<?= htmlspecialchars(url('services/rivers/')) ?>">Dashboard</a>
                <a class="rivers-map-back" href="<?= htmlspecialchars(url('services/account/logout.php')) ?>">Sign out</a>
            </div>
        </div>
    </header>

    <section class="container admin-page">
        <?php if ($saved): ?><p class="form-success">Saved.</p><?php endif; ?>
        <?php foreach ($errors as $e): ?><p class="form-error"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>

        <form class="admin-form" method="post" action="">
            <?= csrf_field() ?>
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled>

            <label for="display_name">Display name</label>
            <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars((string) ($user['display_name'] ?? '')) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>">

            <label for="phone">Mobile for SMS (E.164)</label>
            <input type="tel" id="phone" name="phone" placeholder="+15551234567" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>">

            <button type="submit" class="btn-primary">Save</button>
        </form>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
