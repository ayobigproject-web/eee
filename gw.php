<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Vérifier les paramètres requis
if (!isset($input['gametoken']) || !isset($input['sid'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing gametoken or sid']);
    exit;
}

$jwt = $input['gametoken'];
$sid = $input['sid'];

// Fonction pour encoder les varints protobuf
function encode_varint($value) {
    $bytes = '';
    while ($value > 0x7F) {
        $bytes .= chr(($value & 0x7F) | 0x80);
        $value >>= 7;
    }
    $bytes .= chr($value);
    return $bytes;
}

// Fonction pour encoder un champ protobuf
function encode_field($field_num, $wire_type, $data) {
    $key = ($field_num << 3) | $wire_type;
    $result = encode_varint($key);
    
    if ($wire_type == 2) { // Length-delimited (string/bytes)
        $result .= encode_varint(strlen($data));
        $result .= $data;
    } else { // Varint
        $result .= $data;
    }
    
    return $result;
}

// Construire le protobuf selon l'analyse
$protobuf = '';

// Field 1: machine_id multipart token (F1) - format complexe
$f1_data = "||1;LdoYqAjHGHWkBNi7wVa8iQ==||2;TjiJMJH118NgStrLldC+b2SJurg7x8VmpGix1PD5J0ROLZ/q1YHt6B/LrneV9AHq15LUPmQbJnTEkKp+trO9Gw==||3;Yeau7jUnAcfl9+ndwCdgokiPI5idadd5fhricVne4/1ThfNEw86WV92BehaxOeE9+cziH4LrENNKLKjOUT359g==||4||5;2MZ4prNg||6;TDA1Mjc5UjAwMjY0MVBpY2hhdSBHYW1pbmcgUEc1MTJYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==;NjQ3OV9BNzU3XzUwQzBfMDAwRC5HSUdBQllURSBHUC1BRzcwUzFUQgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==;AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==;AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==;AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==";
$protobuf .= encode_field(1, 2, $f1_data);

// Field 4: JWT token (F4)
$protobuf .= encode_field(4, 2, $jwt);

// Field 5: RSA public client key (F5) - la clé de l'analyse
$rsa_key = "-----BEGIN PUBLIC KEY-----\nMIIBIDANBgkqhkiG9w0BAQEFAAOCAQ0AMIIBCAKCAQEAmzFbJTtUMkPFhZgCPBFY\nPE3311wYDQrEx6ANjRllSm7VeaAyKhXRLJJeQajA0uuZEslbQopWxUnfNYIrUAnB\npNS6UGLIRDvM0A8jh6MiZbxBbWj02Pwm33ysp9lBCkAWZ7kWGAHmnrHnlp+yI4/5\nF96awR9jz2+ckwrdgiqtVb0mEXqf5y/cZyFthcSqCwT2+DExbIf/HTMOBePGmZXn\nYUp+BoGuGM6l7AitJSu+GYNBghlN4NAI/hHEQx0lorVLRrIzwtFDloCEX1/wKxbT\nz68j0wUPo3s6vDwlzWIStX+MXRHV2pRW0CVOJjabrZlG1hyzsnN6DfyShfZ07z/c\nkQIBEQ==\n-----END PUBLIC KEY-----";
$protobuf .= encode_field(5, 2, $rsa_key);

// Field 8: APP ID (F8) - com.riotgames.valorant
$protobuf .= encode_field(8, 2, "com.riotgames.valorant");

// Field 9: boot = 3 (F9) - varint
$protobuf .= encode_field(9, 0, encode_varint(3));

// Field 13: Session UUID (F13) - utiliser le sid fourni
$protobuf .= encode_field(13, 2, $sid);

// Field 14: Security flags (F14)
$security_flags = "HVCI:1  TPM2:1  VBS:1  IOMMU:1  SB:1";
$protobuf .= encode_field(14, 2, $security_flags);

// Field 15: Hardware token (F15) - base64
$hardware_token = "rp58GtTaY1FnYj+vD2iH1IbBb20=";
$protobuf .= encode_field(15, 2, $hardware_token);

// Field 2: Timestamp (optionnel)
$timestamp = time();
$protobuf .= encode_field(2, 0, encode_varint($timestamp));

// Field 100: Version Windows (optionnel)
$protobuf .= encode_field(100, 2, "10.0.19045");

// Calculer la taille
$protobuf_length = strlen($protobuf);

// Vérifier que le protobuf est assez grand (doit être > 1000 bytes)
if ($protobuf_length < 1000) {
    // Ajouter du padding si nécessaire
    $padding_needed = 1500 - $protobuf_length;
    if ($padding_needed > 0) {
        $protobuf .= encode_field(999, 2, str_repeat("X", $padding_needed));
    }
}

// Générer l'enveloppe RG
// Magic "RG" + version 1 + flags
$rg_header = "RG\x01\x00";
$timestamp_bytes = pack('V', time()); // 4 bytes little-endian
$iv = random_bytes(16); // 16 bytes IV
$payload = $protobuf;
$mac = hash_hmac('sha256', $payload, 'temp_key_for_now', true); // 32 bytes HMAC-SHA256

// Construire l'enveloppe complète
$rg_envelope = $rg_header . $timestamp_bytes . $iv . $payload . $mac;

// Vérifier la taille finale
$envelope_length = strlen($rg_envelope);

// Convertir base64 standard en base64 URL-safe (pour compatibilité avec b64_url_decode)
$b64_data = base64_encode($rg_envelope);
$b64_urlsafe = strtr($b64_data, '+/', '-_'); // Remplacer + par - et / par _
$b64_urlsafe = rtrim($b64_urlsafe, '='); // Enlever le padding (optionnel)

// Solution SIMPLE : Renvoyer juste le base64 directement, sans JSON
// Le client C++ s'attend à du JSON, mais notre jstr() simple a des problèmes avec les guillemets dans le base64
// On va envoyer un format simple et compatible : {"payload":"<base64>"}

echo "{\"payload\":\"" . $b64_urlsafe . "\"}";

?>