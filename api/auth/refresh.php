<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/jwt_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$input = json_decode(file_get_contents("php://input"), true);
$token = $input['token'] ?? '';

if (empty($token)) {
    sendResponse(400, 'Token diperlukan');
}

$userData = verifyJWT($token);
if (!$userData) {
    sendResponse(401, 'Token tidak valid atau sudah kadaluwarsa');
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("SELECT id, username, tier, points FROM users WHERE id = ?");
$stmt->execute([$userData['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    sendResponse(404, 'User tidak ditemukan');
}

$newToken = generateJWT($user['id'], $user['username'], $user['tier']);

$updateStmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
$updateStmt->execute([$user['id']]);

sendResponse(200, 'Token berhasil diperbarui', [
    'token' => $newToken,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'tier' => $user['tier'],
        'points' => (int)$user['points'],
    ]
]);
