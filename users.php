<?php
// User accounts (administrators and registrars). Administrators only.
require __DIR__ . '/includes/config.php';
require_admin();

$errors = [];
$new = ['username' => '', 'full_name' => '', 'role' => 'registrar'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $_SESSION['user_id']) {
            flash('You cannot delete your own account while logged in.', 'error');
        } else {
            $admins = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetch_row()[0];
            $stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            if (!$target) {
                flash('That user was not found.', 'error');
            } elseif ($target['role'] === 'admin' && $admins <= 1) {
                flash('The last administrator account cannot be deleted.', 'error');
            } else {
                $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                flash('The user account was deleted.');
            }
        }
        redirect('users.php');
    }

    if ($action === 'create') {
        $new['username']  = trim($_POST['username'] ?? '');
        $new['full_name'] = trim($_POST['full_name'] ?? '');
        $new['role']      = $_POST['role'] ?? '';
        $password         = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $new['username'])) {
            $errors[] = 'Username must be 3 to 50 characters: letters, numbers, "_" or ".".';
        }
        if ($new['full_name'] === '' || strlen($new['full_name']) > 100) {
            $errors[] = 'Please enter the full name (up to 100 characters).';
        }
        if (!in_array($new['role'], ['admin', 'registrar'], true)) {
            $errors[] = 'Please choose a role.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
            $errors[] = 'The two passwords do not match.';
        }

        if (!$errors) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare('INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('ssss', $new['username'], $hash, $new['full_name'], $new['role']);
                $stmt->execute();
                flash("User {$new['username']} was created.");
                redirect('users.php');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() === 1062) {
                    $errors[] = 'That username is already taken.';
                } else {
                    throw $ex;
                }
            }
        }
    }
}

$users = db()->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY role, username')
    ->fetch_all(MYSQLI_ASSOC);

$title = 'Users';
require __DIR__ . '/includes/header.php';
?>
<h1>User Accounts</h1>
<p class="muted">Administrators and registrars who can log in to the system. Only administrators can see this page.</p>

<div class="card">
    <h2>Existing users</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Username</th><th>Full name</th><th>Role</th><th>Created</th><th class="actions">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['full_name']) ?></td>
                    <td><?= e(ucfirst($u['role'])) ?></td>
                    <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                    <td class="actions">
                        <?php if ((int) $u['id'] === (int) $_SESSION['user_id']): ?>
                            <a class="btn btn-small btn-light" href="account.php">Change password</a>
                        <?php else: ?>
                            <form method="post" class="inline"
                                  onsubmit="return confirm('Delete user <?= e($u['username']) ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h2>Add a user</h2>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>
<form class="card form-grid" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div>
        <label for="username">Username</label>
        <input id="username" name="username" value="<?= e($new['username']) ?>" maxlength="50" required>
    </div>
    <div>
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($new['full_name']) ?>" maxlength="100" required>
    </div>
    <div>
        <label for="role">Role</label>
        <select id="role" name="role" required>
            <option value="registrar" <?= $new['role'] === 'registrar' ? 'selected' : '' ?>>Registrar</option>
            <option value="admin" <?= $new['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>
    </div>
    <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="6" required autocomplete="new-password">
    </div>
    <div>
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="6" required autocomplete="new-password">
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save</button>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
