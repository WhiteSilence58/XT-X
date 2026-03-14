<?php
// includes/config.php

// Timezone
date_default_timezone_set('Europe/Berlin');

// PostgreSQL Verbindung (auf deinem VPS)
define('DB_HOST', '217.160.20.118');
define('DB_PORT', '5432');
define('DB_NAME', 'marketdb');
define('DB_USER', 'marketuser');
define('DB_PASS', 'S!9xvP4#Lq8$Rt2mZ');

// Session Config
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Nur bei HTTPS
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);

// Error Reporting (für Development - später auf 0 setzen!)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// VPS API Configuration
// ============================================

// VPS IP (ersetze mit deiner echten IP!)
define('VPS_API_URL', 'https://kafka-frame.com/proxy.php');  // ⬅️ DEINE VPS IP HIER!

// API Key (MUSS gleich sein wie in VPS .env!)
define('VPS_API_KEY', '97d102132bf910ddce3b4afe7b9a458ad81b725bac20dc7c134a0cbc1293dd67');  // ⬅️ DEIN API_KEY!

// Encryption Key (MUSS gleich sein wie VPS .env!)
define('ENCRYPTION_KEY', '1ea1c9a6c1f125e38ce7292b783a8f606a0fe8c6f95b254e9867b9fb8916ba67');