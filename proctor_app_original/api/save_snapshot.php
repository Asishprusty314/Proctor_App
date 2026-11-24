<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['image'])) { echo json_encode(['success'=>false]); exit; }

$attemptId = (int)($data['attempt_id'] ?? 0);
$faceCount = isset($data['face_count']) ? (int)$data['face_count'] : null;
$hasFace = isset($data['has_face']) ? (int)$data['has_face'] : null;
$userId = $_SESSION['user_id'];

$imageData = str_replace(['data:image/jpeg;base64,','data:image/png;base64,'], '', $data['image']);
$imageData = str_replace(' ', '+', $imageData);
$decoded = base64_decode($imageData);
if (!$decoded) { echo json_encode(['success'=>false,'error'=>'Invalid image']); exit; }

$dir = __DIR__ . '/../uploads/snapshots/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = "snap_{$userId}_{$attemptId}_" . time() . '_' . rand(100,999) . ".jpg";
$filepath = $dir . $filename;
$relativePath = "uploads/snapshots/" . $filename;

$flagged = ($faceCount !== null && ($faceCount === 0 || $faceCount > 1)) ? 1 : 0;

if (file_put_contents($filepath, $decoded)) {
    $stmt = $conn->prepare("INSERT INTO snapshots (attempt_id, user_id, image_path, face_count, has_face, flagged) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("iisiii", $attemptId, $userId, $relativePath, $faceCount, $hasFace, $flagged);
    $result = $stmt->execute();
    $stmt->close();
    echo json_encode(['success'=>$result]);
} else {
    echo json_encode(['success'=>false]);
}
