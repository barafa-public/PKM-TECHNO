<?php
require_once __DIR__ . '/config.php';

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/login.php');
        exit;
    }
    // session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . APP_URL . '/pages/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireGuest() {
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function currentUser() {
    startSession();
    return [
        'id'    => $_SESSION['user_id'] ?? null,
        'nama'  => $_SESSION['user_nama'] ?? '',
        'nim'   => $_SESSION['user_nim'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($status, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

function timeToMinutes($time) {
    list($h, $m) = explode(':', $time);
    return (int)$h * 60 + (int)$m;
}

function hasTimeConflict($startA, $endA, $startB, $endB) {
    $sA = timeToMinutes($startA);
    $eA = timeToMinutes($endA);
    $sB = timeToMinutes($startB);
    $eB = timeToMinutes($endB);
    return $sA < $eB && $sB < $eA;
}
