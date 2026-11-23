<?php
require __DIR__ . '/includes/config.php';
require_login();

$editCode = isset($_GET['code']) ? (string) $_GET['code'] : null;
$isEdit = $editCode !== null;
$c = ['course_code' => '', 'course_title' => '', 'credit_hours' => ''];
$errors = [];

if ($isEdit) {
    $stmt = db()->prepare('SELECT course_code, course_title, credit_hours FROM courses WHERE course_code = ?');
    $stmt->bind_param('s', $editCode);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        flash('That course was not found.', 'error');
        redirect('courses.php');
    }
    $c = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $c['course_code']  = $isEdit ? $editCode : strtoupper(trim($_POST['course_code'] ?? ''));
    $c['course_title'] = trim($_POST['course_title'] ?? '');
    $c['credit_hours'] = trim($_POST['credit_hours'] ?? '');

    if ($c['course_code'] === '' || $c['course_title'] === '' || $c['credit_hours'] === '') {
        $errors[] = 'All fields are required.';
    }
    if ($c['course_code'] !== '' && !preg_match('/^[A-Z0-9\-]{2,15}$/', $c['course_code'])) {
        $errors[] = 'Course code may only contain letters, numbers and "-" (2 to 15 characters), e.g. ICT-101.';
    }
    if (strlen($c['course_title']) > 100) {
        $errors[] = 'Course title must be 100 characters or fewer.';
    }
    $credits = filter_var($c['credit_hours'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);
    if ($c['credit_hours'] !== '' && $credits === false) {
        $errors[] = 'Credit hours must be a whole number from 1 to 10.';
    }

    if (!$errors) {
        try {
            if ($isEdit) {
                $stmt = db()->prepare('UPDATE courses SET course_title = ?, credit_hours = ? WHERE course_code = ?');
                $stmt->bind_param('sis', $c['course_title'], $credits, $c['course_code']);
                $stmt->execute();
                flash("Course {$c['course_code']} was updated.");
            } else {
                $stmt = db()->prepare('INSERT INTO courses (course_code, course_title, credit_hours) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $c['course_code'], $c['course_title'], $credits);
                $stmt->execute();
                flash("Course {$c['course_code']} was added.");
            }
            redirect('courses.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors[] = 'A course with code "' . $c['course_code'] . '" already exists.';
            } else {
                throw $ex;
            }
        }
    }
}

$title = $isEdit ? 'Edit Course' : 'Add Course';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Update Course' : 'Course Registration Form' ?></h1>
    <a class="btn btn-light" href="courses.php">Back to courses</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form class="card form-grid" method="post">
    <?= csrf_field() ?>
    <div>
        <label for="course_code">Course code</label>
        <input id="course_code" name="course_code" value="<?= e($c['course_code']) ?>" maxlength="15"
               placeholder="e.g. ICT-101" <?= $isEdit ? 'readonly' : 'required' ?>>
    </div>
    <div>
        <label for="course_title">Course title</label>
        <input id="course_title" name="course_title" value="<?= e($c['course_title']) ?>" maxlength="100"
               placeholder="e.g. Introduction to Programming" required>
    </div>
    <div>
        <label for="credit_hours">Credit hours</label>
        <input type="number" id="credit_hours" name="credit_hours" value="<?= e($c['credit_hours']) ?>"
               min="1" max="10" step="1" required>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
        <a class="btn btn-light" href="courses.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
