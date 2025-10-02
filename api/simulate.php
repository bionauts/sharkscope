<?php
/**
 * SharkScope Mako-Sense Simulation API
 * 
 * This script simulates the Mako-Sense hardware feedback system
 * for the SharkScope project. It provides hardcoded responses
 * that mimic real hardware behavior for development and testing.
 * 
 * Method: POST only
 * Content-Type: application/json
 * 
 * Expected JSON body:
 * {
 *     "lat": 34.5,
 *     "lon": -120.2,
 *     "prey_code": "tuna_large"
 * }
 * 
 * Example response:
 * {
 *     "status": "Model refined",
 *     "previous_tchi": 0.78,
 *     "refined_tchi": 0.92,
 *     "message": "Positive prey confirmation feedback applied..."
 * }
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output as it will corrupt JSON

// Set the content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for web applications
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Function to log errors
function logError($message) {
    error_log("[SharkScope Mako-Sense] " . $message);
}

// Function to return error JSON
function returnError($message, $code = 400) {
    $response = [
        'error' => true,
        'message' => $message,
        'code' => $code
    ];
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    returnError("Only POST requests are allowed for Mako-Sense simulation.", 405);
}

// Read the JSON input
$input = file_get_contents('php://input');
if (empty($input)) {
    returnError("No JSON data received. Please send a JSON body with lat, lon, and prey_code.", 400);
}

// Parse JSON
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    returnError("Invalid JSON format: " . json_last_error_msg(), 400);
}

// Validate required fields
$requiredFields = ['lat', 'lon', 'prey_code'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        returnError("Missing required field: $field", 400);
    }
}

// Validate latitude and longitude
$lat = floatval($data['lat']);
$lon = floatval($data['lon']);
$preyCode = trim($data['prey_code']);

if ($lat < -90 || $lat > 90) {
    returnError("Invalid latitude. Must be between -90 and 90 degrees.", 400);
}

if ($lon < -180 || $lon > 180) {
    returnError("Invalid longitude. Must be between -180 and 180 degrees.", 400);
}

if (empty($preyCode)) {
    returnError("Prey code cannot be empty.", 400);
}

try {
    // Simulate different responses based on prey code and location
    $simulationResponses = [
        'tuna_large' => [
            'status' => 'Model refined',
            'previous_tchi' => 0.78,
            'refined_tchi' => 0.92,
            'message' => 'Positive prey confirmation feedback applied. Large tuna presence increases habitat quality prediction.'
        ],
        'tuna_small' => [
            'status' => 'Model refined',
            'previous_tchi' => 0.65,
            'refined_tchi' => 0.73,
            'message' => 'Positive prey confirmation feedback applied. Small tuna presence moderately improves habitat prediction.'
        ],
        'sardine_school' => [
            'status' => 'Model refined',
            'previous_tchi' => 0.45,
            'refined_tchi' => 0.68,
            'message' => 'Positive prey confirmation feedback applied. Sardine school indicates productive feeding area.'
        ],
        'squid_large' => [
            'status' => 'Model refined',
            'previous_tchi' => 0.82,
            'refined_tchi' => 0.94,
            'message' => 'Positive prey confirmation feedback applied. Large squid presence indicates premium habitat quality.'
        ],
        'no_prey' => [
            'status' => 'Model refined',
            'previous_tchi' => 0.75,
            'refined_tchi' => 0.42,
            'message' => 'Negative feedback applied. Absence of expected prey reduces habitat quality prediction.'
        ],
        'unknown_species' => [
            'status' => 'Data recorded',
            'previous_tchi' => 0.58,
            'refined_tchi' => 0.58,
            'message' => 'Unknown species detected. Data recorded for future model training. No immediate refinement applied.'
        ]
    ];

    // Add some location-based variation to make it more realistic
    $latModifier = sin(deg2rad($lat)) * 0.05; // Small variation based on latitude
    $lonModifier = cos(deg2rad($lon)) * 0.03; // Small variation based on longitude
    
    // Get base response or default
    $baseResponse = $simulationResponses[$preyCode] ?? $simulationResponses['unknown_species'];
    
    // Apply location-based modifications
    $response = [
        'status' => $baseResponse['status'],
        'previous_tchi' => round($baseResponse['previous_tchi'] + $latModifier, 3),
        'refined_tchi' => round($baseResponse['refined_tchi'] + $latModifier + $lonModifier, 3),
        'message' => $baseResponse['message'],
        'simulation_data' => [
            'location' => [
                'lat' => $lat,
                'lon' => $lon
            ],
            'prey_detected' => $preyCode,
            'processing_time_ms' => rand(150, 350), // Simulate processing time
            'confidence_score' => round(rand(75, 98) / 100, 2),
            'timestamp' => date('c'), // ISO 8601 format
            'hardware_id' => 'MAKO-SIM-' . sprintf('%04d', rand(1000, 9999)),
            'firmware_version' => '2.1.3-beta'
        ]
    ];
    
    // Ensure TCHI values stay within valid range (0-1)
    $response['previous_tchi'] = max(0, min(1, $response['previous_tchi']));
    $response['refined_tchi'] = max(0, min(1, $response['refined_tchi']));
    
    // Log the simulation for debugging
    logError("Mako-Sense simulation: {$preyCode} at ({$lat}, {$lon}) -> TCHI: {$response['previous_tchi']} → {$response['refined_tchi']}");
    
    // Return the simulated response
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    logError("Exception occurred: " . $e->getMessage());
    returnError("Internal server error occurred during simulation.", 500);
}
?>