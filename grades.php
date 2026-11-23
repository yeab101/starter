<?php
require __DIR__ . '/includes/config.php';
require_login();

// Delete a single grade record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    check_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = db()->prepare('DELETE FROM grades WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        flash('The grade record was deleted.');
    } else {
        flash('That grade record was not found.', 'error');
    }
    redirect('grades.php');
}

// List / search by student ID, student name, course code or course title
$q = trim($_GET['q'] ?? '');
$sql = "SELECT g.*, s.first_name, s.father_name, c.course_title, c.credit_hours
        FROM grades g
        JOIN students s ON s.student_id = g.student_id
        JOIN courses  c ON c.course_code = g.course_code";
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = db()->prepare($sql . "
        WHERE g.student_id LIKE ? OR g.course_code LIKE ? OR c.course_title LIKE ?
           OR CONCAT_WS(' ', s.first_name, s.father_name) LIKE ?
        ORDER BY g.student_id, g.course_code");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
    $stmt = db()->prepare($sql . ' ORDER BY g.student_id, g.course_code');
}
$stmt->execute();
$grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$title = 'Grades';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <h1>Grade Management</h1>
    <a class="btn" href="grade_form.php">+ Record grade</a>
</div>

<form class="search" method="get" action="grades.php">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by student ID, name, course code or title">
    <button class="btn" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn btn-light" href="grades.php">Show all</a><?php endif; ?>
</form>

<div class="card">
    <p class="muted">
        <?= count($grades) ?> grade record(s)<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>.
        Pass mark: <?= PASS_MARK ?>. Scale: A ≥ 85, B ≥ 70, C ≥ 60, D ≥ 50, F &lt; 50.
    </p>
    <?php if (!$grades): ?>
        <p>No grades found.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Student ID</th><th>Student name</th><th>Course code</th><th>Course title</th>
                <th>Mark</th><th>Grade</th><th>Status</th><th class="actions">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($grades as $g): ?>
                <tr>
                    <td><?= e($g['student_id']) ?></td>
                    <td><?= e($g['first_name'] . ' ' . $g['father_name']) ?></td>
                    <td><?= e($g['course_code']) ?></td>
                    <td><?= e($g['course_title']) ?></td>
                    <td><?= e(rtrim(rtrim($g['mark'], '0'), '.')) ?></td>
                    <td><strong><?= e($g['grade']) ?></strong></td>
                    <td><span class="badge badge-<?= strtolower($g['status']) ?>"><?= e($g['status']) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-small btn-light" href="grade_form.php?id=<?= (int) $g['id'] ?>">Edit</a>
                        <form method="post" action="grades.php" class="inline"
                              onsubmit="return confirm('Delete this grade record? This cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
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
