<?php
namespace PontifexOI\PublicPart;

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Api\SoapClient;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

// Ensure the PaymentSuccessShortcode file is loaded.
add_action('plugins_loaded', function () {
    $file = PONTIFEX_OI_PATH . 'public/class-payment-success-shortcode.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Include combined product configuration (prices + extras).
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

/**
 * Handles all public-facing functionality of the plugin.
 */
class Frontend
{
    private static $instance = null;
    private ?\PontifexOI\Api\SoapClient $soap = null;

    private const EXAM_TYPES = [
        ['id' => 'los-examen-vca-basis',       'name' => 'VCA Basis'],
        ['id' => 'los-examen-vca-basis-groen', 'name' => 'VCA Basis Groen'],
        ['id' => 'los-examen-vca-vol',         'name' => 'VCA Vol'],
        ['id' => 'los-examen-vca-vil',         'name' => 'VCA VIL'],
    ];

    private const LANGUAGES = [
        ['id' => '',   'name' => 'Kies taal'],
        ['id' => 'nl', 'name' => 'Nederlands'],
        ['id' => 'en', 'name' => 'Engels'],
        ['id' => 'de', 'name' => 'Duits'],
        ['id' => 'fr', 'name' => 'Frans'],
    ];

    private const MATERIALS = [
        ['id' => '',  'name' => 'Geen keuze'],
        ['id' => '1', 'name' => 'Los examen'],
        ['id' => '2', 'name' => 'Examen + boek'],
        ['id' => '4', 'name' => 'Examen + e-learning'],
        ['id' => '5', 'name' => 'Examen + proefexamens'],
        ['id' => '6', 'name' => 'Examen + boek + proefexamens'],
        ['id' => '7', 'name' => 'Examen + e-learning + proefexamens'],
    ];

    public static function get_instance()
    {
        return self::$instance ?: (self::$instance = new self());
    }

    private function __construct()
    {
        add_action('wp_head', [$this, 'add_viewport_meta_tag'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('pontifex_oi_planning', [$this, 'render_planning_shortcode']);
        add_shortcode('pontifex_oi_registration', [$this, 'render_registration_shortcode']);

        $ajax_actions = ['get_planning', 'get_filter_options', 'get_price', 'get_prices'];
        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_pontifex_oi_{$action}", [$this, "ajax_{$action}"]);
            add_action("wp_ajax_nopriv_pontifex_oi_{$action}", [$this, "ajax_{$action}"]);
        }

        add_action('wp_ajax_pontifex_oi_process_payment', [$this, 'process_payment']);
        add_action('wp_ajax_nopriv_pontifex_oi_process_payment', [$this, 'process_payment']);

        add_action('init', function () {
            if (class_exists('\PontifexOI\PublicPart\PaymentSuccessShortcode')) {
                \PontifexOI\PublicPart\PaymentSuccessShortcode::register_shortcode();
            }
        });

        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function add_viewport_meta_tag()
    {
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    }

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'pontifex-oi-shared',
            PONTIFEX_OI_URL . 'assets/css/pontifex-oi-shared.css',
            [],
            filemtime(PONTIFEX_OI_PATH . 'assets/css/pontifex-oi-shared.css')
        );

        $css_dependencies = [
            'pontifex-oi-planning'       => 'pontifex-oi-planning.css',
            'pontifex-oi-registration'   => 'pontifex-oi-registration.css',
            'pontifex-oi-payment-success'=> 'pontifex-oi-payment-success.css',
        ];

        foreach ($css_dependencies as $handle => $file) {
            wp_enqueue_style(
                $handle,
                PONTIFEX_OI_URL . "assets/css/{$file}",
                ['pontifex-oi-shared'],
                filemtime(PONTIFEX_OI_PATH . "assets/css/{$file}")
            );
        }

        $js_dependencies = [
            'pontifex-oi-config'     => ['file' => 'config.js',      'deps' => []],
            'pontifex-oi-utils'      => ['file' => 'utils.js',       'deps' => ['jquery']],
            'pontifex-oi-filters'    => ['file' => 'filters.js',     'deps' => ['pontifex-oi-config', 'pontifex-oi-utils']],
            'pontifex-oi-pagination' => ['file' => 'pagination.js',  'deps' => ['pontifex-oi-utils']],
            'pontifex-oi-sidebar'    => ['file' => 'sidebar.js',     'deps' => ['pontifex-oi-filters']],
            'pontifex-oi-material'   => ['file' => 'material.js',    'deps' => ['pontifex-oi-utils']],
            'pontifex-oi-price'      => ['file' => 'price.js',       'deps' => ['pontifex-oi-material']],
            'pontifex-oi-table'      => ['file' => 'table.js',       'deps' => ['pontifex-oi-price']],
            'pontifex-oi-candidates' => ['file' => 'candidates.js',  'deps' => ['pontifex-oi-table']],
            'pontifex-oi-main'       => ['file' => 'main.js',        'deps' => ['pontifex-oi-filters', 'pontifex-oi-pagination', 'pontifex-oi-sidebar', 'pontifex-oi-material', 'pontifex-oi-price', 'pontifex-oi-table', 'pontifex-oi-candidates']],
            'pontifex-oi-validation' => ['file' => 'form-validation.js', 'deps' => ['pontifex-oi-main']],
        ];

        foreach ($js_dependencies as $handle => $script) {
            wp_enqueue_script(
                $handle,
                PONTIFEX_OI_URL . "assets/js/{$script['file']}",
                $script['deps'],
                filemtime(PONTIFEX_OI_PATH . "assets/js/{$script['file']}"),
                true
            );
        }

        wp_enqueue_script(
            'pontifex-oi-custom',
            PONTIFEX_OI_URL . 'assets/js/pontifex-oi-custom.js',
            ['jquery'],
            filemtime(PONTIFEX_OI_PATH . 'assets/js/pontifex-oi-custom.js'),
            true
        );

        $weekendAllowedByExam = [
            'los-examen-vca-basis'       => ['nl'],
            'los-examen-vca-vol'         => ['nl'],
            'los-examen-vca-basis-groen' => [],
            'los-examen-vca-vil'         => [],
        ];

        $availableLanguages = ['nl','en','de','fr','ar','bg','lt','pl','pt','ro','ru','tr','el','hu','it','hr','uk','sk','es','vi'];
        $availableLanguageLabels = [
            'nl'=>'Nederlands','en'=>'Engels','de'=>'Duits','fr'=>'Frans','ar'=>'Arabisch','bg'=>'Bulgaars','lt'=>'Litouws','pl'=>'Pools',
            'pt'=>'Portugees','ro'=>'Roemeens','ru'=>'Russisch','tr'=>'Turks','el'=>'Grieks','hu'=>'Hongaars','it'=>'Italiaans','hr'=>'Kroatisch',
            'uk'=>'Oekraïens','sk'=>'Slowaaks','es'=>'Spaans','vi'=>'Vietnamees',
        ];

        wp_localize_script('pontifex-oi-config', 'PontifexOIConfigData', array_merge(
            [
                'ajaxUrl'               => admin_url('admin-ajax.php'),
                'restBase'              => rest_url('pontifex-oi/v1'),
                'registrationPageUrl'   => (($__id = (int) get_option('pontifex_oi_registration_page_id')) ? get_permalink($__id) : home_url('/cursus-inschrijven/')),
                'planningPageUrl'       => home_url('/cursus-zoeken/'),
                'weekendAllowedByExam'  => $weekendAllowedByExam,
                'availableLanguages'    => $availableLanguages,
                'availableLanguageLabels' => $availableLanguageLabels,
                'defaultExamType'       => 'los-examen-vca-basis',
                'defaultLanguage'       => 'nl',
            ],
            $this->get_product_data_for_js(),
            ['extraOptions' => $this->get_extra_options_for_js()]
        ));

        wp_localize_script('pontifex-oi-main', 'PontifexOiAjax', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'registration_url' => (($__id = (int) get_option('pontifex_oi_registration_page_id')) ? get_permalink($__id) : home_url('/cursus-inschrijven/')),
            'cache_buster' => (string) time(),
        ]);

        wp_localize_script('pontifex-oi-validation', 'pontifexOiVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pontifex_oi_nonce'),
        ]);

        wp_localize_script('pontifex-oi-main', 'PontifexOIFrontend', [
            'testMode' => (bool) get_option('pontifex_oi_mollie_test_mode', false),
        ]);

        wp_enqueue_script(
            'pontifex-oi-testmode',
            PONTIFEX_OI_URL . 'assets/js/pontifex-oi-testmode.js',
            ['jquery', 'pontifex-oi-main'],
            filemtime(PONTIFEX_OI_PATH . 'assets/js/pontifex-oi-testmode.js'),
            true
        );
    }

    public function render_planning_shortcode($atts = [])
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        $filters = array_merge([
            'exam_type' => '', 'language' => '', 'material' => '', 'daytype' => '', 'month' => '',
            'province' => '', 'location' => '', 'timeslot' => '', 'page' => 1,
        ], array_intersect_key($_GET, array_flip([
            'exam_type', 'language', 'material', 'daytype', 'month', 'province', 'location', 'timeslot', 'page', 'per_page'
        ])));

        if (empty($filters['exam_type'])) $filters['exam_type'] = 'los-examen-vca-basis';
        if (empty($filters['language'])) $filters['language'] = 'nl';

        $page = max(1, (int)($filters['page'] ?? 1));

        if (isset($filters['per_page']) && (int)$filters['per_page'] > 0) {
            $per_page = (int)$filters['per_page'];
        } else {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
                $per_page = 7;
            } elseif (preg_match('/Tablet|iPad/i', $ua)) {
                $per_page = 8;
            } else {
                $per_page = 25;
            }
        }

        $isWeekend = isset($filters['material']) && $filters['material'] === 'cursus-weekend';

        if ($isWeekend) {
            $all_planning = $this->generate_weekend_planning_rows($filters);
        } else {
            $soap = $this->soap ??= new SoapClient();
            $soapFilters = array_intersect_key($filters, array_flip([
                'exam_type', 'language', 'material', 'month', 'province', 'location', 'timeslot'
            ]));
            $all_planning = $soap->getPlanning($soapFilters);
        }

        if (!is_array($all_planning)) {
            $all_planning = [];
        }

        $total = count($all_planning);
        $total_pages = max(1, (int)ceil($total / $per_page));
        $offset = ($page - 1) * $per_page;
        $planning = array_slice($all_planning, $offset, $per_page);

        if (!$isWeekend) {
            $soap = $this->soap ??= new SoapClient();
            $months    = $soap->getMonths($filters);
            $provinces = $soap->getProvinces($filters);
            $locations = $soap->getLocations($filters);
            $timeslots = $soap->getTimeslots($filters);
        } else {
            $months    = $this->get_weekend_month_options(3);
            $provinces = [['id' => 'Zuid-Holland', 'name' => 'Zuid-Holland']];
            $locations = [['id' => 'Den Haag',     'name' => 'Den Haag']];
            $timeslots = [['id' => 'Ochtend',      'name' => 'Ochtend']];
        }

        $exam_types = array_map(static function ($item) {
            $item['name'] = __($item['name'], 'pontifex-oi');
            return $item;
        }, self::EXAM_TYPES);

        $languages = array_map(static function ($item) {
            $item['name'] = __($item['name'], 'pontifex-oi');
            return $item;
        }, self::LANGUAGES);

        $materials = array_map(static function ($item) {
            $item['name'] = __($item['name'], 'pontifex-oi');
            return $item;
        }, self::MATERIALS);

        $args = [
            'per_page'      => $per_page,
            'total_results' => $total,
            'exam_types'    => $exam_types,
            'languages'     => $languages,
            'materials'     => $materials,
            'months'        => $months,
            'provinces'     => $provinces,
            'locations'     => $locations,
            'timeslots'     => $timeslots,
            'planning'      => $planning,
            'filters'       => $filters,
            'current_page'  => $page,
            'total_pages'   => $total_pages,
        ];

        $__pf_placeholder = function (&$arr, $label) {
            if (!is_array($arr)) return;
            if (isset($arr[0]) && is_array($arr[0])) {
                $arr[0]['id'] = '';
                $arr[0]['name'] = $label;
            } else {
                array_unshift($arr, ['id' => '', 'name' => $label]);
            }
        };

        $__pf_placeholder($args['months'],    __('Kies maand',    'pontifex-oi'));
        $__pf_placeholder($args['provinces'], __('Kies provincie','pontifex-oi'));
        $__pf_placeholder($args['locations'], __('Kies locatie',  'pontifex-oi'));
        $__pf_placeholder($args['timeslots'], __('Kies dagdeel',  'pontifex-oi'));

        ob_start();
        include PONTIFEX_OI_PATH . 'public/planning-list.php';
        return ob_get_clean();
    }

    public function render_registration_shortcode($atts = [])
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();

        $defaults = [
            'exam_type' => sanitize_text_field($_GET['exam_type'] ?? ''),
            'language'  => sanitize_text_field($_GET['language'] ?? ''),
            'material'  => sanitize_text_field($_GET['material'] ?? '1'),
            'date'      => sanitize_text_field($_GET['date'] ?? ''),
            'time'      => sanitize_text_field($_GET['time'] ?? ''),
            'location'  => sanitize_text_field($_GET['location'] ?? ''),
            'province'  => sanitize_text_field($_GET['province'] ?? ''),
            'spots'     => sanitize_text_field($_GET['spots'] ?? ''),
            'price'     => '',
        ];

        if (in_array($defaults['exam_type'], ['vca-basis-weekend', 'vca-vol-weekend'], true)) {
            $defaults['province'] = 'Zuid-Holland';
            $defaults['location'] = 'Den Haag';
        }

        ob_start();
        include PONTIFEX_OI_PATH . 'public/registration-form.php';
        return ob_get_clean();
    }

    public function ajax_get_planning()
    {
        $this->set_no_cache_headers();

        $filters = $_POST['filters'] ?? [];
        $page = max(1, (int)($filters['page'] ?? 1));
        $per_page = isset($filters['per_page']) ? (int)$filters['per_page'] : 0;

        $isWeekend = isset($filters['material']) && $filters['material'] === 'cursus-weekend';

        if ($isWeekend) {
            $all = $this->generate_weekend_planning_rows($filters);
        } else {
            $soap = $this->soap ??= new SoapClient();
            $all = $soap->getPlanning($filters);
        }

        $total = count($all);

        if ($per_page > 0) {
            $total_pg = max(1, (int)ceil($total / $per_page));
            $offset = ($page - 1) * $per_page;
            $planning = array_slice($all, $offset, $per_page);
        } else {
            $total_pg = 1;
            $page = 1;
            $planning = $all;
        }

        wp_send_json_success([
            'planning'      => $planning,
            'current_page'  => $page,
            'total_pages'   => $total_pg,
            'total_results' => $total,
            'filters'       => $filters,
        ]);
    }

    public function ajax_get_filter_options()
    {
        $this->set_no_cache_headers();

        $filter = sanitize_text_field($_REQUEST['filter'] ?? '');
        $filters = $_REQUEST['filters'] ?? [];
        if (!is_array($filters)) $filters = [];

        $isWeekend = isset($filters['material']) && $filters['material'] === 'cursus-weekend';
        $options = [];

        if ($isWeekend) {
            switch ($filter) {
                case 'month':
                    $current_month = date('Y-m');
                    $options[] = ['id' => $current_month, 'name' => date_i18n('F Y', strtotime($current_month . '-01'))];
                    for ($i = 1; $i < 3; $i++) {
                        $next_month = date('Y-m', strtotime("+$i months"));
                        $options[] = ['id' => $next_month, 'name' => date_i18n('F Y', strtotime($next_month . '-01'))];
                    }
                    break;
                case 'province':
                    $options = [['id' => 'Zuid-Holland', 'name' => __('Zuid-Holland', 'pontifex-oi')]];
                    break;
                case 'location':
                    $options = [['id' => 'Den Haag', 'name' => __('Den Haag', 'pontifex-oi')]];
                    break;
                case 'timeslot':
                    $options = [['id' => 'Ochtend', 'name' => __('Ochtend', 'pontifex-oi')]];
                    break;
                case 'language':
                    $options = [['id' => 'nl', 'name' => __('Nederlands', 'pontifex-oi')]];
                    break;
            }
        } else {
            $soap = $this->soap ??= new SoapClient();
            switch ($filter) {
                case 'location':  $options = $soap->getLocations($filters);  break;
                case 'language':  $options = $soap->getLanguages($filters);  break;
                case 'material':  $options = $soap->getMaterials($filters);  break;
                case 'month':     $options = $soap->getMonths($filters);     break;
                case 'province':  $options = $soap->getProvinces($filters);  break;
                case 'timeslot':  $options = $soap->getTimeslots($filters);  break;
            }
        }

        wp_send_json_success(['options' => $options]);
    }

    public function ajax_get_price()
    {
        $this->set_no_cache_headers();

        $exam_type = sanitize_text_field($_POST['exam_type'] ?? '');
        $language  = sanitize_text_field($_POST['language'] ?? 'nl');
        $material  = sanitize_text_field($_POST['material'] ?? '1');
        $candidate_count = absint($_POST['candidate_count'] ?? 1);

        if (empty($exam_type)) {
            wp_send_json_error(['message' => 'Geen exam_type opgegeven']);
            return;
        }

        try {
            $extra_options = isset($_POST['extra_options']) && is_array($_POST['extra_options'])
                ? array_map('sanitize_text_field', $_POST['extra_options'])
                : [];

            $order_details = [
                'exam_type'       => $exam_type,
                'language'        => $language,
                'material'        => $material,
                'candidate_count' => $candidate_count,
                'extra_options'   => $extra_options,
            ];

            $totals = PaymentHelpers::calculate_totals_with_vat($order_details);

            $price_incl = (float)($totals['incl'] ?? 0);
            $price_str  = '€' . number_format($price_incl, 2, ',', '.');

            wp_send_json_success([
                'price'      => $price_str,
                'raw_price'  => (float)($totals['excl'] ?? 0),
                'vat21'      => (float)($totals['vat21'] ?? 0),
                'vat_total'  => (float)($totals['vat_total'] ?? 0),
                'total_incl' => $price_incl,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => 'Fout bij prijsberekening']);
        }
    }

    public function ajax_get_prices()
    {
        $this->set_no_cache_headers();

        $items = $_POST['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            wp_send_json_success([]);
            return;
        }

        $out = [];

        foreach ($items as $row) {
            $exam_type = sanitize_text_field($row['exam'] ?? '');
            $language  = sanitize_text_field($row['lang'] ?? 'nl');
            $material  = sanitize_text_field($row['material'] ?? '1');
            $key = sanitize_text_field($row['key'] ?? ($exam_type . '|' . $language . '|' . $material));

            if (empty($exam_type)) continue;

            try {
                $order_details = [
                    'exam_type'       => $exam_type,
                    'language'        => $language,
                    'material'        => $material,
                    'candidate_count' => 1,
                    'extra_options'   => [],
                ];

                $isWeekendDisplay = (
                    in_array($exam_type, ['los-examen-vca-basis', 'los-examen-vca-vol'], true)
                    && $material === 'cursus-weekend'
                );

                if ($isWeekendDisplay) {
                    $out[$key] = 245.00;
                } else {
                    $price = PaymentHelpers::calculate_total_price($order_details);
                    if ($price > 0) {
                        $out[$key] = (float) $price;
                    }
                }
            } catch (\Exception $e) {
                // stilzwijgend overslaan
            }
        }

        wp_send_json_success($out);
    }

    // ... de rest van de class (process_payment, register_rest_routes, etc.) blijft ongewijzigd ...

    private function set_no_cache_headers($response = null)
    {
        if ($response instanceof WP_REST_Response) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', 'Wed, 11 Jan 1984 05:00:00 GMT');
        } else {
            nocache_headers();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        }
    }

    private function get_product_data_for_js()
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;

        $exam_products_simple = [];
        foreach ($EXAM_PRODUCTS as $key => $item) {
            $exam_products_simple[$key] = [
                'label'  => $item['label'],
                'prices' => $item['prices'],
            ];
        }

        return [
            'examProducts'    => $exam_products_simple,
            'materialProducts' => $MATERIAL_PRODUCTS,
            'materialCombis'   => $MATERIAL_COMBIS,
        ];
    }

    private function get_extra_options_for_js()
    {
        global $EXTRA_PRODUCTS;
        if (!isset($EXTRA_PRODUCTS)) {
            require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
        }

        $flat = [];
        foreach ($EXTRA_PRODUCTS as $id => $product) {
            $flat[$id] = [
                'id'    => $id,
                'label' => $product['label'],
                'price' => $product['price'],
            ];
        }

        return $flat;
    }

    private function get_weekend_month_options(int $count = 3): array
    {
        $out = [];
        $start = date('Y-m');
        $startTs = strtotime($start . '-01');

        for ($i = 0; $i < $count; $i++) {
            $ym = date('Y-m', strtotime("+$i months", $startTs));
            $out[] = [
                'id'   => $ym,
                'name' => date_i18n('F Y', strtotime($ym . '-01')),
            ];
        }

        return $out;
    }

    private function generate_weekend_planning_rows(array $filters): array
    {
        if (!empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {
            $months = [$filters['month']];
        } else {
            $months = array_map(
                static fn($m) => $m['id'],
                $this->get_weekend_month_options(3)
            );
        }

        $todayTs = current_time('timestamp');
        $rows = [];

        foreach ($months as $ym) {
            try {
                $dtStart = new \DateTimeImmutable($ym . '-01');
            } catch (\Throwable $e) {
                continue;
            }

            $dtEnd = $dtStart->modify('last day of this month');

            for ($d = $dtStart; $d <= $dtEnd; $d = $d->modify('+1 day')) {
                if ((int) $d->format('N') !== 6) {
                    continue;
                }
                if ($d->getTimestamp() < $todayTs) {
                    continue;
                }

                $rows[] = [
                    'date'     => date_i18n('d-m-Y', $d->getTimestamp()),
                    'time'     => '08:00',
                    'province' => 'Zuid-Holland',
                    'location' => 'Den Haag',
                    'timeslot' => 'Ochtend',
                    'spots'    => __('Beschikbaar', 'pontifex-oi'),
                    'price'    => '€245,00',
                    'register' => __('Nog steeds kandidaten aanmelden', 'pontifex-oi'),
                ];
            }
        }

        return $rows;
    }
}