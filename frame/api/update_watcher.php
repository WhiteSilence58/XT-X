<?php
// api/update_watcher.php - Watcher bearbeiten
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

    $user_id = Auth::getCurrentUserId();

    $watcher_id = $input['watcher_id'] ?? null;
    $label = trim($input['label'] ?? '');
    $min_price = !empty($input['min_price']) ? floatval($input['min_price']) : null;
    $max_price = !empty($input['max_price']) ? floatval($input['max_price']) : null;
    $location = trim($input['location'] ?? '') ?: null;
    $radius_km = !empty($input['radius_km']) ? intval($input['radius_km']) : null;
    $interval = intval($input['interval'] ?? 10);

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

    // Aktualisieren
    $conn = Database::connect();
    $result = pg_query_params($conn, "
        UPDATE watchers SET
            label = $1,
            min_price = $2,
            max_price = $3,
            location_text = $4,
            radius_km = $5,
            interval_minutes = $6,
            updated_at = NOW()
        WHERE id = $7
    ", [$label, $min_price, $max_price, $location, $radius_km, $interval, $watcher_id]);

    if (!$result) {
        $error = pg_last_error($conn);
        error_log("Update Watcher - DB Error: " . $error);
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true, 'message' => 'Beobachtung aktualisiert']);

} catch (Exception $e) {
    error_log("Update Watcher Exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}