<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/jwt_helper.php';
require_once __DIR__ . '/../../config/constants.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(200, 'OK');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$data = json_decode(file_get_contents("php://input"), true);
$idToken = $data['id_token'] ?? '';

if (empty($idToken)) {
    sendResponse(400, 'ID Token diperlukan');
}

try {
    $payload = verifyGoogleToken($idToken);
    if (!$payload) {
        sendResponse(401, 'Token Google tidak valid');
    }

    $googleId = $payload['sub'];
    $email = $payload['email'];
    $name = $payload['name'];
    $picture = $payload['picture'] ?? 'default.png';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Cek user sudah ada berdasarkan google_id
    $stmt = $conn->prepare("SELECT id, username, tier, points FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    
    if ($stmt->rowCount() > 0) {
        // User sudah ada - login
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $token = generateJWT($user['id'], $user['username'], $user['tier']);
        
        $updateStmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        sendResponse(200, 'Login Google berhasil', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'tier' => $user['tier'],
                'points' => $user['points']
            ]
        ]);
        return;
    }
    
    // Fallback: cek berdasarkan email (user mungkin daftar manual sebelumnya)
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id, username, tier, points FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $updateStmt = $conn->prepare("UPDATE users SET google_id = ?, last_active = NOW() WHERE id = ?");
            $updateStmt->execute([$googleId, $user['id']]);
            
            $token = generateJWT($user['id'], $user['username'], $user['tier']);
            
            sendResponse(200, 'Login Google berhasil', [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'tier' => $user['tier'],
                    'points' => $user['points']
                ]
            ]);
            return;
        }
    }
    
    // User baru - register
    $username = generateUniqueUsername($conn, $name);
    $stmt = $conn->prepare("INSERT INTO users (google_id, username, email, avatar) VALUES (?, ?, ?, ?)");
    $stmt->execute([$googleId, $username, $email, $picture]);
    
    $userId = $conn->lastInsertId();
    $token = generateJWT($userId, $username, 'Pemula');
    
    sendResponse(201, 'Registrasi Google berhasil', [
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'tier' => 'Pemula',
            'points' => 0
        ]
    ]);
    
} catch(Exception $e) {
    sendResponse(500, 'Server Error: ' . $e->getMessage());
}

function verifyGoogleToken($idToken) {
    $tokenParts = explode('.', $idToken);
    if (count($tokenParts) !== 3) return null;

    $header = json_decode(base64UrlDecode($tokenParts[0]), true);
    $payload = json_decode(base64UrlDecode($tokenParts[1]), true);
    $signature = base64UrlDecode($tokenParts[2]);

    if (!$header || !$payload) return null;
    if (($header['alg'] ?? '') !== 'RS256') return null;

    if (($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
    if (($payload['iss'] ?? '') !== 'https://accounts.google.com' && ($payload['iss'] ?? '') !== 'accounts.google.com') return null;
    if (($payload['exp'] ?? 0) < time()) return null;
    if (empty($payload['sub'])) return null;

    $certsData = @file_get_contents('https://www.googleapis.com/oauth2/v3/certs');
    if (!$certsData) return null;
    $certs = json_decode($certsData, true);
    if (empty($certs['keys'])) return null;

    $kid = $header['kid'] ?? '';
    $publicKey = null;
    foreach ($certs['keys'] as $key) {
        if (($key['kid'] ?? '') === $kid) {
            $publicKey = certToPem($key);
            break;
        }
    }
    if (!$publicKey) return null;

    $dataToVerify = $tokenParts[0] . '.' . $tokenParts[1];
    $ok = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA256);
    if (!$ok) return null;

    return $payload;
}

function certToPem($key) {
    $n = base64UrlDecode($key['n']);
    $e = base64UrlDecode($key['e']);

    if (ord($n[0]) & 0x80) {
        $n = "\x00" . $n;
    }

    $modulus = "\x02" . encodeLength(strlen($n)) . $n;
    $exponent = "\x02" . encodeLength(strlen($e)) . $e;
    $publicKey = "\x30" . encodeLength(strlen($modulus) + strlen($exponent)) . $modulus . $exponent;

    return "-----BEGIN RSA PUBLIC KEY-----\n" . chunk_split(base64_encode($publicKey), 64, "\n") . "-----END RSA PUBLIC KEY-----\n";
}

function encodeLength($len) {
    if ($len < 128) return chr($len);
    $bytes = [];
    while ($len > 0) {
        array_unshift($bytes, chr($len & 0xFF));
        $len >>= 8;
    }
    return chr(0x80 | count($bytes)) . implode('', $bytes);
}

function generateUniqueUsername($conn, $name) {
    $base = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $base = strtolower(substr($base, 0, 15));

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $username = $base . rand(1000, 99999);
        $username = substr($username, 0, 20);

        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) {
            return $username;
        }
    }

    return substr($base . time(), 0, 20);
}
?>