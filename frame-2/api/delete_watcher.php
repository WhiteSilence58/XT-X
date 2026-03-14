<?php
// api/delete_watcher.php - Watcher löschen
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response(['success' => false, 'error' => 'Ungültige JSON-Daten'], 400);
    }

    $watcher_id = $input['watcher_id'] ?? null;
    $user_id = Auth::getCurrentUserId();

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

    // Löschen
    $conn = Database::connect();
    $result = pg_query_params($conn, "DELETE FROM watchers WHERE id = $1", [$watcher_id]);

    if (!$result) {
        $error = pg_last_error($conn);
        error_log("Delete Watcher - DB Error: " . $error);
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    $deleted = pg_affected_rows($result);

    if ($deleted > 0) {
        json_response(['success' => true, 'message' => 'Beobachtung gelöscht']);
    } else {
        json_response(['success' => false, 'error' => 'Konnte nicht gelöscht werden'], 500);
    }

} catch (Exception $e) {
    error_log("Delete Watcher Exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}