<?php
/**
 * SharkScope Dates API
 * 
 * This script returns a JSON array of all available dates that have processed data.
 * It scans the data/processed directory for subdirectories in YYYY-MM-DD format.
 * 
 * Returns: JSON array of date strings
 * Example: ["2025-09-05", "2025-09-06", "2025-09-07"]
 */

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for local development

// Function to log errors
function logError($message) {
    error_log("[SharkScope Dates API] " . $message);
}

try {
    // Get the base path (go up one level from api directory)
    $basePath = dirname(__DIR__);
    $processedPath = $basePath . '/data/processed';
    
    // Check if the processed data directory exists
    if (!is_dir($processedPath)) {
        logError("Processed data directory not found: $processedPath");
        echo json_encode([]);
        exit;
    }
    
    // Get all directories in the processed folder
    $directories = glob($processedPath . '/*', GLOB_ONLYDIR);
    $dates = [];
    
    // Filter directories that match YYYY-MM-DD format
    foreach ($directories as $dir) {
        $dirName = basename($dir);
        
        // Check if directory name matches YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dirName)) {
            $dates[] = $dirName;
        }
    }
    
    // Sort dates in ascending order
    sort($dates);
    
    // Return the dates as JSON
    echo json_encode($dates);
    
} catch (Exception $e) {
    logError("Exception occurred: " . $e->getMessage());
    echo json_encode([]);
}
?>