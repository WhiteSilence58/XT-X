<?php
// api/_common.php - Gemeinsame API-Konfiguration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/www/frame/logs/api_errors.log');

// Output Buffer starten
ob_start();

// Headers setzen
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Shutdown Handler für Fehlerbehandlung
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Serverfehler',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

// Helper-Funktion für saubere JSON-Response
function json_response($data, $http_code = 200) {
    ob_end_clean();
    http_response_code($http_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}