<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    if (!Auth::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht angemeldet']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = Auth::getCurrentUserId();
    $notification_id = $input['notification_id'] ?? null;

    if (!$notification_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'notification_id fehlt']);
        exit;
    }

    // Verify ownership and delete in one query
    $result = Database::execute("
        DELETE FROM notifications
        WHERE id = $1 AND user_id = $2
        RETURNING id
    ", [$notification_id, $user_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Notification gelöscht']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Notification nicht gefunden']);
    }

} catch (Exception $e) {
    error_log("Delete Notification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Serverfehler: ' . $e->getMessage()]);
}