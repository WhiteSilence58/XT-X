<?php
// api/toggle_watcher.php - Pausieren/Fortsetzen (VEREINFACHT)
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
    $status = $input['status'] ?? null;
    $user_id = Auth::getCurrentUserId();

    if (!$watcher_id) {
        json_response(['success' => false, 'error' => 'Watcher ID fehlt'], 400);
    }

    if (!in_array($status, ['active', 'paused'])) {
        json_response(['success' => false, 'error' => 'Status muss active oder paused sein'], 400);
    }

    // Prüfen ob Watcher dem User gehört
    $watcher = Database::fetchOne("
        SELECT id FROM watchers
        WHERE id = $1 AND user_id = $2
    ", [$watcher_id, $user_id]);

    if (!$watcher) {
        json_response(['success' => false, 'error' => 'Watcher nicht gefunden'], 404);
    }

    // Zwei separate Queries statt CASE
    $conn = Database::connect();

    if ($status === 'active') {
        // Fortsetzen
        $sql = "UPDATE watchers SET status = 'active', next_run_at = NOW() WHERE id = $1";
    } else {
        // Pausieren
        $sql = "UPDATE watchers SET status = 'paused' WHERE id = $1";
    }

    $result = pg_query_params($conn, $sql, [$watcher_id]);

    if (!$result) {
        $db_error = pg_last_error($conn);
        error_log("Toggle Watcher DB Error: " . $db_error);
        json_response([
            'success' => false,
            'error' => 'Datenbankfehler',
            'details' => $db_error
        ], 500);
    }

    $affected = pg_affected_rows($result);

    if ($affected > 0) {
        $message = ($status === 'active')
            ? '✅ Beobachtung fortgesetzt'
            : '⏸️ Beobachtung pausiert';

        json_response([
            'success' => true,
            'message' => $message,
            'new_status' => $status
        ]);
    } else {
        json_response([
            'success' => false,
            'error' => 'Keine Änderung vorgenommen'
        ], 500);
    }

} catch (Exception $e) {
    error_log("Toggle Watcher Exception: " . $e->getMessage() . "\nStack: " . $e->getTraceAsString());
    json_response([
        'success' => false,
        'error' => 'Serverfehler',
        'message' => $e->getMessage()
    ], 500);
}