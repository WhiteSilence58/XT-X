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

    $platform = $input['platform'] ?? 'kleinanzeigen';
    $email = $input['email'] ?? null;
    $password = $input['password'] ?? null;

    if (!$email || !$password) {
        json_response(['success' => false, 'error' => 'Email und Passwort erforderlich'], 400);
    }

    // Verschlüssele Passwort (AES-256) - GLEICHER KEY wie VPS!
    $encryption_key = ENCRYPTION_KEY;  // Aus config.php

    // Convert hex string to binary
    $key_binary = hex2bin($encryption_key);

    if (strlen($key_binary) !== 32) {
        json_response(['success' => false, 'error' => 'Encryption key hat falsche Länge'], 500);
    }

    $iv = openssl_random_pseudo_bytes(16);
    $encrypted_password = openssl_encrypt(
        $password,
        'AES-256-CBC',
        $key_binary,
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($encrypted_password === false) {
        json_response(['success' => false, 'error' => 'Verschlüsselung fehlgeschlagen'], 500);
    }

    // IV + encrypted als Base64
    $password_encrypted = base64_encode($iv . $encrypted_password);

    // Speichere in DB
    $conn = Database::connect();
    $result = pg_query_params($conn, "
        INSERT INTO platform_credentials (
            user_id, platform, email, password_encrypted, status, created_at
        ) VALUES ($1, $2, $3, $4, 'active', NOW())
        ON CONFLICT (user_id, platform)
        DO UPDATE SET
            email = EXCLUDED.email,
            password_encrypted = EXCLUDED.password_encrypted,
            status = 'active',
            updated_at = NOW()
    ", [$user_id, $platform, $email, $password_encrypted]);

    if (!$result) {
        json_response(['success' => false, 'error' => 'Datenbankfehler'], 500);
    }

    json_response(['success' => true, 'message' => 'Zugangsdaten gespeichert']);

} catch (Exception $e) {
    error_log("Save Credentials: " . $e->getMessage());
    json_response(['success' => false, 'error' => 'Serverfehler: ' . $e->getMessage()], 500);
}