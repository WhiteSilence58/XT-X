<?php
require_once '_common.php';
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

try {
    if (!Auth::isLoggedIn()) {
        json_response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
    }

    $user_id = Auth::getCurrentUserId();

    // VPS API aufrufen
    $vps_api_url = VPS_API_URL . '/api/test-login';
    $api_key = VPS_API_KEY;

    $ch = curl_init($vps_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'user_id' => $user_id
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        json_response([
            'success' => false,
            'error' => 'VPS-Verbindung fehlgeschlagen: ' . $curl_error
        ], 500);
    }

    if ($http_code === 200) {
        $result = json_decode($response, true);
        json_response($result);
    } else {
        json_response([
            'success' => false,
            'error' => 'VPS-API Fehler (HTTP ' . $http_code . ')',
            'response' => $response,
            'url' => $vps_api_url,
            'key_length' => strlen($api_key)
        ], 500);
    }

} catch (Exception $e) {
    error_log("Test Login Exception: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler'], 500);
}