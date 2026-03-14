<?php
// api/get_watcher.php - Watcher-Daten laden
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['error' => 'Nicht angemeldet'], 401);
    }

    $watcher_id = $_GET['id'] ?? null;
    $user_id = Auth::getCurrentUserId();

    if (!$watcher_id) {
        json_response(['error' => 'Watcher ID fehlt'], 400);
    }

    $watcher = Database::fetchOne("
        SELECT * FROM watchers
        WHERE id = $1 AND user_id = $2
    ", [$watcher_id, $user_id]);

    if (!$watcher) {
        json_response(['error' => 'Watcher nicht gefunden'], 404);
    }

    json_response($watcher);

} catch (Exception $e) {
    error_log("Get Watcher Exception: " . $e->getMessage());
    json_response(['error' => 'Serverfehler'], 500);
}