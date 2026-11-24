<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success' => false]); exit; }

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['image'])) { echo json_encode(['success' => false]); exit; }

$attemptId = (int)($data['attempt_id'] ?? 0);
$faceCount = isset($data['face_count']) ? (int)$data['face_count'] : null;
$userId = $_SESSION['user_id'];

if (!$attemptId) { echo json_encode(['success' => false]); exit; }

$imageData = str_replace(['data:image/jpeg;base64,', 'data:image/png;base64,'], '', $data['image']);
$imageData = str_replace(' ', '+', $imageData);
$decoded = base64_decode($imageData);
if (!$decoded) { echo json_encode(['success' => false]); exit; }

// Save to a predictable filename that gets overwritten every time
// Admin polls this specific file for the live feed
$dir = __DIR__ . '/../uploads/live/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = "live_{$attemptId}.jpg";
$filepath = $dir . $filename;
$relativePath = "uploads/live/" . $filename;

file_put_contents($filepath, $decoded);

// Also save face count in a sidecar JSON for the admin to read
$metaFile = $dir . "live_{$attemptId}.json";
file_put_contents($metaFile, json_encode([
    'attempt_id' => $attemptId,
    'user_id' => $userId,
    'face_count' => $faceCount,
    'timestamp' => date('Y-m-d H:i:s'),
]));

echo json_encode(['success' => true]);
