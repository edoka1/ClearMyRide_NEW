<?php
// assets/app/db_connect.php - MAMP-friendly (explicit port)
$host = '127.0.0.1';
$port = 8889;               // <- MAMP MySQL default port
$db   = 'clear_my_ride';
$user = 'root';
$pass = 'root';            // <- MAMP default password
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection failed: " . $e->getMessage();
    exit;
}
