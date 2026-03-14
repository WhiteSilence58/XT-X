<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
Auth::requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$watcher_id = $data['watcher_id'] ?? null;

if (!$watcher_id) {
    echo json_encode(['success' => false, 'error' => 'Watcher ID fehlt']);
    exit;
}

$user_id = Auth::getCurrentUserId();

// Prüfe ob Watcher dem User gehört
$watcher = Database::fetchOne(
    "SELECT * FROM watchers WHERE id = $1 AND user_id = $2",
    [$watcher_id, $user_id]
);

if (!$watcher) {
    echo json_encode(['success' => false, 'error' => 'Watcher nicht gefunden']);
    exit;
}

// WICHTIG: Signal-File erstellen für Scheduler
$signal_file = '/tmp/scraper_wakeup_signal';
file_put_contents($signal_file, time());

// Update next_run_at auf JETZT
Database::execute(
    "UPDATE watchers SET next_run_at = NOW() WHERE id = $1",
    [$watcher_id]
);

echo json_encode([
    'success' => true,
    'message' => 'Watcher wird gestartet...'
]);