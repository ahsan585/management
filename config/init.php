<?php
// config/init.php

// Start session with secure defaults
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ── Dynamic Base URL ──
// Works on localhost/management AND on hosting root
if (!defined('BASE_URL')) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script   = $_SERVER['SCRIPT_NAME'] ?? '';

    // Find project root by locating the top-level folder
    // e.g. /management/admin/dashboard.php → /management
    // e.g. /index.php → (empty string = root)
    $parts    = explode('/', trim($script, '/'));
    $docRoot  = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projRoot = realpath(__DIR__ . '/..');

    // Calculate relative path from document root to project root
    $relPath  = '';
    if ($docRoot && $projRoot && strpos($projRoot, $docRoot) === 0) {
        $relPath = str_replace('\\', '/', substr($projRoot, strlen($docRoot)));
    }

    define('BASE_URL', $scheme . '://' . $host . $relPath);
}

// Helper to access current user data
function currentUser() {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}
