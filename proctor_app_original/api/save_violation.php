<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false]); exit; }

$attemptId = (int)($data['attempt_id'] ?? 0);
$examId = (int)($data['exam_id'] ?? 0);
$type = $data['type'] ?? 'tab_switch';
$severity = $data['severity'] ?? 'medium';
$message = $data['message'] ?? '';
$metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
$tabSwitches = (int)($data['tab_switches'] ?? 0);
$totalViolations = (int)($data['total_violations'] ?? 0);

$allowedTypes = ['tab_switch','window_blur','no_face','multiple_faces','fullscreen_exit','copy_paste','keyboard_shortcut','audio_spike','permission_denied','webcam_blocked','mic_blocked','device_switch'];
if (!in_array($type, $allowedTypes)) $type = 'tab_switch';

$userId = $_SESSION['user_id'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $conn->prepare("INSERT INTO violations (attempt_id, user_id, exam_id, type, severity, message, metadata, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiissssss", $attemptId, $userId, $examId, $type, $severity, $message, $metadata, $ip, $ua);
$result = $stmt->execute();
$stmt->close();

// Update attempt counters
$conn->query("UPDATE exam_attempts SET tab_switches=$tabSwitches, total_violations=$totalViolations WHERE id=$attemptId AND user_id=$userId");

echo json_encode(['success'=>$result]);
