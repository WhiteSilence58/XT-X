<?php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $user_id = Auth::getCurrentUserId();

    // Optional: Nur für bestimmten Watcher
    $input = json_decode(file_get_contents('php://input'), true);
    $watcher_id = $input['watcher_id'] ?? null;

    // Update next_run_at auf NOW für sofortigen Run
    $conn = Database::connect();

    if ($watcher_id) {
        // Nur einen Watcher
        $result = pg_query_params($conn, "
            UPDATE watchers
            SET next_run_at = NOW()
            WHERE id = $1 AND user_id = $2
        ", [$watcher_id, $user_id]);
    } else {
        // Alle Watcher des Users
        $result = pg_query_params($conn, "
            UPDATE watchers
            SET next_run_at = NOW()
            WHERE user_id = $1 AND status = 'active'
        ", [$user_id]);
    }

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    // Wake-up Signal Datei erstellen
    $signal_success = false;
    $signal_file    = '/tmp/scraper_wakeup_signal';

    try {
        file_put_contents($signal_file, time());
        $signal_success = true;
    } catch (Exception $e) {
        error_log('Signal file error: ' . $e->getMessage());
    }

    // Benachrichtigungen für bereits vorhandene Daten generieren
    if ($watcher_id) {
        NotificationHelper::generateForWatcher((int)$watcher_id, $user_id);
    } else {
        NotificationHelper::generateForAllWatchers($user_id);
    }

    json_response([
        'success'        => true,
        'message'        => 'Scraper wird gestartet...',
        'signal_created' => $signal_success,
    ]);

} catch (Exception $e) {
    error_log('trigger_scraper error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}
