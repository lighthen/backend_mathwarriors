<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/jwt_helper.php';

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(200, 'OK');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        sendResponse(400, 'Format data tidak valid');
    }

    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        sendResponse(400, 'Username dan password wajib diisi');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT id, username, password, tier, points FROM users WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->rowCount() === 0) {
        sendResponse(401, 'Username atau password salah');
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!password_verify($password, $user['password'])) {
        sendResponse(401, 'Username atau password salah');
    }

    $token = generateJWT($user['id'], $user['username'], $user['tier']);

    $updateStmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    sendResponse(200, 'Login berhasil', [
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'tier' => $user['tier'],
            'points' => $user['points']
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
