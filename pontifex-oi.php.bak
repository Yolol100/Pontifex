<?php
declare(strict_types=1);

/**
 * Plugin Name: Pontifex OI
 * Description: Online inschrijfsysteem voor examens en cursussen
 * Version: 1.0.0
 * Author: Pontifex
 */

defined('ABSPATH') || exit;

/**
 * -----------------------------------------------------------------------------
 * CONSTANTS
 * -----------------------------------------------------------------------------
 */
define('PONTIFEX_OI_VERSION', '1.0.0');
define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));

/**
 * -----------------------------------------------------------------------------
 * BOOTSTRAP
 * Alles centraal en voorspelbaar laden
 * -----------------------------------------------------------------------------
 */
final class Pontifex_OI
{
    public static function init(): void
    {
        self::load_config();
        self::load_dependencies();
        self::register_hooks();
    }

    /**
     * Config & data (MOET als eerste)
     */
    private static function load_config(): void
    {
        require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
    }

    /**
     * Alle helpers, classes en handlers
     */
    private static function load_dependencies(): void
    {
        $includes = [
            // Helpers
            'includes/helpers/class-payment-helpers.php',
            'includes/helpers/class-mail-helpers.php',
            'includes/helpers/submissions-helpers.php',

            // Core classes
            'includes/class-registrations.php',
            'includes/class-frontend.php',
            'includes/class-admin.php',

            // Ajax / Webhooks / Cron
            'includes/ajax-soap-fetch-handler.php',
            'includes/webhook-handler.php',
            'includes/fetch-planning-cron.php',
        ];

        foreach ($includes as $file) {
            $path = PONTIFEX_OI_PATH . $file;
            if (is_readable($path)) {
                require_once $path;
            }
        }
    }

    /**
     * WordPress hooks
     */
    private static function register_hooks(): void
    {
        add_action('init', [self::class, 'register_shortcodes']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    /**
     * Shortcodes
     */
    public static function register_shortcodes(): void
    {
        add_shortcode('pontifex_registration_form', [
            \PontifexOI\Frontend::class,
            'render_registration_form',
        ]);
    }

    /**
     * Frontend JS & CSS
     */
    public static function enqueue_public_assets(): void
    {
        // CONFIG altijd eerst
        wp_enqueue_script(
            'pontifex-config',
            PONTIFEX_OI_URL . 'public/js/config.js',
            [],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_enqueue_script(
            'pontifex-utils',
            PONTIFEX_OI_URL . 'public/js/utils.js',
            ['pontifex-config'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_enqueue_script(
            'pontifex-filters',
            PONTIFEX_OI_URL . 'public/js/filters.js',
            ['pontifex-utils'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_enqueue_script(
            'pontifex-table',
            PONTIFEX_OI_URL . 'public/js/table.js',
            ['pontifex-utils'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_enqueue_style(
            'pontifex-public',
            PONTIFEX_OI_URL . 'public/css/public.css',
            [],
            PONTIFEX_OI_VERSION
        );
    }

    /**
     * Admin JS & CSS
     */
    public static function enqueue_admin_assets(): void
    {
        wp_enqueue_script(
            'pontifex-admin',
            PONTIFEX_OI_URL . 'admin/js/pontifex-oi-admin.js',
            ['jquery'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_enqueue_style(
            'pontifex-admin',
            PONTIFEX_OI_URL . 'admin/css/admin.css',
            [],
            PONTIFEX_OI_VERSION
        );
    }
}

/**
 * -----------------------------------------------------------------------------
 * START PLUGIN
 * -----------------------------------------------------------------------------
 */
Pontifex_OI::init();