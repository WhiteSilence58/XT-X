<?php
// api/run_watcher.php - Watcher manuell starten
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $raw_input = file_get_contents('php://input');
    error_log("Run Watcher - Raw Input: " . $raw_input);

    $input = json_decode($raw_input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        json_response([
            'success' => false,
            'error' => 'Ungültige JSON-Daten',
            'json_error' => json_last_error_msg()
        ], 400);
    }

    $watcher_id = $input['watcher_id'] ?? null;
    $user_id = Auth::getCurrentUserId();

    error_log("Run Watcher - Watcher ID: $watcher_id, User ID: $user_id");

    if (!$watcher_id) {
        json_response(['success' => false, 'error' => 'Watcher ID fehlt'], 400);
    }

    // Prüfen ob Watcher dem User gehört
    $watcher = Database::fetchOne("
        SELECT id, status, label FROM watchers
        WHERE id = $1 AND user_id = $2
    ", [$watcher_id, $user_id]);

    error_log("Run Watcher - Watcher gefunden: " . ($watcher ? 'Ja' : 'Nein'));

    if (!$watcher) {
        json_response([
            'success' => false,
            'error' => 'Watcher nicht gefunden'
        ], 404);
    }

    if ($watcher['status'] !== 'active') {
        json_response([
            'success' => false,
            'error' => 'Watcher ist pausiert'
        ], 400);
    }

    // Next run auf JETZT setzen
    $conn = Database::connect();
    $result = pg_query_params($conn, "
        UPDATE watchers
        SET next_run_at = NOW()
        WHERE id = $1
        RETURNING id
    ", [$watcher_id]);

    if (!$result) {
        $error = pg_last_error($conn);
        error_log("Run Watcher - DB Error: " . $error);
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    $updated_row = pg_fetch_assoc($result);

    if ($updated_row) {
        json_response([
            'success' => true,
            'message' => 'Watcher wird in den nächsten 2 Minuten ausgeführt'
        ]);
    } else {
        json_response(['success' => false, 'error' => 'Konnte nicht aktualisiert werden'], 500);
    }

} catch (Exception $e) {
    error_log("Run Watcher Exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler: ' . $e->getMessage()], 500);
}