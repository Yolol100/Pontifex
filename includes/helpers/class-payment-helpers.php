<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentHelpers
{
    private const VAT_RATE = 0.21;

    /**
     * Statische cache voor de Mollie API-sleutel.
     * @var string|null
     */
    private static $cached_api_key;

    /**
     * Controleert of testmodus actief is.
     */
    public static function is_test_mode(): bool
    {
        return (int) get_option('pontifex_oi_mollie_test_mode', 0) === 1;
    }

    /**
     * Context voor logging: site + modus.
     */
    private static function get_log_context(): string
    {
        $mode = self::is_test_mode() ? 'TEST' : 'LIVE';
        return site_url() . '][' . $mode;
    }

    /**
     * Haalt de juiste Mollie API-sleutel op (test of live).
     *
     * @throws \RuntimeException
     */
    public static function get_mollie_api_key(): string
    {
        if (self::$cached_api_key !== null) {
            return self::$cached_api_key;
        }

        $use_test = self::is_test_mode();
        $key = '';

        // 1. Via plugin opties
        $test_key = trim((string) get_option('pontifex_oi_mollie_test_api_key', ''));
        $live_key = trim((string) get_option('pontifex_oi_mollie_live_api_key', ''));
        $key = $use_test ? $test_key : $live_key;

        // 2. Via wp-config constanten
        if (!$key) {
            $const = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            if (defined($const)) {
                $key = (string) constant($const);
            }
        }

        // 3. Via omgevingsvariabele
        if (!$key) {
            $env_var = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            $env_key = getenv($env_var);
            if ($env_key !== false && $env_key !== '') {
                $key = (string) $env_key;
            }
        }

        if ($key === '') {
            throw new \RuntimeException(
                'Geen Mollie API-sleutel geconfigureerd. Vul deze in bij Instellingen → Pontifex OI.'
            );
        }

        return self::$cached_api_key = $key;
    }

    public static function is_configured(): bool
    {
        try {
            return (bool) self::get_mollie_api_key();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Berekent het totaalbedrag exclusief BTW (altijd excl. BTW).
     */
    public static function calculate_total_price(array $order_details): float
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        if (
            !isset($EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS)
        ) {
            $config_file = PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            if (defined('PONTIFEX_OI_PATH') && file_exists($config_file)) {
                require_once $config_file;
            } else {
                return 0.00;
            }
        }

        $exam_type       = $order_details['exam_type']       ?? '';
        $language        = $order_details['language']        ?? 'nl';
        $material_type   = $order_details['material']        ?? '';
        $candidate_count = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options   = (array)($order_details['extra_options'] ?? []);

        $total = 0.0;

        $aliases = [
            'los-examen-vil-vcu' => 'los-examen-vca-vil',
            'examen-vil'         => 'los-examen-vca-vil',
        ];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        $weekend_ids     = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $weekend_selected = array_intersect($extra_options, $weekend_ids);
        $is_weekend_exam  = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active   = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        // Extra opties (exclusief weekend als die al apart geteld wordt)
        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, $weekend_ids, true) && $weekend_active) {
                continue;
            }
            $total += (float)($EXTRA_PRODUCTS[$extra_id]['price'] ?? 0);
        }

        if ($weekend_active) {
            $total += 245.00;
        } else {
            // Examenprijs
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                if ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl', 'en'], true)) {
                    $exam_price = 139.0;
                } else {
                    $exam_price = (float)($prices[$language] ?? $prices['nl'] ?? 0);
                }
            }
            $total += $exam_price;

            // Materiaal / combi
            if (!empty($material_type) && $material_type !== '1') {
                $combi_key = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'], true)) {
                    $suffix = (str_contains($exam_type_norm, 'vca-vol') || str_contains($exam_type_norm, 'vca-vil'))
                        ? 'vol'
                        : 'basis';
                    $combi_key = "{$material_type}_{$suffix}";
                }

                if (isset($MATERIAL_COMBIS[$combi_key])) {
                    foreach ($MATERIAL_COMBIS[$combi_key] as $prod_id) {
                        $total += (float)($MATERIAL_PRODUCTS[$prod_id]['price'] ?? 0);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $total += (float)($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                }
            }
        }

        $final = round($total * $candidate_count, 2);
        return $final < 0.01 ? 0.00 : $final;
    }

    private static function precise_round(float $value, int $precision = 2): float
    {
        if (function_exists('bcadd')) {
            return (float) number_format($value, $precision, '.', '');
        }
        return round($value, $precision);
    }

    /**
     * Berekent totaal incl. BTW (momenteel alles 21%).
     * Retourneert array met uitsplitsing (incl. vat9=0 voor compatibiliteit).
     */
    public static function calculate_totals_with_vat(array $order_details): array
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        $config_file = PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
        if (
            !isset($EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS)
            && defined('PONTIFEX_OI_PATH')
            && file_exists($config_file)
        ) {
            require_once $config_file;
        }

        if (
            !isset($EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS)
        ) {
            return [
                'excl'      => 0.00,
                'vat9'      => 0.00,
                'vat21'     => 0.00,
                'vat_total' => 0.00,
                'incl'      => 0.00,
            ];
        }

        $exam_type       = $order_details['exam_type']       ?? '';
        $language        = $order_details['language']        ?? 'nl';
        $material_type   = $order_details['material']        ?? '';
        $candidate_count = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options   = (array)($order_details['extra_options'] ?? []);

        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_type = pontifex_normalize_exam_key($exam_type);
        }

        $weekend_ids     = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $weekend_selected = array_intersect($extra_options, $weekend_ids);
        $is_weekend_exam  = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active   = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        $sum_excl  = 0.0;
        $sum_vat21 = 0.0;

        $aliases = [
            'los-examen-vil-vcu' => 'los-examen-vca-vil',
            'examen-vil'         => 'los-examen-vca-vil',
        ];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        // 1. Extra opties — altijd 21%
        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, $weekend_ids, true) && $weekend_active) {
                continue;
            }
            if (!isset($EXTRA_PRODUCTS[$extra_id])) {
                continue;
            }
            $price_excl = (float) ($EXTRA_PRODUCTS[$extra_id]['price'] ?? 0);
            $sum_excl  += $price_excl;
            $sum_vat21 += self::precise_round($price_excl * self::VAT_RATE, 4);
        }

        if ($weekend_active) {
            $bundle_excl = 245.00;
            $sum_excl  += $bundle_excl;
            $sum_vat21 += self::precise_round($bundle_excl * self::VAT_RATE, 4);
        } else {
            // Examen — 21%
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                if ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl','en'])) {
                    $exam_price = 139.0;
                } else {
                    $exam_price = (float) ($prices[$language] ?? $prices['nl'] ?? 0);
                }
            }
            $sum_excl  += $exam_price;
            $sum_vat21 += self::precise_round($exam_price * self::VAT_RATE, 4);

            // Materialen — 21%
            if (!empty($material_type) && $material_type !== '1') {
                $combi_key = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'])) {
                    $suffix = (str_contains($exam_type_norm, 'vca-vol') || str_contains($exam_type_norm, 'vca-vil'))
                        ? 'vol'
                        : 'basis';
                    $combi_key = "{$material_type}_{$suffix}";
                }

                if (isset($MATERIAL_COMBIS[$combi_key])) {
                    foreach ($MATERIAL_COMBIS[$combi_key] as $mat_key) {
                        if (!isset($MATERIAL_PRODUCTS[$mat_key])) continue;
                        $price = (float) ($MATERIAL_PRODUCTS[$mat_key]['price'] ?? 0);
                        $sum_excl  += $price;
                        $sum_vat21 += self::precise_round($price * self::VAT_RATE, 4);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $price = (float) ($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                    $sum_excl  += $price;
                    $sum_vat21 += self::precise_round($price * self::VAT_RATE, 4);
                }
            }
        }

        $final_excl   = self::precise_round($sum_excl  * $candidate_count, 2);
        $final_vat21  = self::precise_round($sum_vat21 * $candidate_count, 2);
        $total_vat    = self::precise_round($final_vat21, 2);
        $total_incl   = self::precise_round($final_excl + $total_vat, 2);

        return [
            'excl'      => $final_excl,
            'vat9'      => 0.00,          // bewust behouden voor compatibiliteit
            'vat21'     => $final_vat21,
            'vat_total' => $total_vat,
            'incl'      => $total_incl,
        ];
    }

    /**
     * Creëert een Mollie-betaling.
     */
    public static function create_mollie_payment(
        float $total_amount,
        string $return_url,
        array $order_details,
        string $customer_email = '',
        string $description = ''
    ): ?\Mollie\Api\Resources\Payment {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $log_context = self::get_log_context();

        global $EXTRA_PRODUCTS, $EXAM_PRODUCTS, $MATERIAL_PRODUCTS;

        error_log('[Pontifex OI DEBUG][' . $log_context . '] Payment creation started; candidates=' . (int) ($order_details['candidate_count'] ?? 0));

        $amount_incl = (float) $total_amount;
        $amountStr = number_format($amount_incl, 2, '.', '');

        $totals = self::calculate_totals_with_vat($order_details);
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Server-side payment total recalculated.');

        if (empty($order_details['language'])) {
            $order_details['language'] = $_POST['language'] ?? $_GET['language'] ?? 'nl';
        }

        if (!preg_match('/^\d+\.\d{2}$/', $amountStr) || (float)$amountStr < 0.01) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Ongeldig Bedrag: ' . $amountStr);
            throw new \Exception(__('Het bedrag kon niet worden berekend.', 'pontifex-oi'));
        }

        $calculated_incl = (float) ($totals['incl'] ?? 0.0);
        if (abs((float)$amountStr - $calculated_incl) > 0.01) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Bedrag mismatch: ingediend ' . $amountStr . ' vs berekend ' . $calculated_incl);
            throw new \Exception(__('Ongeldig bedrag (controle mislukt).', 'pontifex-oi'));
        }

        try {
            $usingTest = self::is_test_mode();
            $apiKey = self::get_mollie_api_key();

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->addVersionString('Pontifex OI/' . (defined('PONTIFEX_OI_VERSION') ? PONTIFEX_OI_VERSION : 'dev'));
            $mollie->setApiKey($apiKey);

            if ($usingTest && strpos($apiKey, 'test_') !== 0) {
                throw new \RuntimeException('Testmodus actief maar geen test API key');
            }
            if (!$usingTest && strpos($apiKey, 'live_') !== 0) {
                throw new \RuntimeException('Live-modus actief maar geen live API key');
            }

            // Beschrijving logica
            $exam_type_raw = $order_details['exam_type'] ?? '';
            $material_raw  = $order_details['material'] ?? '';
            $date          = $order_details['date'] ?? '';
            $location      = $order_details['location'] ?? '';

            $is_flow2 = empty($date) || empty($location);

            if ($is_flow2) {
                $description = 'Inschrijving Certipro';
            } else {
                global $EXAM_PRODUCTS;
                $exam_labels_map = [
                    'los-examen-vca-basis'     => 'VCA Basis',
                    'los-examen-vca-basis-groen' => 'VCA Basis Groen',
                    'los-examen-vca-vol'       => 'VCA Vol',
                    'los-examen-vca-vil'       => 'VCA VIL',
                    'vca-basis-weekend'        => 'Weekendcursus VCA Basis',
                    'vca-vol-weekend'          => 'Weekendcursus VCA Vol',
                ];
                $material_labels_map = [
                    '1'               => 'Los examen',
                    '2'               => 'Examen + boek',
                    '4'               => 'Examen + e-learning',
                    '5'               => 'Examen + proefexamens',
                    '6'               => 'Examen + boek + proefexamens',
                    '7'               => 'Examen + e-learning + proefexamens',
                    'cursus-weekend'  => 'Weekendcursus met examen',
                    'cursus-weekend-nl' => 'Weekendcursus met examen',
                    'cursus-weekend-en' => 'Weekend course with exam',
                ];

                $exam_label = $exam_labels_map[$exam_type_raw] ?? ($EXAM_PRODUCTS[$exam_type_raw]['label'] ?? 'Examen');
                $material_label = $material_labels_map[$material_raw] ?? 'Los examen';

                $description = $exam_label . ' - ' . $material_label;
            }

            $redirect_url = esc_url_raw($return_url ?: home_url('/bedankt-inschrijven/'));
            $webhook_url = esc_url_raw(rest_url('pontifex-oi/v1/webhook'));
            $secret = get_option('pontifex_oi_webhook_secret');
            if (!empty($secret)) {
                $webhook_url = add_query_arg('secret', rawurlencode($secret), $webhook_url);
            }

            // AI-PATCH: houd Mollie metadata bewust klein.
            // Volledige kandidaat-/formulierdata kan te groot of te complex zijn voor Mollie metadata.
            // De volledige order staat tijdelijk in WordPress via order_token.
            $metadata = [
                "order_token" => (string) ($order_details["order_token"] ?? ""),
                "exam_type"   => sanitize_text_field((string) ($order_details["exam_type"] ?? "")),
                "language"    => sanitize_text_field((string) ($order_details["language"] ?? "nl")) ,
                "total_incl"  => $amountStr,
                "vat_total"   => number_format((float)$totals["vat_total"], 2, ".", ""),
            ];

            $mollie_payment_data = [
                "amount" => [
                    "currency" => "EUR",
                    "value"    => $amountStr,
                ],
                "description"  => trim($description) ?: "Inschrijving Certipro",
                "redirectUrl"  => $redirect_url,
                "webhookUrl"   => $webhook_url,
                "metadata"     => $metadata,
            ];

            $payment = $mollie->payments->create($mollie_payment_data);

            if (!$payment || empty($payment->id)) {
                throw new \RuntimeException("Mollie gaf geen geldig payment-object terug.");
            }

            return $payment;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie API request failed; code=' . (int) $e->getCode());
            throw new \RuntimeException('Mollie API fout.', 0, $e);
        } catch (\Throwable $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Payment creation failed (' . get_class($e) . '); code=' . (int) $e->getCode());
            throw new \RuntimeException('Mollie betaling kon niet worden aangemaakt.', 0, $e);
        }
    }

    /**
     * Haalt een Mollie-betaling op.
     */
    public static function get_mollie_payment(string $payment_id): ?\Mollie\Api\Resources\Payment
    {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $log_context = self::get_log_context();

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $apiKey = self::get_mollie_api_key();

            $mollie->setApiKey($apiKey);

            $payment = $mollie->payments->get($payment_id);

            if ($payment->metadata) {
                if (is_object($payment->metadata)) {
                    $payment->metadata = (array) $payment->metadata;
                }
            } else {
                $payment->metadata = [];
            }

            return $payment;
        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie lookup failed; code=' . (int) $e->getCode());
            return null;
        } catch (\Exception $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Payment lookup failed (' . get_class($e) . '); code=' . (int) $e->getCode());
            return null;
        }
    }
}
