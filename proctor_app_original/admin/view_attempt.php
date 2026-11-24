<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/attempts.php'); exit; }

$attempt = $conn->query("
    SELECT ea.*, u.name as student_name, u.email as student_email, e.title as exam_title, e.duration, e.total_marks as exam_total_marks
    FROM exam_attempts ea
    JOIN users u ON ea.user_id = u.id
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.id = $id
")->fetch_assoc();

if (!$attempt) { header('Location: ' . BASE_URL . '/admin/attempts.php'); exit; }

// Handle descriptive answer scoring
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_answer'])) {
    $answerId = (int)$_POST['answer_id'];
    $marksObtained = (int)$_POST['marks_obtained'];
    
    $stmt = $conn->prepare("UPDATE answers SET marks_obtained = ?, is_correct = ? WHERE id = ? AND attempt_id = ?");
    $isCorrect = $marksObtained > 0 ? 1 : 0;
    $stmt->bind_param("iiii", $marksObtained, $isCorrect, $answerId, $id);
    $stmt->execute();
    $stmt->close();

    // Recalculate total score
    $r = $conn->query("SELECT COALESCE(SUM(marks_obtained), 0) as total FROM answers WHERE attempt_id = $id");
    $newScore = $r->fetch_assoc()['total'];
    $conn->query("UPDATE exam_attempts SET score = $newScore WHERE id = $id");
    
    flash('success', 'Score updated!');
    header("Location: " . BASE_URL . "/admin/view_attempt.php?id=$id");
    exit;
}

// Get answers with questions
$answers = $conn->query("
    SELECT a.*, q.question, q.type, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.marks as max_marks
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    WHERE a.attempt_id = $id
    ORDER BY q.sort_order, q.id
");

// Get violations
$violations = $conn->query("
    SELECT * FROM violation_logs WHERE attempt_id = $id ORDER BY created_at
");

// Get webcam captures
$captures = $conn->query("
    SELECT * FROM webcam_captures WHERE attempt_id = $id ORDER BY captured_at LIMIT 20
");

adminHeader('Attempt Details', 'attempts');
?>

<?php if ($msg = flash('success')): ?>
    <div class="alert alert-success">✓ <?= sanitize($msg) ?></div>
<?php endif; ?>

<div style="margin-bottom: 24px;">
    <a href="<?= BASE_URL ?>/admin/attempts.php" class="btn btn-outline btn-sm">← Back to Attempts</a>
</div>

<!-- Attempt Summary -->
<div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
    <div class="stat-card">
        <div class="stat-icon">👤</div>
        <div class="stat-value" style="font-size: 18px;"><?= sanitize($attempt['student_name']) ?></div>
        <div class="stat-label"><?= sanitize($attempt['student_email']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value" style="font-size: 18px;"><?= sanitize($attempt['exam_title']) ?></div>
        <div class="stat-label">Exam</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value"><?= $attempt['score'] ?>/<?= $attempt['total_marks'] ?></div>
        <div class="stat-label">Score (<?= $attempt['total_marks'] > 0 ? round(($attempt['score']/$attempt['total_marks'])*100) : 0 ?>%)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= $attempt['tab_switches'] ?></div>
        <div class="stat-label">Tab Switches</div>
    </div>
</div>

<!-- Answers -->
<div class="card mb-3">
    <div class="card-header">
        <h2>Answers & Grading</h2>
    </div>
    <div class="card-body">
        <?php if ($answers->num_rows === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📄</div>
                <h3>No answers submitted</h3>
            </div>
        <?php else: $qNum = 1; while ($a = $answers->fetch_assoc()): 
            $isGraded = $a['is_correct'] !== null;
            $borderColor = $isGraded ? ($a['marks_obtained'] > 0 ? 'var(--success)' : 'var(--danger)') : 'var(--warning)';
        ?>
            <div style="padding: 20px; border: 1px solid var(--border); border-left: 4px solid <?= $borderColor ?>; border-radius: var(--radius-sm); margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                    <div>
                        <span class="badge <?= $a['type']==='mcq'?'badge-info':'badge-warning' ?>"><?= strtoupper($a['type']) ?></span>
                        <strong style="margin-left: 8px;">Q<?= $qNum++ ?>.</strong>
                        <?php if (!$isGraded): ?>
                            <span class="badge badge-warning" style="margin-left: 6px;">⏳ Not Graded</span>
                        <?php else: ?>
                            <span class="badge badge-success" style="margin-left: 6px;">✓ Graded</span>
                        <?php endif; ?>
                    </div>
                    <span class="text-muted"><?= $a['marks_obtained'] ?> / <?= $a['max_marks'] ?> marks</span>
                </div>
                
                <p style="margin-bottom: 12px; font-size: 15px;"><?= sanitize($a['question']) ?></p>

                <?php if ($a['type'] === 'mcq'): ?>
                    <?php 
                    $studentAnswers = $a['answer'] ? explode(',', $a['answer']) : [];
                    $correctAnswers = $a['correct_answer'] ? explode(',', $a['correct_answer']) : [];
                    ?>
                    <!-- Show MCQ options with student's selections highlighted -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px;">
                        <?php foreach (['A'=>$a['option_a'], 'B'=>$a['option_b'], 'C'=>$a['option_c'], 'D'=>$a['option_d']] as $letter => $opt): ?>
                            <?php if ($opt): 
                                $isStudentPick = in_array($letter, $studentAnswers);
                            ?>
                                <div style="padding: 8px 12px; border-radius: 6px; font-size: 13px;
                                    <?php if ($isStudentPick): ?>background: var(--info-light); color: var(--info); border: 1.5px solid #a3d5f5;
                                    <?php else: ?>background: var(--bg-input); border: 1px solid var(--border);
                                    <?php endif; ?>">
                                    <strong><?= $letter ?>.</strong> <?= sanitize($opt) ?>
                                    <?php if ($isStudentPick): ?> ☑ Student Selected<?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div style="padding: 10px 14px; background: var(--success-light); border: 1px solid #b8e6d5; border-radius: 6px; margin-bottom: 12px; font-size: 13px; color: var(--success);">
                        📌 Correct Answer(s) (Reference): 
                        <strong>
                        <?php 
                        $correctTexts = [];
                        foreach ($correctAnswers as $ca) {
                            $key = 'option_' . strtolower(trim($ca));
                            $correctTexts[] = trim($ca) . '. ' . ($a[$key] ?? '');
                        }
                        echo sanitize(implode(' | ', $correctTexts));
                        ?>
                        </strong>
                    </div>
                <?php else: ?>
                    <!-- Show descriptive answer -->
                    <div style="padding: 12px 16px; background: var(--bg-input); border-radius: 6px; margin-bottom: 12px;">
                        <small class="text-muted" style="display: block; margin-bottom: 4px;">Student's Answer:</small>
                        <p style="white-space: pre-wrap;"><?= sanitize($a['answer'] ?? 'No answer provided') ?></p>
                    </div>
                <?php endif; ?>

                <!-- Manual grading form for ALL question types -->
                <form method="POST" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: var(--bg-input); border-radius: 6px;">
                    <input type="hidden" name="grade_answer" value="1">
                    <input type="hidden" name="answer_id" value="<?= $a['id'] ?>">
                    <label style="font-size: 13px; color: var(--text-secondary); white-space: nowrap;">✏️ Award marks:</label>
                    <input type="number" name="marks_obtained" class="form-control" style="width: 80px;" min="0" max="<?= $a['max_marks'] ?>" value="<?= $a['marks_obtained'] ?>">
                    <span class="text-muted">/ <?= $a['max_marks'] ?></span>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </form>
            </div>
        <?php endwhile; endif; ?>
    </div>
</div>

<!-- Enhanced Violations Log -->
<?php 
$enhancedViolations = $conn->query("SELECT * FROM violations WHERE attempt_id = $id ORDER BY created_at DESC");
$snapshots = $conn->query("SELECT * FROM snapshots WHERE attempt_id = $id ORDER BY created_at DESC LIMIT 30");
$permLogs = $conn->query("SELECT * FROM permission_logs WHERE attempt_id = $id ORDER BY id DESC LIMIT 5");
$sessionLogs = $conn->query("SELECT * FROM exam_sessions WHERE attempt_id = $id ORDER BY id DESC LIMIT 5");
?>

<?php if ($permLogs && $permLogs->num_rows > 0): ?>
<div class="card mb-3">
    <div class="card-header"><h2>Permission Status</h2></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Camera</th><th>Microphone</th><th>IP Address</th><th>Time</th></tr></thead>
            <tbody>
            <?php while ($p = $permLogs->fetch_assoc()): ?>
                <tr>
                    <td><span class="badge <?= $p['camera_status']==='granted'?'badge-success':'badge-danger' ?>"><?= ucfirst($p['camera_status']) ?></span></td>
                    <td><span class="badge <?= $p['mic_status']==='granted'?'badge-success':'badge-danger' ?>"><?= ucfirst($p['mic_status']) ?></span></td>
                    <td class="text-muted"><small><?= sanitize($p['ip_address']) ?></small></td>
                    <td class="text-muted"><small><?= formatDateTime($p['created_at']) ?></small></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($enhancedViolations && $enhancedViolations->num_rows > 0): ?>
<div class="card mb-3">
    <div class="card-header">
        <h2>Violation Log (<?= $enhancedViolations->num_rows ?>)</h2>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>#</th><th>Type</th><th>Severity</th><th>Message</th><th>IP</th><th>Time</th></tr></thead>
            <tbody>
            <?php $vi=1; while ($v = $enhancedViolations->fetch_assoc()): 
                $sevBadge = match($v['severity']) { 'low'=>'badge-default', 'medium'=>'badge-warning', 'high'=>'badge-danger', 'critical'=>'badge-danger', default=>'badge-default' };
            ?>
                <tr>
                    <td><?= $vi++ ?></td>
                    <td><span class="badge badge-info"><?= str_replace('_', ' ', $v['type']) ?></span></td>
                    <td><span class="badge <?= $sevBadge ?>"><?= ucfirst($v['severity']) ?></span></td>
                    <td><small><?= sanitize(substr($v['message'] ?? '-', 0, 80)) ?></small></td>
                    <td class="text-muted"><small><?= sanitize($v['ip_address'] ?? '-') ?></small></td>
                    <td class="text-muted"><small><?= date('H:i:s', strtotime($v['created_at'])) ?></small></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($sessionLogs && $sessionLogs->num_rows > 0): ?>
<div class="card mb-3">
    <div class="card-header"><h2>Session History</h2></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Token</th><th>Browser</th><th>IP Address</th><th>Active</th><th>Last Heartbeat</th></tr></thead>
            <tbody>
            <?php while ($s = $sessionLogs->fetch_assoc()): ?>
                <tr>
                    <td><code style="font-size:11px;"><?= sanitize(substr($s['session_token'], 0, 20)) ?>...</code></td>
                    <td><?= sanitize($s['browser']) ?></td>
                    <td class="text-muted"><?= sanitize($s['ip_address']) ?></td>
                    <td><span class="badge <?= $s['is_active']?'badge-success':'badge-default' ?>"><?= $s['is_active']?'Active':'Ended' ?></span></td>
                    <td class="text-muted"><small><?= $s['last_heartbeat'] ? formatDateTime($s['last_heartbeat']) : '-' ?></small></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Snapshots Gallery -->
<?php if ($snapshots && $snapshots->num_rows > 0): ?>
<div class="card">
    <div class="card-header">
        <h2>Snapshots (<?= $snapshots->num_rows ?>)</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;">
            <?php while ($snap = $snapshots->fetch_assoc()): ?>
                <div style="border: 1px solid var(--border, #e0ddd7); border-radius: 8px; overflow: hidden; <?= $snap['flagged'] ? 'border-color: #e11d48; border-width: 2px;' : '' ?>">
                    <img src="<?= BASE_URL ?>/<?= sanitize($snap['image_path']) ?>" alt="Snapshot" style="width:100%; display:block;" loading="lazy">
                    <div style="padding: 6px 8px; font-size: 11px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="color: var(--text-muted, #8c8579);"><?= date('H:i:s', strtotime($snap['created_at'])) ?></span>
                        <?php if ($snap['face_count'] !== null): ?>
                            <span class="badge <?= $snap['face_count'] === 1 ? 'badge-success' : 'badge-danger' ?>" style="font-size:9px;">
                                <?= $snap['face_count'] ?> face<?= $snap['face_count'] !== 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($snap['flagged']): ?>
                            <span class="badge badge-danger" style="font-size:9px;">Flagged</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php adminFooter(); ?>
