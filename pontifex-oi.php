<?php
/**
 * Plugin Name: Pontifex OI
 * Description: Koppeling met Pontifex Open Inschrijvingen (SOAP-API).
 * Version: 1.0.0
 * Author: Andrew
 * Text Domain: pontifex-oi
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package PontifexOI
 */

// Prevent direct access to the file.
defined('ABSPATH') || exit;

// --- CONSTANTS ---
if (!defined('PONTIFEX_OI_VERSION')) {
    define('PONTIFEX_OI_VERSION', '1.0.0');
    define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
    define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));
    define('PONTIFEX_OI_FILE', __FILE__);
}

// --- AUTOLOAD/INCLUDES ---
// Mollie API integration via composer autoloader.
$pontifex_autoload = PONTIFEX_OI_PATH . 'vendor/autoload.php';
if (file_exists($pontifex_autoload)) {
    require_once $pontifex_autoload;
} else {
    add_action('admin_notices', static function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Pontifex OI: vendor/autoload.php ontbreekt. Draai "composer install" of lever de vendor-map mee in de pluginrelease.', 'pontifex-oi')
            . '</p></div>';
    });
}

require_once PONTIFEX_OI_PATH . 'includes/helpers/class-payment-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-mail-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/cron/fetch-planning-cron.php';
require_once PONTIFEX_OI_PATH . 'includes/webhooks/webhook-handler.php';

// Frontend class.
require_once PONTIFEX_OI_PATH . 'includes/class-frontend.php';

// Admin class needs to be loaded if it's an admin request, including AJAX.
if (is_admin()) {
    require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';
}

// --- TRANSLATIONS ---
add_action('plugins_loaded', function () {
    load_plugin_textdomain('pontifex-oi', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 1);

// --- BOOTSTRAP CLASSES ---
add_action('plugins_loaded', function () {
    if (is_admin() && class_exists('\PontifexOI\Admin\Admin')) {
        \PontifexOI\Admin\Admin::get_instance();
    }
    if (class_exists('\PontifexOI\PublicPart\Frontend')) {
        \PontifexOI\PublicPart\Frontend::get_instance();
    }
}, 20);


/**
 * Helper function to determine the next daily timestamp in local WP time.
 * Example: 03:15 a.m. (silent) - adjust as desired.
 */
function pontifex_oi_next_daily_timestamp($hour = 3, $minute = 15, $second = 0) {
    if (!function_exists('wp_timezone')) {
        // Fallback for very old WP versions
        $tz_string = get_option('timezone_string') ?: 'UTC';
        $tz = new DateTimeZone($tz_string);
    } else {
        $tz = wp_timezone();
    }
    $now = new DateTime('now', $tz);
    $run = new DateTime('today', $tz);
    $run->setTime((int)$hour, (int)$minute, (int)$second);

    if ($run <= $now) {
        $run->modify('+1 day');
    }
    return $run->getTimestamp();
}

/**
 * ACTIVATION: create DB tables (dbDelta) and schedule the CRON job once a day.
 */
register_activation_hook(__FILE__, function () {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;

    $prefix = $wpdb->prefix;

    // === Tables ===
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
        UNIQUE KEY uq_order (order_id)
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

    // === CRON: once per day, starting with the next 03:15 a.m. local time ===
    $hook = 'pontifex_oi_cron_fetch_planning';
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(pontifex_oi_next_daily_timestamp(3, 15, 0), 'daily', $hook);
    }

    flush_rewrite_rules();
});

/**
 * DEACTIVATION: clean up CRON.
 */
register_deactivation_hook(__FILE__, function () {
    $hook = 'pontifex_oi_cron_fetch_planning';
    // Remove all scheduled instances of this hook
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook($hook);
    } else {
        while ($ts = wp_next_scheduled($hook)) {
            wp_unschedule_event($ts, $hook);
        }
    }
});

/**
 * MIGRATION: if an old 'hourly' schedule was running, reschedule it to 'daily'.
 * This runs once and sets a flag in the options.
 */
add_action('plugins_loaded', function () {
    $flag = 'pontifex_oi_cron_daily_migrated';
    if (!get_option($flag)) {
        $hook = 'pontifex_oi_cron_fetch_planning';

        // Remove all old schedules
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook($hook);
        } else {
            while ($ts = wp_next_scheduled($hook)) {
                wp_unschedule_event($ts, $hook);
            }
        }

        // New 'daily' schedule starting from the next 03:15 a.m. local time
        wp_schedule_event(pontifex_oi_next_daily_timestamp(3, 15, 0), 'daily', $hook);

        update_option($flag, 1, true);
    }
});
