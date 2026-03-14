<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
Auth::requireLogin();

$user_id = Auth::getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);

try {
    // Update user table
    Database::execute("
        UPDATE users SET
            notify_new_deals = $1,
            notify_price_drops = $2,
            notify_price_increases = $3,
            notify_listing_gone = $4,
            notify_daily_summary = $5
        WHERE id = $6
    ", [
        $data['notify_new_deals'] ?? false,
        $data['notify_price_drops'] ?? false,
        $data['notify_price_increases'] ?? false,
        $data['notify_listing_gone'] ?? false,
        $data['notify_daily_summary'] ?? false,
        $user_id
    ]);

    // Optional: Update legacy notification_settings if exists
    if (isset($data['price_trigger']) || isset($data['cooldown_minutes'])) {
        $check = Database::fetchOne("SELECT id FROM notification_settings WHERE user_id = $1", [$user_id]);

        if ($check) {
            Database::execute("
                UPDATE notification_settings SET
                    email_price_trigger = $1,
                    email_cooldown_minutes = $2
                WHERE user_id = $3
            ", [
                $data['price_trigger'] ?? null,
                $data['cooldown_minutes'] ?? 30,
                $user_id
            ]);
        } else {
            Database::execute("
                INSERT INTO notification_settings (user_id, email_price_trigger, email_cooldown_minutes)
                VALUES ($1, $2, $3)
            ", [
                $user_id,
                $data['price_trigger'] ?? null,
                $data['cooldown_minutes'] ?? 30
            ]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}