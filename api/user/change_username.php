<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];
$input = json_decode(file_get_contents("php://input"), true);
$newUsername = trim($input['username'] ?? '');

if (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
    sendResponse(400, 'Username harus 3-20 karakter');
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
    sendResponse(400, 'Username hanya boleh huruf, angka, dan underscore');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$newUsername, $user['user_id']]);
    if ($stmt->rowCount() > 0) {
        sendResponse(400, 'Username sudah digunakan');
    }

    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    $stmt->execute([$newUsername, $user['user_id']]);

    sendResponse(200, 'Username berhasil diubah', [
        'username' => $newUsername,
    ]);
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
