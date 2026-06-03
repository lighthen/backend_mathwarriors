<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/response.php';
require_once __DIR__ . '/utils/jwt_helper.php';

handleCORS();

$url = $_GET['url'] ?? '';
$url = trim($url, '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    sendResponse(200, 'OK');
}

$publicRoutes = [
    'api/test'               => __DIR__ . '/api/test.php',
    'api/auth/login'         => __DIR__ . '/api/auth/login.php',
    'api/auth/register'      => __DIR__ . '/api/auth/register.php',
    'api/auth/google_login'  => __DIR__ . '/api/auth/google_login.php',
    'api/auth/refresh'       => __DIR__ . '/api/auth/refresh.php',
];

$protectedRoutes = [
    'api/game/submit'            => __DIR__ . '/api/game/submit.php',
    'api/user/profile'           => __DIR__ . '/api/user/profile.php',
    'api/user/change-password'   => __DIR__ . '/api/user/change_password.php',
    'api/user/change-username'   => __DIR__ . '/api/user/change_username.php',
    'api/user/upload-avatar'     => __DIR__ . '/api/user/upload_avatar.php',
    'api/user/delete-account'    => __DIR__ . '/api/user/delete_account.php',
    'api/user/update-fcm-token'  => __DIR__ . '/api/user/update_fcm_token.php',
    'api/leaderboard'            => __DIR__ . '/api/leaderboard.php',
];

if (isset($publicRoutes[$url])) {
    require $publicRoutes[$url];
    exit;
}

if (isset($protectedRoutes[$url])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (empty($authHeader)) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    }

    if (empty($authHeader) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';
    }

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        sendResponse(401, 'Token tidak ditemukan');
    }

    $user = verifyJWT($matches[1]);
    if (!$user) {
        sendResponse(401, 'Token tidak valid atau sudah kadaluwarsa');
    }

    $GLOBALS['auth_user'] = $user;
    require $protectedRoutes[$url];
    exit;
}

sendResponse(404, 'Endpoint tidak ditemukan');

function handleCORS() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
