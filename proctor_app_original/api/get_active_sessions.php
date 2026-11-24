<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) { 
    echo json_encode(['success' => false]); 
    exit; 
}

// Get all active attempts with latest snapshot and violation counts
$result = $conn->query("
    SELECT ea.id as attempt_id, ea.user_id, ea.exam_id, ea.status, 
           ea.tab_switches, ea.total_violations, ea.last_heartbeat,
           u.name as student_name, u.email as student_email,
           e.title as exam_title, e.max_violations,
           es.ip_address, es.browser, es.last_heartbeat as session_hb,
           (SELECT image_path FROM snapshots WHERE attempt_id = ea.id ORDER BY id DESC LIMIT 1) as latest_snap,
           (SELECT face_count FROM snapshots WHERE attempt_id = ea.id ORDER BY id DESC LIMIT 1) as latest_face_count,
           (SELECT COUNT(*) FROM snapshots WHERE attempt_id = ea.id AND flagged = 1) as flagged_count,
           (SELECT camera_status FROM permission_logs WHERE attempt_id = ea.id ORDER BY id DESC LIMIT 1) as cam_status
    FROM exam_attempts ea
    JOIN users u ON ea.user_id = u.id
    JOIN exams e ON ea.exam_id = e.id
    LEFT JOIN exam_sessions es ON ea.id = es.attempt_id AND es.is_active = 1
    WHERE ea.status = 'in_progress'
    ORDER BY ea.start_time DESC
");

$sessions = [];
while ($row = $result->fetch_assoc()) {
    $hbAge = $row['session_hb'] ? (time() - strtotime($row['session_hb'])) : 999;
    $row['heartbeat_age'] = $hbAge;
    
    // Use live frame file (updated every 3s) instead of DB snapshot (every 10s)
    $liveFramePath = __DIR__ . '/../uploads/live/live_' . $row['attempt_id'] . '.jpg';
    $liveMetaPath = __DIR__ . '/../uploads/live/live_' . $row['attempt_id'] . '.json';
    
    if (file_exists($liveFramePath)) {
        $row['snap_url'] = BASE_URL . '/uploads/live/live_' . $row['attempt_id'] . '.jpg';
        // Read face count from live meta
        if (file_exists($liveMetaPath)) {
            $meta = json_decode(file_get_contents($liveMetaPath), true);
            if ($meta && isset($meta['face_count'])) {
                $row['latest_face_count'] = $meta['face_count'];
            }
        }
    } elseif ($row['latest_snap']) {
        $row['snap_url'] = BASE_URL . '/' . $row['latest_snap'];
    } else {
        $row['snap_url'] = null;
    }
    
    $sessions[] = $row;
}

// Recent violations (last 10)
$violations = [];
$vr = $conn->query("
    SELECT v.type, v.severity, v.message, v.created_at, u.name as student_name
    FROM violations v
    JOIN users u ON v.user_id = u.id
    WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY v.created_at DESC
    LIMIT 10
");
while ($v = $vr->fetch_assoc()) {
    $violations[] = $v;
}

echo json_encode([
    'success' => true,
    'sessions' => $sessions,
    'violations' => $violations,
    'count' => count($sessions),
    'timestamp' => date('Y-m-d H:i:s'),
]);
