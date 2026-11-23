<?php
require __DIR__ . '/includes/config.php';
require_login();

$fields = ['student_id', 'first_name', 'father_name', 'grandfather_name', 'gender',
           'date_of_birth', 'phone', 'department', 'program', 'academic_year'];

$editId = isset($_GET['id']) ? (string) $_GET['id'] : null;
$isEdit = $editId !== null;
$s = array_fill_keys($fields, '');
$errors = [];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM students WHERE student_id = ?');
    $stmt->bind_param('s', $editId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        flash('That student was not found.', 'error');
        redirect('students.php');
    }
    $s = array_intersect_key($row, $s);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($fields as $f) {
        $s[$f] = trim($_POST[$f] ?? '');
    }
    if ($isEdit) {
        $s['student_id'] = $editId;   // the ID cannot be changed
    }

    // Validation
    foreach ($fields as $f) {
        if ($s[$f] === '') {
            $errors[] = 'All fields are required.';
            break;
        }
    }
    if ($s['student_id'] !== '' && !preg_match('~^[A-Za-z0-9/\-]{1,20}$~', $s['student_id'])) {
        $errors[] = 'Student ID may only contain letters, numbers, "/" and "-" (max 20 characters).';
    }
    if ($s['gender'] !== '' && !in_array($s['gender'], ['Male', 'Female'], true)) {
        $errors[] = 'Please choose a gender.';
    }
    if ($s['date_of_birth'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $s['date_of_birth']);
        if (!$d || $d->format('Y-m-d') !== $s['date_of_birth'] || $d > new DateTime('today')) {
            $errors[] = 'Please enter a valid date of birth in the past.';
        }
    }
    if ($s['phone'] !== '' && !preg_match('/^\+?[0-9 ]{9,15}$/', $s['phone'])) {
        $errors[] = 'Phone number should contain 9 to 15 digits, e.g. 0911234567 or +251911234567.';
    }
    if ($s['program'] !== '' && !in_array($s['program'], PROGRAMS, true)) {
        $errors[] = 'Please choose a program.';
    }
    if ($s['academic_year'] !== '' && !in_array($s['academic_year'], ACADEMIC_YEARS, true)) {
        $errors[] = 'Please choose an academic year.';
    }

    if (!$errors) {
        try {
            if ($isEdit) {
                $stmt = db()->prepare(
                    'UPDATE students SET first_name=?, father_name=?, grandfather_name=?, gender=?,
                     date_of_birth=?, phone=?, department=?, program=?, academic_year=?
                     WHERE student_id=?'
                );
                $stmt->bind_param('ssssssssss', $s['first_name'], $s['father_name'], $s['grandfather_name'],
                    $s['gender'], $s['date_of_birth'], $s['phone'], $s['department'], $s['program'],
                    $s['academic_year'], $s['student_id']);
                $stmt->execute();
                flash("Student {$s['student_id']} was updated.");
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO students (student_id, first_name, father_name, grandfather_name, gender,
                     date_of_birth, phone, department, program, academic_year)
                     VALUES (?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->bind_param('ssssssssss', $s['student_id'], $s['first_name'], $s['father_name'],
                    $s['grandfather_name'], $s['gender'], $s['date_of_birth'], $s['phone'],
                    $s['department'], $s['program'], $s['academic_year']);
                $stmt->execute();
                flash("Student {$s['student_id']} was registered.");
            }
            redirect('students.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors[] = 'A student with ID "' . $s['student_id'] . '" already exists.';
            } else {
                throw $ex;
            }
        }
    }
}

// Department suggestions from existing records
$departments = db()->query('SELECT DISTINCT department FROM students ORDER BY department')->fetch_all();

$title = $isEdit ? 'Edit Student' : 'Register Student';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1><?= $isEdit ? 'Update Student Information' : 'Student Registration Form' ?></h1>
    <a class="btn btn-light" href="students.php">Back to students</a>
</div>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form class="card form-grid" method="post">
    <?= csrf_field() ?>
    <div>
        <label for="student_id">Student ID</label>
        <input id="student_id" name="student_id" value="<?= e($s['student_id']) ?>" maxlength="20"
               placeholder="e.g. APC/0123/24" <?= $isEdit ? 'readonly' : 'required' ?>>
    </div>
    <div>
        <label for="first_name">First name</label>
        <input id="first_name" name="first_name" value="<?= e($s['first_name']) ?>" maxlength="50" required>
    </div>
    <div>
        <label for="father_name">Father's name</label>
        <input id="father_name" name="father_name" value="<?= e($s['father_name']) ?>" maxlength="50" required>
    </div>
    <div>
        <label for="grandfather_name">Grandfather's name</label>
        <input id="grandfather_name" name="grandfather_name" value="<?= e($s['grandfather_name']) ?>" maxlength="50" required>
    </div>
    <div>
        <label for="gender">Gender</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select --</option>
            <?php foreach (['Male', 'Female'] as $g): ?>
                <option <?= $s['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="date_of_birth">Date of birth</label>
        <input type="date" id="date_of_birth" name="date_of_birth" value="<?= e($s['date_of_birth']) ?>"
               max="<?= date('Y-m-d') ?>" required>
    </div>
    <div>
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" value="<?= e($s['phone']) ?>" maxlength="20"
               placeholder="e.g. 0911234567" required>
    </div>
    <div>
        <label for="department">Department</label>
        <input id="department" name="department" value="<?= e($s['department']) ?>" maxlength="100"
               list="department-list" placeholder="e.g. Information Technology" required>
        <datalist id="department-list">
            <?php foreach ($departments as $d): ?><option value="<?= e($d[0]) ?>"><?php endforeach; ?>
        </datalist>
    </div>
    <div>
        <label for="program">Program</label>
        <select id="program" name="program" required>
            <option value="">-- Select --</option>
            <?php foreach (PROGRAMS as $p): ?>
                <option <?= $s['program'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="academic_year">Academic year</label>
        <select id="academic_year" name="academic_year" required>
            <option value="">-- Select --</option>
            <?php foreach (ACADEMIC_YEARS as $y): ?>
                <option <?= $s['academic_year'] === $y ? 'selected' : '' ?>><?= e($y) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit"><?= $isEdit ? 'Update' : 'Save' ?></button>
        <a class="btn btn-light" href="students.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
