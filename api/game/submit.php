<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../utils/fcm_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, 'Method Not Allowed');
}

$user = $GLOBALS['auth_user'];
$input = json_decode(file_get_contents("php://input"), true);

$score = (int)($input['score'] ?? 0);
$correctCount = (int)($input['correct_count'] ?? 0);
$wrongCount = (int)($input['wrong_count'] ?? 0);
$totalQuestions = (int)($input['total_questions'] ?? 10);

if ($totalQuestions <= 0) {
    sendResponse(400, 'Data tidak valid');
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        sendResponse(500, 'Koneksi database gagal');
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS game_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        score INT DEFAULT 0,
        correct_count INT DEFAULT 0,
        wrong_count INT DEFAULT 0,
        total_questions INT DEFAULT 10,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT points, tier FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user['user_id']]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        $conn->rollBack();
        sendResponse(404, 'User tidak ditemukan');
    }

    $oldPoints = (int)$current['points'];
    $newPoints = $oldPoints + $score;
    if ($newPoints < 0) $newPoints = 0;
    $newTier = getTierByPoints($newPoints);

    $stmt = $conn->prepare("UPDATE users SET points = ?, tier = ?, last_active = NOW() WHERE id = ?");
    $stmt->execute([$newPoints, $newTier, $user['user_id']]);

    $stmt = $conn->prepare(
        "INSERT INTO game_sessions (user_id, score, correct_count, wrong_count, total_questions) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user['user_id'], $score, $correctCount, $wrongCount, $totalQuestions]);

    $sessionId = $conn->lastInsertId();

    $conn->commit();

    $beatenPlayers = [];
    if ($newPoints > $oldPoints) {
        $stmt = $conn->prepare(
            "SELECT id, username, fcm_token, points FROM users WHERE points > ? AND points <= ? AND id != ? ORDER BY points DESC"
        );
        $stmt->execute([$oldPoints, $newPoints, $user['user_id']]);
        $beatenPlayers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($beatenPlayers as $beaten) {
            $fcmToken = $beaten['fcm_token'] ?? '';
            if (!empty($fcmToken)) {
                try {
                    FcmHelper::sendNotification(
                        $fcmToken,
                        '⚠️ Skor Terlewati!',
                        $user['username'] . ' melewati skor kamu! Ayo main lagi untuk mengambil alih posisi!',
                        [
                            'type' => 'score_beaten',
                            'beaten_by' => $user['username'],
                            'beaten_by_points' => (string)$newPoints,
                            'your_points' => (string)$beaten['points'],
                        ]
                    );
                } catch (Throwable $fcmErr) {
                    error_log('[FCM] Gagal kirim ke ' . $beaten['username'] . ': ' . $fcmErr->getMessage());
                }
            }
        }
    }

    sendResponse(200, 'Skor berhasil disimpan', [
        'session_id' => $sessionId,
        'score' => $score,
        'total_points' => $newPoints,
        'tier' => $newTier,
        'user' => [
            'id' => (int)$user['user_id'],
            'username' => $user['username'],
            'tier' => $newTier,
            'points' => $newPoints,
        ],
        'beaten' => array_map(function($b) {
            return ['username' => $b['username'], 'points' => (int)$b['points']];
        }, $beatenPlayers),
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    sendResponse(500, 'Database error: ' . $e->getMessage());
}
