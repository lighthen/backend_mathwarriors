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
    $email = $data['email'] ?? '';

    if (strlen($username) < 3 || strlen($username) > 20) {
        sendResponse(400, 'Username harus 3-20 karakter');
    }

    if (strlen($password) < 6) {
        sendResponse(400, 'Password minimal 6 karakter');
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        sendResponse(400, 'Username hanya boleh huruf, angka, dan underscore');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->rowCount() > 0) {
        sendResponse(400, 'Username sudah digunakan');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hashedPassword, $email]);

    $userId = $conn->lastInsertId();
    $token = generateJWT($userId, $username, 'Pemula');

    sendResponse(201, 'Registrasi berhasil', [
        'token' => $token,
        'user' => [
            'id' => $userId,
            'username' => $username,
            'tier' => 'Pemula',
            'points' => 0
        ]
    ]);

} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
