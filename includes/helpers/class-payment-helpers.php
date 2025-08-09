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

        $total_price = 0.00;

        $exam_type       = $order_details['exam_type'] ?? '';
        $language        = $order_details['language'] ?? 'nl';
        $material_type   = $order_details['material'] ?? '';
        $extra_materials = $order_details['extra_material'] ?? [];
        $candidate_count = $order_details['candidate_count'] ?? 1;

        // Basis examen prijs
        if (isset($EXAM_PRODUCTS[$exam_type])) {
            $prices     = $EXAM_PRODUCTS[$exam_type]['prices'];
            $exam_price = $prices[$language] ?? $prices['nl'] ?? 0;
            $total_price += $exam_price;
        } else {
            error_log('[Pontifex OI Error] Onbekend examen type: ' . $exam_type . ' in calculate_total_price.');
        }

        $is_weekend_cursus = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);

        if (!$is_weekend_cursus) {
            $combiKey = $material_type;
            if (in_array($material_type, ['2', '4', '5', '6', '7'], true)) {
                $suffix  = (strpos($exam_type, 'vca-vol') !== false) ? 'vol' : 'basis';
                $combiKey = "{$material_type}_{$suffix}";
            }

            if (isset($MATERIAL_COMBIS[$combiKey])) {
                foreach ($MATERIAL_COMBIS[$combiKey] as $prod) {
                    if (isset($MATERIAL_PRODUCTS[$prod])) {
                        $item_price  = $MATERIAL_PRODUCTS[$prod]['price'] ?? 0;
                        $total_price += $item_price;
                    } else {
                        error_log('[Pontifex OI Error] Onbekend product in materiaal combinatie: ' . $prod);
                    }
                }
            }
        }

        // Extra materialen
        foreach ($extra_materials as $extra_mat_id) {
            if (isset($MATERIAL_PRODUCTS[$extra_mat_id]['price'])) {
                $total_price += $MATERIAL_PRODUCTS[$extra_mat_id]['price'];
            } else {
                error_log('[Pontifex OI Error] Onbekend extra materiaal ID: ' . $extra_mat_id . ' in calculate_total_price.');
            }
        }

        $total_price *= max(1, (int) $candidate_count);

        error_log('[Pontifex OI Debug] Berekende totale prijs (in PaymentHelpers): ' . $total_price);
        return (float) $total_price;
    }

    /**
     * Creëert een Mollie-betaling.
     */
    public static function create_mollie_payment(float $total_amount, string $return_url, array $order_details, string $customer_email = '', string $description = '') {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        // --- DEBUG: Toon ALLE inputwaarden ---
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