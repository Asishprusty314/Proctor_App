<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Set timezone
$conn->query("SET time_zone = '+05:30'");

// Get exams
$exams = $conn->query("
    SELECT 
        e.*,

        (
            SELECT COUNT(*) 
            FROM questions 
            WHERE exam_id = e.id
        ) AS question_count,

        (
            SELECT id 
            FROM exam_attempts 
            WHERE exam_id = e.id 
            AND user_id = $userId
            LIMIT 1
        ) AS attempt_id,

        CASE 
            WHEN NOW() < e.start_time THEN 'upcoming'
            WHEN NOW() BETWEEN e.start_time AND e.end_time THEN 'live'
            ELSE 'ended'
        END AS availability

    FROM exams e
    WHERE e.status = 'active'
    ORDER BY e.start_time ASC
");

studentHeader('Available Exams', 'exams');
?>

<?php if ($msg = flash('error')): ?>
    <div class="alert alert-danger">
        ⚠️ <?= sanitize($msg) ?>
    </div>
<?php endif; ?>

<div class="exam-grid">

    <?php if ($exams->num_rows === 0): ?>

        <div class="empty-state" style="grid-column: 1 / -1;">
            <div class="empty-icon">📝</div>
            <h3>No Exams Available</h3>
            <p>There are no active exams right now. Check back later.</p>
        </div>

    <?php else: ?>

        <?php while ($exam = $exams->fetch_assoc()): ?>

            <?php

            // Count attempts
            $attemptCount = $conn->query("
                SELECT COUNT(*) as total
                FROM exam_attempts
                WHERE user_id = $userId
                AND exam_id = {$exam['id']}
            ")->fetch_assoc()['total'];

            // Remaining attempts
            $remainingAttempts = $exam['max_attempts'] - $attemptCount;

            ?>

            <div class="exam-card">

                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:6px;">

                    <div class="exam-title" style="margin-bottom:0;">
                        <?= sanitize($exam['title']) ?>
                    </div>

                    <?php if ($remainingAttempts <= 0): ?>

                        <span class="badge badge-success">
                            ✓ Completed
                        </span>

                    <?php elseif ($exam['availability'] === 'live'): ?>

                        <span class="badge badge-success">
                            ● LIVE
                        </span>

                    <?php elseif ($exam['availability'] === 'upcoming'): ?>

                        <span class="badge badge-info">
                            🕐 Upcoming
                        </span>

                    <?php else: ?>

                        <span class="badge badge-default">
                            Ended
                        </span>

                    <?php endif; ?>

                </div>

                <?php if (!empty($exam['description'])): ?>

                    <div class="exam-desc">
                        <?= sanitize(substr($exam['description'], 0, 120)) ?>
                    </div>

                <?php endif; ?>

                <div class="exam-meta">

                    <div class="exam-meta-item">
                        <span class="icon">⏱️</span>
                        <?= $exam['duration'] ?> minutes
                    </div>

                    <div class="exam-meta-item">
                        <span class="icon">❓</span>
                        <?= $exam['question_count'] ?> questions
                    </div>

                    <div class="exam-meta-item">
                        <span class="icon">📊</span>
                        <?= $exam['total_marks'] ?> marks
                    </div>

                </div>

                <div class="exam-rules">

                    <?php if ($exam['webcam_enabled']): ?>
                        <span class="badge badge-info">📷 Webcam</span>
                    <?php endif; ?>

                    <?php if (($exam['face_detection'] ?? 0)): ?>
                        <span class="badge badge-info">🧑 Face Detection</span>
                    <?php endif; ?>

                    <?php if (($exam['audio_monitoring'] ?? 0)): ?>
                        <span class="badge badge-info">🎤 Audio</span>
                    <?php endif; ?>

                    <?php if (($exam['fullscreen_required'] ?? 0)): ?>
                        <span class="badge badge-info">🖥️ Fullscreen</span>
                    <?php endif; ?>

                    <span class="badge badge-warning">
                        ⚠️ Max violations:
                        <?= $exam['max_violations'] ?? 5 ?>
                    </span>

                    <?php
                    $keyCheck = $conn->query("
                        SELECT id
                        FROM exam_keys
                        WHERE exam_id = {$exam['id']}
                        LIMIT 1
                    ");
                    ?>

                    <?php if (!empty($exam['access_key']) || $keyCheck->num_rows > 0): ?>
                        <span class="badge badge-default">🔑 Key required</span>
                    <?php endif; ?>

                </div>

                <div style="font-size:12px; color:var(--text-muted); margin-bottom:16px;">

                    📅
                    <?= formatDateTime($exam['start_time']) ?>
                    —
                    <?= formatDateTime($exam['end_time']) ?>

                </div>

                <!-- BUTTON LOGIC -->

                <?php if ($exam['availability'] === 'live' && $remainingAttempts > 0): ?>

                    <a href="<?= BASE_URL ?>/student/pre_exam.php?id=<?= $exam['id'] ?>"
                       class="btn btn-primary btn-block">

                        <?= $attemptCount > 0 ? 'Reattempt Exam' : 'Start Exam' ?>

                    </a>

                    <div style="text-align:center; margin-top:8px; font-size:12px; color:#888;">

                        Remaining Attempts:
                        <?= $remainingAttempts ?>

                    </div>

                <?php elseif ($attemptCount > 0): ?>

                    <a href="<?= BASE_URL ?>/student/result_detail.php?attempt_id=<?= $exam['attempt_id'] ?>"
                       class="btn btn-outline btn-block">

                        View Result

                    </a>

                    <div style="text-align:center; margin-top:8px; font-size:12px; color:#888;">

                        Attempts Used:
                        <?= $attemptCount ?>/<?= $exam['max_attempts'] ?>

                    </div>

                <?php elseif ($exam['availability'] === 'upcoming'): ?>

                    <span class="badge badge-info"
                          style="width:100%; justify-content:center; padding:10px;">

                        🕐 Starts:
                        <?= formatDateTime($exam['start_time']) ?>

                    </span>

                <?php else: ?>

                    <span class="badge badge-default"
                          style="width:100%; justify-content:center; padding:10px;">

                        Exam Ended

                    </span>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php endif; ?>

</div>

<?php studentFooter(); ?>