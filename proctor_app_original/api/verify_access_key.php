<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$examId = (int)($data['exam_id'] ?? 0);
$key = trim($data['access_key'] ?? '');
$userId = $_SESSION['user_id'];

if (!$examId || !$key) { echo json_encode(['success'=>false,'error'=>'Missing exam ID or key']); exit; }

// Check exam-level key first
$exam = $conn->query("SELECT access_key FROM exams WHERE id=$examId")->fetch_assoc();
if ($exam && $exam['access_key'] && $exam['access_key'] === $key) {
    $_SESSION['exam_key_verified_' . $examId] = true;
    echo json_encode(['success'=>true,'type'=>'exam_key']);
    exit;
}

// Check user-specific key
$stmt = $conn->prepare("SELECT id, user_id, is_used FROM exam_keys WHERE exam_id=? AND access_key=?");
$stmt->bind_param("is", $examId, $key);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) { echo json_encode(['success'=>false,'error'=>'Invalid access key']); exit; }
if ($result['is_used']) { echo json_encode(['success'=>false,'error'=>'This key has already been used']); exit; }
if ($result['user_id'] && $result['user_id'] != $userId) { echo json_encode(['success'=>false,'error'=>'This key is not assigned to you']); exit; }

// Mark key as used
$conn->query("UPDATE exam_keys SET is_used=1, used_at=NOW(), used_by=$userId WHERE id={$result['id']}");
$_SESSION['exam_key_verified_' . $examId] = true;
echo json_encode(['success'=>true,'type'=>'user_key']);
