<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$userId = (int)($_GET['user_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);

$where = [];
if ($userId) $where[] = "ea.user_id = $userId";
if ($examId) $where[] = "ea.exam_id = $examId";
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$attempts = $conn->query("
    SELECT ea.*, u.name as student_name, u.email as student_email, e.title as exam_title
    FROM exam_attempts ea
    JOIN users u ON ea.user_id = u.id
    JOIN exams e ON ea.exam_id = e.id
    $whereStr
    ORDER BY ea.created_at DESC
");

$pageTitle = 'All Attempts';
if ($userId) {
    $r = $conn->query("SELECT name FROM users WHERE id=$userId");
    $row = $r->fetch_assoc();
    if ($row) $pageTitle = "Attempts by " . $row['name'];
}

adminHeader($pageTitle, 'attempts');
?>

<div class="card">
    <div class="card-header">
        <h2><?= sanitize($pageTitle) ?> (<?= $attempts->num_rows ?>)</h2>
        <div class="btn-group">
            <?php if ($userId || $examId): ?>
                <a href="<?= BASE_URL ?>/admin/attempts.php" class="btn btn-outline btn-sm">View All</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Tab Switches</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attempts->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:40px;">No attempts found</td></tr>
                <?php else: $i=1; while ($a = $attempts->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <strong><?= sanitize($a['student_name']) ?></strong><br>
                            <small class="text-muted"><?= sanitize($a['student_email']) ?></small>
                        </td>
                        <td><?= sanitize($a['exam_title']) ?></td>
                        <td>
                            <strong><?= $a['score'] ?></strong> / <?= $a['total_marks'] ?>
                            <?php if ($a['total_marks'] > 0): ?>
                                <br><small class="text-muted"><?= round(($a['score']/$a['total_marks'])*100) ?>%</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($a['tab_switches'] > 0): ?>
                                <span class="badge badge-warning"><?= $a['tab_switches'] ?> switches</span>
                            <?php else: ?>
                                <span class="badge badge-success">Clean</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $sb = match($a['status']) {
                                'completed' => 'badge-success',
                                'in_progress' => 'badge-warning',
                                'auto_submitted' => 'badge-info',
                                'violation' => 'badge-danger',
                                default => 'badge-default'
                            };
                            ?>
                            <span class="badge <?= $sb ?>"><?= ucfirst(str_replace('_', ' ', $a['status'])) ?></span>
                        </td>
                        <td class="text-muted"><small><?= formatDateTime($a['created_at']) ?></small></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/view_attempt.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
