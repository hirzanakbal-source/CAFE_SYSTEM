<?php
// db.php - Database connection file

$host = 'localhost';
$db = 'cafe_digital_system';
$user = 'root';
$pass = ''; // your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    // set error mode
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB CONNECTION FAILED: " . $e->getMessage());
}
?>