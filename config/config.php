<?php
// Load .env variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'dbname' => $_ENV['DB_DATABASE'] ?? 'sharkscope_db',
        'user' => $_ENV['DB_USERNAME'] ?? 'root',
        'pass' => $_ENV['DB_PASSWORD'] ?? ''
    ],
    'paths' => [
        'project_root' => dirname(__DIR__),
        // Prefer PYTHON_EXE from .env; fallback to a common unix path
        'python_executable' => $_ENV['PYTHON_EXE'] ?? '/usr/bin/python3',
        'data_dir' => dirname(__DIR__) . '/data',
        'src_dir' => dirname(__DIR__) . '/src',
    ],
    'urls' => [
        'sst' => 'https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/MUR-JPL-L4-GLOB-v4.1/%s090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc',
        'chla' => 'https://oceandata.sci.gsfc.nasa.gov/cgi/getfile/SNPP_VIIRS.%s.L3m.DAY.CHL.chlor_a.4km.NRT.nc',
        'eke' => 'https://my.cmems-du.eu/...' // EKE URL placeholder
    ],
    'gdal' => [
        'warp_cmd' => 'gdalwarp',
        'dem_cmd' => 'gdaldem',
    ],
    'gemini' => [
        'api_key' => $_ENV['GEMINI_API_KEY'] ?? '',
        'model' => $_ENV['GEMINI_MODEL'] ?? 'gemini-2.0-flash-exp'
    ],
    'app' => [
        'base_url' => $_ENV['APP_BASE_URL'] ?? '/sharkscope' // For local dev, use '/sharkscope'; for production root, use ''
    ]
];
