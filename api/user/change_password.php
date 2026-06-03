<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];
$input = json_decode(file_get_contents("php://input"), true);

$oldPassword = $input['old_password'] ?? '';
$newPassword = $input['new_password'] ?? '';

if (empty($oldPassword) || empty($newPassword)) {
    sendResponse(400, 'Password lama dan baru wajib diisi');
}

if (strlen($newPassword) < 6) {
    sendResponse(400, 'Password baru minimal 6 karakter');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? AND google_id IS NULL");
    $stmt->execute([$user['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(400, 'Akun Google tidak bisa ganti password');
    }

    if (!password_verify($oldPassword, $row['password'])) {
        sendResponse(401, 'Password lama salah');
    }

    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $user['user_id']]);

    sendResponse(200, 'Password berhasil diubah');
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
