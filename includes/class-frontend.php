<?php

namespace PontifexOI\PublicPart;

use PontifexOI\Helpers\PaymentHelpers;

if (!defined('ABSPATH')) {
    exit;
}

// Robuust inladen van de shortcode-bestand(en)
add_action('plugins_loaded', function () {
    $candidates = [
        PONTIFEX_OI_PATH . 'public/class-payment-success-shortcode.php',
        PONTIFEX_OI_PATH . 'public/payment-success-shortcode.php',
    ];
    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            break;
        }
    }
});


require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

class Frontend {
    private static $instance = null;

    public static function get_instance() {
        return self::$instance ?: (self::$instance = new self());
    }

    private function __construct() {
        add_shortcode('pontifex_oi_planning', [$this, 'render_planning_shortcode']);
        add_shortcode('pontifex_oi_registration', [$this, 'render_registration_shortcode']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_action('wp_ajax_pontifex_oi_get_planning', [$this, 'ajax_get_planning']);
        add_action('wp_ajax_nopriv_pontifex_oi_get_planning', [$this, 'ajax_get_planning']);

        add_action('wp_ajax_pontifex_oi_process_payment', [$this, 'process_payment']);
        add_action('wp_ajax_nopriv_pontifex_oi_process_payment', [$this, 'process_payment']);

        add_action('wp_ajax_pontifex_oi_get_filter_options', [$this, 'ajax_get_filter_options']);
        add_action('wp_ajax_nopriv_pontifex_oi_get_filter_options', [$this, 'ajax_get_filter_options']);

        add_action('wp_ajax_pontifex_oi_get_price', [$this, 'ajax_get_price']);
        add_action('wp_ajax_nopriv_pontifex_oi_get_price', [$this, 'ajax_get_price']);

        // Alleen registreren als de shortcode-klasse beschikbaar is
        add_action('init', function() {
            if (class_exists('\PontifexOI\PublicPart\PaymentSuccessShortcode')) {
                \PontifexOI\PublicPart\PaymentSuccessShortcode::register_shortcode();
            }
        });
    }

    public function enqueue_assets() {
        // CSS
        wp_enqueue_style('pontifex-oi-shared', PONTIFEX_OI_URL . 'assets/css/pontifex-oi-shared.css', [], PONTIFEX_OI_VERSION);
        wp_enqueue_style('pontifex-oi-planning', PONTIFEX_OI_URL . 'assets/css/pontifex-oi-planning.css', ['pontifex-oi-shared'], PONTIFEX_OI_VERSION);
        wp_enqueue_style('pontifex-oi-registration', PONTIFEX_OI_URL . 'assets/css/pontifex-oi-registration.css', ['pontifex-oi-shared'], PONTIFEX_OI_VERSION);
        wp_enqueue_style('pontifex-oi-payment-success', PONTIFEX_OI_URL . 'assets/css/pontifex-oi-payment-success.css', ['pontifex-oi-shared'], PONTIFEX_OI_VERSION);

        // JS – basis config
        wp_enqueue_script('pontifex-oi-config', PONTIFEX_OI_URL . 'assets/js/config.js', [], PONTIFEX_OI_VERSION, true);

        // JS – modules (overgenomen uit pontifex-oi.php)
        wp_enqueue_script('pontifex-oi-utils', PONTIFEX_OI_URL . 'assets/js/utils.js', ['jquery'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-filters', PONTIFEX_OI_URL . 'assets/js/filters.js', ['pontifex-oi-config','pontifex-oi-utils'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-pagination', PONTIFEX_OI_URL . 'assets/js/pagination.js', ['pontifex-oi-utils'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-sidebar', PONTIFEX_OI_URL . 'assets/js/sidebar.js', ['pontifex-oi-filters'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-material', PONTIFEX_OI_URL . 'assets/js/material.js', ['pontifex-oi-utils'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-price', PONTIFEX_OI_URL . 'assets/js/price.js', ['pontifex-oi-material'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-table', PONTIFEX_OI_URL . 'assets/js/table.js', ['pontifex-oi-price'], PONTIFEX_OI_VERSION, true);
        wp_enqueue_script('pontifex-oi-candidates', PONTIFEX_OI_URL . 'assets/js/candidates.js', ['pontifex-oi-table'], PONTIFEX_OI_VERSION, true);

        // JS – main NA de modules
        wp_enqueue_script(
            'pontifex-oi',
            PONTIFEX_OI_URL . 'assets/js/main.js',
            ['pontifex-oi-filters','pontifex-oi-pagination','pontifex-oi-sidebar','pontifex-oi-material','pontifex-oi-price','pontifex-oi-table','pontifex-oi-candidates'],
            PONTIFEX_OI_VERSION,
            true
        );

        // JS – form validation NA main
        wp_enqueue_script('pontifex-oi-validation', PONTIFEX_OI_URL . 'assets/js/form-validation.js', ['pontifex-oi'], PONTIFEX_OI_VERSION, true);

        // ===== JS data localize =====
        // 1) Grote config naar JS (zoals je al deed)
        $weekendAllowedByExam = [
            'los-examen-vca-basis' => ['nl','en'],
            'los-examen-vca-vol' => ['nl','en'],
            'los-examen-vca-basis-groen' => [],  // weekend UIT
            'los-examen-vca-vil' => [],  // weekend UIT
        ];
        $availableLanguages = ['nl','en','de','fr','ar','bg','lt','pl','pt','ro','ru','tr','el','hu','it','hr','uk','sk','es','vi'];
        $availableLanguageLabels = [
            'nl'=>'Nederlands','en'=>'Engels','de'=>'Duits','fr'=>'Frans','ar'=>'Arabisch','bg'=>'Bulgaars','lt'=>'Litouws','pl'=>'Pools',
            'pt'=>'Portugees','ro'=>'Roemeens','ru'=>'Russisch','tr'=>'Turks','el'=>'Grieks','hu'=>'Hongaars','it'=>'Italiaans','hr'=>'Kroatisch',
            'uk'=>'Oekraïens','sk'=>'Slowaaks','es'=>'Spaans','vi'=>'Vietnamees',
        ];

        wp_localize_script('pontifex-oi-config', 'PontifexOIConfigData', array_merge(
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'registrationPageUrl' => ( ($__id=(int)get_option('pontifex_oi_registration_page_id')) ? get_permalink($__id) : home_url('/cursus-inschrijven/') ),
                'planningPageUrl' => home_url('/cursus-zoeken/'),
                'weekendAllowedByExam' => $weekendAllowedByExam,
                'availableLanguages' => $availableLanguages,
                'availableLanguageLabels' => $availableLanguageLabels,
            ],
            $this->get_product_data_for_js(),
            ['extraMaterialCheckboxes' => $this->get_extra_material_checkboxes_for_js()]
        ));

        // 2) AJAX basisvariabelen (zoals voorheen in root)
        wp_localize_script('pontifex-oi', 'PontifexOiAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'registration_url' => ( ($__id=(int)get_option('pontifex_oi_registration_page_id')) ? get_permalink($__id) : home_url('/cursus-inschrijven/') ),
        ]);

        // 3) NONCE voor validatie/POSTs (was eerder in root; hoort hier)
        wp_localize_script('pontifex-oi-validation', 'pontifexOiVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pontifex_oi_nonce'),
        ]);
    }

    public function render_planning_shortcode($atts = []) {
        $filters = array_merge([
            'exam_type' => '',
            'language' => '',
            'material' => '',
            'daytype' => '',
            'month' => '',
            'province' => '',
            'location' => '',
            'timeslot' => '',
            'page' => 1,
        ], array_intersect_key($_GET, array_flip([
            'exam_type', 'language', 'material', 'daytype', 'month', 'province', 'location', 'timeslot', 'page'
        ])));

        if (empty($filters['exam_type'])) $filters['exam_type'] = 'los-examen-vca-basis';
        if (empty($filters['language'])) $filters['language'] = 'nl';
        if (empty($filters['material'])) $filters['material'] = '1';

        $args['exam_types'] = [
            ['id' => 'los-examen-vca-basis', 'name' => 'VCA Basis'],
            ['id' => 'los-examen-vca-basis-groen', 'name' => 'VCA Basis Groen'],
            ['id' => 'los-examen-vca-vol', 'name' => 'VCA Vol'],
            ['id' => 'los-examen-vca-vil', 'name' => 'VCA VIL'],
        ];
        $args['languages'] = [
            ['id' => '', 'name' => 'Kies taal'],
            ['id' => 'nl', 'name' => 'Nederlands'],
            ['id' => 'en', 'name' => 'Engels'],
            ['id' => 'de', 'name' => 'Duits'],
            ['id' => 'fr', 'name' => 'Frans'],
        ];
        $args['materials'] = [
            ['id' => '', 'name' => 'Geen keuze'],
            ['id' => '1', 'name' => 'Los examen'],
            ['id' => '2', 'name' => 'Examen + boek'],
            ['id' => '4', 'name' => 'Examen + e-learning'],
            ['id' => '5', 'name' => 'Examen + proefexamens'],
            ['id' => '6', 'name' => 'Examen + boek + proefexamens'],
            ['id' => '7', 'name' => 'Examen + e-learning + proefexamens'],
        ];

        $soap = new \PontifexOI\Api\SoapClient();
        $per_page = 10;
        $all_planning = $soap->getPlanning($filters);
        $total_results = count($all_planning);
        $total_pages = (int) ceil($total_results / $per_page);
        $offset = ($filters['page'] - 1) * $per_page;
        $planning = array_slice($all_planning, $offset, $per_page);

        $args += [
            'months' => $soap->getMonths($filters),
            'provinces' => $soap->getProvinces($filters),
            'locations' => $soap->getLocations($filters),
            'timeslots' => $soap->getTimeslots($filters),
            'planning' => $planning,
            'filters' => $filters,
            'current_page' => $filters['page'],
            'total_pages' => $total_pages,
            'per_page' => $per_page,
        ];

        $__pf_placeholder = function (&$arr, $label) {
            if (!is_array($arr)) return;
            if (isset($arr[0]) && is_array($arr[0])) {
                // Zorg dat de eerste optie een lege "Kies …" is
                $arr[0]['id']   = '';
                $arr[0]['name'] = $label;
            } else {
                array_unshift($arr, ['id' => '', 'name' => $label]);
            }
        };

        $__pf_placeholder($args['months'],   __('Kies maand','pontifex-oi'));
        $__pf_placeholder($args['provinces'], __('Kies provincie','pontifex-oi'));
        $__pf_placeholder($args['locations'], __('Kies locatie','pontifex-oi'));
        $__pf_placeholder($args['timeslots'], __('Kies dagsoort','pontifex-oi'));

        ob_start();
        include PONTIFEX_OI_PATH . 'public/planning-list.php';
        return ob_get_clean();
    }

    public function render_registration_shortcode($atts = []) {
        $defaults = [
            'exam_type' => sanitize_text_field($_GET['exam_type'] ?? ''),
            'language' => sanitize_text_field($_GET['language'] ?? ''),
            'material' => sanitize_text_field($_GET['material'] ?? ''),
            'date' => sanitize_text_field($_GET['date'] ?? ''),
            'time' => sanitize_text_field($_GET['time'] ?? ''),
            'location' => sanitize_text_field($_GET['location'] ?? ''),
            'province' => sanitize_text_field($_GET['province'] ?? ''),
            'spots' => sanitize_text_field($_GET['spots'] ?? ''),
            'price' => sanitize_text_field($_GET['price'] ?? ''),
        ];

        ob_start();
        include PONTIFEX_OI_PATH . 'public/registration-form.php';
        return ob_get_clean();
    }

    public function ajax_get_planning() {
        $filters = $_POST['filters'] ?? [];
        $page = max(1, (int) ($filters['page'] ?? 1));
        $per_page = max(1, (int) ($filters['per_page'] ?? 10));

        $soap = new \PontifexOI\Api\SoapClient();
        $all = $soap->getPlanning($filters);
        $total = count($all);
        $total_pg = max(1, (int) ceil($total / $per_page));
        $offset = ($page - 1) * $per_page;
        $planning = array_slice($all, $offset, $per_page);

        wp_send_json_success([
            'planning' => $planning,
            'current_page' => $page,
            'total_pages' => $total_pg,
        ]);
    }

    public function ajax_get_filter_options() {
        $filter = sanitize_text_field($_POST['filter'] ?? '');
        $filters = $_POST['filters'] ?? [];

        $soap = new \PontifexOI\Api\SoapClient();
        $options = [];

        switch ($filter) {
            case 'location': $options = $soap->getLocations($filters); break;
            case 'language': $options = $soap->getLanguages($filters); break;
            case 'material': $options = $soap->getMaterials($filters); break;
            case 'month': $options = $soap->getMonths($filters); break;
            case 'province': $options = $soap->getProvinces($filters); break;
            case 'timeslot': $options = $soap->getTimeslots($filters); break;
        }

        wp_send_json_success(['options' => $options]);
    }

    public function ajax_get_price() {
        $exam_type = sanitize_text_field($_POST['exam_type'] ?? '');
        $language = sanitize_text_field($_POST['language'] ?? 'nl');
        $material = sanitize_text_field($_POST['material'] ?? '1');
        $candidate_count = absint($_POST['candidate_count'] ?? 1);

        if (empty($exam_type)) {
            wp_send_json_error(['message' => 'Geen exam_type opgegeven']);
            return;
        }

        try {
            $order_details = [
                'exam_type' => $exam_type,
                'language' => $language,
                'material' => $material,
                'candidate_count' => $candidate_count,
                'extra_material' => isset($_POST['extra_material']) && is_array($_POST['extra_material'])
                    ? array_map('sanitize_text_field', $_POST['extra_material'])
                    : [],
            ];
            
            $price = PaymentHelpers::calculate_total_price($order_details);
            $price_str = is_numeric($price) && $price > 0
                ? '€' . number_format((float)$price, 2, ',', '.')
                : '';

            wp_send_json_success([
                'price' => $price_str,
                'raw_price' => $price,
                'debug_info' => $order_details,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fout bij prijsberekening: ' . $e->getMessage()]);
        }
    }

    public function process_payment() {
        check_ajax_referer('pontifex_oi_nonce', 'nonce', false);

        $order_data = $_POST['order'] ?? [];
        $amount_from_frontend = floatval($_POST['amount'] ?? 0);

        if (isset($order_data['extra_material']) && !is_array($order_data['extra_material'])) {
            $order_data['extra_material'] = [$order_data['extra_material']];
        }
        $order_data['extra_material'] = array_map('sanitize_text_field', $order_data['extra_material'] ?? []);
        $order_data['candidate_fullname'] = array_map('sanitize_text_field', $order_data['candidate_fullname'] ?? []);
        $order_data['candidate_infix'] = array_map('sanitize_text_field', $order_data['candidate_infix'] ?? []);
        $order_data['candidate_lastname'] = array_map('sanitize_text_field', $order_data['candidate_lastname'] ?? []);
        $order_data['candidate_birthdate'] = array_map('sanitize_text_field', $order_data['candidate_birthdate'] ?? []);
        $order_data['candidate_count'] = count($order_data['candidate_fullname']);
        $order_data['exam_type'] = sanitize_text_field($order_data['exam_type'] ?? '');
        $order_data['language'] = sanitize_text_field($order_data['language'] ?? 'nl');
        $order_data['material'] = sanitize_text_field($order_data['material'] ?? '');

        $calculated_total_price = PaymentHelpers::calculate_total_price($order_data);

        if ($amount_from_frontend <= 0 || abs($amount_from_frontend - $calculated_total_price) > 0.02) {
            wp_send_json_error(['message' => 'Ongeldig bedrag. Probeer het opnieuw.']);
            return;
        }

        global $EXAM_PRODUCTS;
        $exam_label = $EXAM_PRODUCTS[$order_data['exam_type']]['label'] ?? 'Onbekend Examen';
        $language_label = $order_data['language'];

        $candidate_names = [];
        foreach ($order_data['candidate_fullname'] as $key => $fullname) {
            $infix = $order_data['candidate_infix'][$key] ?? '';
            $lastname = $order_data['candidate_lastname'][$key] ?? '';
            $candidate_names[] = trim($fullname . ' ' . $infix . ' ' . $lastname);
        }
        $candidate_list = implode(', ', array_filter($candidate_names));

        $description_parts = [
            'Inschrijving',
            $exam_label,
            '(' . $language_label . ')',
            'op ' . ($order_data['date'] ?? 'onbekende datum'),
            'om ' . ($order_data['time'] ?? 'onbekende tijd'),
        ];
        if (!empty($candidate_list)) {
            $description_parts[] = 'voor: ' . $candidate_list;
        }
        $description = implode(' ', $description_parts);
        
        $order_email = sanitize_email($order_data['order_email'] ?? '');

        try {
            $order_token = uniqid('pontifex_order_');
            $redirect_url_with_token = add_query_arg('order_token', $order_token, home_url('/bedankt-inschrijven/'));

            $payment = PaymentHelpers::create_mollie_payment(
                (float) $calculated_total_price,
                $redirect_url_with_token,
                $order_data,
                $order_email,
                $description
            );

            if ($payment === false) {
                throw new \Exception('Mollie betaling kon niet worden aangemaakt.');
            }

            set_transient('mollie_payment_id_for_token_' . $order_token, $payment->id, 3600);

            wp_send_json_success([
                'payment_url' => $payment->getCheckoutUrl(),
                'order_token' => $order_token,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fout bij starten betaling: ' . $e->getMessage()]);
        }
    }

    private function get_product_data_for_js() {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;

        $exam_products_simple = [];
        foreach ($EXAM_PRODUCTS as $key => $item) {
            $exam_products_simple[$key] = [
                'label' => $item['label'],
                'prices' => $item['prices'],
            ];
        }

        return [
            'examProducts' => $exam_products_simple,
            'materialProducts' => $MATERIAL_PRODUCTS,
            'materialCombis' => $MATERIAL_COMBIS,
        ];
    }

    private function get_extra_material_checkboxes_for_js() {
        $php = get_extra_material_options();
        $flat = [];
        foreach ($php as $exam => $langs) {
            foreach ($langs as $lang => $arr) {
                $flat[$exam . '_' . $lang] = $arr;
            }
        }
        return $flat;
    }
}