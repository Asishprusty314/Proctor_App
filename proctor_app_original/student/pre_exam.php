<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireStudent();

$userId = $_SESSION['user_id'];
$examId = (int)($_GET['id'] ?? 0);
$conn->query("SET time_zone = '+05:30'");

if (!$examId) { header('Location: ' . BASE_URL . '/student/exams.php'); exit; }

$exam = $conn->query("SELECT * FROM exams WHERE id = $examId AND status = 'active'")->fetch_assoc();
if (!$exam) { header('Location: ' . BASE_URL . '/student/exams.php'); exit; }
// Count previous attempts
$attemptCount = $conn->query("
    SELECT COUNT(*) as total
    FROM exam_attempts
    WHERE user_id = $userId
    AND exam_id = $examId
")->fetch_assoc()['total'];

// Stop if maximum attempts reached
if ($attemptCount >= $exam['max_attempts']) {

    flash('error', 'Maximum exam attempts reached.');

    header('Location: ' . BASE_URL . '/student/exams.php');
    exit;
}


$requiresKey = !empty($exam['access_key']) || $conn->query("SELECT id FROM exam_keys WHERE exam_id=$examId LIMIT 1")->num_rows > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Readiness Check — ExamProctor</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .pre-exam-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg-body, #f4f2ee); padding: 20px; }
        .pre-exam-card { background: #fff; border-radius: 20px; padding: 40px; width: 100%; max-width: 560px; border: 1px solid #e0ddd7; box-shadow: 0 20px 60px rgba(0,0,0,.04); }
        .pre-header { text-align: center; margin-bottom: 30px; }
        .pre-header h1 { font-size: 22px; font-weight: 800; margin-bottom: 4px; }
        .pre-header p { font-size: 13px; color: #8c8579; }
        .check-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 24px; }
        .check-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-radius: 10px; border: 1px solid #e0ddd7; transition: all .3s; }
        .check-item.pass { border-color: rgba(5,150,105,.2); background: rgba(5,150,105,.04); }
        .check-item.fail { border-color: rgba(225,29,72,.2); background: rgba(225,29,72,.04); }
        .check-item.pending { border-color: rgba(180,83,9,.2); background: rgba(180,83,9,.04); }
        .check-label { font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .check-status { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
        .check-status.pass { background: rgba(5,150,105,.12); color: #059669; }
        .check-status.fail { background: rgba(225,29,72,.12); color: #e11d48; }
        .check-status.pending { background: rgba(180,83,9,.12); color: #b45309; }
        .check-status.checking { background: rgba(3,105,161,.12); color: #0369a1; }
        .key-section { margin-bottom: 24px; padding: 18px; background: #faf9f6; border-radius: 10px; border: 1px solid #e0ddd7; }
        .key-section label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #5c564d; }
        .key-section input { width: 100%; padding: 11px 14px; border: 1.5px solid #e0ddd7; border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 14px; letter-spacing: 2px; text-align: center; outline: none; transition: all .2s; }
        .key-section input:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,.1); }
        .key-error { color: #e11d48; font-size: 12px; font-weight: 600; margin-top: 6px; display: none; }
        .start-btn { width: 100%; padding: 14px; border-radius: 10px; font-size: 15px; font-weight: 700; background: #059669; color: #fff; border: none; cursor: pointer; font-family: inherit; transition: all .25s; box-shadow: 0 2px 8px rgba(5,150,105,.2); }
        .start-btn:hover:not(:disabled) { background: #10b981; transform: translateY(-1px); }
        .start-btn:disabled { opacity: .4; cursor: not-allowed; transform: none; }
        .start-btn:active:not(:disabled) { transform: scale(.98); }
        .back-link { display: block; text-align: center; margin-top: 16px; font-size: 13px; color: #8c8579; }
        .back-link:hover { color: #059669; }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(0,0,0,.1); border-top-color: currentColor; border-radius: 50%; animation: spin .6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="pre-exam-wrap">
    <div class="pre-exam-card">
        <div class="pre-header">
            <h1><?= sanitize($exam['title']) ?></h1>
            <p>Complete the readiness checks before starting your exam</p>
        </div>

        <div class="check-list">
            <div class="check-item pending" id="check-browser">
                <span class="check-label">🌐 Browser Compatibility</span>
                <span class="check-status checking"><span class="spinner"></span></span>
            </div>
            <div class="check-item pending" id="check-camera">
                <span class="check-label">📷 Camera Access</span>
                <span class="check-status pending">Waiting</span>
            </div>
            <?php if ($exam['audio_monitoring']): ?>
            <div class="check-item pending" id="check-mic">
                <span class="check-label">🎤 Microphone Access</span>
                <span class="check-status pending">Waiting</span>
            </div>
            <?php endif; ?>
            <?php if ($exam['fullscreen_required']): ?>
            <div class="check-item pending" id="check-fullscreen">
                <span class="check-label">🖥️ Fullscreen Support</span>
                <span class="check-status pending">Waiting</span>
            </div>
            <?php endif; ?>
            <?php if ($exam['face_detection']): ?>
            <div class="check-item pending" id="check-face">
                <span class="check-label">🧑 Face Detection</span>
                <span class="check-status pending">Waiting</span>
            </div>
            <?php endif; ?>
            <div class="check-item pending" id="check-network">
                <span class="check-label">🌍 Network Connection</span>
                <span class="check-status pending">Waiting</span>
            </div>
        </div>

        <?php if ($requiresKey): ?>
        <div class="key-section" id="key-section">
            <label>🔑 Enter Access Key</label>
            <input type="text" id="access-key" placeholder="Enter your exam key" maxlength="50">
            <div class="key-error" id="key-error"></div>
        </div>
        <?php endif; ?>

        <button class="start-btn" id="start-btn" disabled onclick="proceedToExam()">Checking readiness...</button>
        <a href="<?= BASE_URL ?>/student/exams.php" class="back-link">← Back to exams</a>
    </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const EXAM_ID = <?= $examId ?>;
const WEBCAM_ENABLED = <?= $exam['webcam_enabled'] ? 'true' : 'false' ?>;
const AUDIO_ENABLED = <?= $exam['audio_monitoring'] ? 'true' : 'false' ?>;
const FULLSCREEN_REQ = <?= $exam['fullscreen_required'] ? 'true' : 'false' ?>;
const FACE_DETECTION = <?= $exam['face_detection'] ? 'true' : 'false' ?>;
const REQUIRES_KEY = <?= $requiresKey ? 'true' : 'false' ?>;

let allPassed = false;
let keyVerified = !REQUIRES_KEY; // true if no key needed

function setCheck(id, status, text) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'check-item ' + status;
    el.querySelector('.check-status').className = 'check-status ' + status;
    el.querySelector('.check-status').textContent = text;
}

async function runChecks() {
    // Browser check
    const hasMedia = !!navigator.mediaDevices?.getUserMedia;
    setCheck('check-browser', 'pass' ,'Disabled');

    // Camera check
    if (WEBCAM_ENABLED) {
        try {
            const constraints = { video: { width: 640, height: 480 } };
            if (AUDIO_ENABLED) constraints.audio = true;
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            setCheck('check-camera', 'pass', 'Granted');
            if (AUDIO_ENABLED) setCheck('check-mic', 'pass', 'Granted');
            // Keep stream for later
            window._proctorStream = stream;
        } catch (e) {
            setCheck('check-camera', 'fail', 'Denied');
            if (AUDIO_ENABLED) setCheck('check-mic', 'fail', 'Denied');
        }
    } else {
        setCheck('check-camera', 'pass', 'Not required');
    }

    // Fullscreen check
    if (FULLSCREEN_REQ) {
        const fsSupport = document.documentElement.requestFullscreen !== undefined;
        setCheck('check-fullscreen', fsSupport ? 'pass' : 'fail', fsSupport ? 'Supported' : 'Not supported');
    }

    // Face detection check
    // if (FACE_DETECTION) {
    //     setCheck('check-face', 'pass', 'Available');
    // }

    // Network check
    setCheck('check-network', navigator.onLine ? 'pass' : 'fail', navigator.onLine ? 'Connected' : 'Offline');

    // Evaluate overall readiness
    const failedChecks = document.querySelectorAll('.check-item.fail');
    const btn = document.getElementById('start-btn');

    if (failedChecks.length === 0) {
        allPassed = true;
        updateStartButton();
    } else {
        btn.textContent = failedChecks.length + ' check(s) failed — Fix and retry';
        btn.disabled = true;
    }
}

function updateStartButton() {
    const btn = document.getElementById('start-btn');
    if (allPassed && keyVerified) {
        btn.disabled = false;
        btn.textContent = 'All checks passed — Start exam';
    } else if (allPassed && !keyVerified) {
        btn.disabled = true;
        btn.textContent = 'Enter access key above';
    }
}

// Access key verification
if (REQUIRES_KEY) {
    const keyInput = document.getElementById('access-key');
    const keyError = document.getElementById('key-error');

    keyInput.addEventListener('input', async function() {
        const key = this.value.trim();
        keyError.style.display = 'none';

        if (key.length < 3) { keyVerified = false; updateStartButton(); return; }

        try {
            const resp = await fetch(BASE_URL + '/api/verify_access_key.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ exam_id: EXAM_ID, access_key: key })
            });
            const data = await resp.json();

            if (data.success) {
                keyVerified = true;
                keyInput.style.borderColor = '#059669';
                keyInput.style.background = 'rgba(5,150,105,.04)';
            } else {
                keyVerified = false;
                keyError.textContent = data.error || 'Invalid key';
                keyError.style.display = 'block';
                keyInput.style.borderColor = '#e11d48';
            }
        } catch (e) {
            keyError.textContent = 'Network error';
            keyError.style.display = 'block';
        }

        updateStartButton();
    });
}

function proceedToExam() {
    // Stop preview stream
    if (window._proctorStream) {
        window._proctorStream.getTracks().forEach(t => t.stop());
    }
    window.location.href = BASE_URL + '/student/take_exam.php?id=' + EXAM_ID;
}

// Run checks on load
runChecks();
</script>
</body>
</html>
