<?php
/**
 * Plugin Name: Pontifex OI
 * Description: Koppeling met Pontifex Open Inschrijvingen (SOAP-API).
 * Version: 1.0.1
 * Author: Andrew
 * Text Domain: pontifex-oi
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Update URI: https://github.com/Yolol100/Pontifex
 *
 * @package PontifexOI
 */

defined('ABSPATH') || exit;

if (!defined('PONTIFEX_OI_VERSION')) {
    define('PONTIFEX_OI_VERSION', '1.0.1');
    define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
    define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));
    define('PONTIFEX_OI_FILE', __FILE__);
}

// Composer dependencies are required only for Mollie/payment paths. A source
// checkout without vendor/ must still be able to bootstrap planning/admin code.
$autoload = PONTIFEX_OI_PATH . 'vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
}

require_once PONTIFEX_OI_PATH . 'includes/cron/fetch-planning-cron.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-payment-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-mail-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-pending-orders.php';

// Connect WordPress core verified personal-data request workflows to our
// custom registration table. Access is gated by WordPress's privacy UI.
add_filter(
    'wp_privacy_personal_data_exporters',
    [\PontifexOI\Helpers\Registrations::class, 'register_privacy_exporter']
);
add_filter(
    'wp_privacy_personal_data_erasers',
    [\PontifexOI\Helpers\Registrations::class, 'register_privacy_eraser']
);
add_action('admin_init', static function (): void {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }
    $text = '<p>' . esc_html__(
        'Pontifex OI bewaart inschrijf-, kandidaat- en betaalinformatie in een eigen WordPress-databasetabel. '
        . 'Een betaalstatus wordt bij Mollie gecontroleerd en bevestigingsmails worden verzonden via de mailvoorziening van de site. '
        . 'Het exporteerhulpmiddel bevat kopergegevens; overige kandidaatgegevens en wettelijke bewaartermijnen moeten afzonderlijk worden beoordeeld. '
        . 'Niet-afgehandelde betaalgegevens worden standaard na 30 dagen gewist of voor handmatige controle gemarkeerd.',
        'pontifex-oi'
    ) . '</p>';
    wp_add_privacy_policy_content('Pontifex OI', wp_kses_post($text));
});
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registration-validator.php';
require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/webhooks/webhook-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/class-frontend.php';
require_once PONTIFEX_OI_PATH . 'includes/frontend-asset-guard.php';

if (is_admin()) {
    require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';
}

add_action('plugins_loaded', function () {
    load_plugin_textdomain('pontifex-oi', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 1);

add_action('plugins_loaded', function () {
    if (is_admin() && class_exists('\\PontifexOI\\Admin\\Admin')) {
        \PontifexOI\Admin\Admin::get_instance();
    }
    if (class_exists('\\PontifexOI\\PublicPart\\Frontend')) {
        \PontifexOI\PublicPart\Frontend::get_instance();
    }
}, 20);

/**
 * Determine the next daily timestamp in local WordPress time.
 */
function pontifex_oi_next_daily_timestamp($hour = 3, $minute = 15, $second = 0) {
    if (!function_exists('wp_timezone')) {
        $tz_string = get_option('timezone_string') ?: 'UTC';
        $tz = new DateTimeZone($tz_string);
    } else {
        $tz = wp_timezone();
    }

    $now = new DateTime('now', $tz);
    $run = new DateTime('today', $tz);
    $run->setTime((int) $hour, (int) $minute, (int) $second);

    if ($run <= $now) {
        $run->modify('+1 day');
    }

    return $run->getTimestamp();
}

/**
 * Add an atomic payment-id constraint only if legacy data is conflict-free.
 * Never silently discard duplicate registrations on customer installations.
 */
function pontifex_oi_ensure_unique_registration_index(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'pontifex_oi_registrations';
    $existing = $wpdb->get_var(
        "SHOW INDEX FROM {$table} WHERE Key_name = 'pontifex_unique_order_id'"
    );
    if ($existing !== null) {
        update_option('pontifex_oi_order_index_checked', 'installed', false);
        return true;
    }
    $duplicate = $wpdb->get_var(
        "SELECT order_id FROM {$table} GROUP BY order_id HAVING COUNT(*) > 1 LIMIT 1"
    );
    if ($duplicate !== null) {
        update_option('pontifex_oi_order_index_checked', 'needs_migration', false);
        return false;
    }
    $result = $wpdb->query(
        "ALTER TABLE {$table} ADD UNIQUE KEY pontifex_unique_order_id (order_id)"
    );
    $ok = $result !== false;
    update_option('pontifex_oi_order_index_checked', $ok ? 'installed' : 'needs_review', false);
    return $ok;
}

add_action('admin_init', static function (): void {
    if (current_user_can('manage_options')
        && get_option('pontifex_oi_order_index_checked', false) === false) {
        pontifex_oi_ensure_unique_registration_index();
    }
});

/**
 * Create database tables and schedule the daily planning refresh.
 */
register_activation_hook(__FILE__, function () {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;

    $prefix = $wpdb->prefix;

    $table_planning = $prefix . 'pontifex_planning';
    $sql_planning = "CREATE TABLE {$table_planning} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        planning_identifier VARCHAR(64) NOT NULL UNIQUE,
        planning_date DATE NULL,
        planning_time TIME NULL,
        planning_start_date DATETIME NULL,
        planning_end_date DATETIME NULL,
        planning_updated DATETIME NULL,
        planning_status VARCHAR(32) NULL,
        available_seats INT UNSIGNED DEFAULT 0,
        location_identifier VARCHAR(64) NULL,
        location_name VARCHAR(191) DEFAULT '',
        location_street VARCHAR(191) DEFAULT '',
        location_number VARCHAR(32) DEFAULT '',
        location_suffix VARCHAR(32) DEFAULT '',
        location_postcode VARCHAR(32) DEFAULT '',
        location_city VARCHAR(191) DEFAULT '',
        location_province VARCHAR(191) DEFAULT '',
        location_country VARCHAR(64) DEFAULT '',
        location_seats INT UNSIGNED DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_date (planning_date),
        KEY idx_identifier (planning_identifier)
    ) {$wpdb->get_charset_collate()};";

    $table_regs = $prefix . 'pontifex_oi_registrations';
    $sql_regs = "CREATE TABLE {$table_regs} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id VARCHAR(64) NOT NULL,
        planning_identifier VARCHAR(64) NULL,
        payload LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY idx_order (order_id)
    ) {$wpdb->get_charset_collate()};";

    $table_logs = $prefix . 'pontifex_oi_logs';
    $sql_logs = "CREATE TABLE {$table_logs} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        level VARCHAR(16) NOT NULL DEFAULT 'info',
        message TEXT NOT NULL,
        context LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_level (level)
    ) {$wpdb->get_charset_collate()};";

    dbDelta($sql_planning);
    dbDelta($sql_regs);
    dbDelta($sql_logs);

    pontifex_oi_ensure_unique_registration_index();

    $hook = 'pontifex_oi_cron_fetch_planning';
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(pontifex_oi_next_daily_timestamp(3, 15, 0), 'daily', $hook);
    }

    if (!wp_next_scheduled('pontifex_oi_pending_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pontifex_oi_pending_cleanup');
    }
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('pontifex_oi_pending_cleanup');
    $hook = 'pontifex_oi_cron_fetch_planning';
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook($hook);
    } else {
        while ($ts = wp_next_scheduled($hook)) {
            wp_unschedule_event($ts, $hook);
        }
    }
    flush_rewrite_rules();
});

/**
 * Migrate an old hourly schedule to the daily schedule once.
 */
add_action('plugins_loaded', function () {
    $flag = 'pontifex_oi_cron_daily_migrated';
    if (!get_option($flag)) {
        $hook = 'pontifex_oi_cron_fetch_planning';

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook($hook);
        } else {
            while ($ts = wp_next_scheduled($hook)) {
                wp_unschedule_event($ts, $hook);
            }
        }

        wp_schedule_event(pontifex_oi_next_daily_timestamp(3, 15, 0), 'daily', $hook);
        update_option($flag, 1, true);
    }
});

// Existing installations need cleanup scheduling without reactivation.
add_action('init', static function (): void {
    if (!wp_next_scheduled('pontifex_oi_pending_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'pontifex_oi_pending_cleanup');
    }
}, 99);
