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
    $platform = $input['platform'] ?? 'kleinanzeigen';

    $conn = Database::connect();
    $result = pg_query_params($conn, "
        DELETE FROM platform_credentials
        WHERE user_id = $1 AND platform = $2
    ", [$user_id, $platform]);

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true, 'message' => 'Zugangsdaten gelöscht']);

} catch (Exception $e) {
    error_log("Delete Credentials: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}