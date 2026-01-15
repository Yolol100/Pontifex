<?php
/**
 * Plugin Name: Pontifex OI
 * Description: Koppeling met Pontifex Open Inschrijvingen (SOAP-API).
 * Version: 1.0.0
 * Author: Andrew
 * Text Domain: pontifex-oi
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 *
 * @package PontifexOI
 */

declare(strict_types=1);

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

// 1. Vendor (Mollie API e.d.)
if (file_exists(PONTIFEX_OI_PATH . 'vendor/autoload.php')) {
    require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';
}

// 2. Config & Core (CRUCIAAL: Prijzen eerst laden!)
// We gaan ervan uit dat je het bestand in includes/config/ hebt gezet, of in de root.
// Pas het pad aan indien nodig. Hieronder de veilige check.
if (file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
    require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
} elseif (file_exists(PONTIFEX_OI_PATH . 'producten-prijzen.php')) {
    require_once PONTIFEX_OI_PATH . 'producten-prijzen.php';
}

// 3. Helpers & API
require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-payment-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-mail-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/submissions-helpers.php'; // Nieuwe helper class

// 4. Handlers (AJAX/Webhook/Cron)
require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/webhooks/webhook-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/cron/fetch-planning-cron.php';

// 5. Frontend & Admin
require_once PONTIFEX_OI_PATH . 'includes/class-frontend.php';

if (is_admin()) {
    require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';
}

// --- TRANSLATIONS ---
add_action('plugins_loaded', static function () {
    load_plugin_textdomain('pontifex-oi', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 1);

// --- BOOTSTRAP CLASSES ---
add_action('plugins_loaded', static function () {
    if (is_admin() && class_exists(\PontifexOI\Admin\Admin::class)) {
        \PontifexOI\Admin\Admin::get_instance();
    }
    if (class_exists(\PontifexOI\PublicPart\Frontend::class)) {
        \PontifexOI\PublicPart\Frontend::get_instance();
    }
}, 20);

// --- ACTIVATION HOOK ---
register_activation_hook(__FILE__, static function () {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    // 1. Planning Tabel
    // Let op: 'location_postcode' gewijzigd naar 'location_zip_code' om te matchen met SoapClient
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
        location_zip_code VARCHAR(32) DEFAULT '', 
        location_city VARCHAR(191) DEFAULT '',
        location_province VARCHAR(191) DEFAULT '',
        location_country VARCHAR(64) DEFAULT '',
        location_seats INT UNSIGNED DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_date (planning_date),
        KEY idx_identifier (planning_identifier)
    ) {$charset_collate};";

    // 2. Registraties Tabel
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
    ) {$charset_collate};";

    // (Logs tabel verwijderd conform nieuwe standaarden)

    dbDelta($sql_planning);
    dbDelta($sql_regs);

    // 3. CRON Schedule
    $hook = 'pontifex_oi_cron_fetch_planning';
    if (!wp_next_scheduled($hook)) {
        wp_schedule_event(pontifex_oi_next_daily_timestamp(3, 15, 0), 'daily', $hook);
    }

    flush_rewrite_rules();
});

// --- DEACTIVATION HOOK ---
register_deactivation_hook(__FILE__, static function () {
    $hook = 'pontifex_oi_cron_fetch_planning';
    wp_clear_scheduled_hook($hook);
    flush_rewrite_rules();
});

/**
 * Helper function to determine the next daily timestamp in local WP time.
 */
function pontifex_oi_next_daily_timestamp(int $hour = 3, int $minute = 15, int $second = 0): int 
{
    $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
    
    $now = new \DateTime('now', $tz);
    $run = new \DateTime('today', $tz);
    $run->setTime($hour, $minute, $second);

    if ($run <= $now) {
        $run->modify('+1 day');
    }
    return $run->getTimestamp();
}