<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
$attemptId = (int)($data['attempt_id'] ?? 0);
$answers = $data['answers'] ?? [];
$userId = $_SESSION['user_id'];

if (!$attemptId || empty($answers)) { echo json_encode(['success'=>false]); exit; }

// Verify attempt belongs to user
$check = $conn->query("SELECT id FROM exam_attempts WHERE id=$attemptId AND user_id=$userId AND status='in_progress'");
if ($check->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'Invalid attempt']); exit; }

$saved = 0;
foreach ($answers as $qid => $answer) {
    $qid = (int)$qid;
    $answerStr = is_array($answer) ? implode(',', $answer) : $conn->real_escape_string($answer);
    
    // Save to autosave table
    $stmt = $conn->prepare("INSERT INTO answer_autosave (attempt_id, question_id, answer) VALUES (?,?,?) ON DUPLICATE KEY UPDATE answer=VALUES(answer), saved_at=NOW()");
    $stmt->bind_param("iis", $attemptId, $qid, $answerStr);
    $stmt->execute();
    $stmt->close();
    
    // Also save to real answers table
    $isCorrect = null;
    $marksObtained = 0;
    $stmt2 = $conn->prepare("INSERT INTO answers (attempt_id, question_id, answer, is_correct, marks_obtained) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE answer=VALUES(answer)");
    $stmt2->bind_param("iisii", $attemptId, $qid, $answerStr, $isCorrect, $marksObtained);
    $stmt2->execute();
    $stmt2->close();
    $saved++;
}

echo json_encode(['success'=>true,'saved'=>$saved]);
