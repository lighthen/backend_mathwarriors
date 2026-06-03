<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];

if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    sendResponse(400, 'Gagal mengunggah file');
}

$file = $_FILES['avatar'];
$maxSize = 2 * 1024 * 1024;
$allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if ($file['size'] > $maxSize) {
    sendResponse(400, 'Ukuran file maksimal 2MB');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes)) {
    sendResponse(400, 'Hanya file JPG, PNG, WebP, dan GIF yang diizinkan');
}

try {
    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg',
    };

    $filename = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $ext;
    $uploadDir = __DIR__ . '/../../uploads/avatars/';
    $destPath = $uploadDir . $filename;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $oldAvatar = null;
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$user['user_id']]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($old && $old['avatar']) {
        $oldAvatar = $old['avatar'];
    }

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        sendResponse(500, 'Gagal menyimpan file');
    }

    if ($oldAvatar && $oldAvatar !== 'default.png') {
        $oldPath = $uploadDir . $oldAvatar;
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$filename, $user['user_id']]);

    sendResponse(200, 'Foto profil berhasil diubah', [
        'avatar' => $filename,
        'avatar_url' => getAvatarUrl($filename),
    ]);
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Throwable $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}

function getAvatarUrl($filename) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return "$protocol://$host/math-warriors/backend/uploads/avatars/$filename";
}
