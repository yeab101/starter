<?php
require __DIR__ . '/includes/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash, full_name, role FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];
            redirect('dashboard.php');
        }
        $error = 'Incorrect username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Student Registration System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <div class="logo logo-large">APC</div>
    <h1>Adama Polytechnic College</h1>
    <p class="muted">Student Registration &amp; Academic Management System</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if (!$error && isset($_GET['logged_out'])): ?>
        <div class="alert alert-success">You have been logged out.</div>
    <?php endif; ?>

    <form method="post" action="login.php">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <button type="submit" class="btn btn-block">Login</button>
    </form>
</div>
</body>
</html>
