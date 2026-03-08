<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';

Auth::requireLogin();
$user_id = Auth::getCurrentUserId();

// Hole einen Watcher
$watcher = Database::fetchOne("
    SELECT id, label FROM watchers WHERE user_id = $1 LIMIT 1
", [$user_id]);

if (!$watcher) {
    die("❌ Du brauchst mindestens eine Beobachtung!");
}

// Erstelle Test-Notifications
$notifications = [
    [
        'type' => 'new_deal',
        'title' => '🎉 Neuer Deal gefunden!',
        'message' => 'iPhone 15 Pro 256GB - Nur 899€ (Spart 100€ gegenüber Zielpreis)'
    ],
    [
        'type' => 'price_change',
        'title' => '📉 Preis gesunken!',
        'message' => 'MacBook Air M2: 949€ (war 1099€, 13.6% günstiger)'
    ],
    [
        'type' => 'new_deal',
        'title' => '💰 Top Angebot!',
        'message' => 'Sony WH-1000XM5 Kopfhörer - Nur 279€'
    ],
    [
        'type' => 'system',
        'title' => '⚙️ System-Info',
        'message' => 'Deine Beobachtung wurde erfolgreich aktualisiert'
    ]
];

foreach ($notifications as $notif) {
    Database::execute("
        INSERT INTO notifications (user_id, type, title, message, watcher_id, is_read, created_at)
        VALUES ($1, $2, $3, $4, $5, FALSE, NOW() - INTERVAL '1 hour' * random())
    ", [$user_id, $notif['type'], $notif['title'], $notif['message'], $watcher['id']]);
}

echo "✅ Test-Notifications erstellt!\n";
echo "<a href='alerts.php'>→ Zu den Alerts</a>";