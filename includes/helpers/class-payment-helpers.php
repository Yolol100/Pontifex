<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) { exit; }

class PaymentHelpers
{
    /**
     * Statische cache voor de Mollie API-sleutel.
     * @var string|null
     */
    private static $cached_api_key;

    /**
     * Controleert de testmodus.
     * @return bool
     */
    public static function is_test_mode(): bool
    {
        return (int) get_option('pontifex_oi_mollie_test_mode', 0) === 1;
    }

    /**
     * Haalt de juiste Mollie API-sleutel op.
     * @return string
     * @throws \RuntimeException Als er geen sleutel is geconfigureerd.
     */
    public static function get_mollie_api_key(): string
    {
        if (self::$cached_api_key) {
            return self::$cached_api_key;
        }

        $use_test = self::is_test_mode();
        $key = '';

        $test = trim((string) get_option('pontifex_oi_mollie_test_api_key', ''));
        $live = trim((string) get_option('pontifex_oi_mollie_live_api_key', ''));
        $key = $use_test ? $test : $live;

        if (!$key) {
            if ($use_test && defined('MOLLIE_TEST_API_KEY')) {
                $key = (string) constant('MOLLIE_TEST_API_KEY');
            } elseif (!$use_test && defined('MOLLIE_LIVE_API_KEY')) {
                $key = (string) constant('MOLLIE_LIVE_API_KEY');
            }
        }

        if (!$key) {
            $env_var_name = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            $env_key = getenv($env_var_name);
            if ($env_key) {
                $key = (string) $env_key;
            }
        }

        if (!$key) {
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
     * Retourneert excl BTW totaal.
     * @param array $order_details
     * @return float Excl BTW totaal.
     */
    public static function calculate_total_price(array $order_details): float
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        if (!isset($EXAM_PRODUCTS) || !isset($MATERIAL_PRODUCTS) || !isset($MATERIAL_COMBIS) || !isset($EXTRA_PRODUCTS)) {
            if (defined('PONTIFEX_OI_PATH') && file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
                require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            } else {
                return 0.00;
            }
        }

        $exam_type = $order_details['exam_type'] ?? '';
        $language = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_count = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = is_array($order_details['extra_options'] ?? []) ? $order_details['extra_options'] : [];

        $total = 0.0;
        $aliases = ['los-examen-vil-vcu' => 'los-examen-vca-vil', 'examen-vil' => 'los-examen-vca-vil'];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        $weekend_selected = array_intersect($extra_options, ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh']);
        $is_weekend_exam = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'], true) && $weekend_active) {
                continue;
            }
            if (isset($EXTRA_PRODUCTS[$extra_id])) {
                $total += (float)($EXTRA_PRODUCTS[$extra_id]['price'] ?? 0);
            }
        }

        if ($weekend_active) {
            $total += 245.0;
        } else {
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

            if (!empty($material_type) && $material_type !== '1') {
                $combiKey = $material_type;
                if (in_array($material_type, ['2', '4', '5', '6', '7'], true)) {
                    $suffix = (strpos($exam_type_norm, 'vca-vol') !== false || strpos($exam_type_norm, 'vca-vil') !== false) ? 'vol' : 'basis';
                    $combiKey = "{$material_type}_{$suffix}";
                }
                if (isset($MATERIAL_COMBIS[$combiKey])) {
                    foreach ($MATERIAL_COMBIS[$combiKey] as $prodId) {
                        $total += (float)($MATERIAL_PRODUCTS[$prodId]['price'] ?? 0);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $total += (float)($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                }
            }
        }

        return round($total * $candidate_count, 2);
    }

    /**
     * Berekent totalen met BTW (vanaf 2026: enkel nog 21%).
     * vat9-veld is volledig verwijderd.
     *
     * @param array $order_details
     * @return array
     */
    public static function calculate_totals_with_vat(array $order_details): array
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        if (!isset($EXAM_PRODUCTS) || !isset($MATERIAL_PRODUCTS) || !isset($MATERIAL_COMBIS) || !isset($EXTRA_PRODUCTS)) {
            if (defined('PONTIFEX_OI_PATH') && file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
                require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            } else {
                return ['excl' => 0.00, 'vat21' => 0.00, 'vat_total' => 0.00, 'incl' => 0.00];
            }
        }

        $exam_type = $order_details['exam_type'] ?? '';
        $language = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_cnt = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = is_array($order_details['extra_options'] ?? []) ? $order_details['extra_options'] : [];

        $WEEKEND_IDS = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $sum_excl = 0.0;

        $aliases = ['los-examen-vil-vcu' => 'los-examen-vca-vil', 'examen-vil' => 'los-examen-vca-vil'];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        $weekend_active = !empty(array_intersect($extra_options, $WEEKEND_IDS))
            || $material_type === 'cursus-weekend'
            || in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);

        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, $WEEKEND_IDS, true) && $weekend_active) {
                continue;
            }
            if (isset($EXTRA_PRODUCTS[$extra_id])) {
                $sum_excl += (float) $EXTRA_PRODUCTS[$extra_id]['price'];
            }
        }

        if ($weekend_active) {
            $sum_excl += 245.00;
        } else {
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                $exam_price = ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl','en'], true))
                    ? 139.0
                    : (float) ($prices[$language] ?? $prices['nl'] ?? 0);
            }
            $sum_excl += $exam_price;

            if (!empty($material_type) && $material_type !== '1') {
                $combiKey = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'], true)) {
                    $suffix = (strpos($exam_type_norm, 'vca-vol') !== false || strpos($exam_type_norm, 'vca-vil') !== false)
                        ? 'vol'
                        : 'basis';
                    $combiKey = "{$material_type}_{$suffix}";
                }
                if (isset($MATERIAL_COMBIS[$combiKey])) {
                    foreach ($MATERIAL_COMBIS[$combiKey] as $matKey) {
                        $sum_excl += (float) ($MATERIAL_PRODUCTS[$matKey]['price'] ?? 0);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $sum_excl += (float) ($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                }
            }
        }

        $final_excl = round($sum_excl * $candidate_cnt, 2);
        $final_vat21 = round($final_excl * 0.21, 2);
        $total_vat = $final_vat21;
        $total_incl = round($final_excl + $total_vat, 2);

        return [
            'excl'      => $final_excl,
            'vat21'     => $final_vat21,
            'vat_total' => $total_vat,
            'incl'      => $total_incl,
        ];
    }

    public static function create_mollie_payment(
        float $total_amount,
        string $return_url,
        array $order_details,
        string $customer_email = '',
        string $description = ''
    ): ?\Mollie\Api\Resources\Payment {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $amount_incl = (float) $total_amount;
        $amountStr = number_format($amount_incl, 2, '.', '');
        $totals = self::calculate_totals_with_vat($order_details);

        if (empty($order_details['language'])) {
            $order_details['language'] = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : (isset($_GET['language']) ? sanitize_text_field(wp_unslash($_GET['language'])) : 'nl');
        }

        if (!preg_match('/^\d+\.\d{2}$/', $amountStr) || (float)$amountStr < 0.01) {
            throw new \Exception(__('Het bedrag kon niet worden berekend.', 'pontifex-oi'));
        }

        if (abs((float)$amountStr - (float)$totals['incl']) > 0.01) {
            throw new \Exception(__('Ongeldig bedrag (BTW).', 'pontifex-oi'));
        }

        try {
            $apiKey = self::get_mollie_api_key();
            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey($apiKey);

            $is_flow2 = empty($order_details['date']) || empty($order_details['location']);

            if ($is_flow2) {
                $description = 'Inschrijving Certipro';
            } else {
                $exam_labels_map = [
                    'los-examen-vca-basis'       => 'VCA Basis',
                    'los-examen-vca-basis-groen' => 'VCA Basis Groen',
                    'los-examen-vca-vol'         => 'VCA Vol',
                    'los-examen-vca-vil'         => 'VCA VIL',
                    'vca-basis-weekend'          => 'Weekendcursus VCA Basis',
                    'vca-vol-weekend'            => 'Weekendcursus VCA Vol',
                ];
                $material_labels_map = [
                    '1'                   => 'Los examen',
                    '2'                   => 'Examen + boek',
                    '4'                   => 'Examen + e-learning',
                    '5'                   => 'Examen + proefexamens',
                    '6'                   => 'Examen + boek + proefexamens',
                    '7'                   => 'Examen + e-learning + proefexamens',
                    'cursus-weekend'      => 'Weekendcursus met examen',
                    'cursus-weekend-nl'   => 'Weekendcursus met examen',
                    'cursus-weekend-en'   => 'Weekend course with exam',
                ];
                $exam_label    = $exam_labels_map[$order_details['exam_type']] ?? 'Examen';
                $material_label = $material_labels_map[$order_details['material']] ?? 'Los examen';
                $description   = $exam_label . ' - ' . $material_label;
            }

            $redirect_url = esc_url_raw($return_url ?: home_url('/bedankt-inschrijven/'));
            $webhook_url  = esc_url_raw(rest_url('pontifex-oi/v1/webhook'));
            $secret       = get_option('pontifex_oi_webhook_secret');
            if (!empty($secret)) {
                $webhook_url = add_query_arg('secret', rawurlencode($secret), $webhook_url);
            }

            $payment = $mollie->payments->create([
                "amount"      => ["currency" => "EUR", "value" => $amountStr],
                "description" => trim($description) ?: 'Inschrijving Certipro',
                "redirectUrl" => $redirect_url,
                "webhookUrl"  => $webhook_url,
                "metadata"    => array_merge($order_details, [
                    'calculated_price_excl' => number_format((float)$totals['excl'], 2, '.', ''),
                    'vat21'                 => number_format((float)$totals['vat21'], 2, '.', ''),
                    'vat_total'             => number_format((float)$totals['vat_total'], 2, '.', ''),
                    'total_incl'            => $amountStr,
                ]),
            ]);

            return ($payment && !empty($payment->id)) ? $payment : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function get_mollie_payment(string $payment_id): ?\Mollie\Api\Resources\Payment
    {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $apiKey = self::get_mollie_api_key();
            if (empty($apiKey)) {
                return null;
            }

            $mollie->setApiKey($apiKey);
            $payment = $mollie->payments->get($payment_id);

            if ($payment->metadata) {
                $payment->metadata = (array) $payment->metadata;
            }

            return $payment;
        } catch (\Exception $e) {
            return null;
        }
    }
}