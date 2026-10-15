<?php
/**
 * Persistent, bounded pending-order data for delayed Mollie callbacks.
 *
 * @package PontifexOI
 */
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class PendingOrders {
    private static function key(string $token): string {
        return preg_match('/^pontifex_order_[a-f0-9]{32}$/D', $token)
            ? 'pontifex_pending_' . hash('sha256', $token)
            : '';
    }

    /**
     * Store PII only inside the local WordPress database, never Mollie metadata.
     * The 30-day default is a technical upper bound, not a legal retention policy.
     */
    public static function create(string $token, array $order): bool {
        $key = self::key($token);
        if ($key === '') {
            return false;
        }
        return add_option($key, [
            'created_at' => time(),
            'payment_id' => '',
            'order' => $order,
        ], '', false);
    }

    public static function set_payment_id(string $token, string $payment_id): bool {
        if (!preg_match('/^tr_[A-Za-z0-9_-]{4,76}$/D', $payment_id)) {
            return false;
        }
        $key = self::key($token);
        $record = $key ? get_option($key) : false;
        if (!is_array($record) || empty($record['order'])) {
            return false;
        }
        $record['payment_id'] = $payment_id;
        return update_option($key, $record, false)
            || get_option($key, false) === $record;
    }

    public static function get_order(string $token, string $payment_id): ?array {
        $key = self::key($token);
        $record = $key ? get_option($key) : false;
        if (!is_array($record) || !is_array($record['order'] ?? null)) {
            return null;
        }
        if (($record['payment_id'] ?? '') !== $payment_id) {
            return null; // Bind the original buyer payload to the verified provider payment.
        }
        return $record['order'];
    }

    public static function payment_id(string $token): string {
        $key = self::key($token);
        $record = $key ? get_option($key) : false;
        return is_array($record) ? (string) ($record['payment_id'] ?? '') : '';
    }

    /** Remove sensitive order details after both registration and notifications. */
    public static function mark_complete(string $token): void {
        $key = self::key($token);
        $record = $key ? get_option($key) : false;
        if (is_array($record)) {
            unset($record['order']);
            $record['completed_at'] = time();
            update_option($key, $record, false);
        }
    }

    public static function discard(string $token): void {
        $key = self::key($token);
        if ($key !== '') {
            delete_option($key);
        }
    }

    /**
     * On expiry, erase pending PII. Keep an anonymous review marker for
     * unresolved payments; callbacks arriving later need human reconciliation.
     */
    public static function cleanup_expired(): void {
        global $wpdb;
        $days = min(365, max(7, (int) apply_filters('pontifex_oi_pending_retention_days', 30)));
        $cutoff = time() - $days * DAY_IN_SECONDS;
        $like = $wpdb->esc_like('pontifex_pending_') . '%';
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT 100",
                $like
            )
        );
        foreach ($keys ?: [] as $key) {
            $record = get_option($key);
            if (!is_array($record) || (int) ($record['created_at'] ?? time()) > $cutoff) {
                continue;
            }
            if (!empty($record['order']) && !empty($record['payment_id'])) {
                add_option(
                    'pontifex_review_' . substr($key, strlen('pontifex_pending_')),
                    ['payment_id' => $record['payment_id'], 'reason' => 'pending_expired', 'created_at' => time()],
                    '',
                    false
                );
            }
            delete_option($key);
        }
    }
}

add_action('pontifex_oi_pending_cleanup', [PendingOrders::class, 'cleanup_expired']);
