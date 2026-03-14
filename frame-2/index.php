<?php
// index.php
require_once 'includes/config.php';
require_once 'includes/db.php';      // Auch hier
require_once 'includes/auth.php';

// Wenn eingeloggt -> Dashboard, sonst -> Auth
if (Auth::isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: auth.php');
}
exit;