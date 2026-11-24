<?php

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function flash($key, $message = null) {
    if ($message) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

function getExamStats($conn) {
    $stats = [];
    $r = $conn->query("SELECT COUNT(*) as c FROM exams");
    $stats['total_exams'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='student'");
    $stats['total_students'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM exam_attempts");
    $stats['total_attempts'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE status='completed'");
    $stats['completed_attempts'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT COUNT(*) as c FROM exams WHERE status='active' AND start_time <= NOW() AND end_time >= NOW()");
    $stats['active_exams'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT AVG(score) as avg_score FROM exam_attempts WHERE status IN ('completed','auto_submitted')");
    $row = $r->fetch_assoc();
    $stats['avg_score'] = $row['avg_score'] ? round($row['avg_score'], 1) : 0;

    return $stats;
}

function getStudentStats($conn, $userId) {
    $stats = [];
    $uid = (int)$userId;

    $r = $conn->query("SELECT COUNT(*) as c FROM exam_attempts WHERE user_id=$uid");
    $stats['exams_taken'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT AVG(score) as avg FROM exam_attempts WHERE user_id=$uid AND status IN ('completed','auto_submitted')");
    $row = $r->fetch_assoc();
    $stats['avg_score'] = $row['avg'] ? round($row['avg'], 1) : 0;

    $r = $conn->query("SELECT COUNT(*) as c FROM exams WHERE status='active' AND id NOT IN (SELECT exam_id FROM exam_attempts WHERE user_id=$uid)");
    $stats['available_exams'] = $r->fetch_assoc()['c'];

    $r = $conn->query("SELECT SUM(tab_switches) as ts FROM exam_attempts WHERE user_id=$uid");
    $row = $r->fetch_assoc();
    $stats['total_violations'] = $row['ts'] ?? 0;

    return $stats;
}

function formatDateTime($dt) {
    return date('M d, Y h:i A', strtotime($dt));
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year(s) ago';
    if ($diff->m > 0) return $diff->m . ' month(s) ago';
    if ($diff->d > 0) return $diff->d . ' day(s) ago';
    if ($diff->h > 0) return $diff->h . ' hour(s) ago';
    if ($diff->i > 0) return $diff->i . ' min(s) ago';
    return 'Just now';
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}
