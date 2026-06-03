<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../config/constants.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, 'Method Not Allowed');
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $stmt = $conn->prepare("
        SELECT id, username, avatar, tier, points 
        FROM users 
        ORDER BY points DESC, last_active DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $result = [];
    $rank = $offset + 1;
    foreach ($leaderboard as $row) {
        $result[] = [
            'rank' => $rank++,
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'avatar' => $row['avatar'],
            'tier' => $row['tier'],
            'points' => (int)$row['points'],
        ];
    }

    sendResponse(200, 'Leaderboard', [
        'leaderboard' => $result,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
} catch (PDOException $e) {
    sendResponse(500, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    sendResponse(500, 'Server error: ' . $e->getMessage());
}
