<?php
/**
 * Pontifex OI - Admin Class
 *
 * Beheert alle admin-zijde functionaliteit van de plugin:
 * - Menu's & submenu's
 * - Instellingen registratie
 * - Enqueue van assets
 * - AJAX handlers
 *
 * @package PontifexOI
 * @since   1.0.0
 */

declare(strict_types=1);

namespace PontifexOI\Admin;

use PontifexOI\Helpers\Registrations;

defined('ABSPATH') || exit;

final class Admin
{
    private static ?self $instance = null;

    /**
     * Singleton pattern - retourneert altijd dezelfde instance
     */
    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers (alleen voor ingelogde gebruikers)
        add_action('wp_ajax_pontifex_oi_fetch_soap_data', 'pontifex_oi_fetch_soap_data_handler');
        add_action('wp_ajax_pontifex_oi_fetch_submissions', [self::class, 'ajax_fetch_submissions']);
    }

    /**
     * Registreert het hoofdmenu en alle submenu's
     */
    public function register_menu(): void
    {
        add_menu_page(
            __('Pontifex OI', 'pontifex-oi'),
            __('Pontifex OI', 'pontifex-oi'),
            'manage_options',
            'pontifex_oi_main',
            [$this, 'mollie_settings_page'],
            'dashicons-awards',
            60
        );

        add_submenu_page(
            'pontifex_oi_main',
            __('Mollie & Webhook', 'pontifex-oi'),
            __('Mollie & Webhook', 'pontifex-oi'),
            'manage_options',
            'pontifex_oi_main',
            [$this, 'mollie_settings_page']
        );

        add_submenu_page(
            'pontifex_oi_main',
            __('SOAP-instellingen', 'pontifex-oi'),
            __('SOAP-instellingen', 'pontifex-oi'),
            'manage_options',
            'pontifex_oi_soap',
            [$this, 'soap_settings_page']
        );

        add_submenu_page(
            'pontifex_oi_main',
            __('Inzendingen', 'pontifex-oi'),
            __('Inzendingen', 'pontifex-oi'),
            'manage_options',
            'pontifex_oi_submissions',
            [$this, 'submissions_page']
        );
    }

    /**
     * Registreert alle plugin-instellingen met juiste sanitization
     */
    public function register_settings(): void
    {
        // Mollie groep
        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_live_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_test_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_test_mode', [
            'type'              => 'integer',
            'default'           => 0,
            'sanitize_callback' => fn($value) => in_array($value, [1, '1', true, 'true', 'on'], true) ? 1 : 0,
        ]);

        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_webhook_secret', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // SOAP groep
        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_url', [
            'sanitize_callback' => 'esc_url_raw',
        ]);

        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_user_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_company_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_hash', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    /**
     * Laadt alleen de benodigde admin styles & scripts op Pontifex pagina's
     */
    public function enqueue_admin_assets(string $hook): void
    {
        // Snelle check op relevante pagina's
        if (!str_contains($hook, 'pontifex_oi')) {
            return;
        }

        // Stijlen
        wp_enqueue_style(
            'pontifex-oi-shared',
            PONTIFEX_OI_URL . 'assets/css/pontifex-oi-shared.css',
            [],
            PONTIFEX_OI_VERSION
        );

        wp_enqueue_style(
            'pontifex-oi-admin',
            PONTIFEX_OI_URL . 'assets/css/pontifex-oi-admin.css',
            ['pontifex-oi-shared'],
            PONTIFEX_OI_VERSION
        );

        // Script + data
        if (file_exists(PONTIFEX_OI_PATH . 'assets/js/pontifex-oi-admin.js')) {
            wp_enqueue_script(
                'pontifex-oi-admin',
                PONTIFEX_OI_URL . 'assets/js/pontifex-oi-admin.js',
                ['jquery'],
                PONTIFEX_OI_VERSION,
                true
            );

            wp_localize_script('pontifex-oi-admin', 'PontifexOIAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('pontifex_oi_admin'),
            ]);

            // i18n voor JavaScript
            wp_localize_script('pontifex-oi-admin', 'POI_PRICES_DATA', [
                'i18n' => [
                    'loading'              => __('Laden...', 'pontifex-oi'),
                    'please_wait'          => __('Even geduld...', 'pontifex-oi'),
                    'no_submissions_found' => __('Geen inzendingen gevonden.', 'pontifex-oi'),
                    'try_another_search'   => __('Probeer een andere zoekopdracht.', 'pontifex-oi'),
                    'error'                => __('Fout', 'pontifex-oi'),
                    'loading_error_generic' => __('Er is een fout opgetreden bij het laden.', 'pontifex-oi'),
                    'processing'           => __('Bezig...', 'pontifex-oi'),
                    'fetch_data'           => __('Haal gegevens op', 'pontifex-oi'),
                    'fetch_success'        => __('Gegevens succesvol opgehaald en opgeslagen.', 'pontifex-oi'),
                    'fetch_failed'         => __('Ophalen mislukt. Probeer opnieuw.', 'pontifex-oi'),
                    'add'                  => __('Toevoegen', 'pontifex-oi'),
                    'remove'               => __('Verwijderen', 'pontifex-oi'),
                    'course'               => __('Cursus', 'pontifex-oi'),
                    'price'                => __('Prijs', 'pontifex-oi'),
                    'lang_exists'          => __('Taalcode bestaat al of is ongeldig', 'pontifex-oi'),
                    'need_one_lang'        => __('Minimaal één taal verplicht', 'pontifex-oi'),
                    'need_one_course'      => __('Minimaal één cursus verplicht', 'pontifex-oi'),
                    'confirm_remove_lang'  => __('Weet u zeker dat u deze taal wilt verwijderen?', 'pontifex-oi'),
                    'confirm_remove_course' => __('Weet u zeker dat u deze cursus wilt verwijderen?', 'pontifex-oi'),
                ]
            ]);
        }
    }

    public function mollie_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om deze pagina te bekijken.', 'pontifex-oi'));
        }

        require PONTIFEX_OI_PATH . 'templates/admin/page-mollie-settings.php';
    }

    public function soap_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om deze pagina te bekijken.', 'pontifex-oi'));
        }

        require PONTIFEX_OI_PATH . 'templates/admin/page-soap-settings.php';
    }

    public function submissions_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Je hebt geen rechten om deze pagina te bekijken.', 'pontifex-oi'));
        }

        require PONTIFEX_OI_PATH . 'templates/admin/page-submissions.php';
    }

    /**
     * AJAX handler voor het ophalen van inzendingen (submissions overzicht)
     */
    public static function ajax_fetch_submissions(): void
    {
        check_ajax_referer('pontifex_oi_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Onvoldoende rechten.', 'pontifex-oi')], 403);
        }

        $post = wp_unslash($_POST);

        $page     = max(1, (int)($post['paged'] ?? $post['page'] ?? 1));
        $per_page = max(1, min(200, (int)($post['per_page'] ?? 20)));
        $search   = sanitize_text_field((string)($post['s'] ?? ''));
        $orderby  = sanitize_key((string)($post['orderby'] ?? 'created_at'));
        $order    = strtoupper((string)($post['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $list = Registrations::list($page, $per_page, $search, $orderby, $order);

        if (!is_array($list)) {
            $list = [
                'rows'     => [],
                'total'    => 0,
                'page'     => 1,
                'pages'    => 1,
                'per_page' => $per_page,
            ];
        }

        if (!function_exists('pontifex_oi_flatten_payload')) {
            require_once PONTIFEX_OI_PATH . 'includes/helpers/submissions-helpers.php';
        }

        $cards = [];
        foreach (($list['rows'] ?? []) as $row) {
            $payload = json_decode((string)($row['payload'] ?? '{}'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $cards[] = pontifex_oi_flatten_payload($payload, [
                'order_id'   => (string)($row['order_id'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ]);
        }

        wp_send_json_success([
            'rows'     => $cards,
            'total'    => (int)($list['total'] ?? 0),
            'pages'    => (int)($list['pages'] ?? 1),
            'page'     => (int)($list['page'] ?? 1),
            'per_page' => (int)($list['per_page'] ?? $per_page),
        ]);
    }
}