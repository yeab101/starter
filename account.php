<?php
// Change the logged-in user's own password.
require __DIR__ . '/includes/config.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_row()[0] ?? '';

    if (!password_verify($current, $hash)) {
        $errors[] = 'Your current password is incorrect.';
    }
    if (strlen($newPass) < 6) {
        $errors[] = 'The new password must be at least 6 characters.';
    } elseif ($newPass !== ($_POST['new_password_confirm'] ?? '')) {
        $errors[] = 'The two new passwords do not match.';
    }

    if (!$errors) {
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $newHash, $_SESSION['user_id']);
        $stmt->execute();
        flash('Your password was changed.');
        redirect('dashboard.php');
    }
}

$title = 'Change Password';
require __DIR__ . '/includes/header.php';
?>
<h1>Change Password</h1>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form class="card form-grid" method="post">
    <?= csrf_field() ?>
    <div>
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div>
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" minlength="6" required autocomplete="new-password">
    </div>
    <div>
        <label for="new_password_confirm">Confirm new password</label>
        <input type="password" id="new_password_confirm" name="new_password_confirm" minlength="6" required autocomplete="new-password">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="dashboard.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
