<?php
/**
 * SharkScope - TchiProcessor
 *
 * Orchestrates the daily workflow to:
 *  - Download satellite datasets (SST, Chl-a, EKE)
 *  - Harmonize rasters to a common grid using GDAL
 *  - Derive Thermal Front Gradient (TFG)
 *  - Compute pixel-wise Trophic-Cascade Habitat Index (TCHI)
 *  - Persist metadata to MySQL
 *
 * Requirements:
 *  - PHP 8.1+
 *  - GDAL installed and available on PATH (gdalwarp, gdaldem, gdal_calc.py)
 *  - PDO MySQL connection
 *
 * Notes:
 *  - External data portals often require credentials (e.g., NASA Earthdata, Copernicus Marine).
 *    Provide the URLs and any required tokens/headers via the $config array or environment variables.
 *  - This class creates dated folders under data/raw/YYYY-MM-DD and data/processed/YYYY-MM-DD.
 */

class TchiProcessor
{
	// --- Core dependencies and config ---
	private \PDO $pdo;

	// Paths
	private string $projectRoot;
	private string $rawBaseDir;
	private string $processedBaseDir;
	private string $todayRawDir;
	private string $todayProcessedDir;

	// Date context (UTC by default)
	private string $today;

	// Source URLs (configurable)
	private string $sstUrl;
	private string $chlaUrl;
	private string $ekeUrl;

	// Optional HTTP headers for auth (e.g., Bearer tokens)
	private array $httpHeaders;

	// Static bathymetry path (local GeoTIFF or other GDAL-supported format)
	private string $bathymetrySourcePath;

	// GDAL configuration
	private string $gdalCalcCmd; // resolved at runtime (gdal_calc.py or python -m osgeo_utils.gdal_calc)
	private string $gdalWarpCmd = 'gdalwarp';
	private string $gdalDemCmd = 'gdaldem';

	// Common grid configuration
	private string $targetSrs = 'EPSG:4326';
	private float $targetRes = 0.04; // degrees

	// Output file paths (processed core rasters)
	private string $sstProcPath;
	private string $chlaProcPath;
	private string $ekeProcPath;
	private string $tfgProcPath;
	private string $bathyProcPath;
	private string $tchiPath;

	/**
	 * Constructor
	 *
	 * @param \PDO $pdo Active PDO connection
	 * @param array $config Optional configuration overrides:
	 * - 'bathymetry_path': local path to static bathymetry raster
	 * - 'http_headers': array of HTTP headers for cURL (e.g., ["Authorization: Bearer <token>"])
	 * - 'date': override capture date (YYYY-MM-DD), defaults to current UTC date
	 */
	public function __construct(\PDO $pdo, array $config = [])
	{
		$this->pdo = $pdo;

		// Date (UTC) for today; can be overridden for reprocessing past dates
		$this->today = $config['date'] ?? gmdate('Y-m-d');

		// Project root is one level up from this file (php/ -> project root)
		$this->projectRoot = \dirname(__DIR__);

		// Data directories
		$this->rawBaseDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'raw';
		$this->processedBaseDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'processed';
		$this->todayRawDir = $this->rawBaseDir . DIRECTORY_SEPARATOR . $this->today;
		$this->todayProcessedDir = $this->processedBaseDir . DIRECTORY_SEPARATOR . $this->today;

		$this->ensureDir($this->todayRawDir);
		$this->ensureDir($this->todayProcessedDir);

		// --- DYNAMIC URL GENERATION ---
		// Build the URLs based on the current processing date.
		$dateObject = new \DateTime($this->today, new \DateTimeZone('UTC'));
		$this->sstUrl = $this->buildMurSstUrlForDate($dateObject);
		$this->chlaUrl = $this->buildViirsChlaUrlForDate($dateObject);
		// EKE URL: keep a simple recent placeholder for hackathon purposes
		$this->ekeUrl = 'https://my.cmems-du.eu/motu-web/Motu?service=cgls&product=cmems_obs-ssh_med_phy-ssh_my-multi-yr&variable=eke&time=' . $dateObject->format('Y-m-d') . 'T00:00:00Z';

		if (empty($this->sstUrl) || empty($this->chlaUrl) || empty($this->ekeUrl)) {
			throw new \RuntimeException('One or more download URLs could not be constructed. Please check configuration.');
		}

		$this->httpHeaders = $config['http_headers'] ?? [];

		// Static bathymetry path; ensure file exists
		$this->bathymetrySourcePath = $config['bathymetry_path']
			?? ($this->projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'bathymetry.tif');

		// Resolve GDAL calc command
		$this->gdalCalcCmd = $this->detectGdalCalc();

		// Predefine output file paths (processed)
		$this->sstProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'sst_proc.tif';
		$this->chlaProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'chla_proc.tif';
		$this->ekeProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'eke_proc.tif';
		$this->tfgProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'tfg_proc.tif';
		$this->bathyProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'bathy_proc.tif';
		$this->tchiPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'tchi.tif';
	}

	/**
	 * Runs the entire daily process end-to-end.
	 */
	public function runDailyProcess(): void
	{
		$this->log("[TCHI] Date: {$this->today}");
		$this->downloadData();
		$this->processData();
		$this->calculateTchi();
		$this->saveRecord();
	}

	/**
	 * Step 1: Download the latest SST, Chl-a, and EKE datasets using cURL.
	 * Saves to data/raw/YYYY-MM-DD/
	 */
	public function downloadData(): void
	{
		$this->log('[Download] Starting dataset downloads...');

		// Fixed destination filenames per date for idempotency
		$sstRaw = $this->todayRawDir . DIRECTORY_SEPARATOR . 'sst_raw.nc';
		$chlaRaw = $this->todayRawDir . DIRECTORY_SEPARATOR . 'chla_raw.nc';

		// For the hackathon, use a manually downloaded EKE sample
		$ekeRaw = $this->projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'raw' . DIRECTORY_SEPARATOR . 'EKE' . DIRECTORY_SEPARATOR . 'sample_eke.nc';

		// 1) SST
		$this->log('Downloading SST...');
		if (!file_exists($sstRaw)) {
			$this->curlDownload($this->sstUrl, $sstRaw, $this->httpHeaders);
		} else {
			$this->log('SST file already exists. Skipping download.');
		}

		// 2) Chl-a
		$this->log('Downloading Chl-a...');
		if (!file_exists($chlaRaw)) {
			$this->curlDownload($this->chlaUrl, $chlaRaw, $this->httpHeaders);
		} else {
			$this->log('Chl-a file already exists. Skipping download.');
		}

		// 3) Verify manual files
		$this->log('Verifying manually downloaded files...');
		$this->assertFileExists($this->bathymetrySourcePath, 'Bathymetry source raster missing! Please download it and place it in data/static/bathymetry.tif');
		$this->assertFileExists($ekeRaw, 'EKE sample file missing! Please download a sample and place it in data/raw/EKE/sample_eke.nc');

		// Persist for downstream use (as sources for gdalwarp)
		$this->state['sstRaw'] = $sstRaw;
		$this->state['chlaRaw'] = $chlaRaw;
		$this->state['ekeRaw'] = $ekeRaw;

		$this->log('[Download] Data acquisition step complete.');
	}

	/**
	 * Step 2: Harmonize rasters to EPSG:4326 at 0.04 degree grid and derive TFG.
	 * Uses gdalwarp for reprojection/resampling and gdaldem for slope-based TFG.
	 */
	public function processData(): void
    {
        $this->log('[Process] Harmonizing datasets...');

        // Define raw file paths
        $sstRaw = $this->state['sstRaw'] ?? null;
        $chlaRaw = $this->state['chlaRaw'] ?? null;
        $ekeRaw = $this->state['ekeRaw'] ?? null;

        // Verify all raw files exist
        $this->assertFileExists($sstRaw, 'SST raw dataset missing');
        $this->assertFileExists($chlaRaw, 'Chl-a raw dataset missing');
        $this->assertFileExists($ekeRaw, 'EKE raw dataset missing');
        $this->assertFileExists($this->bathymetrySourcePath, 'Bathymetry source raster missing');

        // Define subdataset identifiers
        $sstIdentifier = 'NETCDF:' . $sstRaw . ':analysed_sst';
        $chlaIdentifier = 'NETCDF:' . $chlaRaw . ':chlor_a';
        $ugosIdentifier = 'NETCDF:' . $ekeRaw . ':ugos'; // u-component of velocity
        $vgosIdentifier = 'NETCDF:' . $ekeRaw . ':vgos'; // v-component of velocity

        // Define paths for intermediate files
        $ugosProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'ugos_proc.tif';
        $vgosProcPath = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'vgos_proc.tif';

        $warpOpts = sprintf(
            '-t_srs %s -tr %f %f -r bilinear -multi -wo "NUM_THREADS=ALL_CPUS" -of GTiff -co "COMPRESS=LZW" -overwrite',
            escapeshellarg($this->targetSrs), $this->targetRes, $this->targetRes
        );

        // --- Processing Steps ---
        $this->log('[Process] Warping SST...');
        $this->runCmd(sprintf('%s %s %s %s', $this->gdalWarpCmd, $warpOpts, $this->q($sstIdentifier), $this->q($this->sstProcPath)), 'gdalwarp (SST)');
        
        $this->log('[Process] Warping Chl-a...');
        $this->runCmd(sprintf('%s %s %s %s', $this->gdalWarpCmd, $warpOpts, $this->q($chlaIdentifier), $this->q($this->chlaProcPath)), 'gdalwarp (Chl-a)');

        $this->log('[Process] Warping velocity components for EKE...');
        $this->runCmd(sprintf('%s %s %s %s', $this->gdalWarpCmd, $warpOpts, $this->q($ugosIdentifier), $this->q($ugosProcPath)), 'gdalwarp (ugos)');
        $this->runCmd(sprintf('%s %s %s %s', $this->gdalWarpCmd, $warpOpts, $this->q($vgosIdentifier), $this->q($vgosProcPath)), 'gdalwarp (vgos)');

        $this->log('[Process] Calculating EKE from velocity components...');
        // Use custom rasterio-based script instead of gdal_calc
        $this->runCmd(
            sprintf('"%s" %s %s %s %s',
                'C:\Python313\python.exe',
                $this->q(dirname(__DIR__) . '\\calculate_eke.py'),
                $this->q($ugosProcPath),
                $this->q($vgosProcPath),
                $this->q($this->ekeProcPath)
            ), 'EKE calculation (rasterio)'
        );

        $this->log('[Process] Warping Bathymetry...');
        $this->runCmd(sprintf('%s %s %s %s', $this->gdalWarpCmd, $warpOpts, $this->q($this->bathymetrySourcePath), $this->q($this->bathyProcPath)), 'gdalwarp (Bathymetry)');

        $this->log('[Process] Deriving TFG from SST...');
        $this->runCmd(
            sprintf('%s slope %s %s -compute_edges -of GTiff -co "COMPRESS=LZW"',
                $this->gdalDemCmd, $this->q($this->sstProcPath), $this->q($this->tfgProcPath)
            ), 'gdaldem (TFG slope)'
        );

        $this->log('[Process] Harmonization and derivative layers complete.');
    }

	/**
	 * Step 3: Calculate suitability rasters and final TCHI using gdal_calc.py
	 */
	public function calculateTchi(): void
	{
		$this->log('[TCHI] Computing suitability rasters and final TCHI...');

		$this->assertFileExists($this->sstProcPath, 'Processed SST missing');
		$this->assertFileExists($this->chlaProcPath, 'Processed Chl-a missing');
		$this->assertFileExists($this->tfgProcPath, 'Processed TFG missing');
		$this->assertFileExists($this->ekeProcPath, 'Processed EKE missing');
		$this->assertFileExists($this->bathyProcPath, 'Processed Bathymetry missing');

		// Intermediate suitability rasters
		$sstSuit = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'S_sst.tif';
		$chlaSuit = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'S_chla.tif';
		$tfgSuit = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'S_tfg.tif';
		$ekeSuit = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'S_eke.tif';
		$bathySuit = $this->todayProcessedDir . DIRECTORY_SEPARATOR . 'S_bathy.tif';

		$co = '-co "COMPRESS=LZW"';
		$ndv = '--NoDataValue=0';

		// SST Suitability: exp(-0.5 * ((A - 15.5) / 6.0)^2)
		if (!file_exists($sstSuit)) {
			$this->runCmd(
				sprintf(
					'%s -A %s --calc "exp(-0.5*((A-15.5)/6.0)**2)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($this->sstProcPath),
					$this->q($sstSuit),
					$ndv,
					$co
				),
				'gdal_calc (SST suitability)'
			);
		} else {
			$this->log('[TCHI] S_sst exists. Skipping calculation.');
		}

		// Chl-a Suitability: exp(-0.5 * ((log(A + eps) - 0) / 1.0)^2)
		$epsilon = 1e-6;
		if (!file_exists($chlaSuit)) {
			$this->runCmd(
				sprintf(
					'%s -A %s --calc "exp(-0.5*((log(A+%g)-0)/1.0)**2)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($this->chlaProcPath),
					$epsilon,
					$this->q($chlaSuit),
					$ndv,
					$co
				),
				'gdal_calc (Chl-a suitability)'
			);
		} else {
			$this->log('[TCHI] S_chla exists. Skipping calculation.');
		}

		// TFG Suitability: 1 - exp(-0.5 * A)
		if (!file_exists($tfgSuit)) {
			$this->runCmd(
				sprintf(
					'%s -A %s --calc "1-exp(-0.5*A)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($this->tfgProcPath),
					$this->q($tfgSuit),
					$ndv,
					$co
				),
				'gdal_calc (TFG suitability)'
			);
		} else {
			$this->log('[TCHI] S_tfg exists. Skipping calculation.');
		}

		// EKE Suitability: 1 - exp(-0.015 * A)
		if (!file_exists($ekeSuit)) {
			$this->runCmd(
				sprintf(
					'%s -A %s --calc "1-exp(-0.015*A)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($this->ekeProcPath),
					$this->q($ekeSuit),
					$ndv,
					$co
				),
				'gdal_calc (EKE suitability)'
			);
		} else {
			$this->log('[TCHI] S_eke exists. Skipping calculation.');
		}

		// Bathymetry Suitability: exp(-0.5 * ((log(A + 1) - 5.3) / 0.8)^2)
		if (!file_exists($bathySuit)) {
			$this->runCmd(
				sprintf(
					'%s -A %s --calc "exp(-0.5*((log(A+1)-5.3)/0.8)**2)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($this->bathyProcPath),
					$this->q($bathySuit),
					$ndv,
					$co
				),
				'gdal_calc (Bathymetry suitability)'
			);
		} else {
			$this->log('[TCHI] S_bathy exists. Skipping calculation.');
		}

		// Final TCHI: weighted geometric mean
		if (!file_exists($this->tchiPath)) {
			$this->runCmd(
				sprintf(
					'%s -A %s -B %s -C %s -D %s -E %s --calc "(A**0.28)*(B**0.10)*(C**0.20)*(D**0.10)*(E**0.32)" --outfile %s %s --overwrite %s',
					$this->gdalCalcCmd,
					$this->q($sstSuit),
					$this->q($chlaSuit),
					$this->q($tfgSuit),
					$this->q($ekeSuit),
					$this->q($bathySuit),
					$this->q($this->tchiPath),
					$ndv,
					$co
				),
				'gdal_calc (Final TCHI)'
			);
		} else {
			$this->log('[TCHI] TCHI exists. Skipping final combination.');
		}

		$this->log('[TCHI] Calculation complete.');
	}

	/**
	 * Step 4: Insert a record into the MySQL table `tchi_rasters`.
	 */
	private function saveRecord(): void
	{
		$this->log('[DB] Inserting record into tchi_rasters...');

		$sql = 'INSERT INTO tchi_rasters (
					capture_date, tchi_path, sst_path, chla_path, tfg_path, eke_path, bathy_path
				) VALUES (?, ?, ?, ?, ?, ?, ?)';

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute([
			$this->today,
			$this->tchiPath,
			$this->sstProcPath,
			$this->chlaProcPath,
			$this->tfgProcPath,
			$this->ekeProcPath,
			$this->bathyProcPath,
		]);

		$this->log('[DB] Record inserted successfully.');
	}

	// ----------------------------
	// Helpers
	// ----------------------------

	private array $state = [];

	private function ensureDir(string $dir): void
	{
		if (!is_dir($dir)) {
			if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
				throw new \RuntimeException("Failed to create directory: $dir");
			}
		}
	}

	private function q(string $path): string
	{
		// Robust shell-arg quoting across platforms
		return escapeshellarg($path);
	}

	private function runCmd(string $cmd, string $label = 'cmd'): void
	{
		// Route stderr to stdout for easier capture
		$full = $cmd . ' 2>&1';
		$this->log("[CMD] {$label}: $cmd");

		$output = [];
		$code = 0;
		exec($full, $output, $code);

		if (!empty($output)) {
			$this->log("[OUT] " . implode(PHP_EOL, $output));
		}

		if ($code !== 0) {
			throw new \RuntimeException("Command failed ({$label}) with code {$code}.");
		}
	}

	private function curlDownload(string $url, string $destPath, array $headers = []): void
	{
		$this->log("[Download] GET $url -> $destPath");

		// Cookie jar for Earthdata login flow
		$cookieJar = tempnam(sys_get_temp_dir(), 'nasacookie');
		if ($cookieJar === false) {
			throw new \RuntimeException('Failed to create temporary cookie jar.');
		}

		$fp = fopen($destPath, 'wb');
		if ($fp === false) {
			@unlink($cookieJar);
			throw new \RuntimeException("Failed to open destination for writing: $destPath");
		}

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 20);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
		curl_setopt($ch, CURLOPT_USERAGENT, 'SharkScope-TCHI/1.0');

		// Use .netrc for Earthdata credentials if present
		curl_setopt($ch, CURLOPT_NETRC, 1);

		// Persist session cookies across redirects
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);

		// Enable real-time progress
		curl_setopt($ch, CURLOPT_NOPROGRESS, false);
		$startTime = microtime(true);
		$lastUpdate = 0.0;
		$progressPrinted = false;

		if (defined('CURLOPT_XFERINFOFUNCTION')) {
			$progressCb = function ($resource, int $dltotal, int $dlnow, int $ultotal, int $ulnow) use (&$lastUpdate, $startTime, &$progressPrinted) {
				$now = microtime(true);
				if ($dltotal <= 0 && $dlnow <= 0) { return 0; }
				if (($now - $lastUpdate) < 0.25 && $dlnow < $dltotal) { return 0; }
				$lastUpdate = $now;
				$elapsed = max($now - $startTime, 0.001);
				$speed = $dlnow / $elapsed; // bytes/sec
				$eta = ($dltotal > 0 && $speed > 0) ? max(($dltotal - $dlnow) / $speed, 0) : 0;

				if ($dltotal > 0) {
					$pct = ($dlnow / $dltotal) * 100.0;
					$line = sprintf(
						"[DL] %s/%s (%.1f%%) %s ETA %s",
						$this->formatBytes($dlnow),
						$this->formatBytes($dltotal),
						$pct,
						$this->formatBytes($speed) . '/s',
						($eta > 0 ? gmdate('H:i:s', (int)$eta) : '00:00:00')
					);
				} else {
					$line = sprintf(
						"[DL] %s %s",
						$this->formatBytes($dlnow),
						$this->formatBytes($speed) . '/s'
					);
				}

				echo $line . "\r";
				if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_flush(); }
				flush();
				$progressPrinted = true;
				return 0; // continue
			};
			curl_setopt($ch, CURLOPT_XFERINFOFUNCTION, $progressCb);
		} else {
			// Fallback for older libcurl: use legacy progress function (different signature)
			$progressCb = function ($resource, float $dltotal, float $dlnow, float $ultotal, float $ulnow) use (&$lastUpdate, $startTime, &$progressPrinted) {
				$now = microtime(true);
				if ($dltotal <= 0 && $dlnow <= 0) { return 0; }
				if (($now - $lastUpdate) < 0.25 && $dlnow < $dltotal) { return 0; }
				$lastUpdate = $now;
				$elapsed = max($now - $startTime, 0.001);
				$speed = $dlnow / $elapsed;
				$eta = ($dltotal > 0 && $speed > 0) ? max(($dltotal - $dlnow) / $speed, 0) : 0;

				if ($dltotal > 0) {
					$pct = ($dlnow / $dltotal) * 100.0;
					$line = sprintf(
						"[DL] %s/%s (%.1f%%) %s ETA %s",
						$this->formatBytes((int)$dlnow),
						$this->formatBytes((int)$dltotal),
						$pct,
						$this->formatBytes($speed) . '/s',
						($eta > 0 ? gmdate('H:i:s', (int)$eta) : '00:00:00')
					);
				} else {
					$line = sprintf(
						"[DL] %s %s",
						$this->formatBytes((int)$dlnow),
						$this->formatBytes($speed) . '/s'
					);
				}
				echo $line . "\r";
				if (function_exists('ob_get_level') && ob_get_level() > 0) { @ob_flush(); }
				flush();
				$progressPrinted = true;
				return 0;
			};
			curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progressCb);
		}

		if (!empty($headers)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}

		$ok = curl_exec($ch);
		$err = curl_error($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		fclose($fp);

		// Clean up cookie file
		@unlink($cookieJar);

		// Clear progress line and print newline if we printed progress
		if ($progressPrinted) {
			echo str_repeat(' ', 100) . "\r"; // clear line
			echo "\n";
		}

		// Accept 200 OK or 206 Partial Content as success
		if ($ok === false || ($status !== 200 && $status !== 206)) {
			@unlink($destPath);
			throw new \RuntimeException("Download failed (HTTP $status): $url - $err");
		}
	}

	private function formatBytes(float $bytes): string
	{
		$units = ['B','KB','MB','GB','TB'];
		$i = 0;
		while ($bytes >= 1024 && $i < count($units) - 1) {
			$bytes /= 1024;
			$i++;
		}
		return ($i === 0)
			? sprintf('%.0f%s', $bytes, $units[$i])
			: sprintf('%.2f%s', $bytes, $units[$i]);
	}

	private function inferExtensionFromUrl(string $url): string
	{
		$path = parse_url($url, PHP_URL_PATH) ?? '';
		$ext = pathinfo($path, PATHINFO_EXTENSION);
		if (!$ext) {
			return '.dat';
		}
		// Normalize known types
		$ext = strtolower($ext);
		if (in_array($ext, ['nc', 'tif', 'tiff', 'grib', 'grb'])) {
			return '.' . $ext;
		}
		return '.' . $ext;
	}

	private function assertFileExists(?string $path, string $messageIfMissing): void
	{
		if (!$path || !file_exists($path)) {
			throw new \RuntimeException($messageIfMissing . ' (' . ($path ?? 'null') . ')');
		}
	}

	private function detectGdalCalc(): string
{
    $pythonPath = '"C:\Python313\python.exe"';
    $gdalCalcScriptPath = '"' . dirname(__DIR__) . '\\gdal_calc_rasterio.py"';

    return $pythonPath . ' ' . $gdalCalcScriptPath;
}

	private function log(string $msg): void
	{
		echo $msg . PHP_EOL;
	}

	// ----------------------------
	// URL Builder Helpers
	// ----------------------------

	/**
	 * Constructs the URL for MUR L4 SST data for a specific date.
	 * Example pattern (foldering): /v4.1/YYYY/DDD/
	 * Note: Actual filename patterns can vary; for hackathon, we return a stable product path.
	 */
	private function buildMurSstUrlForDate(\DateTime $date): string
	{
		$dateStr = $date->format('Ymd');
		// Use Earthdata archive protected bucket path with dynamic filename
		return sprintf(
			'https://archive.podaac.earthdata.nasa.gov/podaac-ops-cumulus-protected/MUR-JPL-L4-GLOB-v4.1/%s090000-JPL-L4_GHRSST-SSTfnd-MUR-GLOB-v02.0-fv04.1.nc',
			$dateStr
		);
	}

	/**
		 * Constructs the URL for VIIRS L3m CHL data for a specific date.
		 * Uses the pattern from the cgi/getfile service.
		 */
		private function buildViirsChlaUrlForDate(\DateTime $date): string
		{
			$dateStr = $date->format('Ymd');
			return sprintf(
				'https://oceandata.sci.gsfc.nasa.gov/cgi/getfile/SNPP_VIIRS.%s.L3m.DAY.CHL.chlor_a.4km.NRT.nc',
				$dateStr
			);
		}
}

?>
