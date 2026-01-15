<?php
/**
 * PaymentHelpers - Beheer van Mollie betalingen, prijsberekeningen en BTW (2026+)
 *
 * @package PontifexOI
 * @since 2026-01-01
 */
declare(strict_types=1);

namespace PontifexOI\Helpers;

use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

if (!defined('ABSPATH')) {
    exit;
}

// Zorg dat de Registrations class beschikbaar is (zelfde namespace)
use PontifexOI\Helpers\Registrations;

class PaymentHelpers
{
    private static ?string $cached_api_key = null;

    public static function is_test_mode(): bool
    {
        return (int) get_option('pontifex_oi_mollie_test_mode', 0) === 1;
    }

    private static function get_log_context(): string
    {
        $mode = self::is_test_mode() ? 'TEST' : 'LIVE';
        return site_url() . '][' . $mode;
    }

    /**
     * Haalt de juiste Mollie API-sleutel op met robuuste fallbacks + caching
     *
     * @throws \RuntimeException
     */
    public static function get_mollie_api_key(): string
    {
        if (self::$cached_api_key !== null) {
            return self::$cached_api_key;
        }

        $use_test = self::is_test_mode();

        // 1. Plugin instellingen
        $test_key = trim((string) get_option('pontifex_oi_mollie_test_api_key', ''));
        $live_key = trim((string) get_option('pontifex_oi_mollie_live_api_key', ''));
        $key = $use_test ? $test_key : $live_key;

        // 2. wp-config.php constanten
        if (!$key) {
            $const_name = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            if (defined($const_name)) {
                $key = (string) constant($const_name);
            }
        }

        // 3. Omgevingsvariabele
        if (!$key) {
            $env_var = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            $env_key = getenv($env_var);
            if ($env_key !== false && $env_key !== '') {
                $key = (string) $env_key;
            }
        }

        if ($key === '') {
            $mode_str = $use_test ? 'test' : 'live';
            throw new \RuntimeException(
                "Geen Mollie {$mode_str} API-sleutel gevonden. " .
                "Configureer deze in Instellingen → Pontifex OI of in wp-config.php."
            );
        }

        return self::$cached_api_key = $key;
    }

    public static function is_configured(): bool
    {
        try {
            return self::get_mollie_api_key() !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Berekent totaalprijs excl. BTW (per 2026 alleen 21% BTW van toepassing)
     */
    public static function calculate_total_price(array $order_details): float
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        if (
            !isset($EXAM_PRODUCTS) ||
            !isset($MATERIAL_PRODUCTS) ||
            !isset($MATERIAL_COMBIS) ||
            !isset($EXTRA_PRODUCTS)
        ) {
            $config_path = defined('PONTIFEX_OI_PATH')
                ? PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php'
                : '';
            if ($config_path && file_exists($config_path)) {
                require_once $config_path;
            } else {
                return 0.00;
            }
        }

        $exam_type     = $order_details['exam_type'] ?? '';
        $language      = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_cnt = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = (array)($order_details['extra_options'] ?? []);

        $total = 0.0;

        // Aliassen voor oude/ongeldige keys
        $aliases = [
            'los-examen-vil-vcu' => 'los-examen-vca-vil',
            'examen-vil'         => 'los-examen-vca-vil',
        ];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        // Weekend detectie
        $weekend_ids    = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $weekend_selected = array_intersect($extra_options, $weekend_ids);
        $is_weekend_exam  = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active   = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        // 1. Extra opties (weekend overslaan als weekend actief is)
        foreach ($extra_options as $id) {
            if (in_array($id, $weekend_ids, true) && $weekend_active) {
                continue;
            }
            $total += (float)($EXTRA_PRODUCTS[$id]['price'] ?? 0);
        }

        if ($weekend_active) {
            $total += 245.0;
        } else {
            // Normaal examen
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                $exam_price = ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl', 'en'], true))
                    ? 139.0
                    : (float)($prices[$language] ?? $prices['nl'] ?? 0);
            }
            $total += $exam_price;

            // Materiaal toevoegen
            if ($material_type !== '' && $material_type !== '1') {
                $combi_key = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'], true)) {
                    $suffix = str_contains($exam_type_norm, 'vca-vol') || str_contains($exam_type_norm, 'vca-vil')
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

        $final_total = round($total * $candidate_cnt, 2);
        return $final_total < 0.01 ? 0.00 : $final_total;
    }

    /**
     * Berekent totaal incl. alleen 21% BTW (2026+ situatie)
     *
     * @return array{ excl: float, vat21: float, vat_total: float, incl: float }
     */
    public static function calculate_totals_with_vat(array $order_details): array
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        if (
            !isset($EXAM_PRODUCTS) ||
            !isset($MATERIAL_PRODUCTS) ||
            !isset($MATERIAL_COMBIS) ||
            !isset($EXTRA_PRODUCTS)
        ) {
            $config_path = defined('PONTIFEX_OI_PATH')
                ? PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php'
                : '';
            if ($config_path && file_exists($config_path)) {
                require_once $config_path;
            } else {
                return [
                    'excl'      => 0.00,
                    'vat21'     => 0.00,
                    'vat_total' => 0.00,
                    'incl'      => 0.00,
                ];
            }
        }

        $exam_type     = $order_details['exam_type'] ?? '';
        $language      = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_cnt = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = (array)($order_details['extra_options'] ?? []);

        $sum_excl   = 0.0;
        $sum_vat21  = 0.0;

        $exam_type_norm = match ($exam_type) {
            'los-examen-vil-vcu', 'examen-vil' => 'los-examen-vca-vil',
            default => $exam_type,
        };

        $weekend_ids      = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
        $weekend_selected = array_intersect($extra_options, $weekend_ids);
        $is_weekend_exam  = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active   = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        $precise_round = static fn(float $value, int $decimals = 2): float => round($value, $decimals);

        // 1. Extra opties
        foreach ($extra_options as $id) {
            if (in_array($id, $weekend_ids, true) && $weekend_active) {
                continue;
            }
            $price = (float)($EXTRA_PRODUCTS[$id]['price'] ?? 0);
            $sum_excl  += $price;
            $sum_vat21 += $precise_round($price * 0.21);
        }

        if ($weekend_active) {
            $bundle_excl = 245.00;
            $sum_excl  += $bundle_excl;
            $sum_vat21 += $precise_round($bundle_excl * 0.21);
        } else {
            // Examen
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                $exam_price = ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl', 'en'], true))
                    ? 139.0
                    : (float)($prices[$language] ?? $prices['nl'] ?? 0);
            }
            $sum_excl  += $exam_price;
            $sum_vat21 += $precise_round($exam_price * 0.21);

            // Materiaal
            if ($material_type !== '' && $material_type !== '1') {
                $combi_key = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'], true)) {
                    $suffix = str_contains($exam_type_norm, 'vca-vol') || str_contains($exam_type_norm, 'vca-vil')
                        ? 'vol'
                        : 'basis';
                    $combi_key = "{$material_type}_{$suffix}";
                }

                if (isset($MATERIAL_COMBIS[$combi_key])) {
                    foreach ($MATERIAL_COMBIS[$combi_key] as $prod_id) {
                        $price = (float)($MATERIAL_PRODUCTS[$prod_id]['price'] ?? 0);
                        $sum_excl  += $price;
                        $sum_vat21 += $precise_round($price * 0.21);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $price = (float)($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                    $sum_excl  += $price;
                    $sum_vat21 += $precise_round($price * 0.21);
                }
            }
        }

        $final_excl   = $precise_round($sum_excl * $candidate_cnt);
        $final_vat21  = $precise_round($sum_vat21 * $candidate_cnt);
        $total_vat    = $precise_round($final_vat21);
        $total_incl   = $precise_round($final_excl + $total_vat);

        return [
            'excl'      => $final_excl,
            'vat21'     => $final_vat21,
            'vat_total' => $total_vat,
            'incl'      => $total_incl,
        ];
    }

    /**
     * Creëert een nieuwe Mollie betaling (2026 versie – alleen 21% BTW)
     */
    public static function create_mollie_payment(
        float $total_amount,
        string $return_url,
        array $order_details,
        string $customer_email = '',
        string $description = ''
    ): ?Payment
    {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $log_context = self::get_log_context();

        error_log("[Pontifex OI DEBUG][{$log_context}] Order details: " . print_r($order_details, true));
        error_log("[Pontifex OI DEBUG][{$log_context}] Ontvangen totaal bedrag (incl): {$total_amount}");

        $amount_incl = (float) $total_amount;
        $amount_str  = number_format($amount_incl, 2, '.', '');

        // Controle herberekening
        $calculated = self::calculate_totals_with_vat($order_details);
        $calc_incl  = (float) ($calculated['incl'] ?? 0);

        error_log("[Pontifex OI DEBUG][{$log_context}] Herberekend totaal incl: {$calc_incl}");

        if (abs($amount_incl - $calc_incl) > 0.05) {
            error_log("[Pontifex OI ERROR][{$log_context}] Bedrag mismatch! Ingediend: {$amount_incl} vs berekend: {$calc_incl}");
            throw new Exception(__('Het berekende bedrag komt niet overeen met de order. Neem contact op met de administratie.', 'pontifex-oi'));
        }

        if (!preg_match('/^\d+\.\d{2}$/', $amount_str) || $amount_incl < 0.01) {
            error_log("[Pontifex OI ERROR][{$log_context}] Ongeldig bedrag: {$amount_str}");
            throw new Exception(__('Kan geen geldig bedrag berekenen.', 'pontifex-oi'));
        }

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey(self::get_mollie_api_key());
            $mollie->addVersionString('Pontifex OI/' . (defined('PONTIFEX_OI_VERSION') ? PONTIFEX_OI_VERSION : 'dev'));

            // Taal fallback
            $order_details['language'] ??= $_POST['language'] ?? $_GET['language'] ?? 'nl';

            // Beschrijving genereren
            $exam_type = $order_details['exam_type'] ?? '';
            $material  = $order_details['material'] ?? '';
            $date      = $order_details['date'] ?? '';
            $location  = $order_details['location'] ?? '';

            $is_flow2 = empty($date) || empty($location);

            $description = $is_flow2
                ? 'Inschrijving Certipro'
                : self::generate_exam_description($exam_type, $material);

            $redirect_url = esc_url_raw($return_url ?: home_url('/bedankt-inschrijven/'));
            $webhook_url  = esc_url_raw(rest_url('pontifex-oi/v1/webhook'));

            $secret = get_option('pontifex_oi_webhook_secret');
            if ($secret !== '') {
                $webhook_url = add_query_arg('secret', rawurlencode($secret), $webhook_url);
            }

            $payment_data = [
                'amount' => [
                    'currency' => 'EUR',
                    'value'    => $amount_str,
                ],
                'description' => trim($description) ?: 'Inschrijving Certipro',
                'redirectUrl' => $redirect_url,
                'webhookUrl'  => $webhook_url,
                'metadata'    => [
                    ...$order_details,
                    'calculated_price_excl' => number_format($calculated['excl'], 2, '.', ''),
                    'vat21'                 => number_format($calculated['vat21'], 2, '.', ''),
                    'vat_total'             => number_format($calculated['vat_total'], 2, '.', ''),
                    'total_incl'            => $amount_str,
                ],
            ];

            $payment = $mollie->payments->create($payment_data);

            // Optioneel: direct opslaan in Registrations (als die bestaat)
            if (class_exists(Registrations::class)) {
                Registrations::save($payment, $order_details);
            }

            return $payment;
        } catch (ApiException $e) {
            error_log("[Pontifex OI ERROR][{$log_context}] Mollie API fout: {$e->getMessage()}");
            return null;
        } catch (\Throwable $e) {
            error_log("[Pontifex OI ERROR][{$log_context}] Fout bij aanmaken betaling: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Genereert leesbare betalingsbeschrijving voor Flow 1
     */
    private static function generate_exam_description(string $exam_type, string $material): string
    {
        $exam_labels = [
            'los-examen-vca-basis'       => 'VCA Basis',
            'los-examen-vca-basis-groen' => 'VCA Basis Groen',
            'los-examen-vca-vol'         => 'VCA Vol',
            'los-examen-vca-vil'         => 'VCA VIL',
            'vca-basis-weekend'          => 'Weekendcursus VCA Basis',
            'vca-vol-weekend'            => 'Weekendcursus VCA Vol',
        ];

        $material_labels = [
            '1'             => 'Los examen',
            '2'             => 'Examen + boek',
            '4'             => 'Examen + e-learning',
            '5'             => 'Examen + proefexamens',
            '6'             => 'Examen + boek + proefexamens',
            '7'             => 'Examen + e-learning + proefexamens',
            'cursus-weekend' => 'Weekendcursus met examen',
        ];

        $exam_label     = $exam_labels[$exam_type]     ?? 'Examen';
        $material_label = $material_labels[$material] ?? 'Los examen';

        return $exam_label . ' - ' . $material_label;
    }

    /**
     * Haalt een bestaande Mollie betaling op
     */
    public static function get_mollie_payment(string $payment_id): ?Payment
    {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $log_context = self::get_log_context();

        try {
            $mollie = new MollieApiClient();
            $mollie->setApiKey(self::get_mollie_api_key());

            $payment = $mollie->payments->get($payment_id);

            if ($payment->metadata) {
                $payment->metadata = (array) $payment->metadata;
            } else {
                $payment->metadata = [];
            }

            return $payment;
        } catch (ApiException $e) {
            error_log("[Pontifex OI ERROR][{$log_context}] Mollie ophalen fout: {$e->getMessage()}");
            return null;
        } catch (\Throwable $e) {
            error_log("[Pontifex OI ERROR][{$log_context}] Algemene fout ophalen betaling: {$e->getMessage()}");
            return null;
        }
    }
}