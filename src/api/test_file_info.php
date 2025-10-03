<?php
require_once __DIR__ . '/../../config/bootstrap.php';
header('Content-Type: text/plain');

$date = $_GET['date'] ?? '2025-09-05';
$name = $_GET['name'] ?? 'tchi.tif';
$path = $_GET['path'] ?? ($config['paths']['data_dir'] . "/processed/{$date}/{$name}");

echo "File info for: {$path}\n";
if (!file_exists($path)) {
    echo "exists: false\n";
    exit;
}

$stat = @stat($path);
$size = $stat ? $stat['size'] : filesize($path);
$mtime = $stat ? $stat['mtime'] : filemtime($path);

echo "exists: true\n";
echo "size: {$size} bytes\n";
echo "mtime: " . gmdate('c', $mtime) . "\n";

// MIME (may be generic)
if (function_exists('finfo_open')) {
    $f = finfo_open(FILEINFO_MIME_TYPE);
    if ($f) {
        $mime = finfo_file($f, $path);
        finfo_close($f);
        echo "mime: {$mime}\n";
    }
}

$fp = @fopen($path, 'rb');
if (!$fp) {
    echo "open: failed\n";
    exit;
}
$buf = fread($fp, 64);
fclose($fp);

// Hex dump first 32 bytes
$hex = strtoupper(implode(' ', str_split(bin2hex($buf), 2)));
echo "head_hex: {$hex}\n";

// Detect TIFF signature
$is_tiff = false; $is_bigtiff = false; $endian='';
if (strlen($buf) >= 4) {
    $b0 = ord($buf[0]); $b1 = ord($buf[1]); $b2 = ord($buf[2]); $b3 = ord($buf[3]);
    if ($b0==0x49 && $b1==0x49) { // 'II'
        $endian = 'little';
        if ($b2==0x2A && $b3==0x00) $is_tiff = true; // classic TIFF
        if ($b2==0x2B && $b3==0x00 && strlen($buf)>=8 && ord($buf[4])==0x08) $is_bigtiff = true; // BigTIFF
    } elseif ($b0==0x4D && $b1==0x4D) { // 'MM'
        $endian = 'big';
        if ($b2==0x00 && $b3==0x2A) $is_tiff = true;
        if ($b2==0x00 && $b3==0x2B && strlen($buf)>=8 && ord($buf[7])==0x08) $is_bigtiff = true;
    }
}

echo "tiff_signature: " . ($is_tiff ? 'classic' : ($is_bigtiff ? 'bigtiff' : 'no')) . ", endian: {$endian}\n";

// If not TIFF signature, print a short ASCII preview
$ascii = preg_replace('/[^\x20-\x7E]/', '.', substr($buf, 0, 64));
echo "ascii_preview: {$ascii}\n";
