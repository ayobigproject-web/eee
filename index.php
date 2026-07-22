<?php
// gw_advanced.php - Relay qui forward au vrai serveur Riot
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['gametoken'])) {
    echo json_encode(['error' => 'Missing gametoken']);
    exit;
}

$jwt = $input['gametoken'];
$sid = $input['sid'] ?? '';

// Forwarder au vrai serveur Riot (exemple pour NA)
$riot_url = 'https://na.vg.ac.pvp.net:8443/vanguard/v1/gateway';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $riot_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jwt); // Le JWT brut
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-protobuf',
    'User-Agent: VGClient/1.0'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    // Encode la réponse pour ton client
    $encoded = base64_encode($response);
    echo json_encode([
        'success' => true,
        'data' => urlencode($encoded)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => "Riot server returned HTTP $http_code"
    ]);
}
