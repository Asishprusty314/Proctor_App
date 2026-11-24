<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$token = $data['session_token'] ?? '';
$tabSwitches = (int)($data['tab_switches'] ?? 0);
$totalViolations = (int)($data['total_violations'] ?? 0);
$userId = $_SESSION['user_id'];

// Update heartbeat
$conn->query("UPDATE exam_sessions SET last_heartbeat=NOW() WHERE session_token='" . $conn->real_escape_string($token) . "' AND user_id=$userId");
$conn->query("UPDATE exam_attempts SET last_heartbeat=NOW(), tab_switches=$tabSwitches, total_violations=$totalViolations WHERE id=$attemptId AND user_id=$userId");

// Check for duplicate sessions
$r = $conn->query("SELECT session_token FROM exam_sessions WHERE user_id=$userId AND is_active=1 ORDER BY last_heartbeat DESC LIMIT 1");
$row = $r->fetch_assoc();
$sessionInvalid = ($row && $row['session_token'] !== $token);

// Check if admin force-submitted
$attempt = $conn->query("SELECT status FROM exam_attempts WHERE id=$attemptId")->fetch_assoc();
$forceSubmit = ($attempt && in_array($attempt['status'], ['completed','auto_submitted','violation']));

echo json_encode([
    'success' => true,
    'session_invalid' => $sessionInvalid,
    'force_submit' => $forceSubmit,
    'timestamp' => date('Y-m-d H:i:s'),
]);
