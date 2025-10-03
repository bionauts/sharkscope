<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$date = $_GET['date'] ?? null;
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid date parameter is required (YYYY-MM-DD).']);
    exit;
}

$basePath = dirname(__DIR__);
$tchiPath = $basePath . "/data/processed/{$date}/tchi.tif";

if (!file_exists($tchiPath)) {
    http_response_code(404);
    echo json_encode(['error' => "No processed data found for date: {$date}"]);
    exit;
}

// --- Configuration for Python Interop ---
$pythonPath = '"C:\Python313\python.exe"'; // Ensure this path is correct
$hotspotScriptPath = '"' . $basePath . DIRECTORY_SEPARATOR . 'find_hotspots.py"';

$command = sprintf(
    '%s %s %s',
    $pythonPath,
    $hotspotScriptPath,
    escapeshellarg($tchiPath)
);

$output = shell_exec($command);
echo $output; // The Python script already outputs JSON
?>