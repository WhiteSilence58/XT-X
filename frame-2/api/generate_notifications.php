<?php
// api/generate_notifications.php
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

    $input      = json_decode(file_get_contents('php://input'), true) ?? [];
    $watcher_id = isset($input['watcher_id'])
        ? (int)$input['watcher_id']
        : (isset($_GET['watcher_id']) ? (int)$_GET['watcher_id'] : null);

    if ($watcher_id) {
        NotificationHelper::generateForWatcher($watcher_id, $user_id);
    } else {
        NotificationHelper::generateForAllWatchers($user_id);
    }

    json_response(['success' => true, 'message' => 'Benachrichtigungen generiert']);

} catch (Exception $e) {
    error_log('generate_notifications error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Fehler beim Generieren der Benachrichtigungen'], 500);
}
