<?php
/**
 * SharkScope Point Analysis API
 * * This script provides time-series analysis for specific geographic points
 * by extracting pixel values from processed raster data. It uses a robust
 * Python script with the Rasterio library to ensure accurate coordinate lookups.
 *
 * Parameters:
 * - lat: Latitude in decimal degrees (e.g., 34.5)
 * - lon: Longitude in decimal degrees (e.g., -120.2)
 * * Example: /api/point_analysis.php?lat=34.5&lon=-120.2
 */

// Load configuration and database connection
require_once __DIR__ . '/../../config/bootstrap.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output as it will corrupt JSON

// Set the content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for web applications

// --- Configuration for Python Interop ---
// Use configured Python interpreter and escape paths for shell safety
$pythonPath = escapeshellarg($config['paths']['python_executable']);
$queryScriptPath = escapeshellarg($config['paths']['src_dir'] . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'query_raster.py');

// Function to log errors
function logError($message) {
    error_log("[SharkScope Point Analysis] " . $message);
}

// Function to return error JSON
function returnError($message, $code = 400) {
    http_response_code($code);
    $response = [
        'error' => true,
        'message' => $message,
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Function to find all available processed data dates
function getAvailableDates($dataDir) {
    $processedPath = $dataDir . "/processed";
    $dates = [];
    
    if (!is_dir($processedPath)) {
        return $dates;
    }
    
    $directories = scandir($processedPath);
    foreach ($directories as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        
        $fullPath = $processedPath . '/' . $dir;
        if (is_dir($fullPath) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dir)) {
            $dates[] = $dir;
        }
    }
    
    sort($dates);
    return $dates;
}

/**
 * Extracts a pixel value using a robust Python script with Rasterio.
 * This replaces the less reliable direct gdallocationinfo -geoloc command.
 */
function getPixelValue($rasterFile, $lat, $lon) {
    global $pythonPath, $queryScriptPath; // Use global config variables

    if (!file_exists($rasterFile)) {
        logError("Raster file not found: " . $rasterFile);
        return null;
    }

    // Construct the command to call our robust Python query script
    $command = sprintf(
        '%s %s %s %s %s',
        $pythonPath,
        $queryScriptPath,
        escapeshellarg($rasterFile),
        escapeshellarg($lon), // Python script expects lon, then lat
        escapeshellarg($lat)
    );

    $output = trim(shell_exec($command . ' 2>&1')); // Capture stderr as well

    if (is_numeric($output)) {
        return floatval($output);
    } else {
        // The script returned 'nan' or an error message
        logError("Query script failed for {$rasterFile} at {$lat},{$lon}. Output: {$output}");
        return null;
    }
}

// Function to process a single date's data
function processDateData($dataPath, $date, $lat, $lon) {
    $datePath = $dataPath . '/' . $date;
    
    $rasterFiles = [
        'tchi' => 'tchi.tif',
        'sst' => 'sst_proc.tif',
        'chla' => 'chla_proc.tif',
        'tfg' => 'tfg_proc.tif',
        'eke' => 'eke_proc.tif',
        'bathy' => 'bathy_proc.tif'
    ];
    
    $values = [];
    $hasAnyValidData = false;

    foreach ($rasterFiles as $key => $filename) {
        $filePath = $datePath . '/' . $filename;
        $value = getPixelValue($filePath, $lat, $lon);
        $values[$key] = $value;
        if ($value !== null) {
            $hasAnyValidData = true;
        }
    }
    
    // Only return a result for this date if the main TCHI value is valid
    if ($values['tchi'] === null) {
        return null;
    }
    
    return [
        'date' => $date,
        'tchi_score' => $values['tchi'],
        'factors' => [
            'sst' => $values['sst'],
            'chla' => $values['chla'],
            'tfg' => $values['tfg'],
            'eke' => $values['eke'],
            'bathy' => $values['bathy'] // Corrected key to match master protocol
        ]
    ];
}

// --- Main Execution Block ---

// Get and validate parameters
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

if ($lat === null || $lat < -90 || $lat > 90) {
    returnError("Invalid latitude. Must be between -90 and 90 degrees.");
}

if ($lon === null || $lon < -180 || $lon > 180) {
    returnError("Invalid longitude. Must be between -180 and 180 degrees.");
}

// Define paths
$processedDataPath = $config['paths']['data_dir'] . "/processed";

if (!is_dir($processedDataPath)) {
    returnError("Processed data directory not found.", 500);
}

try {
    $availableDates = getAvailableDates($config['paths']['data_dir']);
    
    if (empty($availableDates)) {
        returnError("No processed data available.", 404);
    }
    
    $timeseries = [];
    foreach ($availableDates as $date) {
        $dateData = processDateData($processedDataPath, $date, $lat, $lon);
        if ($dateData !== null) {
            $timeseries[] = $dateData;
        }
    }
    
    $metadata = [
        'total_dates' => count($timeseries),
        'generated_at' => date('c'), // ISO 8601 format
        'data_source' => 'SharkScope Processed Rasters'
    ];

    if (empty($timeseries)) {
        $metadata['date_range'] = null;
        $metadata['message'] = 'No data available for this location. This may be a land area, outside data coverage, or in a region with no valid measurements.';
    } else {
        $metadata['date_range'] = [
            'start' => $timeseries[0]['date'],
            'end' => $timeseries[count($timeseries) - 1]['date']
        ];
    }

    $response = [
        'location' => ['lat' => $lat, 'lon' => $lon],
        'timeseries' => $timeseries,
        'metadata' => $metadata
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logError("Exception occurred: " . $e->getMessage());
    returnError("Internal server error occurred.", 500);
}
?>