<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: text/plain');

$python = $config['paths']['python_executable'] ?? null;
if (!$python) { http_response_code(500); echo "PYTHON_EXE not configured\n"; exit; }

$home = null;
if (preg_match('#^/home/([^/]+)/#', $python, $m)) { $home = "/home/{$m[1]}"; }
$envPrefix = $home ? ('HOME=' . escapeshellarg($home) . ' ') : '';

$code = <<<'PY'
import sys
mods = {}
try:
    import rasterio
    mods['rasterio'] = getattr(rasterio, '__version__', 'ok')
except Exception as e:
    mods['rasterio'] = f'ERROR: {e}'
try:
    import numpy as np
    mods['numpy'] = getattr(np, '__version__', 'ok')
except Exception as e:
    mods['numpy'] = f'ERROR: {e}'
try:
    import scipy
    mods['scipy'] = getattr(scipy, '__version__', 'ok')
except Exception as e:
    mods['scipy'] = f'ERROR: {e}'
print(mods)
PY;

$cmd = $envPrefix . escapeshellarg($python) . ' -c ' . escapeshellarg($code);
$out = shell_exec($cmd . ' 2>&1');
if ($out === null) { http_response_code(500); echo "No output\n"; exit; }

echo $out;
