<?php
/**
 * Get Available Processing Dates API
 * 
 * Returns a JSON array of available dates from the processed data directory.
 * Dates are sorted chronologically and returned in ISO format (YYYY-MM-DD).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../../config/bootstrap.php';

try {
    // Get the processed data directory path from config
    $processedDir = $config['paths']['processed'] ?? __DIR__ . '/../../data/processed';
    
    // Check if directory exists
    if (!is_dir($processedDir)) {
        throw new Exception('Processed data directory not found');
    }
    
    // Scan for date directories (format: YYYY-MM-DD)
    $dates = [];
    $items = scandir($processedDir);
    
    foreach ($items as $item) {
        // Skip hidden files and parent directories
        if ($item === '.' || $item === '..' || $item[0] === '.') {
            continue;
        }
        
        $itemPath = $processedDir . DIRECTORY_SEPARATOR . $item;
        
        // Check if it's a directory and matches date format YYYY-MM-DD
        if (is_dir($itemPath) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $item)) {
            $dates[] = $item;
        }
    }
    
    // Sort dates chronologically
    sort($dates);
    
    // Return as JSON array
    echo json_encode($dates, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
