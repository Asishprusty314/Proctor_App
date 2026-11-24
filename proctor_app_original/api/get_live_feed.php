<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) { 
    echo json_encode(['success' => false]); 
    exit; 
}

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if (!$attemptId) { echo json_encode(['success' => false]); exit; }

// Get latest snapshot
$snap = $conn->query("
    SELECT image_path, face_count, has_face, flagged, created_at 
    FROM snapshots 
    WHERE attempt_id = $attemptId 
    ORDER BY id DESC 
    LIMIT 1
")->fetch_assoc();

// Get latest violation
$violation = $conn->query("
    SELECT type, severity, message, created_at 
    FROM violations 
    WHERE attempt_id = $attemptId 
    ORDER BY id DESC 
    LIMIT 1
")->fetch_assoc();

// Get attempt status
$attempt = $conn->query("
    SELECT status, tab_switches, total_violations, last_heartbeat, score 
    FROM exam_attempts 
    WHERE id = $attemptId
")->fetch_assoc();

echo json_encode([
    'success' => true,
    'snapshot' => $snap ? [
        'url' => BASE_URL . '/' . $snap['image_path'],
        'face_count' => $snap['face_count'],
        'flagged' => $snap['flagged'],
        'time' => $snap['created_at'],
    ] : null,
    'violation' => $violation,
    'attempt' => $attempt,
    'timestamp' => date('Y-m-d H:i:s'),
]);
