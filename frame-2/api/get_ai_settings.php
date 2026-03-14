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
    $watcher_id = $_GET['watcher_id'] ?? null;

    if (!$watcher_id) {
        json_response(['success' => false, 'error' => 'Watcher ID fehlt'], 400);
    }

    $watcher = Database::fetchOne("
        SELECT
            ai_assistant_enabled,
            ai_target_price,
            ai_auto_negotiate,
            ai_max_price_deviation
        FROM watchers
        WHERE id = $1 AND user_id = $2
    ", [$watcher_id, $user_id]);

    if (!$watcher) {
        json_response(['success' => false, 'error' => 'Watcher nicht gefunden'], 404);
    }

    json_response([
        'success' => true,
        'settings' => $watcher
    ]);

} catch (Exception $e) {
    error_log("Get AI Settings: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}