// Dans ton gw.php sur Railway
$fakeProtobuf = base64_encode(
    // Format qui ressemble à un vrai protobuf Riot
    "\x0A\x1C" . "RIOT_GATEWAY_AUTH_RESPONSE" .
    "\x12\x18" . date('Y-m-d\TH:i:s\Z') .
    "\x1A\x10" . "VALID_SESSION_TOKEN" .
    "\x22\x20" . bin2hex(random_bytes(16)) .
    "\x2A\x08" . "SUCCESS"
);

echo json_encode([
    'success' => true,
    'data' => urlencode($fakeProtobuf),
    'debug' => ['type' => 'protobuf_simulated']
]);
