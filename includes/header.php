<?php
// Shared page header and navigation. Set $title before including.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> · Student Registration System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar no-print">
    <div class="brand">
        <span class="logo">APC</span>
        <span class="brand-text">Student Registration &amp; Academic Management</span>
    </div>
    <nav>
        <?= nav_link('dashboard.php', 'Dashboard') ?>
        <?= nav_link('students.php', 'Students', ['student_form.php']) ?>
        <?= nav_link('courses.php', 'Courses', ['course_form.php']) ?>
        <?= nav_link('grades.php', 'Grades', ['grade_form.php']) ?>
        <?= nav_link('report.php', 'Academic Reports') ?>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <?= nav_link('users.php', 'Users') ?>
            <?php if (PMA_URL !== ''): ?>
                <a href="<?= e(PMA_URL) ?>" target="_blank" rel="noopener" title="View the raw MySQL tables in phpMyAdmin">Database &#8599;</a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>
    <div class="user">
        <a class="user-name" href="account.php" title="Change password">
            <?= e($_SESSION['full_name'] ?? '') ?> (<?= e(ucfirst($_SESSION['role'] ?? '')) ?>)
        </a>
        <a class="btn btn-small btn-light" href="logout.php">Logout</a>
    </div>
</header>
<main class="container">
<?php if ($f = flash()): ?>
    <div class="alert alert-<?= e($f[1]) ?> no-print"><?= e($f[0]) ?></div>
<?php endif; ?>
