<?php

namespace PontifexOI\PublicPart;

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Api\SoapClient;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

// Laad PaymentSuccessShortcode indien aanwezig
add_action('plugins_loaded', static function () {
    $file = PONTIFEX_OI_PATH . 'public/class-payment-success-shortcode.php';
    file_exists($file) and require_once $file;
});

// Laad de product configuratie (geen global meer in de methodes zelf)
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

enum ExamType: string
{
    case VcaBasis      = 'los-examen-vca-basis';
    case VcaBasisGroen = 'los-examen-vca-basis-groen';
    case VcaVol        = 'los-examen-vca-vol';
    case VcaVil        = 'los-examen-vca-vil';

    public function getLabel(): string
    {
        return match ($this) {
            self::VcaBasis      => 'VCA Basis',
            self::VcaBasisGroen => 'VCA Basis Groen',
            self::VcaVol        => 'VCA Vol',
            self::VcaVil        => 'VCA VIL',
        };
    }
}

final class Frontend
{
    private static ?self $instance = null;

    private readonly SoapClient $soap;

    private const WEEKEND_ALLOWED_BY_EXAM = [
        'los-examen-vca-basis'       => ['nl'],
        'los-examen-vca-vol'         => ['nl'],
        'los-examen-vca-basis-groen' => [],
        'los-examen-vca-vil'         => [],
    ];

    private const AVAILABLE_LANGUAGES = [
        'nl', 'en', 'de', 'fr', 'ar', 'bg', 'lt', 'pl', 'pt', 'ro',
        'ru', 'tr', 'el', 'hu', 'it', 'hr', 'uk', 'sk', 'es', 'vi'
    ];

    private function __construct()
    {
        $this->soap = new SoapClient();

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

        add_action('init', static function () {
            class_exists('\PontifexOI\PublicPart\PaymentSuccessShortcode')
            and \PontifexOI\PublicPart\PaymentSuccessShortcode::register_shortcode();
        });

        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public static function get_instance(): self
    {
        return self::$instance ??= new self();
    }

    public function add_viewport_meta_tag(): void
    {
        if (!$this->should_enqueue_frontend_assets()) {
            return;
        }
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    }

    public function enqueue_assets(): void
    {
        if (!$this->should_enqueue_frontend_assets()) {
            return;
        }

        $base_url = PONTIFEX_OI_URL;
        $base_path = PONTIFEX_OI_PATH;
        $version = static function (string $file) use ($base_path): string {
            $path = $base_path . $file;
            return file_exists($path) ? (string) filemtime($path) : PONTIFEX_OI_VERSION;
        };

        wp_enqueue_style('pontifex-oi-shared', "{$base_url}assets/css/pontifex-oi-shared.css", [], $version('assets/css/pontifex-oi-shared.css'));

        foreach (['planning', 'registration', 'payment-success'] as $name) {
            $handle = "pontifex-oi-{$name}";
            $file = "assets/css/pontifex-oi-{$name}.css";
            wp_enqueue_style($handle, "{$base_url}{$file}", ['pontifex-oi-shared'], $version($file));
        }

        $js_deps = [
            'config'     => [],
            'utils'      => ['jquery'],
            'filters'    => ['pontifex-oi-config', 'pontifex-oi-utils'],
            'pagination' => ['pontifex-oi-utils'],
            'sidebar'    => ['pontifex-oi-filters'],
            'material'   => ['pontifex-oi-utils'],
            'price'      => ['pontifex-oi-material'],
            'table'      => ['pontifex-oi-price'],
            'candidates' => ['pontifex-oi-table'],
            'main'       => [
                'pontifex-oi-filters', 'pontifex-oi-pagination', 'pontifex-oi-sidebar',
                'pontifex-oi-material', 'pontifex-oi-price', 'pontifex-oi-table',
                'pontifex-oi-candidates'
            ],
            'validation' => ['pontifex-oi-main'],
        ];

        $js_files = [
            "config"     => "config.js",
            "utils"      => "utils.js",
            "filters"    => "filters.js",
            "pagination" => "pagination.js",
            "sidebar"    => "sidebar.js",
            "material"   => "material.js",
            "price"      => "price.js",
            "table"      => "table.js",
            "candidates" => "candidates.js",
            "main"       => "main.js",
            "validation" => "form-validation.js",
        ];

        foreach ($js_deps as $handle => $deps) {
            $file = "assets/js/" . $js_files[$handle];
            wp_enqueue_script("pontifex-oi-{$handle}", "{$base_url}{$file}", $deps, $version($file), true);
        }

        wp_enqueue_script(
            'pontifex-oi-custom',
            "{$base_url}assets/js/pontifex-oi-custom.js",
            ['jquery'],
            $version('assets/js/pontifex-oi-custom.js'),
            true
        );

        wp_enqueue_script(
            'pontifex-oi-testmode',
            "{$base_url}assets/js/pontifex-oi-testmode.js",
            ['jquery', 'pontifex-oi-main'],
            $version('assets/js/pontifex-oi-testmode.js'),
            true
        );

        $reg_page_id = (int) get_option('pontifex_oi_registration_page_id');
        $reg_url = $reg_page_id ? get_permalink($reg_page_id) : home_url('/cursus-inschrijven/');

        wp_localize_script('pontifex-oi-config', 'PontifexOIConfigData', array_merge(
            [
                'ajaxUrl'              => admin_url('admin-ajax.php'),
                'restBase'             => rest_url('pontifex-oi/v1'),
                'registrationPageUrl'  => $reg_url,
                'planningPageUrl'      => home_url('/cursus-zoeken/'),
                'weekendAllowedByExam' => self::WEEKEND_ALLOWED_BY_EXAM,
                'availableLanguages'   => self::AVAILABLE_LANGUAGES,
                'defaultExamType'      => ExamType::VcaBasis->value,
                'defaultLanguage'      => 'nl',
            ],
            $this->get_sanitized_product_data_for_js(),
            ['extraOptions' => $this->get_sanitized_extra_options_for_js()]
        ));

        wp_localize_script('pontifex-oi-main', 'PontifexOIFrontend', [
            'testMode' => (bool) get_option('pontifex_oi_mollie_test_mode', false),
        ]);

        wp_localize_script('pontifex-oi-validation', 'pontifexOiVars', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pontifex_oi_nonce'),
        ]);
    }

    private function should_enqueue_frontend_assets(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (is_singular()) {
            $post = get_post();
            if ($post && has_shortcode((string) $post->post_content, 'pontifex_oi_planning')) {
                return true;
            }
            if ($post && has_shortcode((string) $post->post_content, 'pontifex_oi_registration')) {
                return true;
            }
            if ($post && has_shortcode((string) $post->post_content, 'pontifex_oi_payment_success')) {
                return true;
            }
        }

        return false;
    }

    public function render_planning_shortcode(array $atts = []): string
    {
        defined('DONOTCACHEPAGE') or define('DONOTCACHEPAGE', true);
        nocache_headers();

        return sprintf(
            '<div id="pontifex-planning-app" data-initial-filters="%s">
                <div class="pontifex-loader">Laden...</div>
            </div>',
            esc_attr(wp_json_encode(array_map('sanitize_text_field', wp_unslash($_GET)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
        );
    }

    /**
     * Render Registration Form via een Mount Point (SPA Ready)
     */
    public function render_registration_shortcode(array $atts = []): string
    {
        // Zorg dat de benodigde scripts en styles geladen worden
        wp_enqueue_script('pontifex-oi-main');
        wp_enqueue_style('pontifex-oi-registration');

        defined('DONOTCACHEPAGE') or define('DONOTCACHEPAGE', true);
        nocache_headers();

        return sprintf(
            '<div id="pontifex-registration-app" 
                  data-order-id="%s" 
                  data-step="1">
                <div class="pontifex-loader">Inschrijfformulier wordt geladen...</div>
            </div>',
            esc_attr(isset($_GET['order_id']) ? sanitize_text_field(wp_unslash($_GET['order_id'])) : '') // Optioneel: voor hervatten van een bestelling
        );
    }

    public function ajax_get_planning(): never
    {
        $this->set_no_cache_headers();

        $filters = isset($_POST['filters']) ? wp_unslash($_POST['filters']) : [];
        $filters = is_array($filters) ? array_map('sanitize_text_field', $filters) : [];

        $is_weekend = ($filters['material'] ?? null) === 'cursus-weekend';

        $planning_data = $is_weekend
            ? $this->generate_weekend_planning_rows($filters)
            : ($this->soap ??= new SoapClient())->getPlanning($filters);

        $page     = max(1, (int)($filters['page']     ?? 1));
        $per_page = (int)($filters['per_page'] ?? 0);

        $total = count($planning_data);

        if ($per_page > 0) {
            $total_pages = max(1, (int)ceil($total / $per_page));
            $offset      = ($page - 1) * $per_page;
            $planning    = array_slice($planning_data, $offset, $per_page);
        } else {
            $total_pages = 1;
            $planning    = $planning_data;
        }

        wp_send_json_success([
            'planning'       => $planning,
            'current_page'   => $page,
            'total_pages'    => $total_pages,
            'total_results'  => $total,
            'filters'        => $filters,
        ]);
    }

    public function ajax_get_filter_options(): never
    {
        $this->set_no_cache_headers();

        $filter_type = isset($_REQUEST['filter']) ? sanitize_text_field(wp_unslash($_REQUEST['filter'])) : '';
        $filters     = isset($_REQUEST['filters']) ? wp_unslash($_REQUEST['filters']) : [];
        $filters     = is_array($filters) ? array_map('sanitize_text_field', $filters) : [];

        $is_weekend = ($filters['material'] ?? null) === 'cursus-weekend';

        $options = $is_weekend
            ? $this->get_weekend_filter_options($filter_type)
            : $this->get_regular_filter_options($filter_type, $filters);

        wp_send_json_success(['options' => $options]);
    }

    private function get_weekend_filter_options(string $filter_type): array
    {
        return match (strtolower($filter_type)) {
            'month'     => $this->get_weekend_month_options(3),
            'province'  => [['id' => 'Zuid-Holland', 'name' => __('Zuid-Holland', 'pontifex-oi')]],
            'location'  => [['id' => 'Den Haag',     'name' => __('Den Haag', 'pontifex-oi')]],
            'timeslot'  => [['id' => 'Ochtend',      'name' => __('Ochtend', 'pontifex-oi')]],
            'language'  => [['id' => 'nl',           'name' => __('Nederlands', 'pontifex-oi')]],
            default     => [],
        };
    }

    private function get_regular_filter_options(string $filter_type, array $filters): array
    {
        return match (strtolower($filter_type)) {
            'month'     => $this->soap->getMonths(),
            'province'  => $this->soap->getProvinces(),
            'location'  => $this->soap->getLocations($filters),
            'timeslot'  => $this->soap->getTimeslots(),
            'language'  => $this->soap->getLanguages(),
            'material'  => $this->soap->getMaterials(),
            default     => [],
        };
    }

    public function ajax_get_price(): never
    {
        $this->set_no_cache_headers();

        $exam_type      = isset($_POST['exam_type']) ? sanitize_text_field(wp_unslash($_POST['exam_type'])) : '';
        $language       = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'nl';
        $material       = isset($_POST['material']) ? sanitize_text_field(wp_unslash($_POST['material'])) : '1';
        $candidate_count = isset($_POST['candidate_count']) ? absint(wp_unslash($_POST['candidate_count'])) : 1;
        $extra_options  = isset($_POST['extra_options']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['extra_options'])) : [];

        if (!$exam_type) {
            wp_send_json_error(['message' => 'Geen exam_type opgegeven']);
        }

        try {
            $totals = PaymentHelpers::calculate_totals_with_vat([
                'exam_type'      => $exam_type,
                'language'       => $language,
                'material'       => $material,
                'candidate_count' => $candidate_count,
                'extra_options'  => $extra_options,
            ]);

            $price_incl = (float)($totals['incl'] ?? 0.0);

            wp_send_json_success([
                'price'      => $price_incl > 0 ? '€' . number_format($price_incl, 2, ',', '.') : '',
                'raw_price'  => (float)($totals['excl'] ?? 0.0),
                'vat9'       => (float)($totals['vat9']  ?? 0.0),
                'vat21'      => (float)($totals['vat21'] ?? 0.0),
                'vat_total'  => (float)(($totals['vat9'] ?? 0) + ($totals['vat21'] ?? 0)),
                'total_incl' => $price_incl,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Fout bij prijsberekening: ' . $e->getMessage()]);
        }
    }

    public function ajax_get_prices(): never
    {
        $this->set_no_cache_headers();

        $items = isset($_POST['items']) ? (array) wp_unslash($_POST['items']) : [];
        if (!$items) {
            wp_send_json_success([]);
        }

        $prices = [];

        foreach ($items as $row) {
            $exam = sanitize_text_field($row['exam'] ?? '');
            $lang = sanitize_text_field($row['lang'] ?? 'nl');
            $mat  = sanitize_text_field($row['material'] ?? '1');

            $key = sanitize_text_field($row['key'] ?? ($exam . '|' . $lang . '|' . $mat));

            if (!$exam) {
                continue;
            }

            $is_weekend_display = in_array($exam, ['los-examen-vca-basis', 'los-examen-vca-vol'], true)
                               && $mat === 'cursus-weekend';

            $price = $is_weekend_display
                ? 245.00
                : PaymentHelpers::calculate_total_price([
                    'exam_type'      => $exam,
                    'language'       => $lang,
                    'material'       => $mat,
                    'candidate_count' => 1,
                    'extra_options'  => [],
                ]);

            if ($price > 0) {
                $prices[$key] = (float) $price;
            }
        }

        wp_send_json_success($prices);
    }

    public function process_payment(): never
    {
        $this->set_no_cache_headers();

        if (!check_ajax_referer('pontifex_oi_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Ongeldige beveiligingstoken. Vernieuw de pagina en probeer opnieuw.', 'pontifex-oi')], 403);
        }

        if (!PaymentHelpers::is_configured()) {
            wp_send_json_error([
                'message' => 'Mollie is niet geconfigureerd. Vul de API-sleutel in bij Instellingen → Pontifex OI.'
            ]);
        }

        $order = wp_unslash($_POST['order'] ?? []);
        $order = is_array($order) ? $order : [];

        // Candidate data opschonen
        $candidate_fields = ['candidate_fullname', 'candidate_infix', 'candidate_lastname', 'candidate_birthdate'];

        foreach ($candidate_fields as $field) {
            $order[$field] = isset($order[$field]) && is_array($order[$field])
                ? array_values(array_filter(
                    array_map('sanitize_text_field', $order[$field]),
                    static fn($v) => trim((string)$v) !== ''
                ))
                : [];
        }

        $candidate_count = min(count($order['candidate_fullname'] ?? []), 12);

        if ($candidate_count === 0) {
            wp_send_json_error(['message' => 'Geen kandidaten gevonden. Vul minimaal 1 kandidaat in.']);
        }

        $order['candidate_count'] = $candidate_count;

        foreach ($candidate_fields as $field) {
            $order[$field] ??= [];
            $order[$field] = array_slice(array_pad($order[$field], $candidate_count, ''), 0, $candidate_count);
        }

        foreach ($order as $key => $value) {
            if (in_array($key, $candidate_fields, true) || $key === 'extra_options') {
                continue;
            }
            $order[$key] = is_scalar($value) ? sanitize_text_field((string) $value) : '';
        }

        $order['exam_type']     = sanitize_text_field($order['exam_type'] ?? '');
        $order['language']      = sanitize_text_field($order['language'] ?? 'nl');
        $order['material']      = sanitize_text_field($order['material'] ?? '');
        $order['extra_options'] = array_map('sanitize_text_field', (array)($order['extra_options'] ?? []));
        $order['order_email']   = sanitize_email($order['order_email'] ?? ($order['email'] ?? ''));

        $totals = PaymentHelpers::calculate_totals_with_vat($order);
        $calculated_incl = (float)($totals['incl'] ?? 0.0);

        $frontend_amount = isset($_POST['amount']) ? (float) wp_unslash($_POST['amount']) : (isset($_POST['payment_amount']) ? (float) wp_unslash($_POST['payment_amount']) : 0.0);
        $diff = abs($frontend_amount - $calculated_incl);

        if ($frontend_amount <= 0 || $diff > 0.05) {
            wp_send_json_error([
                'message' => 'Ongeldig bedrag (BTW). Probeer het opnieuw.',
                'debug'   => WP_DEBUG ? compact('frontend_amount', 'calculated_incl', 'diff') : null,
            ]);
        }

        $exam_label = $GLOBALS['EXAM_PRODUCTS'][$order['exam_type']]['label'] ?? 'Onbekend Examen';
        $lang_label = $order['language'];

        $names = [];
        foreach ($order['candidate_fullname'] ?? [] as $i => $fullname) {
            $infix = $order['candidate_infix'][$i] ?? '';
            $lastname = $order['candidate_lastname'][$i] ?? '';
            $names[] = trim("$fullname $infix $lastname");
        }

        $candidate_list = implode(', ', array_filter($names));

        $description = implode(' ', array_filter([
            'Inschrijving',
            $exam_label,
            "({$lang_label})",
            'op ' . ($order['date'] ?? 'onbekende datum'),
            'om ' . ($order['time'] ?? 'onbekende tijd'),
            $candidate_list ? 'voor: ' . $candidate_list : null,
        ]));

        try {
            $token = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : wp_hash(uniqid('pontifex_order_', true));
            $redirect = add_query_arg('order_token', $token, home_url('/bedankt-inschrijven/'));

            $payment = PaymentHelpers::create_mollie_payment(
                $calculated_incl,
                $redirect,
                $order,
                $order['order_email'],
                $description
            );

            if (!$payment || empty($payment->id)) {
                throw new \Exception('Mollie betaling kon niet worden aangemaakt.');
            }

            set_transient("mollie_payment_id_for_token_{$token}", sanitize_text_field($payment->id), HOUR_IN_SECONDS);

            wp_send_json_success([
                'payment_url' => $payment->getCheckoutUrl(),
                'order_token' => $token,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['message' => 'Fout bij starten betaling: ' . $e->getMessage()]);
        }
    }

    public function register_rest_routes(): void
    {
        register_rest_route('pontifex-oi/v1', '/planning', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_get_planning'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('pontifex-oi/v1', '/price', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_get_price'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function rest_get_planning(WP_REST_Request $request): WP_REST_Response
    {
        $filters = $request->get_param('filters') ?? [];
        $filters = is_array($filters) ? array_map('sanitize_text_field', $filters) : [];

        $page     = max(1, (int)($filters['page'] ?? 1));
        $per_page = (int)($filters['per_page'] ?? 0);

        $is_weekend = ($filters['material'] ?? null) === 'cursus-weekend';

        $all = $is_weekend
            ? $this->generate_weekend_planning_rows($filters)
            : $this->soap->getPlanning($filters);

        $total = count($all);

        if ($per_page > 0) {
            $total_pages = max(1, (int)ceil($total / $per_page));
            $offset      = ($page - 1) * $per_page;
            $planning    = array_slice($all, $offset, $per_page);
        } else {
            $total_pages = 1;
            $planning    = $all;
        }

        $response = new WP_REST_Response([
            'planning'       => $planning,
            'current_page'   => $page,
            'total_pages'    => $total_pages,
            'total_results'  => $total,
        ]);

        $this->set_no_cache_headers($response);
        return $response;
    }

    public function rest_get_price(WP_REST_Request $request): WP_REST_Response
    {
        $exam_type = sanitize_text_field($request->get_param('exam_type') ?? '');
        $language  = sanitize_text_field($request->get_param('language') ?? 'nl');
        $material  = sanitize_text_field($request->get_param('material') ?? '1');

        $price = PaymentHelpers::calculate_total_price([
            'exam_type'      => $exam_type,
            'language'       => $language,
            'material'       => $material,
        ]);

        $price_str = $price > 0 ? '€' . number_format((float)$price, 2, ',', '.') : '';

        $response = new WP_REST_Response([
            'price'     => $price_str,
            'raw_price' => (float)$price,
        ]);

        $this->set_no_cache_headers($response);
        return $response;
    }

    private function set_no_cache_headers($response = null): void
    {
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => 'Wed, 11 Jan 1984 05:00:00 GMT',
        ];

        if ($response instanceof WP_REST_Response) {
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        } else {
            nocache_headers();
            foreach ($headers as $key => $value) {
                header("$key: $value");
            }
        }
    }

    /**
     * Geeft productdata voor JavaScript zonder gebruik van global in de functie zelf
     */
    private function get_sanitized_product_data_for_js(): array
    {
        $exam_products = [];
        foreach ($GLOBALS['EXAM_PRODUCTS'] ?? [] as $key => $item) {
            $exam_products[$key] = [
                'label'  => $item['label']   ?? 'Onbekend examen',
                'prices' => $item['prices']  ?? [],
            ];
        }

        return [
            'examProducts'     => $exam_products,
            'materialProducts' => $GLOBALS['MATERIAL_PRODUCTS'] ?? [],
            'materialCombis'   => $GLOBALS['MATERIAL_COMBIS']   ?? [],
        ];
    }

    /**
     * Geeft extra opties voor JavaScript zonder global misbruik in de functie body
     */
    private function get_sanitized_extra_options_for_js(): array
    {
        $options = [];
        foreach ($GLOBALS['EXTRA_PRODUCTS'] ?? [] as $id => $product) {
            $options[$id] = [
                'id'    => $id,
                'label' => $product['label'] ?? 'Onbekend product',
                'price' => $product['price'] ?? 0.00,
            ];
        }

        return $options;
    }

    private function get_weekend_month_options(int $count = 3): array
    {
        $options = [];
        $now = new \DateTimeImmutable();

        for ($i = 0; $i < $count; $i++) {
            $month = $now->modify("+$i months");
            $ym = $month->format('Y-m');
            $options[] = [
                'id'   => $ym,
                'name' => date_i18n('F Y', $month->getTimestamp()),
            ];
        }

        return $options;
    }

    private function generate_weekend_planning_rows(array $filters): array
    {
        $months = !empty($filters['month']) && preg_match('/^\d{4}-\d{2}$/', $filters['month'])
            ? [$filters['month']]
            : array_column($this->get_weekend_month_options(3), 'id');

        $today = current_time('timestamp');
        $rows = [];

        foreach ($months as $ym) {
            try {
                $start = new \DateTimeImmutable("$ym-01");
                $end   = $start->modify('last day of this month');
            } catch (\Throwable) {
                continue;
            }

            $current = $start;
            while ($current <= $end) {
                if ($current->format('N') === '6') { // zaterdag
                    $ts = $current->getTimestamp();
                    if ($ts >= $today) {
                        $rows[] = [
                            'date'     => date_i18n('d-m-Y', $ts),
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
                $current = $current->modify('+1 day');
            }
        }

        return $rows;
    }
}
