<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];
$input = json_decode(file_get_contents("php://input"), true);

$fcmToken = $input['fcm_token'] ?? '';

if (empty($fcmToken)) {
    sendResponse(400, 'fcm_token is required');
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        sendResponse(500, 'Koneksi database gagal');
    }

    $stmt = $conn->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
    $stmt->execute([$fcmToken, $user['user_id']]);

    sendResponse(200, 'FCM token berhasil disimpan');
} catch (Throwable $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
}
