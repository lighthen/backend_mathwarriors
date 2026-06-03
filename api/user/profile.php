<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT id, username, email, avatar, tier, points, last_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$user['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        sendResponse(404, 'User tidak ditemukan');
    }

    $stmt = $conn->prepare("SELECT COUNT(*) as total_games, SUM(score) as total_score FROM game_sessions WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT id, score, correct_count, wrong_count, total_questions, created_at FROM game_sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['user_id']]);
    $recentGames = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $games = [];
    foreach ($recentGames as $g) {
        $games[] = [
            'id' => (int)$g['id'],
            'score' => (int)$g['score'],
            'correct_count' => (int)$g['correct_count'],
            'wrong_count' => (int)$g['wrong_count'],
            'total_questions' => (int)$g['total_questions'],
            'created_at' => $g['created_at'],
        ];
    }

    sendResponse(200, 'Profil user', [
        'user' => [
            'id' => (int)$userData['id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'avatar' => $userData['avatar'],
            'tier' => $userData['tier'],
            'points' => (int)$userData['points'],
            'last_active' => $userData['last_active'],
            'created_at' => $userData['created_at'],
        ],
        'stats' => [
            'total_games' => (int)($stats['total_games'] ?? 0),
            'total_score' => (int)($stats['total_score'] ?? 0),
        ],
        'recent_games' => $games,
    ]);
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
