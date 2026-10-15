<?php
/**
 * Verify cleanup after Pontifex has been uninstalled in an ephemeral WP-CLI site.
 *
 * @package PontifexOI
 */

if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress runtime is required.');
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'pontifex_planning',
    $wpdb->prefix . 'pontifex_oi_registrations',
    $wpdb->prefix . 'pontifex_oi_logs',
];
foreach ($tables as $table) {
    $found = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
    );
    if ($found !== null) {
        throw new RuntimeException('Pontifex uninstall left a plugin table.');
    }
}

if (get_option('pontifex_oi_webhook_secret', null) !== null
    || get_option('pontifex_wh_state_' . hash('sha256', 'uninstall-smoke'), null) !== null
    || wp_next_scheduled('pontifex_oi_cron_fetch_planning')) {
    throw new RuntimeException('Pontifex uninstall left owned options or scheduled events.');
}

echo 'Pontifex single-site uninstall and option cleanup: OK' . PHP_EOL;
