<?php
require __DIR__ . '/includes/config.php';
require_login();

// Delete (only courses with no recorded grades)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $code = $_POST['course_code'] ?? '';

    $stmt = db()->prepare('SELECT COUNT(*) FROM grades WHERE course_code = ?');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $gradeCount = (int) $stmt->get_result()->fetch_row()[0];

    if ($gradeCount > 0) {
        flash("Course $code has $gradeCount recorded grade(s), so it cannot be deleted. "
            . 'Historical academic records are kept.', 'error');
    } else {
        $stmt = db()->prepare('DELETE FROM courses WHERE course_code = ?');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            flash("Course $code was deleted.");
        } else {
            flash('That course was not found.', 'error');
        }
    }
    redirect('courses.php');
}

// List / search by code or title
$q = trim($_GET['q'] ?? '');
$by = $_GET['by'] ?? 'all';
$searchColumns = ['code' => ['c.course_code'], 'title' => ['c.course_title']];
if (!isset($searchColumns[$by])) {
    $by = 'all';
}
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM grades g WHERE g.course_code = c.course_code) AS grade_count
        FROM courses c';
if ($q !== '') {
    $cols = $by === 'all' ? array_merge(...array_values($searchColumns)) : $searchColumns[$by];
    $where = implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cols));
    $like = '%' . $q . '%';
    $params = array_fill(0, count($cols), $like);
    $stmt = db()->prepare($sql . " WHERE $where ORDER BY c.course_code");
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
} else {
    $stmt = db()->prepare($sql . ' ORDER BY c.course_code');
}
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Courses';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Course Management</h1>
    <a class="btn" href="course_form.php">+ Add course</a>
</div>

<form class="search" method="get" action="courses.php">
    <select name="by" aria-label="Search by">
        <?php foreach (['all' => 'All fields', 'code' => 'Course code', 'title' => 'Course title'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= $by === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by course code or course title">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-light" href="courses.php">Show all</a><?php endif; ?>
</form>

<div class="card">
    <p class="muted"><?= count($courses) ?> course(s)<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?></p>
    <?php if (!$courses): ?>
        <p>No courses found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>Course code</th><th>Course title</th><th>Credit hours</th><th>Grades recorded</th><th class="actions">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?= e($c['course_code']) ?></td>
                    <td><?= e($c['course_title']) ?></td>
                    <td><?= (int) $c['credit_hours'] ?></td>
                    <td><?= (int) $c['grade_count'] ?></td>
                    <td class="actions">
                        <a class="btn btn-small btn-light" href="course_form.php?code=<?= urlencode($c['course_code']) ?>">Edit</a>
                        <form method="post" action="courses.php" class="inline"
                              onsubmit="return confirm('Delete course <?= e($c['course_code']) ?>? This cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="course_code" value="<?= e($c['course_code']) ?>">
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
