<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Générer un protobuf simulé plus réaliste
$fakeProtobuf =
    "\x0A\x1C" . "RIOT_GATEWAY_AUTH_RESPONSE" .  // Field 1, length 28
    "\x12\x18" . date('Y-m-d\TH:i:s\Z') .        // Field 2, length 24 (ISO date)
    "\x1A\x10" . "VALID_SESSION_TOKEN" .          // Field 3, length 16
    "\x22\x20" . random_bytes(16) .               // Field 4, length 32 (bytes bruts!)
    "\x2A\x08" . "SUCCESS";                       // Field 5, length 8

echo json_encode([
    'success' => true,
    'data' => urlencode(base64_encode($fakeProtobuf)),
    'debug' => [
        'type' => 'protobuf_simulated',
        'protobuf_length' => strlen($fakeProtobuf),
        'timestamp' => time()
    ]
]);
