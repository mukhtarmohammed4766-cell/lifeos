<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../database/db.php';

$userId = (int)($_SESSION['user_id'] ?? 1);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check whether notifications are enabled.
$stmt = $conn->prepare(
    "SELECT notifications FROM user_settings WHERE userid = ? LIMIT 1"
);
$stmt->bind_param("i", $userId);
$stmt->execute();

$settings = $stmt->get_result()->fetch_assoc();
$stmt->close();

$enabled = $settings ? (int)$settings['notifications'] : 1;

if (!$enabled) {
    echo json_encode([
        'success' => true,
        'enabled' => false,
        'unread' => 0,
        'notifications' => []
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'read') {
        $id = (int)($input['id'] ?? 0);

        $stmt = $conn->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE id = ? AND userid = ?"
        );
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'read_all') {

        $stmt = $conn->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE userid = ? AND is_read = 0"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);

        $stmt = $conn->prepare(
            "DELETE FROM notifications
             WHERE id = ? AND userid = ?"
        );
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        $stmt->close();

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Load notifications.
$stmt = $conn->prepare(
    "SELECT id, title, message, type, link, is_read, created_at
     FROM notifications
     WHERE userid = ?
     ORDER BY created_at DESC
     LIMIT 30"
);

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

$notifications = [];
$unread = 0;

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int)$row['id'];
    $row['is_read'] = (int)$row['is_read'];

    if (!$row['is_read']) {
        $unread++;
    }

    $notifications[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'enabled' => true,
    'unread' => $unread,
    'notifications' => $notifications
], JSON_UNESCAPED_UNICODE);