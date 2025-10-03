<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../src/php/TchiProcessor.php';
set_time_limit(0);

// --- UPDATED --- allow processing a specific date ---
$dateToProcess = '2025-09-05';
echo "Starting SharkScope TCHI process for date: $dateToProcess\n";

$processor = new TchiProcessor($pdo, $config, ['date' => $dateToProcess]);
$processor->runDailyProcess();

echo "Process completed successfully!\n";
?>