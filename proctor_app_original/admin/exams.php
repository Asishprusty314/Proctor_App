<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM exams WHERE id = $id");
    flash('success', 'Exam deleted successfully.');
    header('Location: ' . BASE_URL . '/admin/exams.php');
    exit;
}

// Handle status toggle
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->query("UPDATE exams SET status = IF(status='active','inactive','active') WHERE id = $id");
    header('Location: ' . BASE_URL . '/admin/exams.php');
    exit;
}

$exams = $conn->query("
    SELECT e.*, 
        (SELECT COUNT(*) FROM questions WHERE exam_id = e.id) as question_count,
        (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id) as attempt_count
    FROM exams e 
    ORDER BY e.created_at DESC
");

adminHeader('Manage Exams', 'exams');
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success">✓ <?= sanitize($msg) ?></div>
<?php endif; ?>

<div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <p class="text-muted"><?= $exams->num_rows ?> exam(s) found</p>
    <a href="<?= BASE_URL ?>/admin/add_exam.php" class="btn btn-primary">+ Create New Exam</a>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Duration</th>
                    <th>Marks</th>
                    <th>Questions</th>
                    <th>Attempts</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($exams->num_rows === 0): ?>
                    <tr><td colspan="9" class="text-center text-muted" style="padding: 40px;">No exams created yet</td></tr>
                <?php else: $i=1; while ($exam = $exams->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= sanitize($exam['title']) ?></strong></td>
                        <td><?= $exam['duration'] ?> min</td>
                        <td><?= $exam['total_marks'] ?></td>
                        <td><?= $exam['question_count'] ?></td>
                        <td><?= $exam['attempt_count'] ?></td>
                        <td>
                            <small class="text-muted">
                                <?= formatDateTime($exam['start_time']) ?><br>
                                to <?= formatDateTime($exam['end_time']) ?>
                            </small>
                        </td>
                        <td>
                            <a href="?toggle=<?= $exam['id'] ?>" class="badge <?= $exam['status']==='active'?'badge-success':'badge-default' ?>" style="cursor:pointer">
                                <?= ucfirst($exam['status']) ?>
                            </a>
                        </td>
                        <td>
                            <div class="btn-group">
                                <a href="<?= BASE_URL ?>/admin/edit_exam.php?id=<?= $exam['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <a href="<?= BASE_URL ?>/admin/questions.php?exam_id=<?= $exam['id'] ?>" class="btn btn-outline btn-sm">Questions</a>
                                <a href="?delete=<?= $exam['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirmDelete('Delete this exam and all its questions?')">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
