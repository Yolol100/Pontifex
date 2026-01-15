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
//  CONSTANTS – altijd als eerste
// -----------------------------------------------------------------------------
define('PONTIFEX_OI_VERSION', '1.0.0');
define('PONTIFEX_OI_PATH', plugin_dir_path(__FILE__));
define('PONTIFEX_OI_URL', plugin_dir_url(__FILE__));
define('PONTIFEX_OI_BASENAME', plugin_basename(__FILE__));

// -----------------------------------------------------------------------------
//  Autoloader (optioneel – maar sterk aanbevolen voor toekomst)
// -----------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'PontifexOI\\')) {
        return;
    }

    $path = str_replace(['PontifexOI\\', '\\'], ['', '/'], $class);
    $file = PONTIFEX_OI_PATH . 'includes/' . strtolower($path) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// -----------------------------------------------------------------------------
//  Centrale plugin class
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
     * Laadt configuratiebestanden die altijd nodig zijn
     */
    private function load_config(): void
    {
        require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
    }

    /**
     * Laadt alle benodigde classes, helpers en handlers
     */
    private function load_dependencies(): void
    {
        $files = [
            // Helpers
            'includes/helpers/class-payment-helpers.php',
            'includes/helpers/class-mail-helpers.php',
            'includes/helpers/submissions-helpers.php',

            // Core
            'includes/class-registrations.php',
            'includes/class-frontend.php',
            'includes/class-admin.php',

            // AJAX / Webhooks / Cron
            'includes/ajax-soap-fetch-handler.php',
            'includes/webhook-handler.php',
            'includes/fetch-planning-cron.php',
        ];

        foreach ($files as $file) {
            $path = PONTIFEX_OI_PATH . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Registreert alle WordPress hooks op het juiste moment
     */
    private function register_hooks(): void
    {
        // Start alle componenten pas als plugins geladen zijn
        add_action('plugins_loaded', static function (): void {
            // Admin gedeelte (menu, instellingen, AJAX)
            \PontifexOI\Admin\Admin::get_instance();

            // Frontend (shortcodes, planning, inschrijven)
            \PontifexOI\PublicPart\Frontend::get_instance();
        });

        // Assets alleen op de juiste plekken
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    /**
     * Laadt publieke assets (frontend)
     */
    public static function enqueue_public_assets(): void
    {
        // Alleen op pagina's waar nodig (bijv. via shortcode detectie)
        if (!has_shortcode(get_the_content(), 'pontifex_oi_planning') &&
            !has_shortcode(get_the_content(), 'pontifex_oi_registration')) {
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
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pontifex_oi_public'),
        ]);
    }

    /**
     * Laadt admin assets (alleen op Pontifex pagina's)
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
//  START DE PLUGIN
// -----------------------------------------------------------------------------
Pontifex_OI::get_instance();