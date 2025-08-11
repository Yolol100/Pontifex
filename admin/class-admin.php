<?php
namespace PontifexOI\Admin;

defined('ABSPATH') || exit;

require_once PONTIFEX_OI_PATH . 'includes/helpers/ajax-soap-fetch-handler.php';

class Admin {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Robuuster screen-check
        $is_pontifex = false;

        if (strpos($hook, 'pontifex_oi') !== false) {
            $is_pontifex = true;
        } else {
            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                if ($screen && (
                    str_contains($screen->id, 'pontifex_oi') ||
                    in_array($screen->id, [
                        'toplevel_page_pontifex_oi_main',
                        'pontifex_oi_main_page_pontifex_oi_soap',
                    ], true)
                )) {
                    $is_pontifex = true;
                }
            }
        }

        if (!$is_pontifex) {
            return;
        }

        // 1) Shared variabelen eerst (gebruikt door admin.css)
        wp_enqueue_style(
            'pontifex-oi-shared',
            PONTIFEX_OI_URL . 'assets/css/pontifex-oi-shared.css',
            [],
            PONTIFEX_OI_VERSION
        );

        // 2) Admin styles, afhankelijk van shared
        wp_enqueue_style(
            'pontifex-oi-admin',
            PONTIFEX_OI_URL . 'assets/css/pontifex-oi-admin.css',
            ['pontifex-oi-shared'],
            PONTIFEX_OI_VERSION
        );

        // (optioneel) admin JS
        if (file_exists(PONTIFEX_OI_PATH . 'assets/js/pontifex-oi-admin.js')) {
            wp_enqueue_script(
                'pontifex-oi-admin',
                PONTIFEX_OI_URL . 'assets/js/pontifex-oi-admin.js',
                ['jquery'],
                PONTIFEX_OI_VERSION,
                true
            );
        }
    }

    public function add_menu() {
        // Hoofdmenu: Pontifex (niet klikbaar)
        add_menu_page(
            'Pontifex',
            'Pontifex',
            'manage_options',
            'pontifex_oi_main',
            '', // Geen callback, menu alleen als container
            'dashicons-awards',
            60
        );

        // Submenu Mollie
        add_submenu_page(
            'pontifex_oi_main',
            'Mollie instellingen',
            'Mollie',
            'manage_options',
            'pontifex_oi_mollie',
            [$this, 'settings_page']
        );

        // Submenu SOAP
        add_submenu_page(
            'pontifex_oi_main',
            'SOAP instellingen',
            'SOAP',
            'manage_options',
            'pontifex_oi_soap',
            [$this, 'soap_settings_page']
        );

        // Verwijder standaard dubbel submenu-item
        add_action('admin_head', function () {
            remove_submenu_page('pontifex_oi_main', 'pontifex_oi_main');
        });
    }

    public function register_settings() {
        // Mollie instellingen
        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_live_api_key');
        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_test_api_key');
        register_setting('pontifex_oi_mollie_group', 'pontifex_oi_mollie_test_mode', [
            'type' => 'boolean',
            'default' => false,
        ]);

        // SOAP instellingen
        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_url');
        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_user_id');
        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_company_id');
        register_setting('pontifex_oi_soap_group', 'pontifex_oi_soap_hash');
    }

    public function settings_page() {
        $live_key = esc_attr(get_option('pontifex_oi_mollie_live_api_key', ''));
        $test_key = esc_attr(get_option('pontifex_oi_mollie_test_api_key', ''));
        $test_mode = get_option('pontifex_oi_mollie_test_mode') ? 'checked' : '';
        ?>
        <div class="pontifex-admin-wrap">
            <div class="pontifex-admin-card">
                <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
                    <div id="message" class="updated notice notice-success is-dismissible"
                         style="margin:0 0 1rem 0 !important; width:85%;">
                        <p><?php esc_html_e('Instellingen zijn opgeslagen.', 'pontifex-oi'); ?></p>
                    </div>
                <?php endif; ?>
                <form method="post" action="options.php" autocomplete="off">
                    <?php settings_fields('pontifex_oi_mollie_group'); ?>
                    <div class="pontifex-admin-row">
                        <label for="pontifex_oi_mollie_live_api_key" class="pontifex-admin-label">
                            <?php esc_html_e('Live API Key', 'pontifex-oi'); ?>
                        </label>
                        <div class="pontifex-admin-desc">
                            <?php esc_html_e('Voer hier je live Mollie API key in (begin meestal met live_).', 'pontifex-oi'); ?>
                        </div>
                        <input type="text" id="pontifex_oi_mollie_live_api_key" name="pontifex_oi_mollie_live_api_key"
                               class="pontifex-admin-input" value="<?php echo $live_key; ?>" autocomplete="off"/>
                    </div>

                    <div class="pontifex-admin-row">
                        <label for="pontifex_oi_mollie_test_api_key" class="pontifex-admin-label">
                            <?php esc_html_e('Test API Key', 'pontifex-oi'); ?>
                        </label>
                        <div class="pontifex-admin-desc">
                            <?php esc_html_e('Voer hier je test Mollie API key in (begin meestal met test_).', 'pontifex-oi'); ?>
                        </div>
                        <input type="text" id="pontifex_oi_mollie_test_api_key" name="pontifex_oi_mollie_test_api_key"
                               class="pontifex-admin-input" value="<?php echo $test_key; ?>" autocomplete="off"/>
                    </div>

                    <div class="pontifex-admin-row pontifex-admin-toggle-row">
                        <label for="pontifex_oi_mollie_test_mode" class="pontifex-admin-label" style="margin-bottom:0;">
                            <?php esc_html_e('Testmodus', 'pontifex-oi'); ?>
                        </label>
                        <label class="pontifex-toggle-switch<?php echo $test_mode ? ' checked' : ''; ?>">
                            <input type="checkbox" id="pontifex_oi_mollie_test_mode" name="pontifex_oi_mollie_test_mode"
                                   value="1" style="display:none;" <?php echo $test_mode; ?>>
                            <span class="pontifex-toggle-knob"></span>
                        </label>
                        <span class="pontifex-toggle-label">
                            <?php echo $test_mode ? esc_html__('ingeschakeld', 'pontifex-oi') : esc_html__('uitgeschakeld', 'pontifex-oi'); ?>
                        </span>
                    </div>

                    <button type="submit"
                            class="pontifex-admin-submit"><?php esc_html_e('Opslaan', 'pontifex-oi'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    public function soap_settings_page() {
        include_once PONTIFEX_OI_PATH . 'admin/page-soap-settings.php';
    }
}

// ==== AJAX HANDLER BUITEN DE CLASS ====
add_action('wp_ajax_pontifex_oi_fetch_soap_data', 'pontifex_oi_fetch_soap_data_handler');