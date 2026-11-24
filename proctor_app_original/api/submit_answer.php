<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$attemptId = (int)($data['attempt_id'] ?? 0);
$questionId = (int)($data['question_id'] ?? 0);
$answer = trim($data['answer'] ?? '');

if (!$attemptId || !$questionId) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Verify attempt belongs to user and is in progress
$userId = $_SESSION['user_id'];
$attempt = $conn->query("SELECT id FROM exam_attempts WHERE id=$attemptId AND user_id=$userId AND status='in_progress'")->fetch_assoc();
if (!$attempt) {
    echo json_encode(['success' => false, 'error' => 'Invalid attempt']);
    exit;
}

// Save answer without auto-grading — all answers will be graded manually by admin
$isCorrect = null;
$marksObtained = 0;

// Insert or update answer (no auto-grading, marks stay 0 until admin reviews)
$stmt = $conn->prepare("INSERT INTO answers (attempt_id, question_id, answer, is_correct, marks_obtained) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE answer = VALUES(answer)");
$stmt->bind_param("iisii", $attemptId, $questionId, $answer, $isCorrect, $marksObtained);
$result = $stmt->execute();
$stmt->close();

echo json_encode(['success' => $result]);
