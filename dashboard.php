<?php
require __DIR__ . '/includes/config.php';
require_login();

$stats = db()->query(
    "SELECT
        (SELECT COUNT(*) FROM students)                  AS students,
        (SELECT COUNT(*) FROM courses)                   AS courses,
        (SELECT COUNT(*) FROM grades)                    AS grades,
        (SELECT COUNT(*) FROM grades WHERE status='Pass') AS passed,
        (SELECT COUNT(*) FROM grades WHERE status='Fail') AS failed"
)->fetch_assoc();

$recent = db()->query(
    'SELECT student_id, first_name, father_name, department, created_at
     FROM students ORDER BY created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$recentGrades = db()->query(
    'SELECT g.student_id, g.course_code, g.mark, g.grade, g.status, g.recorded_at, s.first_name, s.father_name
     FROM grades g JOIN students s ON s.student_id = g.student_id
     ORDER BY g.recorded_at DESC, g.id DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

$title = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>
<p class="muted">Welcome, <?= e($_SESSION['full_name']) ?>. Here is a summary of the system.</p>

<div class="stats">
    <a class="stat" href="students.php">
        <span class="stat-number"><?= (int) $stats['students'] ?></span>
        <span class="stat-label">Registered students</span>
    </a>
    <a class="stat" href="courses.php">
        <span class="stat-number"><?= (int) $stats['courses'] ?></span>
        <span class="stat-label">Available courses</span>
    </a>
    <a class="stat" href="grades.php">
        <span class="stat-number"><?= (int) $stats['grades'] ?></span>
        <span class="stat-label">Recorded grades</span>
    </a>
    <div class="stat">
        <span class="stat-number">
            <span class="text-pass"><?= (int) $stats['passed'] ?></span> /
            <span class="text-fail"><?= (int) $stats['failed'] ?></span>
        </span>
        <span class="stat-label">Passed / failed results</span>
    </div>
</div>

<h2>Modules</h2>
<div class="modules">
    <a class="module" href="students.php"><strong>Student Management</strong><span>Register, search, update and remove students</span></a>
    <a class="module" href="courses.php"><strong>Course Management</strong><span>Add, search and update courses</span></a>
    <a class="module" href="grades.php"><strong>Grade Management</strong><span>Record marks; grades and pass/fail are automatic</span></a>
    <a class="module" href="report.php"><strong>Academic Reports</strong><span>Look up a student and print their results</span></a>
    <a class="module module-logout" href="logout.php"><strong>Logout</strong><span>Securely exit the system</span></a>
</div>

<div class="card">
    <h2>Recently registered students</h2>
    <?php if (!$recent): ?>
        <p class="muted">No students registered yet. <a href="student_form.php">Register the first student</a>.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Student ID</th><th>Name</th><th>Department</th><th>Registered</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['student_id']) ?></td>
                    <td><?= e($r['first_name'] . ' ' . $r['father_name']) ?></td>
                    <td><?= e($r['department']) ?></td>
                    <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recently recorded grades</h2>
    <?php if (!$recentGrades): ?>
        <p class="muted">No grades recorded yet. <a href="grade_form.php">Record a grade</a>.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Student</th><th>Course</th><th>Mark</th><th>Grade</th><th>Status</th><th>Recorded</th></tr></thead>
            <tbody>
            <?php foreach ($recentGrades as $g): ?>
                <tr>
                    <td><?= e($g['student_id'] . ' · ' . $g['first_name'] . ' ' . $g['father_name']) ?></td>
                    <td><?= e($g['course_code']) ?></td>
                    <td><?= e(rtrim(rtrim($g['mark'], '0'), '.')) ?></td>
                    <td><strong><?= e($g['grade']) ?></strong></td>
                    <td><span class="badge badge-<?= strtolower($g['status']) ?>"><?= e($g['status']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($g['recorded_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
