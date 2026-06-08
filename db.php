<?php
/**
 * ====================================================================
 * Cafe Digital - Database Configuration
 * ====================================================================
 */

// ===== DATABASE CONNECTION SETTINGS =====
$host = 'localhost';
$user = 'root';
$pass = '';
$name = 'cafe_digital_system';

// ===== CREATE DATABASE CONNECTION =====
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die('Database connection failed.');
}

// ===== SESSION CONFIGURATION =====
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}
?>
