<?php
/**
 * Gemini-powered hotspot restaurant naming utility.
 * 
 * Uses Google's Gemini 2.0 Flash model to generate creative, 
 * location-aware restaurant names based on coordinates.
 * 
 * Includes 12-hour file-based caching to reduce API calls.
 */

/**
 * Get the cache file path for name storage.
 */
function getNameCachePath() {
    // Use global config if available, otherwise fall back to relative path
    if (isset($GLOBALS['config'])) {
        $cacheDir = $GLOBALS['config']['paths']['data_dir'] . '/cache';
    } else {
        $basePath = dirname(__DIR__, 2); // Go up two levels to project root
        $cacheDir = $basePath . '/data/cache';
    }
    
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    return $cacheDir . '/gemini_names.json';
}

/**
 * Load cached names from disk.
 * 
 * @return array Associative array of coordinate keys => [name, timestamp]
 */
function loadNameCache() {
    $cachePath = getNameCachePath();
    if (!file_exists($cachePath)) {
        return [];
    }
    
    $json = @file_get_contents($cachePath);
    if ($json === false) {
        return [];
    }
    
    $cache = json_decode($json, true);
    return is_array($cache) ? $cache : [];
}

/**
 * Save cached names to disk.
 * 
 * @param array $cache Associative array of coordinate keys => [name, timestamp]
 */
function saveNameCache($cache) {
    $cachePath = getNameCachePath();
    $json = json_encode($cache, JSON_PRETTY_PRINT);
    @file_put_contents($cachePath, $json, LOCK_EX);
}

/**
 * Generate a cache key from coordinates (rounded to 4 decimals for ~11m precision).
 */
function getCacheKey($lat, $lon) {
    return sprintf('%.4f_%.4f', $lat, $lon);
}

/**
 * Get a cached name if valid (within 12 hours).
 * 
 * @param float $lat Latitude
 * @param float $lon Longitude
 * @return string|null Cached name or null if expired/missing
 */
function getCachedName($lat, $lon) {
    $cache = loadNameCache();
    $key = getCacheKey($lat, $lon);
    
    if (!isset($cache[$key])) {
        return null;
    }
    
    $entry = $cache[$key];
    $maxAge = 12 * 3600; // 12 hours in seconds
    
    if (!isset($entry['timestamp']) || (time() - $entry['timestamp']) > $maxAge) {
        return null;
    }
    
    return $entry['name'] ?? null;
}

/**
 * Store a generated name in the cache.
 * 
 * @param float $lat Latitude
 * @param float $lon Longitude
 * @param string $name Generated restaurant name
 */
function cacheGeneratedName($lat, $lon, $name) {
    $cache = loadNameCache();
    $key = getCacheKey($lat, $lon);
    
    $cache[$key] = [
        'name' => $name,
        'timestamp' => time()
    ];
    
    saveNameCache($cache);
}

function generateRestaurantName($lat, $lon) {
    // Use global config if available
    $apiKey = '';
    $model = 'gemini-2.0-flash-exp';
    
    if (isset($GLOBALS['config'])) {
        $apiKey = $GLOBALS['config']['gemini']['api_key'] ?? '';
        $model = $GLOBALS['config']['gemini']['model'] ?? 'gemini-2.0-flash-exp';
    }
    
    if (empty($apiKey)) {
        error_log("Gemini API key not configured");
        return null;
    }
    
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    
    $prompt = sprintf(
        "You are a creative naming expert. Generate a single, catchy restaurant name for a shark-watching hotspot located at latitude %.4f, longitude %.4f. The name should be 2-4 words, relate to the nearby geography or ocean feature (e.g., bay, sea, trench, current), and evoke adventure or marine life. Examples: 'The Bengal Buffet' for Bay of Bengal, 'Mariana Depths Diner' for Mariana Trench. Only return the restaurant name, no explanation or quotes.",
        $lat,
        $lon
    );
    
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.9,
            'maxOutputTokens' => 50
        ]
    ]);
    
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        error_log("Gemini API request failed (HTTP {$httpCode}): {$response}");
        return null;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API returned unexpected structure: {$response}");
        return null;
    }
    
    $rawName = trim($data['candidates'][0]['content']['parts'][0]['text']);
    // Strip any surrounding quotes or markdown artifacts
    $cleanName = preg_replace('/^["\'`]+|["\'`]+$/', '', $rawName);
    
    return $cleanName ?: null;
}

/**
 * Generate restaurant names for a batch of hotspots.
 * 
 * @param array $hotspots Array of hotspot objects with 'lat' and 'lon' keys
 * @return array Same hotspot array with 'name' field added to each
 */
function enrichHotspotsWithNames($hotspots) {
    if (!is_array($hotspots) || empty($hotspots)) {
        return $hotspots;
    }
    
    foreach ($hotspots as $index => &$hotspot) {
        if (!isset($hotspot['lat']) || !isset($hotspot['lon'])) {
            $hotspot['name'] = "Hotspot #" . ($index + 1);
            continue;
        }
        
        // Try cache first
        $cachedName = getCachedName($hotspot['lat'], $hotspot['lon']);
        if ($cachedName) {
            $hotspot['name'] = $cachedName;
            continue;
        }
        
        // Generate new name if cache miss
        $generatedName = generateRestaurantName($hotspot['lat'], $hotspot['lon']);
        
        if ($generatedName) {
            $hotspot['name'] = $generatedName;
            cacheGeneratedName($hotspot['lat'], $hotspot['lon'], $generatedName);
        } else {
            // Fallback to numbered naming if API fails
            $hotspot['name'] = "Hotspot #" . ($index + 1);
        }
        
        // Small delay to respect rate limits (if any)
        if ($index < count($hotspots) - 1) {
            usleep(200000); // 200ms between requests
        }
    }
    
    return $hotspots;
}
?>
