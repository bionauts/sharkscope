<?php
/**
 * SharkScope Point Analysis API
 * 
 * This script provides time-series analysis for specific geographic points
 * by extracting pixel values from processed raster data using GDAL tools.
 * 
 * Parameters:
 * - lat: Latitude in decimal degrees (e.g., 34.5)
 * - lon: Longitude in decimal degrees (e.g., -120.2)
 * 
 * Example: /api/point_analysis.php?lat=34.5&lon=-120.2
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output as it will corrupt JSON

// Set the content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for web applications

// Function to log errors
function logError($message) {
    error_log("[SharkScope Point Analysis] " . $message);
}

// Function to return error JSON
function returnError($message, $code = 400) {
    $response = [
        'error' => true,
        'message' => $message,
        'code' => $code
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Function to find all available processed data dates
function getAvailableDates($basePath) {
    $processedPath = $basePath . "/data/processed";
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
    
    // Sort dates chronologically
    sort($dates);
    return $dates;
}

// Function to extract pixel value using gdallocationinfo
function getPixelValue($rasterFile, $lat, $lon) {
    if (!file_exists($rasterFile)) {
        return null;
    }
    
    // Use gdallocationinfo to get the pixel value at the specified coordinates
    $command = sprintf(
        'gdallocationinfo -wgs84 -valonly "%s" %f %f 2>&1',
        $rasterFile,
        $lon, // Note: gdallocationinfo expects lon, lat order
        $lat
    );
    
    $output = shell_exec($command);
    $output = trim($output);
    
    // Check if the output is a valid number
    if (is_numeric($output)) {
        $value = floatval($output);
        
        // Handle common NoData values based on file type
        $filename = basename($rasterFile);
        
        if (strpos($filename, 'eke') !== false) {
            // EKE specific NoData handling
            if ($value < -1000000000 || $value == -2.1474836e+09) {
                return null;
            }
        } else {
            // Standard NoData values for other files
            if ($value == -32768 || $value == -32767 || $value == -9999 || $value < -9000) {
                return null;
            }
        }
        
        return $value;
    }
    
    // Handle 'nan' or other invalid outputs
    if (strtolower($output) === 'nan' || $output === '' || strpos($output, 'ERROR') !== false) {
        return null;
    }
    
    return null;
}

// Function to process a single date's data
function processDateData($dataPath, $date, $lat, $lon) {
    $datePath = $dataPath . '/' . $date;
    
    // Define the raster files we need to process
    $rasterFiles = [
        'tchi' => 'tchi.tif',
        'sst' => 'sst_proc.tif',
        'chla' => 'chla_proc.tif',
        'tfg' => 'tfg_proc.tif',
        'eke' => 'eke_proc.tif',
        'bathy' => 'bathy_proc.tif'
    ];
    
    $values = [];
    $hasValidData = false;
    
    foreach ($rasterFiles as $key => $filename) {
        $filePath = $datePath . '/' . $filename;
        $value = getPixelValue($filePath, $lat, $lon);
        $values[$key] = $value;
        
        if ($value !== null) {
            $hasValidData = true;
        }
    }
    
    // Only return data if we have at least some valid values
    if (!$hasValidData) {
        return null;
    }
    
    // Calculate TCHI score (use the tchi value directly, or calculate if needed)
    $tchiScore = $values['tchi'];
    
    // Format according to SharkScope Master Protocol
    return [
        'date' => $date,
        'tchi_score' => $tchiScore,
        'factors' => [
            'sst' => $values['sst'],
            'chla' => $values['chla'],
            'tfg' => $values['tfg'],
            'eke' => $values['eke'],
            'bathymetry' => $values['bathy']
        ]
    ];
}

// Get and validate parameters
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

// Validate latitude
if ($lat === null || $lat < -90 || $lat > 90) {
    returnError("Invalid latitude. Must be between -90 and 90 degrees.");
}

// Validate longitude
if ($lon === null || $lon < -180 || $lon > 180) {
    returnError("Invalid longitude. Must be between -180 and 180 degrees.");
}

// Define paths
$basePath = dirname(__DIR__); // Go up one level from api directory
$processedDataPath = $basePath . "/data/processed";

// Check if processed data directory exists
if (!is_dir($processedDataPath)) {
    returnError("Processed data directory not found.", 404);
}

try {
    // Get all available dates
    $availableDates = getAvailableDates($basePath);
    
    if (empty($availableDates)) {
        returnError("No processed data available.", 404);
    }
    
    // Process each date
    $timeseries = [];
    foreach ($availableDates as $date) {
        $dateData = processDateData($processedDataPath, $date, $lat, $lon);
        if ($dateData !== null) {
            $timeseries[] = $dateData;
        }
    }
    
    // Check if we have any valid data
    if (empty($timeseries)) {
        // Still provide a response but indicate no data
        $response = [
            'location' => [
                'lat' => $lat,
                'lon' => $lon
            ],
            'timeseries' => [],
            'metadata' => [
                'total_dates' => 0,
                'date_range' => null,
                'generated_at' => date('c'),
                'data_source' => 'SharkScope Processed Rasters',
                'message' => 'No data available for this location. This may be a land area, outside data coverage, or in a region with no valid measurements.'
            ]
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Format final response according to SharkScope Master Protocol
    $response = [
        'location' => [
            'lat' => $lat,
            'lon' => $lon
        ],
        'timeseries' => $timeseries,
        'metadata' => [
            'total_dates' => count($timeseries),
            'date_range' => [
                'start' => $timeseries[0]['date'] ?? null,
                'end' => $timeseries[count($timeseries) - 1]['date'] ?? null
            ],
            'generated_at' => date('c'), // ISO 8601 format
            'data_source' => 'SharkScope Processed Rasters'
        ]
    ];
    
    // Output JSON response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logError("Exception occurred: " . $e->getMessage());
    returnError("Internal server error occurred.", 500);
}
?>