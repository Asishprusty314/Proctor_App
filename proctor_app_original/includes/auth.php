<?php
require_once __DIR__ . '/config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function getCurrentUser($conn) {
    if (!isLoggedIn()) return null;
    $id = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT id, name, email, role FROM users WHERE id = $id");
    return $result->fetch_assoc();
}
