<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];
$input = json_decode(file_get_contents("php://input"), true);
$password = $input['password'] ?? '';

if (empty($password)) {
    sendResponse(400, 'Password wajib diisi');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        sendResponse(404, 'User tidak ditemukan');
    }

    if ($row['password'] && !password_verify($password, $row['password'])) {
        sendResponse(401, 'Password salah');
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("DELETE FROM game_sessions WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user['user_id']]);

    $conn->commit();

    sendResponse(200, 'Akun berhasil dihapus');
} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
