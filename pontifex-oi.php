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

defined('ABSPATH') || exit;

// --- CONSTANTEN ---
if (!defined('PONTIFEX_OI_VERSION')) {
    define('PONTIFEX_OI_VERSION', '1.0.0');
    define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
    define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));
}

// --- AUTOLOAD/INCLUDES ---
// Helpers en API
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-payment-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/class-mail-helpers.php';
require_once PONTIFEX_OI_PATH . 'includes/webhooks/webhook-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';

// Frontend en admin classes
require_once PONTIFEX_OI_PATH . 'includes/class-frontend.php';
require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';

// Mollie API integratie via composer autoloader
require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

// --- VERTALINGEN ---
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

// --- ACTIVATION/DEACTIVATION HOOKS ---
register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});