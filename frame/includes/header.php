<?php
// includes/header.php
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Kafka-Frame');
}
if (!defined('SHOW_ADD_BUTTON')) {
    define('SHOW_ADD_BUTTON', false);
}

// User-Info laden
if (!isset($user_id)) {
    $user_id = Auth::getCurrentUserId();
}
if (!isset($user)) {
    $user = Database::fetchOne("SELECT display_name, email FROM users WHERE id = $1", [$user_id]);
}

$display_name = $user['display_name'] ?? 'User';
$user_email = $user['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="de" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="theme-color" content="#dde2ec">
<title><?= PAGE_TITLE ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:ital,opsz,wght@0,6..12,300;0,6..12,400;0,6..12,500;0,6..12,600;0,6..12,700;1,6..12,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body>

<div class="bg-scene">
  <div class="orb orb-a"></div>
  <div class="orb orb-b"></div>
</div>

<?php include __DIR__ . '/sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <button class="menu-btn" onclick="openSidebar()">☰</button>
    <h1 class="page-title"><?= PAGE_TITLE ?></h1>
    <div class="search-bar">
      <span style="opacity:0.4;font-size:14px">🔍</span>
      <input type="text" placeholder="Suchen…">
    </div>
    <?php if (SHOW_ADD_BUTTON): ?>
    <button class="topbar-btn" onclick="showModal()">+ Neuer Watch</button>
    <?php endif; ?>
    <button class="theme-btn" onclick="toggleTheme()" id="theme-btn">🌙</button>
  </div>