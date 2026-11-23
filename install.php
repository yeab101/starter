<?php
// One-time setup: creates the database and tables from database.sql
// and adds the first administrator account. Delete this file after setup.
require __DIR__ . '/includes/config.php';

$messages = [];
$ok = false;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    $conn->set_charset('utf8mb4');

    $sql = file_get_contents(__DIR__ . '/database.sql');
    $conn->multi_query($sql);
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    $messages[] = 'Database "' . DB_NAME . '" and tables are ready.';

    $conn->select_db(DB_NAME);
    $count = (int) $conn->query('SELECT COUNT(*) FROM users')->fetch_row()[0];
    if ($count === 0) {
        $hash = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "INSERT INTO users (username, password_hash, full_name, role)
             VALUES (?, ?, 'System Administrator', 'admin')"
        );
        $user = DEFAULT_ADMIN_USERNAME;
        $stmt->bind_param('ss', $user, $hash);
        $stmt->execute();
        $messages[] = 'Administrator account created. The username and password are in README.md.';
    } else {
        $messages[] = 'User accounts already exist, so no new account was created.';
    }
    $ok = true;
} catch (mysqli_sql_exception $ex) {
    $messages[] = 'Setup failed: ' . $ex->getMessage()
        . '. Make sure Apache and MySQL are running in the XAMPP Control Panel.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install · Student Registration System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>System Setup</h1>
    <?php foreach ($messages as $m): ?>
        <div class="alert alert-<?= $ok ? 'success' : 'error' ?>"><?= e($m) ?></div>
    <?php endforeach; ?>
    <?php if ($ok): ?>
        <p>Setup is complete. For security, delete <code>install.php</code> now.</p>
        <a class="btn btn-block" href="login.php">Go to login</a>
    <?php else: ?>
        <a class="btn btn-block" href="install.php">Try again</a>
    <?php endif; ?>
</div>
</body>
</html>
