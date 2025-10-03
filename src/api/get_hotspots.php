<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/gemini_namer.php';

// Make config available globally for gemini_namer functions
$GLOBALS['config'] = $config;

$date = $_GET['date'] ?? null;
$count = isset($_GET['count']) ? (int) $_GET['count'] : 10;

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid date parameter is required (YYYY-MM-DD).']);
    exit;
}

if ($count <= 0) {
    $count = 10;
}

$count = max(1, min($count, 50));

$tchiPath = $config['paths']['data_dir'] . "/processed/{$date}/tchi.tif";

if (!file_exists($tchiPath)) {
    http_response_code(404);
    echo json_encode(['error' => "No processed data found for date: {$date}"]);
    exit;
}

// --- CACHING (48h) -------------------------------------------------------
$cacheDir = $config['paths']['data_dir'] . '/cache/hotspots';
if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
$cacheKey = sprintf('hotspots_%s_%d.json', $date, $count);
$cachePath = $cacheDir . '/' . $cacheKey;
$ttl = 48 * 3600; // 48 hours

if (is_file($cachePath) && (time() - filemtime($cachePath) < $ttl)) {
    $cachedJson = @file_get_contents($cachePath);
    if ($cachedJson !== false) {
        echo $cachedJson;
        return; // served from cache
    }
}

// --- Configuration for Python Interop ---
$pythonPath = escapeshellarg($config['paths']['python_executable']);
$hotspotScriptPath = escapeshellarg($config['paths']['src_dir'] . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'find_hotspots.py');
// Derive HOME from interpreter path for cPanel Python App wrappers
$home = null;
if (isset($config['paths']['python_executable']) && preg_match('#^/home/([^/]+)/#', $config['paths']['python_executable'], $m)) {
    $home = "/home/{$m[1]}";
}
$envPrefix = $home ? ('HOME=' . escapeshellarg($home) . ' ') : '';

$command = sprintf(
    '%s %s %s %d',
    $pythonPath,
    $hotspotScriptPath,
    escapeshellarg($tchiPath),
    $count
);

$output = shell_exec($envPrefix . $command . ' 2>&1');

if ($output === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute hotspot analysis.']);
    exit;
}

$decoded = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Hotspot analysis returned invalid JSON.']);
    exit;
}

// Enrich hotspots with Gemini-generated restaurant names
$enriched = enrichHotspotsWithNames($decoded);

$jsonOut = json_encode($enriched);
// Store cache (best-effort)
@file_put_contents($cachePath, $jsonOut, LOCK_EX);
echo $jsonOut;
?>