<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentHelpers {

    public static function is_test_mode() {
        return (bool) get_option('pontifex_oi_mollie_test_mode', false);
    }

    public static function get_mollie_api_key() {
        if (self::is_test_mode()) {
            return get_option('pontifex_oi_mollie_test_api_key');
        }
        return get_option('pontifex_oi_mollie_live_api_key');
    }

    public static function calculate_total_price($order_details) {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;
        if (!isset($EXAM_PRODUCTS) || !isset($MATERIAL_PRODUCTS) || !isset($MATERIAL_COMBIS)) {
            require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
        }

        $exam_type       = $order_details['exam_type'] ?? '';
        $language        = $order_details['language'] ?? 'nl';
        $material_type   = $order_details['material'] ?? '';
        $extra_materials = is_array($order_details['extra_material'] ?? []) ? $order_details['extra_material'] : [];
        $candidate_count = max(1, (int)($order_details['candidate_count'] ?? 1));

        // Normaliseer legacy key
        $exam_type_normalized = $exam_type;
        if ($exam_type === 'los-examen-vil-vcu') {
            $exam_type_normalized = 'los-examen-vca-vil';
        }
        
        $weekend_selected = in_array('cursus-weekend', $extra_materials, true);
        $is_basis_or_vol_exam = in_array($exam_type_normalized, ['los-examen-vca-basis', 'los-examen-vca-vol'], true);
        $is_weekend_exam = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);

        // Basis examenprijs
        $exam_price = 0.0;
        if (isset($EXAM_PRODUCTS[$exam_type_normalized])) {
            $prices = $EXAM_PRODUCTS[$exam_type_normalized]['prices'];
            if ($exam_type_normalized === 'los-examen-vca-vil' && in_array($language, ['nl', 'en'], true)) {
                $exam_price = 139; // Speciale regel
            } else {
                $exam_price = (float)($prices[$language] ?? $prices['nl'] ?? 0);
            }
        }

        // Weekend override: vaste €245 i.p.v. (129/139/…)
        $base = ($weekend_selected && $is_basis_or_vol_exam) ? 245.0 : $exam_price;

        // Materiaal combi
        if (!$is_weekend_exam) { // Apply material combo only if it's not a weekend exam type
            if (!empty($material_type) && $material_type !== '1') {
                $combiKey = $material_type;
                if (in_array($material_type, ['2', '4', '5', '6', '7'], true)) {
                    $suffix = (strpos($exam_type_normalized, 'vca-vol') !== false || strpos($exam_type_normalized, 'vca-vil') !== false) ? 'vol' : 'basis';
                    $combiKey = "{$material_type}_{$suffix}";
                }
                if (isset($MATERIAL_COMBIS[$combiKey])) {
                    foreach ($MATERIAL_COMBIS[$combiKey] as $prodId) {
                        $base += (float)($MATERIAL_PRODUCTS[$prodId]['price'] ?? 0);
                    }
                }
            }
        }
        

        // Extra losse materialen (excl. weekend; weekend zit al in $base bij override)
        foreach ($extra_materials as $extra_id) {
            if ($extra_id === 'cursus-weekend' && $is_basis_or_vol_exam) {
                continue;
            }
            $base += (float)($MATERIAL_PRODUCTS[$extra_id]['price'] ?? 0);
        }

        return round($base * $candidate_count, 2);
    }
    
    public static function create_mollie_payment(float $total_amount, string $return_url, array $order_details, string $customer_email = '', string $description = '') {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        error_log('[Pontifex OI DEBUG] Aangeroepen create_mollie_payment met: ' . print_r([
            'total_amount'   => $total_amount,
            'return_url'     => $return_url,
            'order_details'  => $order_details,
            'customer_email' => $customer_email,
            'description'    => $description,
        ], true));

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $apiKey = self::get_mollie_api_key();
            if (empty($apiKey)) {
                error_log('[Pontifex OI ERROR] Mollie API sleutel is NIET gezet!');
                throw new \Exception('Mollie API sleutel is niet geconfigureerd in de admin instellingen.');
            }
            $mollie->setApiKey($apiKey);
            error_log('[Pontifex OI Debug] Mollie API sleutel ingesteld (eerste 8 karakters): ' . substr($apiKey, 0, 8));

            if (empty($description)) {
                $description = "Inschrijving examen " . ($order_details['exam_type'] ?? 'Onbekend Examen');
            }

            $mollie_payment_data = [
                "amount" => [
                    "currency" => "EUR",
                    "value"    => number_format((float)$total_amount, 2, '.', ''),
                ],
                "description" => $description,
                "redirectUrl" => $return_url,
                "webhookUrl"  => rest_url('pontifex-oi/v1/webhook'),
                "metadata"    => $order_details,
                "billingEmail"=> $customer_email,
            ];
            error_log('[Pontifex OI DEBUG] Mollie payment request: ' . print_r($mollie_payment_data, true));

            $payment = $mollie->payments->create($mollie_payment_data);

            error_log('[Pontifex OI Debug] Mollie betaling aangemaakt: ' . print_r($payment, true));

            if (!$payment || empty($payment->id)) {
                error_log('[Pontifex OI ERROR] Mollie payment object is leeg of heeft geen ID!');
                return false;
            }

            return $payment;

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            error_log('[Pontifex OI ERROR] Mollie API Exception: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | Field: ' . (method_exists($e, 'getField') ? $e->getField() : 'onbekend'));
            return false;
        } catch (\Exception $e) {
            error_log('[Pontifex OI ERROR] Algemene Exception: ' . $e->getMessage());
            return false;
        }
    }

    public static function get_mollie_payment($payment_id) {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $apiKey = self::get_mollie_api_key();
            if (empty($apiKey)) {
                error_log('[Pontifex OI Error] Mollie API sleutel ontbreekt bij ophalen betaling.');
                return false;
            }
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
            error_log('[Pontifex OI ERROR] Mollie API Exception bij ophalen Mollie betaling ' . $payment_id . ': ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('[Pontifex OI ERROR] Algemene fout bij ophalen Mollie betaling ' . $payment_id . ': ' . $e->getMessage());
            return false;
        }
    }
}