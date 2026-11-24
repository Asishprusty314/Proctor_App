<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$status = $data['status'] ?? 'completed';
$tabSwitches = (int)($data['tab_switches'] ?? 0);
$totalViolations = (int)($data['total_violations'] ?? 0);
$autoSubmitReason = $data['auto_submit_reason'] ?? null;

$allowed = ['completed','auto_submitted','violation'];
if (!in_array($status, $allowed)) $status = 'completed';

$userId = $_SESSION['user_id'];
$attempt = $conn->query("SELECT * FROM exam_attempts WHERE id=$attemptId AND user_id=$userId")->fetch_assoc();
if (!$attempt) { echo json_encode(['success'=>false]); exit; }

// Calculate score from answers
$scoreR = $conn->query("SELECT COALESCE(SUM(marks_obtained),0) as total FROM answers WHERE attempt_id=$attemptId");
$totalScore = $scoreR->fetch_assoc()['total'];

$stmt = $conn->prepare("UPDATE exam_attempts SET end_time=NOW(), score=?, tab_switches=?, total_violations=?, status=?, auto_submit_reason=? WHERE id=?");
$stmt->bind_param("iiissi", $totalScore, $tabSwitches, $totalViolations, $status, $autoSubmitReason, $attemptId);
$result = $stmt->execute();
$stmt->close();

// Deactivate session
$conn->query("UPDATE exam_sessions SET is_active=0 WHERE attempt_id=$attemptId");

// Clean up live frame files
$liveDir = __DIR__ . '/../uploads/live/';
@unlink($liveDir . "live_{$attemptId}.jpg");
@unlink($liveDir . "live_{$attemptId}.json");

echo json_encode(['success'=>$result,'score'=>$totalScore,'total_marks'=>$attempt['total_marks']]);
