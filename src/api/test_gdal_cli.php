<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: text/plain');

echo "GDAL CLI checks\n";

$checks = [
    'gdal_translate --version',
    'gdaldem --version',
];

foreach ($checks as $c) {
    $out = shell_exec($c . ' 2>&1');
    echo "> $c\n" . ($out ?: "(no output)\n") . "\n";
}
