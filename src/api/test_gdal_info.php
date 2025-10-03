<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: text/plain');

$date = $_GET['date'] ?? '2025-09-05';
$file = $_GET['file'] ?? ($config['paths']['data_dir'] . "/processed/{$date}/tchi.tif");

$envParts = [];
if (!empty($config['gdal']['data'])) { $envParts[] = 'GDAL_DATA=' . escapeshellarg($config['gdal']['data']); }
if (!empty($config['gdal']['proj'])) { $envParts[] = 'PROJ_LIB=' . escapeshellarg($config['gdal']['proj']); }
if (!empty($config['gdal']['bin_dir'])) { $envParts[] = 'PATH=' . escapeshellarg($config['gdal']['bin_dir'] . PATH_SEPARATOR . getenv('PATH')); }
$envPrefix = $envParts ? (implode(' ', $envParts) . ' ') : '';

$bin = (!empty($config['gdal']['bin_dir']) ? rtrim($config['gdal']['bin_dir'], '/').'/gdalinfo' : 'gdalinfo');
$cmd1 = $envPrefix . escapeshellcmd($bin) . ' --formats 2>&1';
$cmd2 = $envPrefix . escapeshellcmd($bin) . ' ' . escapeshellarg($file) . ' -json 2>&1';

echo "> $cmd1\n" . shell_exec($cmd1) . "\n\n";

echo "> $cmd2\n";
$out = shell_exec($cmd2);
if ($out === null || $out === '') {
    echo "(no output)\n";
} else {
    echo $out;
}
