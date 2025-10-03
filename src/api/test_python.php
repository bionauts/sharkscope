<?php
// Simple endpoint to verify Python configuration from the browser
require_once __DIR__ . '/../../config/bootstrap.php';

header('Content-Type: text/plain');

$python = $config['paths']['python_executable'] ?? null;
if (!$python) {
    http_response_code(500);
    echo "PYTHON_EXE not configured";
    exit;
}

$home = null;
if (preg_match('#^/home/([^/]+)/#', $python, $m)) {
    $home = "/home/{$m[1]}";
}
$envPrefix = $home ? ('HOME=' . escapeshellarg($home) . ' ') : '';
$cmd = $envPrefix . (escapeshellarg($python) . ' -c ' . escapeshellarg('import sys; print(sys.version)'));
$output = shell_exec($cmd . ' 2>&1');

if ($output === null || $output === '') {
    http_response_code(500);
    echo "No output from python. Command: $cmd\n";
    exit;
}

echo "Python OK\n";
echo $output;
