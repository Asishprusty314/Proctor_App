<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$students = $conn->query("
    SELECT u.*, 
        (SELECT COUNT(*) FROM exam_attempts WHERE user_id = u.id) as attempt_count,
        (SELECT AVG(score) FROM exam_attempts WHERE user_id = u.id AND status IN ('completed','auto_submitted')) as avg_score
    FROM users u 
    WHERE u.role = 'student'
    ORDER BY u.created_at DESC
");

adminHeader('Students', 'students');
?>

<div class="card">
    <div class="card-header">
        <h2>Registered Students (<?= $students->num_rows ?>)</h2>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Exams Taken</th>
                    <th>Avg Score</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students->num_rows === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">No students registered yet</td></tr>
                <?php else: $i=1; while ($s = $students->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= sanitize($s['name']) ?></strong></td>
                        <td class="text-muted"><?= sanitize($s['email']) ?></td>
                        <td><?= $s['attempt_count'] ?></td>
                        <td><?= $s['avg_score'] ? round($s['avg_score'], 1) : '-' ?></td>
                        <td class="text-muted"><?= formatDateTime($s['created_at']) ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/admin/attempts.php?user_id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">View Attempts</a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php adminFooter(); ?>
