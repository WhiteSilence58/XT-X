<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "🔄 Running migration: Add email notification columns...\n\n";

$queries = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_new_deals BOOLEAN DEFAULT TRUE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_price_drops BOOLEAN DEFAULT TRUE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_price_increases BOOLEAN DEFAULT FALSE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_listing_gone BOOLEAN DEFAULT FALSE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS notify_daily_summary BOOLEAN DEFAULT FALSE",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE",
    "UPDATE users SET
        notify_new_deals = COALESCE(notify_new_deals, TRUE),
        notify_price_drops = COALESCE(notify_price_drops, TRUE),
        notify_price_increases = COALESCE(notify_price_increases, FALSE),
        notify_listing_gone = COALESCE(notify_listing_gone, FALSE),
        notify_daily_summary = COALESCE(notify_daily_summary, FALSE),
        email_verified = COALESCE(email_verified, FALSE)"
];

foreach ($queries as $i => $query) {
    try {
        Database::execute($query);
        echo "✅ Query " . ($i + 1) . " executed successfully\n";
    } catch (Exception $e) {
        echo "❌ Query " . ($i + 1) . " failed: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Migration completed!\n";
echo "\n🔍 Checking columns...\n";

// Verify columns exist
$result = Database::fetchAll("
    SELECT column_name, data_type, column_default
    FROM information_schema.columns
    WHERE table_name = 'users'
    AND column_name LIKE 'notify_%' OR column_name = 'email_verified'
    ORDER BY column_name
");

if ($result) {
    echo "\nExisting columns:\n";
    foreach ($result as $col) {
        echo "  - {$col['column_name']} ({$col['data_type']}) = {$col['column_default']}\n";
    }
} else {
    echo "⚠️ Could not verify columns\n";
}

// Show current user settings
$user_settings = Database::fetchAll("
    SELECT id, email, notify_new_deals, notify_price_drops, email_verified
    FROM users
    LIMIT 5
");

echo "\n👤 User settings:\n";
foreach ($user_settings as $u) {
    echo "  User {$u['id']} ({$u['email']}): ";
    echo "new_deals=" . ($u['notify_new_deals'] ? 'true' : 'false') . ", ";
    echo "price_drops=" . ($u['notify_price_drops'] ? 'true' : 'false') . ", ";
    echo "verified=" . ($u['email_verified'] ? 'true' : 'false') . "\n";
}

echo "\n✅ Done! You can now use the settings page.\n";