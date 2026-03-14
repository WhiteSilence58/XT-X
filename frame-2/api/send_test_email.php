<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
Auth::requireLogin();

$user_id = Auth::getCurrentUserId();

$user = Database::fetchOne("SELECT email, display_name FROM users WHERE id = $1", [$user_id]);

if (!$user || !$user['email']) {
    echo json_encode(['success' => false, 'error' => 'Keine Email-Adresse gefunden']);
    exit;
}

// Hier Python-Script aufrufen oder direkt PHP-Mail
// Beispiel mit exec:
$python_script = __DIR__ . '/../send_test_email.py';
$command = "python3 $python_script " . escapeshellarg($user_id) . " 2>&1";
$output = shell_exec($command);

echo json_encode([
    'success' => true,
    'message' => 'Test-Email wurde versendet',
    'debug' => $output
]);