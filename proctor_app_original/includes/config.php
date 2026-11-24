<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'proctor_app');
define('DB_PORT', 3301);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Sync MySQL timezone with PHP timezone (IST)
$conn->query("SET time_zone = '+05:30'");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Base URL - adjust if needed
define('BASE_URL', '/exam-system/');

// Upload directory for webcam captures
define('UPLOAD_DIR', __DIR__ . '/../uploads/captures/');

// Timezone
date_default_timezone_set('Asia/Kolkata');
