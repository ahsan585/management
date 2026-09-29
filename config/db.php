<?php
// config/db.php
// Database connection — reads from environment variables
// Set these on your hosting panel (cPanel > Environment Variables or .htaccess)

$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_PORT = getenv('DB_PORT') ?: '5432';
$DB_NAME = getenv('DB_NAME') ?: 'School_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: 'ahsan123';

$dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    _runMigrations($pdo);
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

function _runMigrations(PDO $pdo): void {
    // Classes table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS classes (
            id         SERIAL       PRIMARY KEY,
            name       VARCHAR(100) NOT NULL UNIQUE,
            section    VARCHAR(50),
            created_at TIMESTAMPTZ  DEFAULT now()
        )
    ");

    // Subjects table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subjects (
            id           SERIAL       PRIMARY KEY,
            subject_name VARCHAR(255) NOT NULL,
            class_id     VARCHAR(100) NOT NULL,
            created_at   TIMESTAMPTZ  DEFAULT now(),
            UNIQUE (subject_name, class_id)
        )
    ");

    // notice_reads table (agar missing ho)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notice_reads (
            id        SERIAL      PRIMARY KEY,
            notice_id INTEGER     NOT NULL REFERENCES notices(id) ON DELETE CASCADE,
            user_id   INTEGER     NOT NULL REFERENCES users(id)   ON DELETE CASCADE,
            read_at   TIMESTAMPTZ DEFAULT now(),
            UNIQUE (notice_id, user_id)
        )
    ");

    // note_reads table — tracks which students have seen each note
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS note_reads (
            id      SERIAL      PRIMARY KEY,
            note_id INTEGER     NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
            user_id INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            read_at TIMESTAMPTZ DEFAULT now(),
            UNIQUE (note_id, user_id)
        )
    ");
}
