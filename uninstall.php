<?php
/**
 * Permanent cleanup for Pontifex OI (only on WordPress plugin uninstall).
 *
 * Uninstall is destructive by design. Deactivation preserves registrations.
 *
 * @package PontifexOI
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete Pontifex-owned data for the currently selected WordPress site.
 *
 * @return void
 */
function pontifex_oi_uninstall_site(): void {
    global $wpdb;

    wp_clear_scheduled_hook('pontifex_oi_cron_fetch_planning');
    wp_clear_scheduled_hook('pontifex_oi_pending_cleanup');

    $options = [
        'pontifex_oi_settings',
        'pontifex_oi_api_cache',
        'pontifex_oi_soap_url',
        'pontifex_oi_soap_user_id',
        'pontifex_oi_soap_hash',
        'pontifex_oi_soap_company_id',
        'pontifex_oi_mollie_live_api_key',
        'pontifex_oi_mollie_test_api_key',
        'pontifex_oi_mollie_test_mode',
        'pontifex_oi_webhook_secret',
        'pontifex_oi_registration_page_id',
        'pontifex_oi_cron_daily_migrated',
    ];
    foreach ($options as $key) {
        delete_option($key);
    }

    // Remove idempotency flags and locks plus pending-order/cache transients.
    // Keep the prefixes scoped to Pontifex; do not delete unrelated options.
    $prefixes = [
        'pontifex_pending_',
        'pontifex_review_',
        'pontifex_wh_state_',
        'pontifex_wh_lock_',
        '_transient_pontifex_order_data_for_token_',
        '_transient_timeout_pontifex_order_data_for_token_',
        '_transient_mollie_payment_id_for_token_',
        '_transient_timeout_mollie_payment_id_for_token_',
        '_transient_pontifex_oi_',
        '_transient_timeout_pontifex_oi_',
    ];
    foreach ($prefixes as $prefix) {
        $like = $wpdb->esc_like($prefix) . '%';
        $keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
        foreach ($keys ?: [] as $key) {
            delete_option($key);
        }
    }

    delete_metadata('user', 0, 'pontifex_oi_field', '', true);
    delete_metadata('post', 0, 'pontifex_oi_linked_planning', '', true);

    // These are exactly the three fixed tables created by this plugin.
    foreach ([
        $wpdb->prefix . 'pontifex_oi_registrations',
        $wpdb->prefix . 'pontifex_oi_logs',
        $wpdb->prefix . 'pontifex_planning',
    ] as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        try {
            pontifex_oi_uninstall_site();
        } finally {
            restore_current_blog();
        }
    }
} else {
    pontifex_oi_uninstall_site();
}
