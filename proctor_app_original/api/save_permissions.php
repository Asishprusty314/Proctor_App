<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$camStatus = $data['camera_status'] ?? 'denied';
$micStatus = $data['mic_status'] ?? 'denied';
$userId = $_SESSION['user_id'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $conn->prepare("INSERT INTO permission_logs (attempt_id, user_id, camera_status, mic_status, ip_address, user_agent) VALUES (?,?,?,?,?,?)");
$stmt->bind_param("iissss", $attemptId, $userId, $camStatus, $micStatus, $ip, $ua);
$result = $stmt->execute();
$stmt->close();
echo json_encode(['success'=>$result]);
