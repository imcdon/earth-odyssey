<?php
/*
 * register.php - Create a public member account for the river dashboard.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

start_session();
if (current_user()) {
    header('Location: ' . login_next());
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $errors = register_member(
        (string) ($_POST['username'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['display_name'] ?? '')
    );
    if (!$errors) {
        login_user(trim((string) $_POST['username']), (string) $_POST['password']);
        header('Location: ' . url('services/account/pending.php'));
        exit;
    }
}

$page_theme = 'rivers';
$page_title = 'Create account | River Gauges | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page">
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker"><a href="<?= htmlspecialchars(url('services/rivers/')) ?>">Stream gauges</a></p>
            <h1 class="rivers-title">Create account</h1>
            <p class="rivers-intro">Request access to save rivers, set fishability ranges, and get alerts. An admin must approve new accounts.</p>
        </div>
    </header>

    <section class="container admin-page">
        <?php foreach ($errors as $e): ?>
            <p class="form-error"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
        <form class="admin-form" method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= htmlspecialchars((string) ($_POST['next'] ?? $_GET['next'] ?? '')) ?>">

            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username" pattern="[A-Za-z0-9_]{3,50}" value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>">

            <label for="display_name">Display name (optional)</label>
            <input type="text" id="display_name" name="display_name" autocomplete="nickname" value="<?= htmlspecialchars((string) ($_POST['display_name'] ?? '')) ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">

            <button type="submit" class="btn-primary">Create account</button>
        </form>
        <p class="rivers-intro" style="margin-top:1rem">
            Already have an account? <a href="<?= htmlspecialchars(url('services/account/login.php')) ?>">Sign in</a>
        </p>
    </section>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
