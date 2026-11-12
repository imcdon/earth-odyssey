<?php
/*
 * pending.php - Shown while a new account waits for admin approval.
 */
require __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = require_login();
if (account_is_approved($user)) {
    header('Location: ' . (is_staff($user) ? url('admin/index.php') : url('services/rivers/')));
    exit;
}

$page_theme = 'rivers';
$page_title = 'Awaiting approval | ' . $site_name;
require __DIR__ . '/../../includes/header.php';
?>

<div class="rivers-page">
    <header class="rivers-head rivers-head--compact">
        <div class="container">
            <p class="kicker">Account</p>
            <h1 class="rivers-title">Awaiting approval</h1>
            <p class="rivers-intro">
                Thanks, <?= htmlspecialchars((string) ($user['display_name'] ?: $user['username'])) ?>.
                An admin needs to approve your account and set permissions before you can use the library, tools, ranges, or fishing reports.
                We emailed you a confirmation, and we will email you again when your account is ready.
            </p>
            <div class="rivers-head-tools">
                <a class="btn-secondary" href="<?= htmlspecialchars(url('services/account/logout.php')) ?>">Sign out</a>
            </div>
        </div>
    </header>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
