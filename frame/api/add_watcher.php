<?php
// api/add_watcher.php - Neuen Watcher erstellen
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
    $query = trim($input['query'] ?? '');
    $url = trim($input['url'] ?? '');
    $platform = 'kleinanzeigen';
    $min_price = !empty($input['min_price']) ? floatval($input['min_price']) : null;
    $max_price = !empty($input['max_price']) ? floatval($input['max_price']) : null;
    $interval = intval($input['interval'] ?? 10);
    $location = trim($input['location'] ?? '') ?: null;
    $radius_km = !empty($input['radius_km']) ? intval($input['radius_km']) : null;

    if (empty($query) && empty($url)) {
        json_response(['success' => false, 'error' => 'Suchbegriff oder URL erforderlich'], 400);
    }

    $type = !empty($url) ? 'url' : 'keyword';

    if (!empty($url)) {
        $label = "Kleinanzeigen Link";
    } else {
        $label = $query;
        if ($min_price && $max_price) {
            $label .= " (" . number_format($min_price, 0, ',', '.') . "-" . number_format($max_price, 0, ',', '.') . "€)";
        } elseif ($max_price) {
            $label .= " (≤ " . number_format($max_price, 0, ',', '.') . "€)";
        } elseif ($min_price) {
            $label .= " (≥ " . number_format($min_price, 0, ',', '.') . "€)";
        }
    }

    $result = Database::fetchOne("
        INSERT INTO watchers (
            user_id, label, type, platform, query_text, url,
            min_price, max_price, interval_minutes, location_text,
            radius_km, status, next_run_at
        ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, 'active', NOW()
        )
        RETURNING id
    ", [
        $user_id, $label, $type, $platform, $query ?: null, $url ?: null,
        $min_price, $max_price, $interval, $location, $radius_km
    ]);

    if ($result && isset($result['id'])) {
        json_response([
            'success' => true,
            'watcher_id' => $result['id'],
            'message' => 'Beobachtung erstellt'
        ]);
    } else {
        json_response(['success' => false, 'error' => 'Konnte nicht erstellt werden'], 500);
    }

} catch (Exception $e) {
    error_log("Add Watcher Exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}