<?php
require __DIR__ . '/includes/config.php';
require_login();

$id = trim($_GET['id'] ?? '');
$student = null;
$results = [];

if ($id !== '') {
    $stmt = db()->prepare('SELECT * FROM students WHERE student_id = ?');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if ($student) {
        $stmt = db()->prepare(
            'SELECT g.course_code, c.course_title, c.credit_hours, g.mark, g.grade, g.status
             FROM grades g JOIN courses c ON c.course_code = g.course_code
             WHERE g.student_id = ? ORDER BY g.course_code'
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Summary figures
$totalCredits = 0;
$totalPoints = 0;
$passed = 0;
foreach ($results as $r) {
    $totalCredits += (int) $r['credit_hours'];
    $totalPoints  += grade_points($r['grade']) * (int) $r['credit_hours'];
    if ($r['status'] === 'Pass') {
        $passed++;
    }
}
$gpa = $totalCredits > 0 ? $totalPoints / $totalCredits : 0;
$failed = count($results) - $passed;

$title = $student ? 'Report · ' . $student['student_id'] : 'Academic Reports';
require __DIR__ . '/includes/header.php';
?>
<div class="no-print">
    <h1>Academic Report</h1>
    <form class="search" method="get" action="report.php">
        <input type="search" name="id" value="<?= e($id) ?>" placeholder="Enter student ID" required>
        <button class="btn" type="submit">Search</button>
    </form>
</div>

<?php if ($id !== '' && !$student): ?>
    <div class="alert alert-error">No student found with ID "<?= e($id) ?>".</div>
<?php endif; ?>

<?php if ($student): ?>
<div class="card report">
    <div class="report-head">
        <div class="logo logo-large">APC</div>
        <div>
            <h2>Adama Polytechnic College</h2>
            <p>Student Academic Result Report</p>
        </div>
    </div>

    <table class="info">
        <tr>
            <th>Student ID</th><td><?= e($student['student_id']) ?></td>
            <th>Department</th><td><?= e($student['department']) ?></td>
        </tr>
        <tr>
            <th>Full name</th><td><?= e($student['first_name'] . ' ' . $student['father_name'] . ' ' . $student['grandfather_name']) ?></td>
            <th>Program</th><td><?= e($student['program']) ?></td>
        </tr>
        <tr>
            <th>Gender</th><td><?= e($student['gender']) ?></td>
            <th>Academic year</th><td><?= e($student['academic_year']) ?></td>
        </tr>
        <tr>
            <th>Date of birth</th><td><?= e(date('d M Y', strtotime($student['date_of_birth']))) ?></td>
            <th>Phone</th><td><?= e($student['phone']) ?></td>
        </tr>
    </table>

    <h3>Course results</h3>
    <?php if (!$results): ?>
        <p class="muted">No grades have been recorded for this student yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr><th>#</th><th>Course code</th><th>Course title</th><th>Credit hours</th><th>Mark</th><th>Grade</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($results as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['course_code']) ?></td>
                    <td><?= e($r['course_title']) ?></td>
                    <td><?= (int) $r['credit_hours'] ?></td>
                    <td><?= e(rtrim(rtrim($r['mark'], '0'), '.')) ?></td>
                    <td><strong><?= e($r['grade']) ?></strong></td>
                    <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= e($r['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary">
            <div><span>Courses taken</span><strong><?= count($results) ?></strong></div>
            <div><span>Total credit hours</span><strong><?= $totalCredits ?></strong></div>
            <div><span>Passed / failed</span><strong><?= $passed ?> / <?= $failed ?></strong></div>
            <div><span>GPA (4.0 scale)</span><strong><?= number_format($gpa, 2) ?></strong></div>
            <div><span>Overall status</span><strong class="<?= $failed ? 'text-fail' : 'text-pass' ?>"><?= $failed ? 'Has failed course(s)' : 'All courses passed' ?></strong></div>
        </div>
    <?php endif; ?>

    <div class="signatures">
        <div><span class="line"></span>Registrar signature</div>
        <div><span class="line"></span>Date</div>
    </div>
    <p class="muted small">Generated on <?= date('d M Y, H:i') ?> by <?= e($_SESSION['full_name']) ?>.</p>

    <div class="no-print form-actions">
        <button class="btn" type="button" onclick="window.print()">Print report</button>
        <a class="btn btn-light" href="grade_form.php?student_id=<?= urlencode($student['student_id']) ?>">Record a grade</a>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
