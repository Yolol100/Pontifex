<?php
/**
 * Plugin Name:       Pontifex OI
 * Plugin URI:        https://certipro.nl/pontifex-oi
 * Description:       Online inschrijfsysteem voor examens en cursussen (VCA, VOL, etc.)
 * Version:           1.0.0
 * Author:            Pontifex / CertiPro
 * Author URI:        https://certipro.nl
 * Text Domain:       pontifex-oi
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// CONSTANTS – altijd als eerste
// -----------------------------------------------------------------------------
define('PONTIFEX_OI_VERSION', '1.0.0');
define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));
define('PONTIFEX_OI_BASENAME', plugin_basename(__FILE__));

// -----------------------------------------------------------------------------
// HOOFDPLUGIN CLASS
// -----------------------------------------------------------------------------
final class Pontifex_OI
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->load_config();
        $this->load_dependencies();
        $this->register_hooks();
    }

    /**
     * Configuratie (MOET altijd als eerste)
     */
    private function load_config(): void
    {
        require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
    }

    /**
     * ALLE benodigde bestanden expliciet laden
     * (geen onbetrouwbare autoloading)
     */
    private function load_dependencies(): void
    {
        $files = [
            // API / extern
            'includes/api/class-soap-client.php',

            // Helpers
            'includes/helpers/class-payment-helpers.php',
            'includes/helpers/class-mail-helpers.php',
            'includes/helpers/submissions-helpers.php',

            // Core
            'includes/class-registrations.php',
            'includes/class-admin.php',
            'includes/class-frontend.php',

            // AJAX / Webhooks / Cron
            'includes/ajax-soap-fetch-handler.php',
            'includes/webhook-handler.php',
            'includes/fetch-planning-cron.php',
        ];

        foreach ($files as $file) {
            $path = PONTIFEX_OI_PATH . $file;
            if (is_readable($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Hooks pas registreren als alles geladen is
     */
    private function register_hooks(): void
    {
        add_action('plugins_loaded', static function (): void {

            // ADMIN
            if (class_exists(\PontifexOI\Admin\Admin::class)) {
                \PontifexOI\Admin\Admin::get_instance();
            }

            // FRONTEND
            if (class_exists(\PontifexOI\PublicPart\Frontend::class)) {
                \PontifexOI\PublicPart\Frontend::get_instance();
            }
        });

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    /**
     * Frontend assets – alleen laden als nodig
     */
    public static function enqueue_public_assets(): void
    {
        global $post;

        if (
            !is_a($post, 'WP_Post') ||
            (
                !has_shortcode($post->post_content, 'pontifex_oi_planning') &&
                !has_shortcode($post->post_content, 'pontifex_oi_registration') &&
                !has_shortcode($post->post_content, 'pontifex_oi_payment_success')
            )
        ) {
            return;
        }

        wp_enqueue_style(
            'pontifex-oi-public',
            PONTIFEX_OI_URL . 'public/css/public.css',
            [],
            PONTIFEX_OI_VERSION
        );

        wp_enqueue_script(
            'pontifex-oi-public',
            PONTIFEX_OI_URL . 'public/js/public.js',
            ['jquery'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_localize_script('pontifex-oi-public', 'PontifexOI', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pontifex_oi_public'),
        ]);
    }

    /**
     * Admin assets – alleen op Pontifex pagina’s
     */
    public static function enqueue_admin_assets(string $hook): void
    {
        if (!str_contains($hook, 'pontifex_oi')) {
            return;
        }

        wp_enqueue_style(
            'pontifex-oi-admin',
            PONTIFEX_OI_URL . 'admin/css/admin.css',
            [],
            PONTIFEX_OI_VERSION
        );

        wp_enqueue_script(
            'pontifex-oi-admin',
            PONTIFEX_OI_URL . 'admin/js/pontifex-oi-admin.js',
            ['jquery'],
            PONTIFEX_OI_VERSION,
            true
        );

        wp_localize_script('pontifex-oi-admin', 'PontifexOIAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pontifex_oi_admin'),
        ]);
    }
}

// -----------------------------------------------------------------------------
// START PLUGIN
// -----------------------------------------------------------------------------
Pontifex_OI::get_instance();