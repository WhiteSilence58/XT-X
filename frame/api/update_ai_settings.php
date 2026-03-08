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

    $watcher_id = $input['watcher_id'] ?? null;
    $ai_enabled = $input['ai_enabled'] ?? false;
    $ai_target_price = !empty($input['ai_target_price']) ? floatval($input['ai_target_price']) : null;
    $ai_auto_negotiate = $input['ai_auto_negotiate'] ?? false;
    $ai_max_deviation = floatval($input['ai_max_deviation'] ?? 20.0);

    if (!$watcher_id) {
        json_response(['success' => false, 'error' => 'Watcher ID fehlt'], 400);
    }

    // Prüfen ob Watcher dem User gehört
    $watcher = Database::fetchOne("
        SELECT id FROM watchers
        WHERE id = $1 AND user_id = $2
    ", [$watcher_id, $user_id]);

    if (!$watcher) {
        json_response(['success' => false, 'error' => 'Watcher nicht gefunden'], 404);
    }

    // Update
    $conn = Database::connect();
    $result = pg_query_params($conn, "
        UPDATE watchers SET
            ai_assistant_enabled = $1,
            ai_target_price = $2,
            ai_auto_negotiate = $3,
            ai_max_price_deviation = $4,
            updated_at = NOW()
        WHERE id = $5
    ", [
        $ai_enabled ? 't' : 'f',
        $ai_target_price,
        $ai_auto_negotiate ? 't' : 'f',
        $ai_max_deviation,
        $watcher_id
    ]);

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true, 'message' => 'Einstellungen gespeichert']);

} catch (Exception $e) {
    error_log("Update AI Settings: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}