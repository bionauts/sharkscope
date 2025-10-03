<?php
/**
 * SharkScope Map Tile Server
 * 
 * This script serves map tiles for the SharkScope project by processing
 * TCHI (Trophic Cascade Habitat Index) data using GDAL tools.
 * 
 * Parameters:
 * - date: Date in YYYY-MM-DD format (e.g., 2025-09-05)
 * - layer: Data layer type (tchi, sst, chla) - defaults to tchi
 * - z: Zoom level (0-18)
 * - x: Tile X coordinate
 * - y: Tile Y coordinate
 * 
 * Example: /api/tiles.php?date=2025-09-05&layer=sst&z=8&x=45&y=98
 */

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

// Function to create color relief file
function createColorReliefFile($tempDir, $colorMapFile) {
    $colorFile = $tempDir . '/color_relief.txt';
    
    // Define the path to our color map files
    $basePath = dirname(__DIR__); // Go up one level from api directory
    $colorMapPath = dirname(__FILE__) . '/' . $colorMapFile;
    
    // Check if the color map file exists
    if (!file_exists($colorMapPath)) {
        logError("Color map file not found: $colorMapPath");
        return false;
    }
    
    // Copy the color map file to temp directory
    if (!copy($colorMapPath, $colorFile)) {
        logError("Failed to copy color map file to temp directory");
        return false;
    }
    
    return $colorFile;
}

// Get and validate parameters
$date = $_GET['date'] ?? '';
$layer = $_GET['layer'] ?? 'tchi'; // Default to tchi if not specified
$z = isset($_GET['z']) ? intval($_GET['z']) : -1;
$x = isset($_GET['x']) ? intval($_GET['x']) : -1;
$y = isset($_GET['y']) ? intval($_GET['y']) : -1;

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    logError("Invalid date format: $date");
    returnBlankTile();
}

// Set source file and color map based on layer type
switch ($layer) {
    case 'sst':
        $sourceFileName = 'sst_proc.tif';
        $colorMapFile = 'color_sst.txt';
        break;
    case 'chla':
        $sourceFileName = 'chla_proc.tif';
        $colorMapFile = 'color_chla.txt';
        break;
    case 'tchi':
    default:
        $sourceFileName = 'tchi.tif';
        $colorMapFile = 'color_tchi.txt';
        break;
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
$basePath = dirname(__DIR__); // Go up one level from api directory
$dataPath = $basePath . "/data/processed/$date";
$sourceFile = $dataPath . "/$sourceFileName";

// Check if source file exists
if (!file_exists($sourceFile)) {
    logError("Source file not found: $sourceFile");
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
$tempTile = $tempDir . "/tile_${z}_${x}_${y}.tif";
$tempColored = $tempDir . "/colored_tile_${z}_${x}_${y}.tif";
$outputPng = $tempDir . "/output_tile_${z}_${x}_${y}.png";

try {
    // Step 1: Extract the tile area using gdal_translate
    $gdalCommand = sprintf(
        'gdal_translate -of GTiff -projwin %f %f %f %f "%s" "%s" 2>&1',
        $bounds['minLon'],
        $bounds['maxLat'],
        $bounds['maxLon'],
        $bounds['minLat'],
        $sourceFile,
        $tempTile
    );
    
    $gdalOutput = shell_exec($gdalCommand);
    
    // Check if the extraction was successful
    if (!file_exists($tempTile)) {
        logError("gdal_translate failed. Command: $gdalCommand. Output: $gdalOutput");
        returnBlankTile();
    }
    
    // Step 2: Create color relief file
    $colorFile = createColorReliefFile($tempDir, $colorMapFile);
    if (!$colorFile) {
        returnBlankTile();
    }
    
    // Step 3: Apply color relief using gdaldem
    $gdaldemCommand = sprintf(
        'gdaldem color-relief "%s" "%s" "%s" -of GTiff 2>&1',
        $tempTile,
        $colorFile,
        $tempColored
    );
    
    $gdaldemOutput = shell_exec($gdaldemCommand);
    
    // Check if color relief was successful
    if (!file_exists($tempColored)) {
        logError("gdaldem color-relief failed. Command: $gdaldemCommand. Output: $gdaldemOutput");
        returnBlankTile();
    }
    
    // Step 4: Convert to PNG and resize to exactly 256x256
    $gdalTranslateCommand = sprintf(
        'gdal_translate -of PNG -outsize 256 256 "%s" "%s" 2>&1',
        $tempColored,
        $outputPng
    );
    
    $translateOutput = shell_exec($gdalTranslateCommand);
    
    // Check if PNG conversion was successful
    if (!file_exists($outputPng)) {
        logError("PNG conversion failed. Command: $gdalTranslateCommand. Output: $translateOutput");
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
    $filesToClean = [$tempTile, $tempColored, $outputPng, $colorFile ?? ''];
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