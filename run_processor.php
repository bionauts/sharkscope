<?php
require_once 'php/TchiProcessor.php';
set_time_limit(0);

// --- Database Connection ---
$host = '127.0.0.1';
$db   = 'sharkscope_db';
$user = 'root'; // Default XAMPP user
$pass = '';     // Default XAMPP password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
// --- End Connection ---

// --- UPDATED --- allow processing a specific date ---
$dateToProcess = '2025-09-05';
echo "Starting SharkScope TCHI process for date: $dateToProcess\n";

$processor = new TchiProcessor($pdo, ['date' => $dateToProcess]);
$processor->runDailyProcess();

echo "Process completed successfully!\n";
?>