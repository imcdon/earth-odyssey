<?php
/*
 * users.php - Approve / deny accounts and set customer permissions (editors only).
 */
require __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_role('editor');
$labels = auth_permission_labels();
$message = '';
$error = '';
$editId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accountId = (int) ($_POST['account_id'] ?? 0);
    $status = (string) ($_POST['account_status'] ?? AUTH_STATUS_PENDING);
    $role = (string) ($_POST['role'] ?? AUTH_MEMBER_ROLE);
    $preset = (string) ($_POST['preset'] ?? '');
    $perms = [];
    foreach (array_keys(AUTH_PERMISSIONS) as $key) {
        $perms[$key] = !empty($_POST['perms'][$key]);
    }
    $anyPerm = in_array(true, $perms, true);
    // Customer preset button, or Approve with no boxes checked → customer minimum.
    if ($preset === 'customer' || ($status === AUTH_STATUS_APPROVED && !$anyPerm)) {
        foreach (AUTH_CUSTOMER_PERMISSIONS as $key) {
            $perms[$key] = true;
        }
    }
    $errors = auth_review_account($accountId, $status, $perms, (int) $admin['id'], $role);
    if ($errors) {
        $error = $errors[0];
        $editId = $accountId;
    } else {
        $message = $status === AUTH_STATUS_APPROVED
            ? 'Account approved and permissions saved.'
            : ($status === AUTH_STATUS_DENIED ? 'Account denied.' : 'Account updated.');
        $editId = 0;
    }
}

$accounts = auth_list_accounts();
$pending = array_values(array_filter($accounts, static fn($a) => ($a['account_status'] ?? '') === AUTH_STATUS_PENDING));
$editing = null;
if ($editId) {
    foreach ($accounts as $a) {
        if ((int) $a['id'] === $editId) {
            $editing = $a;
            break;
        }
    }
}

$page_theme = 'admin';
$page_title = 'Accounts | ' . $site_name;
require __DIR__ . '/../includes/header.php';
?>

<section class="container admin-page">
    <header class="admin-dash-head">
        <div>
            <p class="kicker">Staff</p>
            <h1 class="section-title">Accounts</h1>
            <p class="section-intro">
                <a href="<?= htmlspecialchars(url('admin/index.php')) ?>">&larr; Back to admin</a>
                · Approve signups and set permissions. Customer level includes library, tools, own ranges, and fishing reports.
            </p>
        </div>
    </header>

    <?php if ($error): ?><p class="form-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($message): ?><p class="form-success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

    <?php if ($pending): ?>
        <h2 class="admin-dash-title">Pending approval</h2>
        <ul class="admin-list admin-account-list">
            <?php foreach ($pending as $a): ?>
                <li>
                    <div>
                        <strong><?= htmlspecialchars($a['username']) ?></strong>
                        <?php if ($a['email']): ?>
                            <span class="admin-account-meta"><?= htmlspecialchars((string) $a['email']) ?></span>
                        <?php endif; ?>
                        <span class="admin-account-meta">Requested <?= htmlspecialchars(date('M j, Y', strtotime((string) $a['created_at']))) ?></span>
                    </div>
                    <a class="btn-primary" href="?id=<?= (int) $a['id'] ?>">Review</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="admin-dash-empty">No accounts waiting for approval.</p>
    <?php endif; ?>

    <?php if ($editing): ?>
        <h2 class="admin-dash-title" style="margin-top:1.75rem">Review <?= htmlspecialchars($editing['username']) ?></h2>
        <form class="admin-form admin-account-form" method="post" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="account_id" value="<?= (int) $editing['id'] ?>">

            <p class="admin-account-meta">
                <?= htmlspecialchars((string) ($editing['email'] ?: 'No email')) ?>
                · joined <?= htmlspecialchars(date('M j, Y', strtotime((string) $editing['created_at']))) ?>
            </p>

            <label for="account_status">Decision</label>
            <select id="account_status" name="account_status" required>
                <option value="<?= AUTH_STATUS_APPROVED ?>" <?= ($editing['account_status'] === AUTH_STATUS_APPROVED) ? 'selected' : '' ?>>Approve</option>
                <option value="<?= AUTH_STATUS_DENIED ?>" <?= ($editing['account_status'] === AUTH_STATUS_DENIED) ? 'selected' : '' ?>>Deny</option>
                <option value="<?= AUTH_STATUS_PENDING ?>" <?= ($editing['account_status'] === AUTH_STATUS_PENDING) ? 'selected' : '' ?>>Keep pending</option>
            </select>

            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="<?= AUTH_MEMBER_ROLE ?>" <?= ($editing['role'] === AUTH_MEMBER_ROLE) ? 'selected' : '' ?>>Customer (member)</option>
                <option value="author" <?= ($editing['role'] === 'author') ? 'selected' : '' ?>>Author (staff)</option>
                <option value="editor" <?= ($editing['role'] === 'editor') ? 'selected' : '' ?>>Editor (admin)</option>
            </select>

            <fieldset class="admin-perm-fieldset">
                <legend>Permissions</legend>
                <p class="admin-account-meta">Customer level checks all four. Staff roles always get full access when approved.</p>
                <button type="submit" class="btn-secondary" name="preset" value="customer">Apply customer level</button>
                <?php foreach ($labels as $key => $label): ?>
                    <?php $col = AUTH_PERMISSIONS[$key]; ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="perms[<?= htmlspecialchars($key) ?>]" value="1" <?= !empty($editing[$col]) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="admin-actions">
                <button type="submit" class="btn-primary">Save decision</button>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('admin/users.php')) ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>

    <h2 class="admin-dash-title" style="margin-top:1.75rem">All accounts</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th scope="col">User</th>
                <th scope="col">Status</th>
                <th scope="col">Role</th>
                <th scope="col">Permissions</th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accounts as $a): ?>
                <?php
                $permBits = [];
                foreach ($labels as $key => $label) {
                    if (!empty($a[AUTH_PERMISSIONS[$key]])) {
                        $permBits[] = $label;
                    }
                }
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($a['username']) ?></strong>
                        <?php if ($a['email']): ?><br><span class="admin-account-meta"><?= htmlspecialchars((string) $a['email']) ?></span><?php endif; ?>
                    </td>
                    <td><span class="status-badge status-<?= htmlspecialchars((string) $a['account_status']) ?>"><?= htmlspecialchars((string) $a['account_status']) ?></span></td>
                    <td><?= htmlspecialchars((string) $a['role']) ?></td>
                    <td><?= $permBits ? htmlspecialchars(implode(', ', $permBits)) : '—' ?></td>
                    <td><a href="?id=<?= (int) $a['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
