<?php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = Auth::getCurrentUserId();
    $notification_id = $input['notification_id'] ?? null;

    if (!$notification_id) {
        json_response(['success' => false, 'error' => 'notification_id fehlt'], 400);
    }

    $conn = Database::connect();
    $result = pg_query_params($conn, "
        UPDATE notifications
        SET is_read = TRUE, read_at = NOW()
        WHERE id = $1 AND user_id = $2
    ", [$notification_id, $user_id]);

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true]);

} catch (Exception $e) {
    error_log("Mark Notification Read: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}