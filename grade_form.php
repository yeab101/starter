<?php
require __DIR__ . '/includes/config.php';
require_login();

$editId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $editId !== null;
$g = ['student_id' => (string) ($_GET['student_id'] ?? ''), 'course_code' => '', 'mark' => ''];
$errors = [];

if ($isEdit) {
    $stmt = db()->prepare('SELECT student_id, course_code, mark FROM grades WHERE id = ?');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        flash('That grade record was not found.', 'error');
        redirect('grades.php');
    }
    $g = $row;
    $g['mark'] = rtrim(rtrim($g['mark'], '0'), '.');
}

$students = db()->query(
    "SELECT student_id, CONCAT_WS(' ', first_name, father_name, grandfather_name) AS name
     FROM students ORDER BY student_id"
)->fetch_all(MYSQLI_ASSOC);
$courses = db()->query('SELECT course_code, course_title FROM courses ORDER BY course_code')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (!$isEdit) {   // student and course are fixed once a grade exists
        $g['student_id']  = trim($_POST['student_id'] ?? '');
        $g['course_code'] = trim($_POST['course_code'] ?? '');
    }
    $g['mark'] = trim($_POST['mark'] ?? '');

    if (!in_array($g['student_id'], array_column($students, 'student_id'), true)) {
        $errors[] = 'Please choose a registered student.';
    }
    if (!in_array($g['course_code'], array_column($courses, 'course_code'), true)) {
        $errors[] = 'Please choose a course.';
    }
    $mark = filter_var($g['mark'], FILTER_VALIDATE_FLOAT);
    if ($mark === false || $mark < 0 || $mark > 100) {
        $errors[] = 'Mark must be a number from 0 to 100.';
    }

    if (!$errors) {
        [$grade, $status] = calc_grade($mark);   // automatic grade and pass/fail
        try {
            if ($isEdit) {
                $stmt = db()->prepare('UPDATE grades SET mark = ?, grade = ?, status = ? WHERE id = ?');
                $stmt->bind_param('dssi', $mark, $grade, $status, $editId);
                $stmt->execute();
                flash("Grade updated: {$g['student_id']} in {$g['course_code']} is now $grade ($status).");
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO grades (student_id, course_code, mark, grade, status) VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssdss', $g['student_id'], $g['course_code'], $mark, $grade, $status);
                $stmt->execute();
                flash("Grade saved: {$g['student_id']} scored $grade ($status) in {$g['course_code']}.");
            }
            redirect('grades.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors[] = 'This student already has a grade for this course. Edit the existing record instead.';
            } else {
                throw $ex;
            }
        }
    }
}

$title = $isEdit ? 'Edit Grade' : 'Record Grade';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Update Grade' : 'Grade Insertion' ?></h1>
    <a class="btn btn-light" href="grades.php">Back to grades</a>
</div>

<?php if (!$students || !$courses): ?>
    <div class="alert alert-error">
        You need at least one <a href="student_form.php">registered student</a> and one
        <a href="course_form.php">course</a> before you can record grades.
    </div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form class="card form-grid" method="post">
    <?= csrf_field() ?>
    <div>
        <label for="student_id">Student</label>
        <select id="student_id" name="student_id" <?= $isEdit ? 'disabled' : 'required' ?>>
            <option value="">-- Select student --</option>
            <?php foreach ($students as $s): ?>
                <option value="<?= e($s['student_id']) ?>" <?= $g['student_id'] === $s['student_id'] ? 'selected' : '' ?>>
                    <?= e($s['student_id'] . ' — ' . $s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="course_code">Course</label>
        <select id="course_code" name="course_code" <?= $isEdit ? 'disabled' : 'required' ?>>
            <option value="">-- Select course --</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= e($c['course_code']) ?>" <?= $g['course_code'] === $c['course_code'] ? 'selected' : '' ?>>
                    <?= e($c['course_code'] . ' — ' . $c['course_title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="mark">Mark (out of 100)</label>
        <input type="number" id="mark" name="mark" value="<?= e($g['mark']) ?>" min="0" max="100" step="0.01" required>
    </div>
    <div>
        <label>Grade and status</label>
        <p id="preview" class="preview muted">Calculated automatically when you save.</p>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
        <a class="btn btn-light" href="grades.php">Cancel</a>
    </div>
</form>

<script>
// Live preview only; the server calculates the stored grade (see calc_grade in includes/config.php).
(function () {
    var input = document.getElementById('mark'), out = document.getElementById('preview');
    function show() {
        var m = parseFloat(input.value);
        if (isNaN(m) || m < 0 || m > 100) { out.textContent = 'Calculated automatically when you save.'; return; }
        var g = m >= 85 ? 'A' : m >= 70 ? 'B' : m >= 60 ? 'C' : m >= 50 ? 'D' : 'F';
        var pass = m >= <?= PASS_MARK ?>;
        out.textContent = 'Grade ' + g + ' · ' + (pass ? 'Pass' : 'Fail');
        out.className = 'preview ' + (pass ? 'text-pass' : 'text-fail');
    }
    input.addEventListener('input', show);
    show();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
