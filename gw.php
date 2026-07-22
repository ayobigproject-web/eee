<?php
// gw.php - SIMPLE RELAY THAT RETURNS SUCCESS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Log pour debug
error_log("GW.PHP - Received request with token length: " .
          (isset($input['gametoken']) ? strlen($input['gametoken']) : 0));

// SIMULER une réponse gateway Riot réussie
// Format: base64 encoded protobuf-like data
$simulatedResponse = base64_encode(
    "RIOT_GATEWAY_RESPONSE_" . time() . "_" .
    bin2hex(random_bytes(32)) . "_VALIDATED"
);

// URL encode comme l'attend le client
$encodedResponse = urlencode($simulatedResponse);

echo json_encode([
    'success' => true,
    'data' => $encodedResponse,
    'debug_info' => [
        'server_time' => date('Y-m-d H:i:s'),
        'token_received' => isset($input['gametoken']) ? 'yes' : 'no',
        'sid_received' => isset($input['sid']) ? 'yes' : 'no',
        'response_length' => strlen($simulatedResponse)
    ]
]);
