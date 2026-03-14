<?php
// api/get_price_history.php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['error' => 'Nicht angemeldet'], 401);
    }

    $listing_id = $_GET['listing_id'] ?? null;
    $user_id = Auth::getCurrentUserId();

    if (!$listing_id) {
        json_response(['error' => 'Listing ID fehlt'], 400);
    }

    // Prüfen ob Listing dem User gehört
    $listing = Database::fetchOne("
        SELECT l.id, l.title, w.user_id
        FROM listings l
        JOIN watchers w ON w.id = l.watcher_id
        WHERE l.id = $1 AND w.user_id = $2
    ", [$listing_id, $user_id]);

    if (!$listing) {
        json_response(['error' => 'Listing nicht gefunden'], 404);
    }

    // Preisverlauf laden
    $history = Database::fetchAll("
        SELECT price, recorded_at
        FROM price_history
        WHERE listing_id = $1
        ORDER BY recorded_at ASC
    ", [$listing_id]);

    json_response([
        'title' => $listing['title'],
        'history' => $history
    ]);

} catch (Exception $e) {
    error_log("Price History Error: " . $e->getMessage());
    json_response(['error' => 'Serverfehler'], 500);
}