<?php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $user_id = Auth::getCurrentUserId();

    $conn = Database::connect();
    $result = pg_query_params($conn, "
        UPDATE notifications
        SET is_read = TRUE, read_at = NOW()
        WHERE user_id = $1 AND is_read = FALSE
    ", [$user_id]);

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true]);

} catch (Exception $e) {
    error_log("Mark All Read: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}