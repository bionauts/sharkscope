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

// Detect if a file is a Git LFS pointer (text file with LFS spec header)
function isLfsPointer($path) {
    if (!is_file($path)) return false;
    $fp = @fopen($path, 'rb');
    if (!$fp) return false;
    $head = fread($fp, 200);
    fclose($fp);
    if ($head === false) return false;
    return str_contains($head, 'version https://git-lfs.github.com/spec/v1');
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
function createColorReliefFile($tempDir, $layerType = 'tchi') {
    $colorFile = $tempDir . '/color_relief.txt';
    
    // Define color palettes for different layers
    $colorMaps = [
        'tchi' => [
            "0.0 10 25 47",      // Deep blue (#0A192F)
            "0.2 20 50 94",      // Medium blue
            "0.4 46 204 113",    // Green (#2ECC71)
            "0.6 241 196 15",    // Yellow (#F1C40F)
            "0.8 231 76 60",     // Red (#E74C3C)
            "1.0 231 76 60"      // Red (#E74C3C)
        ],
        'sst' => [
            "0 0 0 128",         // Dark blue (cold)
            "10 0 128 255",      // Blue
            "15 0 255 255",      // Cyan
            "20 0 255 0",        // Green
            "25 255 255 0",      // Yellow
            "30 255 128 0",      // Orange
            "35 255 0 0"         // Red (hot)
        ],
        'chla' => [
            "0.0 0 0 50",        // Very dark blue (low)
            "0.1 0 50 100",      // Dark blue
            "0.5 0 150 150",     // Cyan
            "1.0 50 200 50",     // Green
            "5.0 200 200 0",     // Yellow
            "10.0 255 100 0"     // Orange (high)
        ],
        'eke' => [
            "0 10 10 50",        // Dark (low energy)
            "50 50 100 150",     // Blue
            "100 100 150 200",   // Light blue
            "200 150 200 100",   // Purple
            "300 200 150 50",    // Orange
            "500 255 50 50"      // Red (high energy)
        ],
        'bathy' => [
            "0 10 25 47",        // Deep ocean (deep blue)
            "-1000 20 50 94",    // Deep blue
            "-500 46 100 150",   // Medium blue
            "-200 100 150 200",  // Light blue
            "-50 150 200 220",   // Very light blue
            "0 200 220 240"      // Near surface
        ]
    ];
    
    // Use TCHI palette as default
    $colorMap = $colorMaps[$layerType] ?? $colorMaps['tchi'];
    
    $content = implode("\n", $colorMap) . "\n";
    
    if (file_put_contents($colorFile, $content) === false) {
        logError("Failed to create color relief file");
        return false;
    }
    
    return $colorFile;
}

// Get and validate parameters
$date = $_GET['date'] ?? '';
$z = isset($_GET['z']) ? intval($_GET['z']) : -1;
$x = isset($_GET['x']) ? intval($_GET['x']) : -1;
$y = isset($_GET['y']) ? intval($_GET['y']) : -1;
$layer = $_GET['layer'] ?? 'tchi'; // Default to TCHI layer

// Validate layer type
$validLayers = ['tchi', 'sst', 'chla', 'eke', 'bathy'];
if (!in_array($layer, $validLayers)) {
    logError("Invalid layer type: $layer");
    returnBlankTile();
}

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

// Map layer names to their file names
$layerFiles = [
    'tchi' => 'tchi.tif',
    'sst' => 'sst_proc.tif',
    'chla' => 'chla_proc.tif',
    'eke' => 'eke_proc.tif',
    'bathy' => 'bathy_proc.tif'
];

// Special case: bathy uses static file instead of processed date
if ($layer === 'bathy') {
    $dataFile = $config['paths']['data_dir'] . "/static/bathymetry.tif";
} else {
    $dataFile = $dataPath . "/" . $layerFiles[$layer];
}

// Check if data file exists
if (!file_exists($dataFile)) {
    logError("$layer file not found: $dataFile");
    returnBlankTile();
}

// Detect Git LFS pointer file and bail early with a clear log
if (isLfsPointer($dataFile)) {
    logError("$layer file appears to be a Git LFS pointer. Replace with the actual GeoTIFF (binary) at: $dataFile");
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
    // Prepare environment prefix for GDAL from config (conda env)
    $envParts = [];
    if (!empty($config['gdal']['data'])) { $envParts[] = 'GDAL_DATA=' . escapeshellarg($config['gdal']['data']); }
    if (!empty($config['gdal']['proj'])) { $envParts[] = 'PROJ_LIB=' . escapeshellarg($config['gdal']['proj']); }
    // Prepend bin_dir to PATH if provided
    if (!empty($config['gdal']['bin_dir'])) {
        $envParts[] = 'PATH=' . escapeshellarg($config['gdal']['bin_dir'] . PATH_SEPARATOR . getenv('PATH'));
    }
    $envPrefix = $envParts ? (implode(' ', $envParts) . ' ') : '';

    $gdalTranslate = $config['gdal']['translate_cmd'] ?? 'gdal_translate';
    $gdaldem = $config['gdal']['dem_cmd'] ?? 'gdaldem';

    // Step 1: Extract the tile area using gdal_translate
    $gdalCommand = sprintf(
        '%s%s -of GTiff -projwin %f %f %f %f "%s" "%s" 2>&1',
        $envPrefix,
        escapeshellcmd($gdalTranslate),
        $bounds['minLon'],
        $bounds['maxLat'],
        $bounds['maxLon'],
        $bounds['minLat'],
        $dataFile,
        $tempTile
    );
    
    $gdalOutput = shell_exec($gdalCommand);
    
    // Check if the extraction was successful
    if (!file_exists($tempTile)) {
        logError("gdal_translate failed. Command: $gdalCommand. Output: $gdalOutput");
        returnBlankTile();
    }
    
    // Step 2: Create color relief file
    $colorFile = createColorReliefFile($tempDir, $layer);
    if (!$colorFile) {
        returnBlankTile();
    }
    
    // Step 3: Apply color relief using gdaldem
    $gdaldemCommand = sprintf(
        '%s%s color-relief "%s" "%s" "%s" -of GTiff 2>&1',
        $envPrefix,
        escapeshellcmd($gdaldem),
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
        '%s%s -of PNG -outsize 256 256 "%s" "%s" 2>&1',
        $envPrefix,
        escapeshellcmd($gdalTranslate),
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