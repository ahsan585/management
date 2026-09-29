<?php
// config/functions.php

function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/index.php');
        exit;
    }
}

function redirectToDashboard() {
    if (!isLoggedIn()) {
        $base = defined('BASE_URL') ? BASE_URL : '';
        header('Location: ' . $base . '/index.php');
        exit;
    }

    $base = defined('BASE_URL') ? BASE_URL : '';
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: ' . $base . '/admin/dashboard.php');
            break;
        case 'teacher':
            header('Location: ' . $base . '/teacher/dashboard.php');
            break;
        case 'student':
            header('Location: ' . $base . '/student/dashboard.php');
            break;
        default:
            header('Location: ' . $base . '/index.php');
    }
    exit;
}

function flash($key, $message = null) {
    if ($message === null) {
        if (isset($_SESSION['flash'][$key])) {
            $msg = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $msg;
        }
        return null;
    }

    $_SESSION['flash'][$key] = $message;
}

/**
 * Generate a unique random numeric key for a given table/column.
 *
 * @param PDO $pdo
 * @param string $table
 * @param string $column
 * @param int $length Number of digits (default 6)
 * @param int $maxAttempts
 * @return string
 */
function generateUniqueKey(PDO $pdo, string $table, string $column, int $length = 6, int $maxAttempts = 10): string {
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;

    for ($i = 0; $i < $maxAttempts; $i++) {
        $key = (string) random_int($min, $max);
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        if (!$stmt->fetch()) {
            return $key;
        }
    }

    throw new RuntimeException('Unable to generate a unique key after ' . $maxAttempts . ' attempts.');
}
