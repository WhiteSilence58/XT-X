<?php
// includes/auth.php

class Auth {

    public static function register($email, $password, $display_name = null) {
        // Validierung
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Ungültige E-Mail-Adresse'];
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'error' => 'Passwort muss mindestens ' . PASSWORD_MIN_LENGTH . ' Zeichen haben'];
        }

        // Prüfen ob Email bereits existiert
        $existing = Database::fetchOne(
            "SELECT id FROM users WHERE email = $1",
            [$email]
        );

        if ($existing) {
            return ['success' => false, 'error' => 'E-Mail-Adresse bereits registriert'];
        }

        // Passwort hashen
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // User erstellen
        $result = Database::fetchOne(
            "INSERT INTO users (email, password_hash, display_name, email_verified)
             VALUES ($1, $2, $3, FALSE)
             RETURNING id, email, display_name",
            [$email, $password_hash, $display_name]
        );

        if ($result) {
            return [
                'success' => true,
                'user' => $result
            ];
        }

        return ['success' => false, 'error' => 'Registrierung fehlgeschlagen'];
    }

    public static function login($email, $password) {
        $user = Database::fetchOne(
            "SELECT id, email, password_hash, display_name, email_verified
             FROM users WHERE email = $1",
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Ungültige Anmeldedaten'];
        }

        // Session erstellen
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['logged_in'] = true;

        // Last login aktualisieren
        Database::query(
            "UPDATE users SET last_login_at = NOW() WHERE id = $1",
            [$user['id']]
        );

        return ['success' => true, 'user' => $user];
    }

    public static function logout() {
        $_SESSION = [];
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: auth.php');
            exit;
        }
    }

    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }

    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) &&
               hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
}