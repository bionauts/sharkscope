<?php
// Simple passthrough to serve hardware/makosense_data.csv with correct headers
// Prevent directory traversal
$root = realpath(__DIR__ . '/..');
$file = $root . '/hardware/makosense_data.csv';
if (!is_readable($file)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "makosense_data.csv not found";
    exit;
}
header('Content-Type: text/csv');
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($file);
