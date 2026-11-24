<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$userId = $_SESSION['user_id'];

$results = $conn->query("
    SELECT ea.*, e.title as exam_title, e.duration
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.user_id = $userId
    ORDER BY ea.created_at DESC
");

studentHeader('My Results', 'results');
?>

<div class="card">
    <div class="card-header">
        <h2>Exam Results</h2>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Exam</th>
                    <th>Score</th>
                    <th>Percentage</th>
                    <th>Violations</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results->num_rows === 0): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:40px;">You haven't taken any exams yet</td></tr>
                <?php else: $i=1; while ($r = $results->fetch_assoc()): 
                    $pct = $r['total_marks'] > 0 ? round(($r['score']/$r['total_marks'])*100) : 0;
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><strong><?= sanitize($r['exam_title']) ?></strong></td>
                        <td><strong><?= $r['score'] ?></strong> / <?= $r['total_marks'] ?></td>
                        <td>
                            <span class="badge <?= $pct >= 60 ? 'badge-success' : ($pct >= 40 ? 'badge-warning' : 'badge-danger') ?>">
                                <?= $pct ?>%
                            </span>
                        </td>
                        <td>
                            <?php if ($r['tab_switches'] > 0): ?>
                                <span class="badge badge-warning"><?= $r['tab_switches'] ?></span>
                            <?php else: ?>
                                <span class="badge badge-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $sb = match($r['status']) { 'completed'=>'badge-success', 'auto_submitted'=>'badge-info', 'violation'=>'badge-danger', default=>'badge-default' }; ?>
                            <span class="badge <?= $sb ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
                        </td>
                        <td class="text-muted"><small><?= formatDateTime($r['created_at']) ?></small></td>
                        <td>
                            <a href="<?= BASE_URL ?>/student/result_detail.php?attempt_id=<?= $r['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php studentFooter(); ?>
