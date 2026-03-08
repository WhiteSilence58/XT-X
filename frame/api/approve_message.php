<?php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = Auth::getCurrentUserId();
    $message_id = $input['message_id'] ?? null;

    if (!$message_id) {
        json_response(['success' => false, 'error' => 'message_id fehlt'], 400);
    }

    $ch = curl_init(VPS_API_URL . '/api/approve-message');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . VPS_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'user_id' => $user_id,
        'message_id' => $message_id
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $result = json_decode($response, true);
        json_response($result);
    } else {
        json_response(['success' => false, 'error' => 'VPS-API Fehler'], 500);
    }

} catch (Exception $e) {
    error_log("Approve Message: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}