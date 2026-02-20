<?php
// db.php
$DB_HOST = 'localhost';
$DB_NAME = 'u205629180_expansor';
$DB_USER = 'u205629180_expansor';
$DB_PASS = 'naotemsenhaA1@75351595'; // ajuste se necessário

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: '.$e->getMessage()]);
    exit;
}
