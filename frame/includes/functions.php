<?php
// includes/functions.php

class DashboardHelper {

    public static function getStats($user_id) {
        $stats = Database::fetchOne("
            SELECT
                COUNT(DISTINCT w.id) as active_watchers,
                COUNT(DISTINCT l.id) FILTER (WHERE l.status = 'active') as total_listings,
                COUNT(DISTINCT l.id) FILTER (
                    WHERE l.status = 'active'
                    AND w.max_price IS NOT NULL
                    AND l.price <= w.max_price
                ) as alerts_count,
                COALESCE(SUM(
                    CASE
                        WHEN w.max_price IS NOT NULL AND l.price <= w.max_price
                        THEN w.max_price - l.price
                        ELSE 0
                    END
                ), 0) as potential_savings
            FROM watchers w
            LEFT JOIN listings l ON l.watcher_id = w.id
            WHERE w.user_id = $1
        ", [$user_id]);

        // AI Score Durchschnitt
        $ai_score = Database::fetchOne("
            SELECT ROUND(AVG(ai.deal_score)) as avg_score
            FROM listing_ai_scores ai
            JOIN listings l ON l.id = ai.listing_id
            JOIN watchers w ON w.id = l.watcher_id
            WHERE w.user_id = $1
        ", [$user_id]);

        return [
            'active_watchers' => (int)($stats['active_watchers'] ?? 0),
            'total_listings' => (int)($stats['total_listings'] ?? 0),
            'alerts_count' => (int)($stats['alerts_count'] ?? 0),
            'potential_savings' => (float)($stats['potential_savings'] ?? 0),
            'avg_ai_score' => (int)($ai_score['avg_score'] ?? 0)
        ];
    }

    public static function getWatchers($user_id, $limit = 5) {
        return Database::fetchAll("
            SELECT
                w.*,
                COUNT(l.id) FILTER (WHERE l.status = 'active') as active_listings,
                MIN(l.price) as current_min_price,
                MAX(l.first_seen_at) as last_new_listing
            FROM watchers w
            LEFT JOIN listings l ON l.watcher_id = w.id
            WHERE w.user_id = $1 AND w.status = 'active'
            GROUP BY w.id
            ORDER BY w.created_at DESC
            LIMIT $2
        ", [$user_id, $limit]);
    }

    public static function getNotifications($user_id, $limit = 5, $unread_only = false) {
        $sql = "
            SELECT n.*, w.label as watcher_label
            FROM notifications n
            LEFT JOIN watchers w ON w.id = n.watcher_id
            WHERE n.user_id = $1
        ";

        if ($unread_only) {
            $sql .= " AND n.is_read = FALSE";
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT $2";

        return Database::fetchAll($sql, [$user_id, $limit]);
    }

    public static function formatPrice($amount, $currency = 'EUR') {
        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        $symbol = $symbols[$currency] ?? $currency;
        return number_format($amount, 0, ',', '.') . ' ' . $symbol;
    }

    public static function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $diff = time() - $time;

        if ($diff < 60) return 'vor ' . $diff . ' Sek';
        if ($diff < 3600) return 'vor ' . floor($diff / 60) . ' Min';
        if ($diff < 86400) return 'vor ' . floor($diff / 3600) . ' Std';
        if ($diff < 604800) return 'vor ' . floor($diff / 86400) . ' Tagen';

        return date('d.m.Y', $time);
    }
}