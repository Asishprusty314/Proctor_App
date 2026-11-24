<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$userId = $_SESSION['user_id'];
$attemptId = (int)($_GET['attempt_id'] ?? 0);

if (!$attemptId) { header('Location: ' . BASE_URL . '/student/results.php'); exit; }

$attempt = $conn->query("
    SELECT ea.*, e.title as exam_title, e.duration, e.total_marks as exam_total
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.id = $attemptId AND ea.user_id = $userId
")->fetch_assoc();

if (!$attempt) { header('Location: ' . BASE_URL . '/student/results.php'); exit; }

$pct = $attempt['total_marks'] > 0 ? round(($attempt['score'] / $attempt['total_marks']) * 100) : 0;

// Get answers
$answers = $conn->query("
    SELECT a.*, q.question, q.type, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.marks as max_marks
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.attempt_id = $attemptId
    ORDER BY q.sort_order, q.id
");

$totalQ = $answers->num_rows;
$correctQ = 0;
$answerList = [];
while ($a = $answers->fetch_assoc()) {
    if ($a['is_correct']) $correctQ++;
    $answerList[] = $a;
}

studentHeader('Exam Result', 'results');
?>

<div style="margin-bottom: 24px;">
    <a href="<?= BASE_URL ?>/student/results.php" class="btn btn-outline btn-sm">← Back to Results</a>
</div>

<!-- Score Card -->
<div class="card mb-3">
    <div class="result-card">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 24px;"><?= sanitize($attempt['exam_title']) ?></h2>
        
        <div class="result-score <?= $pct >= 60 ? 'text-success' : ($pct >= 40 ? 'text-warning' : 'text-danger') ?>">
            <?= $attempt['score'] ?>
        </div>
        <div class="result-total">out of <?= $attempt['total_marks'] ?> (<?= $pct ?>%)</div>
        
        <div style="margin-top: 16px;">
            <?php if ($pct >= 60): ?>
                <span class="badge badge-success" style="font-size: 16px; padding: 8px 20px;">✓ Passed</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 16px; padding: 8px 20px;">✗ Needs Improvement</span>
            <?php endif; ?>
        </div>

        <div class="result-details">
            <div class="result-detail-item">
                <div class="label">Status</div>
                <div class="value"><?= ucfirst(str_replace('_', ' ', $attempt['status'])) ?></div>
            </div>
            <div class="result-detail-item">
                <div class="label">Questions</div>
                <div class="value"><?= $correctQ ?> / <?= $totalQ ?></div>
            </div>
            <div class="result-detail-item">
                <div class="label">Tab Switches</div>
                <div class="value"><?= $attempt['tab_switches'] ?></div>
            </div>
            <div class="result-detail-item">
                <div class="label">Duration</div>
                <div class="value"><?= $attempt['duration'] ?> min</div>
            </div>
            <div class="result-detail-item">
                <div class="label">Started</div>
                <div class="value" style="font-size: 13px;"><?= formatDateTime($attempt['start_time']) ?></div>
            </div>
            <div class="result-detail-item">
                <div class="label">Ended</div>
                <div class="value" style="font-size: 13px;"><?= $attempt['end_time'] ? formatDateTime($attempt['end_time']) : 'N/A' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Answer Review -->
<div class="card">
    <div class="card-header">
        <h2>Answer Review</h2>
    </div>
    <div class="card-body">
        <?php $qNum = 1; foreach ($answerList as $a): 
            $isGraded = $a['is_correct'] !== null;
            $borderColor = $isGraded ? ($a['marks_obtained'] > 0 ? 'var(--success)' : 'var(--danger)') : 'var(--warning)';
        ?>
            <div style="padding: 20px; border: 1px solid var(--border); border-radius: var(--radius-sm); margin-bottom: 16px;
                border-left: 4px solid <?= $borderColor ?>;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <div>
                        <span class="badge <?= $a['type']==='mcq' ? 'badge-info' : 'badge-warning' ?>"><?= strtoupper($a['type']) ?></span>
                        <strong style="margin-left: 8px;">Q<?= $qNum++ ?></strong>
                        <?php if (!$isGraded): ?>
                            <span class="badge badge-warning" style="margin-left: 6px;">⏳ Pending Review</span>
                        <?php else: ?>
                            <span class="badge <?= $a['marks_obtained'] > 0 ? 'badge-success' : 'badge-danger' ?>" style="margin-left: 6px;">
                                <?= $a['marks_obtained'] > 0 ? '✓ Graded' : '✗ Graded' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <span class="<?= $isGraded ? ($a['marks_obtained'] > 0 ? 'text-success' : 'text-danger') : 'text-warning' ?>" style="font-weight: 600;">
                        <?= $isGraded ? $a['marks_obtained'] : '—' ?> / <?= $a['max_marks'] ?>
                    </span>
                </div>
                <p style="margin-bottom: 12px;"><?= sanitize($a['question']) ?></p>

                <?php if ($a['type'] === 'mcq'): ?>
                    <?php $studentAnswers = $a['answer'] ? explode(',', $a['answer']) : []; ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php foreach (['A'=>$a['option_a'], 'B'=>$a['option_b'], 'C'=>$a['option_c'], 'D'=>$a['option_d']] as $l => $opt): ?>
                            <?php if ($opt): 
                                $isStudentPick = in_array($l, $studentAnswers);
                            ?>
                                <div style="padding: 8px 12px; border-radius: 6px; font-size: 13px;
                                    <?php if ($isStudentPick): ?>background: var(--info-light); color: var(--info); border: 1.5px solid #a3d5f5;
                                    <?php else: ?>background: var(--bg-input); border: 1px solid var(--border);
                                    <?php endif; ?>">
                                    <strong><?= $l ?>.</strong> <?= sanitize($opt) ?>
                                    <?php if ($isStudentPick): ?> ☑ Your Answer<?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: 12px; background: var(--bg-input); border-radius: 6px;">
                        <small class="text-muted">Your Answer:</small>
                        <p style="white-space: pre-wrap; margin-top: 4px;"><?= sanitize($a['answer'] ?? 'No answer') ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!$isGraded): ?>
                    <small class="text-muted" style="display: block; margin-top: 10px;">⏳ This answer is pending manual review by the admin. Score will update once graded.</small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php studentFooter(); ?>
