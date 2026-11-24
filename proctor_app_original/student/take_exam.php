<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId = $_SESSION['user_id'];
$examId = (int)($_GET['id'] ?? 0);
$conn->query("SET time_zone = '+05:30'");

if (!$examId) { header('Location: ' . BASE_URL . '/student/exams.php'); exit; }

// Get exam
$exam = $conn->query("SELECT * FROM exams WHERE id = $examId AND status = 'active'")->fetch_assoc();
if (!$exam) {
    flash('error', 'Exam not found or inactive.');
    header('Location: ' . BASE_URL . '/student/exams.php');
    exit;
}

// Access key check — redirect to pre_exam if exam requires key and user hasn't verified
$requiresKey = !empty($exam['access_key']) || $conn->query("SELECT id FROM exam_keys WHERE exam_id=$examId LIMIT 1")->num_rows > 0;
$existingAttempt = $conn->query("SELECT id FROM exam_attempts WHERE user_id=$userId AND exam_id=$examId")->fetch_assoc();
if ($requiresKey && !$existingAttempt && !isset($_SESSION['exam_key_verified_' . $examId])) {
    header('Location: ' . BASE_URL . '/student/pre_exam.php?id=' . $examId);
    exit;
}

// Check if within time window
$now = time();
$start = strtotime($exam['start_time']);
$end = strtotime($exam['end_time']);
if ($now < $start) {
    flash('error', 'This exam has not started yet. It starts at: ' . date('M d, Y h:i A', $start));
    header('Location: ' . BASE_URL . '/student/exams.php');
    exit;
}
if ($now > $end) {
    flash('error', 'This exam has already ended.');
    header('Location: ' . BASE_URL . '/student/exams.php');
    exit;
}

// Check if already attempted
$existing = $conn->query("SELECT * FROM exam_attempts WHERE user_id=$userId AND exam_id=$examId")->fetch_assoc();

if ($existing && in_array($existing['status'], ['completed', 'auto_submitted', 'violation'])) {
    header('Location: ' . BASE_URL . '/student/result_detail.php?attempt_id=' . $existing['id']);
    exit;
}

// Create or resume attempt
if ($existing && $existing['status'] === 'in_progress') {
    $attempt = $existing;
} else {
    $stmt = $conn->prepare("INSERT INTO exam_attempts (user_id, exam_id, start_time, total_marks, status) VALUES (?, ?, NOW(), ?, 'in_progress')");
    $stmt->bind_param("iii", $userId, $examId, $exam['total_marks']);
    $stmt->execute();
    $attemptId = $stmt->insert_id;
    $stmt->close();
    $attempt = $conn->query("SELECT * FROM exam_attempts WHERE id=$attemptId")->fetch_assoc();
}

// Calculate remaining time
$examStartTime = strtotime($attempt['start_time']);
$elapsed = time() - $examStartTime;
$timeLeft = ($exam['duration'] * 60) - $elapsed;
if ($timeLeft <= 0) {
    // Auto-submit - time's up
    $conn->query("UPDATE exam_attempts SET status='auto_submitted', end_time=NOW() WHERE id={$attempt['id']}");
    header('Location: ' . BASE_URL . '/student/result_detail.php?attempt_id=' . $attempt['id']);
    exit;
}

// Get questions
$orderBy = $exam['randomize_questions'] ? "RAND()" : "sort_order, id";
$questions = $conn->query("SELECT * FROM questions WHERE exam_id = $examId ORDER BY $orderBy");
$questionList = [];
while ($q = $questions->fetch_assoc()) {
    // Shuffle options if enabled
    if ($exam['shuffle_options'] && $q['type'] === 'mcq') {
        $options = [];
        foreach (['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']] as $k => $v) {
            if ($v) $options[$k] = $v;
        }
        // Note: For true shuffling, we'd need to track the mapping. 
        // Here we keep original letters for simplicity in grading.
    }
    $questionList[] = $q;
}

// Get existing answers
$existingAnswers = [];
$ea = $conn->query("SELECT question_id, answer FROM answers WHERE attempt_id = {$attempt['id']}");
while ($a = $ea->fetch_assoc()) {
    $existingAnswers[$a['question_id']] = $a['answer'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($exam['title']) ?> - Exam | ExamProctor</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <!-- Instructions Modal -->
    <div class="modal-overlay" id="instructions-modal">
        <div class="modal">
            <h2>📋 Exam Instructions</h2>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                Please read the following instructions carefully before starting.
            </p>
            <ul class="instruction-list">
                <li><span class="inst-icon">📝</span> <strong><?= sanitize($exam['title']) ?></strong></li>
                <li><span class="inst-icon">⏱️</span> Duration: <strong><?= $exam['duration'] ?> minutes</strong></li>
                <li><span class="inst-icon">📊</span> Total Marks: <strong><?= $exam['total_marks'] ?></strong></li>
                <li><span class="inst-icon">❓</span> Total Questions: <strong><?= count($questionList) ?></strong></li>
                <?php if ($exam['webcam_enabled']): ?>
                    <li><span class="inst-icon">📷</span> Webcam monitoring is <strong>enabled</strong>. Snapshots will be captured every <?= $exam['snapshot_interval'] ?? 10 ?> seconds.</li>
                <?php endif; ?>
                <?php if ($exam['face_detection'] ?? 0): ?>
                    <li><span class="inst-icon">🧑</span> <strong>Face detection is active.</strong> Your face must be visible at all times. Multiple faces or no face will trigger violations.</li>
                <?php endif; ?>
                <?php if ($exam['audio_monitoring'] ?? 0): ?>
                    <li><span class="inst-icon">🎤</span> <strong>Audio monitoring is active.</strong> Unusual background noise will be logged.</li>
                <?php endif; ?>
                <?php if ($exam['fullscreen_required']): ?>
                    <li><span class="inst-icon">🖥️</span> Fullscreen mode is <strong>required</strong>. Do not exit fullscreen during the exam.</li>
                <?php endif; ?>
                <li><span class="inst-icon">⚠️</span> Maximum violations allowed: <strong><?= $exam['max_violations'] ?? 5 ?></strong>. Exceeding this will auto-submit your exam.</li>
                <li><span class="inst-icon">🔄</span> Tab switch limit: <strong><?= $exam['tab_switch_limit'] ?></strong>.</li>
                <li><span class="inst-icon">🚫</span> Copy/Paste, right-click, and keyboard shortcuts (Ctrl+C, F12, PrintScreen) are blocked.</li>
                <li><span class="inst-icon">💾</span> Your answers are <strong>auto-saved every 30 seconds</strong>. If you lose connection, your progress is preserved.</li>
                <li><span class="inst-icon">⏰</span> The exam will auto-submit when time runs out.</li>
                <li><span class="inst-icon">📱</span> Only <strong>one device session</strong> is allowed. Opening on another device will terminate this session.</li>
            </ul>
            <button class="btn btn-primary btn-block" onclick="startExam()">I Understand — Start Exam</button>
        </div>
    </div>

    <div class="exam-wrapper" id="exam-container" style="display:none;">
        <!-- Top Bar -->
        <div class="exam-topbar">
            <div class="exam-info">
                <h3><?= sanitize($exam['title']) ?></h3>
            </div>
            <div class="exam-topbar-right">
                <?php if ($exam['face_detection']): ?>
                <span id="face-status-bar" class="face-indicator pending" style="font-size:10px;">Detecting...</span>
                <?php endif; ?>
                <div class="violation-counter">
                    ⚠️ <strong id="violation-count"><?= $attempt['total_violations'] ?? 0 ?></strong> / <?= $exam['max_violations'] ?? 5 ?>
                </div>
                <div class="exam-timer">
                    ⏱️ <span id="exam-timer">--:--:--</span>
                </div>
                <button class="btn btn-danger btn-sm" onclick="Proctor.submitExam()">Submit Exam</button>
            </div>
        </div>

        <div class="exam-body">
            <!-- Questions Panel -->
            <div class="exam-questions-panel">
                <?php foreach ($questionList as $idx => $q): ?>
                    <div class="question-card" style="display:none;" data-index="<?= $idx ?>">
                        <div class="question-header">
                            <span class="question-number">Question <?= $idx + 1 ?> of <?= count($questionList) ?></span>
                            <span class="question-marks"><?= $q['marks'] ?> mark(s)</span>
                        </div>
                        <div class="question-text"><?= sanitize($q['question']) ?></div>

                        <?php if ($q['type'] === 'mcq'): ?>
                            <ul class="option-list">
                                <?php 
                                $options = ['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']];
                                $selectedAnswers = isset($existingAnswers[$q['id']]) ? explode(',', $existingAnswers[$q['id']]) : [];
                                foreach ($options as $letter => $text):
                                    if (!$text) continue;
                                    $isSelected = in_array($letter, $selectedAnswers);
                                ?>
                                    <li class="option-item <?= $isSelected ? 'selected' : '' ?>" data-qid="<?= $q['id'] ?>" data-value="<?= $letter ?>">
                                        <input type="checkbox" name="q_<?= $q['id'] ?>[]" value="<?= $letter ?>" <?= $isSelected ? 'checked' : '' ?>>
                                        <span class="option-letter"><?= $letter ?></span>
                                        <span class="option-text"><?= sanitize($text) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <textarea class="desc-answer" data-qid="<?= $q['id'] ?>" placeholder="Write your answer here..."><?= sanitize($existingAnswers[$q['id']] ?? '') ?></textarea>
                        <?php endif; ?>

                        <div class="question-actions">
                            <button class="btn btn-outline" id="btn-prev" onclick="ExamSystem.prevQuestion()" <?= $idx === 0 ? 'disabled' : '' ?>>← Previous</button>
                            <button class="btn btn-primary" id="btn-next" onclick="ExamSystem.nextQuestion()">
                                <?= $idx === count($questionList) - 1 ? 'Finish' : 'Next →' ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Sidebar -->
            <div class="exam-sidebar">
                <?php if ($exam['webcam_enabled']): ?>
                    <div class="webcam-container">
                        <h4>📷 Webcam Feed</h4>
                        <div class="webcam-preview">
                            <video id="webcam-video" autoplay muted playsinline></video>
                        </div>
                    </div>
                <?php endif; ?>

                <h4 style="font-size: 13px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">Question Navigator</h4>
                <div class="question-nav">
                    <?php foreach ($questionList as $idx => $q): 
                        $answered = isset($existingAnswers[$q['id']]);
                    ?>
                        <button class="q-nav-btn <?= $answered ? 'answered' : '' ?>" data-qid="<?= $q['id'] ?>"><?= $idx + 1 ?></button>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px; padding: 16px; background: var(--bg-card); border-radius: var(--radius-sm); border: 1px solid var(--border);">
                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">Legend</div>
                    <div style="display: flex; flex-direction: column; gap: 6px; font-size: 13px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width:20px;height:20px;border-radius:4px;background:var(--success-bg);border:1px solid var(--success);"></div>
                            <span>Answered</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width:20px;height:20px;border-radius:4px;background:var(--accent);"></div>
                            <span>Current</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width:20px;height:20px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border);"></div>
                            <span>Not Answered</span>
                        </div>
                    </div>
                </div>

                <button class="btn btn-danger btn-block" style="margin-top: 20px;" onclick="Proctor.submitExam()">
                    Submit Exam
                </button>

                <!-- Face & Audio indicators -->
                <?php if ($exam['face_detection']): ?>
                <div style="margin-top:12px;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:10px;color:var(--text-muted,#8c8579);text-transform:uppercase;font-weight:700;letter-spacing:1px;">Face</span>
                    <span id="face-status" class="face-indicator pending">Checking...</span>
                </div>
                <?php endif; ?>
                <?php if ($exam['audio_monitoring']): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:8px;">
                    <span style="font-size:10px;color:var(--text-muted,#8c8579);text-transform:uppercase;font-weight:700;letter-spacing:1px;">Audio</span>
                    <div class="audio-meter"><div class="audio-meter-fill" id="audio-level" style="width:0%"></div></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($exam['face_detection']): ?>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.min.js"></script>
    <?php endif; ?>
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/proctor.js"></script>
    <script>
        async function startExam() {
            document.getElementById('instructions-modal').style.display = 'none';
            document.getElementById('exam-container').style.display = 'block';

            // Initialize legacy exam system (timer, question nav)
            ExamSystem.initExam({
                attemptId: <?= $attempt['id'] ?>,
                examId: <?= $examId ?>,
                timeLeft: <?= $timeLeft ?>,
                tabSwitchLimit: <?= $exam['tab_switch_limit'] ?>,
                fullscreenRequired: false, // Proctor handles this now
                webcamEnabled: false,      // Proctor handles this now
                totalQuestions: <?= count($questionList) ?>,
                baseUrl: '<?= BASE_URL ?>'
            });

            // Initialize enterprise proctoring engine
            await Proctor.init({
                baseUrl: '<?= BASE_URL ?>',
                attemptId: <?= $attempt['id'] ?>,
                examId: <?= $examId ?>,
                userId: <?= $userId ?>,
                webcamEnabled: <?= $exam['webcam_enabled'] ? 'true' : 'false' ?>,
                faceDetection: <?= $exam['face_detection'] ? 'true' : 'false' ?>,
                audioMonitoring: <?= $exam['audio_monitoring'] ? 'true' : 'false' ?>,
                fullscreenRequired: <?= $exam['fullscreen_required'] ? 'true' : 'false' ?>,
                tabSwitchLimit: <?= $exam['tab_switch_limit'] ?>,
                maxViolations: <?= $exam['max_violations'] ?? 5 ?>,
                snapshotInterval: <?= $exam['snapshot_interval'] ?? 10 ?>,
            });

            // Request permissions and start webcam
            if (<?= $exam['webcam_enabled'] ? 'true' : 'false' ?>) {
                const perms = await Proctor.requestPermissions();
                if (perms.camera === 'granted') {
                    Proctor.startWebcam(document.getElementById('webcam-video'));

                    // Start face detection
                    if (<?= $exam['face_detection'] ? 'true' : 'false' ?>) {
                        await Proctor.initFaceDetection();
                    }

                    // Start audio monitoring
                    if (<?= $exam['audio_monitoring'] ? 'true' : 'false' ?>) {
                        await Proctor.initAudioMonitoring();
                    }
                }
            }

            // Start fullscreen
            if (<?= $exam['fullscreen_required'] ? 'true' : 'false' ?>) {
                Proctor.initFullscreenMonitor();
            }

            // Override ExamSystem submit to use Proctor
            ExamSystem.submitExam = () => Proctor.submitExam();
            ExamSystem.autoSubmitExam = (reason) => Proctor.autoSubmit(reason);
        }
    </script>
</body>
</html>
