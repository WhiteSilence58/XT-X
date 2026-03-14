<?php
// includes/notifications.php

class NotificationHelper {

    /** Maximum number of notifications to generate per batch per type */
    private const MAX_PER_BATCH = 50;

    /** Maximum character length for the notification title */
    private const MAX_TITLE_LENGTH = 50;

    /**
     * Generate notifications for all active watchers of a user.
     * Safe to call multiple times – won't create duplicate notifications.
     */
    public static function generateForAllWatchers($user_id) {
        $watchers = Database::fetchAll(
            "SELECT id FROM watchers WHERE user_id = $1 AND status = 'active'",
            [$user_id]
        );

        foreach ($watchers as $watcher) {
            self::generateForWatcher((int)$watcher['id'], $user_id);
        }
    }

    /**
     * Generate notifications for a specific watcher.
     * Checks for new listings (new_deal) and price drops (price_change).
     *
     * @param int $watcher_id
     * @param int $user_id
     * @return bool  false if watcher not found / not owned by user
     */
    public static function generateForWatcher($watcher_id, $user_id) {
        $watcher = Database::fetchOne(
            "SELECT * FROM watchers WHERE id = $1 AND user_id = $2",
            [$watcher_id, $user_id]
        );

        if (!$watcher) {
            return false;
        }

        self::generateNewDealNotifications($watcher, $user_id);
        self::generatePriceChangeNotifications($watcher, $user_id);

        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Create a 'new_deal' notification for every active listing of the watcher
     * that does not yet have one.
     */
    private static function generateNewDealNotifications(array $watcher, $user_id) {
        // Only listings without an existing new_deal notification
        $listings = Database::fetchAll("
            SELECT l.*
            FROM listings l
            WHERE l.watcher_id = $1
              AND l.status = 'active'
              AND NOT EXISTS (
                  SELECT 1
                  FROM notifications n
                  WHERE n.listing_id = l.id
                    AND n.user_id    = $2
                    AND n.type       = 'new_deal'
              )
            ORDER BY l.first_seen_at DESC
            LIMIT " . self::MAX_PER_BATCH . "
        ", [$watcher['id'], $user_id]);

        foreach ($listings as $listing) {
            // Skip listings above the watcher's max price (if set)
            if ($watcher['max_price'] !== null
                && $listing['price'] !== null
                && (float)$listing['price'] > (float)$watcher['max_price']
            ) {
                continue;
            }

            $price_str = $listing['price'] !== null
                ? number_format((float)$listing['price'], 0, ',', '.') . '€'
                : 'Preis unbekannt';

            $title   = '🎉 Neuer Deal: ' . mb_substr($listing['title'], 0, self::MAX_TITLE_LENGTH, 'UTF-8');
            $message = $listing['title'] . ' für ' . $price_str;

            if ($watcher['max_price'] !== null
                && $listing['price'] !== null
                && (float)$listing['price'] < (float)$watcher['max_price']
            ) {
                $savings  = (float)$watcher['max_price'] - (float)$listing['price'];
                $message .= ' (Spart ' . number_format($savings, 0, ',', '.') . '€ gegenüber Zielpreis)';
            }

            Database::execute("
                INSERT INTO notifications
                    (user_id, watcher_id, listing_id, type, title, message, is_read, created_at)
                VALUES ($1, $2, $3, 'new_deal', $4, $5, FALSE, NOW())
            ", [$user_id, $watcher['id'], $listing['id'], $title, $message]);
        }
    }

    /**
     * Create a 'price_change' notification for every price drop in price_history
     * that does not yet have a matching notification within a 24-hour window.
     */
    private static function generatePriceChangeNotifications(array $watcher, $user_id) {
        // Use a window function to find rows where the price is lower than the
        // immediately preceding entry for the same listing.
        $price_drops = Database::fetchAll("
            WITH ordered_prices AS (
                SELECT
                    lph.listing_id,
                    lph.price,
                    lph.recorded_at,
                    LAG(lph.price) OVER (
                        PARTITION BY lph.listing_id
                        ORDER BY lph.recorded_at
                    ) AS prev_price
                FROM price_history lph
                JOIN listings l ON l.id = lph.listing_id
                WHERE l.watcher_id = $1
            )
            SELECT
                op.listing_id,
                op.price      AS new_price,
                op.prev_price,
                op.recorded_at,
                l.title
            FROM ordered_prices op
            JOIN listings l ON l.id = op.listing_id
            WHERE op.prev_price IS NOT NULL
              AND op.price < op.prev_price
              AND NOT EXISTS (
                  SELECT 1
                  FROM notifications n
                  WHERE n.listing_id = op.listing_id
                    AND n.user_id    = $2
                    AND n.type       = 'price_change'
                    AND ABS(EXTRACT(EPOCH FROM (n.created_at - op.recorded_at))) < 86400
              )
            ORDER BY op.recorded_at DESC
            LIMIT " . self::MAX_PER_BATCH . "
        ", [$watcher['id'], $user_id]);

        foreach ($price_drops as $drop) {
            $prev  = (float)$drop['prev_price'];
            $new   = (float)$drop['new_price'];
            $diff  = $prev - $new;
            $percent = $prev > 0 ? ($diff / $prev * 100) : 0;

            $title   = '📉 Preis gesunken: ' . mb_substr($drop['title'], 0, self::MAX_TITLE_LENGTH, 'UTF-8');
            $message = $drop['title']
                . ': ' . number_format($new, 0, ',', '.') . '€'
                . ' (war ' . number_format($prev, 0, ',', '.') . '€'
                . ', ' . number_format($percent, 1, ',', '.') . '% günstiger)';

            // Use the price-change timestamp so the notification appears at the
            // correct time in the activity feed.
            Database::execute("
                INSERT INTO notifications
                    (user_id, watcher_id, listing_id, type, title, message, is_read, created_at)
                VALUES ($1, $2, $3, 'price_change', $4, $5, FALSE, $6)
            ", [
                $user_id,
                $watcher['id'],
                $drop['listing_id'],
                $title,
                $message,
                $drop['recorded_at'],
            ]);
        }
    }
}
