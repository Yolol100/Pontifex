<?php
/**
 * Plugin Name: Pontifex OI
 * Description: Koppeling met Pontifex Open Inschrijvingen (SOAP-API).
 * Version: 1.0.0
 * Author: [Jouw Naam]
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
require_once PONTIFEX_OI_PATH . 'includes/helpers/functions-select.php';
require_once PONTIFEX_OI_PATH . 'includes/webhooks/webhook-handler.php';
require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';

// Frontend en admin classes
require_once PONTIFEX_OI_PATH . 'includes/class-frontend.php';
require_once PONTIFEX_OI_PATH . 'public/class-payment-success-shortcode.php';
require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';

// Mollie API integratie via composer autoloader
require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

// Frontend assets alleen in frontend laden
add_action('wp_enqueue_scripts', function() {
    $version = defined('PONTIFEX_OI_VERSION') ? PONTIFEX_OI_VERSION : '1.0.0';
    $url     = defined('PONTIFEX_OI_URL')     ? PONTIFEX_OI_URL     : plugin_dir_url(__FILE__);

    // CSS
    wp_enqueue_style('pontifex-oi-shared',          $url . 'assets/css/pontifex-oi-shared.css', [], $version);
    wp_enqueue_style('pontifex-oi-planning',        $url . 'assets/css/pontifex-oi-planning.css', ['pontifex-oi-shared'], $version);
    wp_enqueue_style('pontifex-oi-registration',    $url . 'assets/css/pontifex-oi-registration.css', ['pontifex-oi-shared'], $version);
    wp_enqueue_style('pontifex-oi-payment-success', $url . 'assets/css/pontifex-oi-payment-success.css', ['pontifex-oi-shared'], $version);

    // JS
    wp_enqueue_script('pontifex-oi-config',       $url . 'assets/js/config.js', [], $version, true);
    wp_enqueue_script('pontifex-oi-utils',        $url . 'assets/js/utils.js', ['jquery'], $version, true);
    wp_enqueue_script('pontifex-oi-filters',      $url . 'assets/js/filters.js', ['pontifex-oi-config','pontifex-oi-utils'], $version, true);
    wp_enqueue_script('pontifex-oi-pagination',   $url . 'assets/js/pagination.js', ['pontifex-oi-utils'], $version, true);
    wp_enqueue_script('pontifex-oi-sidebar',      $url . 'assets/js/sidebar.js', ['pontifex-oi-filters'], $version, true);
    wp_enqueue_script('pontifex-oi-material',     $url . 'assets/js/material.js', ['pontifex-oi-utils'], $version, true);
    wp_enqueue_script('pontifex-oi-price',        $url . 'assets/js/price.js', ['pontifex-oi-material'], $version, true);
    wp_enqueue_script('pontifex-oi-table',        $url . 'assets/js/table.js', ['pontifex-oi-price'], $version, true);
    wp_enqueue_script('pontifex-oi-candidates',   $url . 'assets/js/candidates.js', ['pontifex-oi-table'], $version, true);
    wp_enqueue_script('pontifex-oi-main',         $url . 'assets/js/main.js', [
        'pontifex-oi-filters',
        'pontifex-oi-pagination',
        'pontifex-oi-sidebar',
        'pontifex-oi-material',
        'pontifex-oi-price',
        'pontifex-oi-table',
        'pontifex-oi-candidates'
    ], $version, true);
    wp_enqueue_script('pontifex-oi-validation',   $url . 'assets/js/form-validation.js', ['pontifex-oi-main'], $version, true);

    // AJAX localiseren
    wp_localize_script('pontifex-oi-main', 'PontifexOiAjax', [
        'ajax_url'         => admin_url('admin-ajax.php'),
        'registration_url' => get_permalink(2207),
    ]);
    wp_localize_script('pontifex-oi-validation', 'pontifexOiVars', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pontifex_oi_nonce'),
    ]);
});

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
    if (class_exists('\PontifexOI\Api\ApiController')) {
        \PontifexOI\Api\ApiController::get_instance();
    }
}, 20);

// --- SHORTCODE VOOR BETALINGS-SUCCES ---
add_action('init', function () {
    if (class_exists('\PontifexOI\PublicPart\PaymentSuccessShortcode')) {
        \PontifexOI\PublicPart\PaymentSuccessShortcode::register_shortcode();
    }
});

// --- ACTIVATION/DEACTIVATION HOOKS ---
register_activation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('pontifex_oi_cronjob_event');
});