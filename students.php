<?php
require __DIR__ . '/includes/config.php';
require_login();

// Delete (only students with no recorded grades, to protect academic history)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $id = $_POST['student_id'] ?? '';

    $stmt = db()->prepare('SELECT COUNT(*) FROM grades WHERE student_id = ?');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $gradeCount = (int) $stmt->get_result()->fetch_row()[0];

    if ($gradeCount > 0) {
        flash("Student $id has $gradeCount recorded grade(s), so the record cannot be deleted. "
            . 'Historical academic records are kept.', 'error');
    } else {
        $stmt = db()->prepare('DELETE FROM students WHERE student_id = ?');
        $stmt->bind_param('s', $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            flash("Student $id was deleted.");
        } else {
            flash('That student was not found.', 'error');
        }
    }
    redirect('students.php');
}

// List / search by ID, name or department
$q = trim($_GET['q'] ?? '');
$by = $_GET['by'] ?? 'all';
$searchColumns = [
    'id'         => ['student_id'],
    'name'       => ["CONCAT_WS(' ', first_name, father_name, grandfather_name)"],
    'department' => ['department'],
];
if (!isset($searchColumns[$by])) {
    $by = 'all';
}
if ($q !== '') {
    $cols = $by === 'all' ? array_merge(...array_values($searchColumns)) : $searchColumns[$by];
    $where = implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols));
    $like = '%' . $q . '%';
    $params = array_fill(0, count($cols), $like);
    $stmt = db()->prepare("SELECT * FROM students WHERE $where ORDER BY student_id");
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
} else {
    $stmt = db()->prepare('SELECT * FROM students ORDER BY student_id');
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Students';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Student Management</h1>
    <a class="btn" href="student_form.php">+ Register student</a>
</div>

<form class="search" method="get" action="students.php">
    <select name="by" aria-label="Search by">
        <?php foreach (['all' => 'All fields', 'id' => 'Student ID', 'name' => 'Student name', 'department' => 'Department'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $by === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by student ID, name or department">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-light" href="students.php">Show all</a><?php endif; ?>
</form>

<div class="card">
    <p class="muted"><?= count($students) ?> student(s)<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?></p>
    <?php if (!$students): ?>
        <p>No students found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th><th>Full name</th><th>Gender</th><th>Date of birth</th><th>Phone</th>
                <th>Department</th><th>Program</th><th>Year</th><th class="actions">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= e($s['student_id']) ?></td>
                    <td><?= e($s['first_name'] . ' ' . $s['father_name'] . ' ' . $s['grandfather_name']) ?></td>
                    <td><?= e($s['gender']) ?></td>
                    <td><?= e($s['date_of_birth']) ?></td>
                    <td><?= e($s['phone']) ?></td>
                    <td><?= e($s['department']) ?></td>
                    <td><?= e($s['program']) ?></td>
                    <td><?= e($s['academic_year']) ?></td>
                    <td class="actions">
                        <a class="btn btn-small btn-light" href="student_form.php?id=<?= urlencode($s['student_id']) ?>">Edit</a>
                        <a class="btn btn-small btn-light" href="report.php?id=<?= urlencode($s['student_id']) ?>">Report</a>
                        <form method="post" action="students.php" class="inline"
                              onsubmit="return confirm('Delete student <?= e($s['student_id']) ?>? This cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="student_id" value="<?= e($s['student_id']) ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
