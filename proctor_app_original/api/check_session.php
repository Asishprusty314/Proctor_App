<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$token = $data['session_token'] ?? '';
$action = $data['action'] ?? 'check';
$userId = $_SESSION['user_id'];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Detect browser
$browser = 'Unknown';
if (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';

if ($action === 'register') {
    // Deactivate any existing sessions for this user
    $conn->query("UPDATE exam_sessions SET is_active=0 WHERE user_id=$userId AND is_active=1");
    
    $stmt = $conn->prepare("INSERT INTO exam_sessions (user_id, attempt_id, session_token, ip_address, user_agent, browser, last_heartbeat) VALUES (?,?,?,?,?,?,NOW())");
    $stmt->bind_param("iissss", $userId, $attemptId, $token, $ip, $ua, $browser);
    $stmt->execute();
    $stmt->close();
    
    // Store session info on attempt
    $conn->query("UPDATE exam_attempts SET ip_address='$ip', user_agent='" . $conn->real_escape_string($ua) . "', session_token='$token' WHERE id=$attemptId");
    
    echo json_encode(['success'=>true]);
} else {
    // Check if this token is still the active one
    $result = $conn->query("SELECT id FROM exam_sessions WHERE user_id=$userId AND session_token='$token' AND is_active=1");
    $valid = $result->num_rows > 0;
    echo json_encode(['success'=>true,'session_valid'=>$valid]);
}
