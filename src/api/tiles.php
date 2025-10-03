<?php
/**
 * SharkScope Map Tile Server
 * 
 * This script serves map tiles for the SharkScope project by processing
 * TCHI (Trophic Cascade Habitat Index) data using GDAL tools.
 * 
 * Parameters:
 * - date: Date in YYYY-MM-DD format (e.g., 2025-09-05)
 * - z: Zoom level (0-18)
 * - x: Tile X coordinate
 * - y: Tile Y coordinate
 * 
 * Example: /api/tiles.php?date=2025-09-05&z=8&x=45&y=98
 */

// Load configuration
require_once __DIR__ . '/../../config/bootstrap.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output as it will corrupt the image

// Set the content type to PNG
header('Content-Type: image/png');

// Function to log errors
function logError($message) {
    error_log("[SharkScope Tiles] " . $message);
}

// Function to return a blank tile when there's an error
function returnBlankTile() {
    // Check if GD extension is available
    if (extension_loaded('gd')) {
        // Create a 256x256 transparent PNG
        $image = imagecreate(256, 256);
        $transparent = imagecolorallocate($image, 255, 255, 255);
        imagecolortransparent($image, $transparent);
        imagepng($image);
        imagedestroy($image);
    } else {
        // Fallback: output a simple 1x1 transparent PNG
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChAGA4UdoCgAAAABJRU5ErkJggg==');
        echo $pngData;
    }
    exit;
}

// Function to calculate tile bounds in geographic coordinates
function tileToLatLon($x, $y, $z) {
    $n = pow(2, $z);
    $lon_deg = $x / $n * 360.0 - 180.0;
    $lat_rad = atan(sinh(pi() * (1 - 2 * $y / $n)));
    $lat_deg = rad2deg($lat_rad);
    
    // Calculate bounds for the tile
    $lon_deg_next = ($x + 1) / $n * 360.0 - 180.0;
    $lat_rad_next = atan(sinh(pi() * (1 - 2 * ($y + 1) / $n)));
    $lat_deg_next = rad2deg($lat_rad_next);
    
    return [
        'minLon' => $lon_deg,
        'maxLon' => $lon_deg_next,
        'minLat' => $lat_deg_next, // Note: reversed because Y increases downward
        'maxLat' => $lat_deg
    ];
}

// No longer using GDAL CLI color-relief; rendering is handled in Python with Rasterio

// Get and validate parameters
$date = $_GET['date'] ?? '';
$z = isset($_GET['z']) ? intval($_GET['z']) : -1;
$x = isset($_GET['x']) ? intval($_GET['x']) : -1;
$y = isset($_GET['y']) ? intval($_GET['y']) : -1;

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    logError("Invalid date format: $date");
    returnBlankTile();
}

// Validate zoom level (reasonable range for web maps)
if ($z < 0 || $z > 18) {
    logError("Invalid zoom level: $z");
    returnBlankTile();
}

// Validate tile coordinates
$maxTiles = pow(2, $z);
if ($x < 0 || $x >= $maxTiles || $y < 0 || $y >= $maxTiles) {
    logError("Invalid tile coordinates: x=$x, y=$y for zoom=$z");
    returnBlankTile();
}

// Define paths
$dataPath = $config['paths']['data_dir'] . "/processed/$date";
$tchiFile = $dataPath . "/tchi.tif";

// Check if TCHI file exists
if (!file_exists($tchiFile)) {
    logError("TCHI file not found: $tchiFile");
    returnBlankTile();
}

// Create temporary directory for processing
$tempDir = sys_get_temp_dir() . '/sharkscope_tiles_' . uniqid();
if (!mkdir($tempDir, 0755, true)) {
    logError("Failed to create temporary directory: $tempDir");
    returnBlankTile();
}

// Calculate geographic bounds for the tile
$bounds = tileToLatLon($x, $y, $z);

// Define temporary file paths
$outputPng = $tempDir . "/output_tile_${z}_${x}_${y}.png";

try {
    // Render tile via Python Rasterio renderer (avoids GDAL CLI requirement)
    $python = $config['paths']['python_executable'] ?? null;
    if (!$python) {
        logError('Python executable not configured');
        returnBlankTile();
    }

    $home = null;
    if (preg_match('#^/home/([^/]+)/#', $python, $m)) { $home = "/home/{$m[1]}"; }
    $envPrefix = $home ? ('HOME=' . escapeshellarg($home) . ' ') : '';

    $scriptPath = $config['paths']['src_dir'] . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'render_tile.py';

    $cmd = sprintf(
        '%s%s %s %s %f %f %f %f %s 2>&1',
        $envPrefix,
        escapeshellarg($python),
        escapeshellarg($scriptPath),
        escapeshellarg($tchiFile),
        $bounds['minLon'],
        $bounds['minLat'],
        $bounds['maxLon'],
        $bounds['maxLat'],
        escapeshellarg($outputPng)
    );

    $pyOut = shell_exec($cmd);
    if (!file_exists($outputPng)) {
        logError("render_tile.py failed. Cmd: $cmd Output: $pyOut");
        returnBlankTile();
    }
    
    // Step 5: Output the PNG image
    $imageData = file_get_contents($outputPng);
    if ($imageData === false) {
        logError("Failed to read output PNG file: $outputPng");
        returnBlankTile();
    }
    
    // Set cache headers (tiles don't change often)
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    
    // Output the image
    echo $imageData;
    
} catch (Exception $e) {
    logError("Exception occurred: " . $e->getMessage());
    returnBlankTile();
} finally {
    // Clean up temporary files
    $filesToClean = [$outputPng];
    foreach ($filesToClean as $file) {
        if ($file && file_exists($file)) {
            unlink($file);
        }
    }
    
    // Remove temporary directory (only if empty)
    if (is_dir($tempDir)) {
        // Try to remove any remaining files
        $remaining = glob($tempDir . '/*');
        foreach ($remaining as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($tempDir); // Suppress warnings
    }
}
?>