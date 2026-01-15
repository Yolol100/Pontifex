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
     * Controleert de testmodus (gebruikt in get_log_context()).
     * @return bool
     */
    public static function is_test_mode(): bool
    {
        // ✅ AANGEPAST: Strikte check op integerwaarde 1.
        return (int) get_option('pontifex_oi_mollie_test_mode', 0) === 1;
    }

    /**
     * Haalt de site URL en de operationele modus op voor logging.
     * @return string
     */
    private static function get_log_context(): string
    {
        $mode = self::is_test_mode() ? 'TEST' : 'LIVE';
        return site_url() . '][' . $mode; // Context zonder de eerste '[' en laatste '] '
    }

    /**
     * Haalt de juiste Mollie API-sleutel op.
     * @return string
     * @throws \RuntimeException Als er geen sleutel is geconfigureerd.
     */
    public static function get_mollie_api_key(): string
    {
        // Caching: Retourneer de sleutel direct als deze al is opgehaald in deze request.
        if (self::$cached_api_key) {
            return self::$cached_api_key;
        }

        $use_test = self::is_test_mode();
        $key = '';

        // 1. Haal key op uit plugininstellingen
        $test = trim((string) get_option('pontifex_oi_mollie_test_api_key', ''));
        $live = trim((string) get_option('pontifex_oi_mollie_live_api_key', ''));
        $key = $use_test ? $test : $live;

        // 2. Fallback: constanten in wp-config.php (modus-specifiek)
        if (!$key) {
            if ($use_test && defined('MOLLIE_TEST_API_KEY')) {
                // Gebruik constant() om type-hinting issues te voorkomen
                $key = (string) constant('MOLLIE_TEST_API_KEY');
            } elseif (!$use_test && defined('MOLLIE_LIVE_API_KEY')) {
                $key = (string) constant('MOLLIE_LIVE_API_KEY');
            }
        }

        // 3. Fallback: omgevingsvariabele (modus-specifiek, MOLLIE_LIVE_API_KEY/MOLLIE_TEST_API_KEY)
        if (!$key) {
            $env_var_name = $use_test ? 'MOLLIE_TEST_API_KEY' : 'MOLLIE_LIVE_API_KEY';
            $env_key = getenv($env_var_name);
            if ($env_key) {
                $key = (string) $env_key;
            }
        }

        // 4. Gooi duidelijke foutmelding als geen key gevonden is
        if (!$key) {
            throw new \RuntimeException(
                'Geen Mollie API-sleutel geconfigureerd. Vul deze in bij Instellingen → Pontifex OI.'
            );
        }

        // Cache de sleutel voordat we deze retourneren
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
     * Retourneert ALTIJD excl BTW totaal.
     * @param array $order_details
     * @return float Excl BTW totaal.
     */
    public static function calculate_total_price(array $order_details): float
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        // ✅ Gecorrigeerde logica: Controleert op alle variabelen en vereist één bestand.
        if (
            !isset($EXAM_PRODUCTS) ||
            !isset($MATERIAL_PRODUCTS) ||
            !isset($MATERIAL_COMBIS) ||
            !isset($EXTRA_PRODUCTS)
        ) {
            // Vereist het producten-prijzen configuratiebestand
            if (defined('PONTIFEX_OI_PATH') && file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
                require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            } else {
                // Failsafe als pad niet gedefinieerd is
                return 0.00;
            }
        }

        $exam_type = $order_details['exam_type'] ?? '';
        $language = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_count = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = is_array($order_details['extra_options'] ?? []) ? $order_details['extra_options'] : [];

        $total = 0.0;

        $aliases = [
            'los-examen-vil-vcu' => 'los-examen-vca-vil',
            'examen-vil' => 'los-examen-vca-vil',
        ];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        // Weekend-detectie (material, extra of exam type)
        $weekend_selected = array_intersect($extra_options, ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh']);
        $is_weekend_exam = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        // 1. Extras toevoegen (skip weekend IDs als weekend active)
        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'], true) && $weekend_active) {
                continue; // FIX: Skip dubbele weekend extra
            }
            if (isset($EXTRA_PRODUCTS[$extra_id])) {
                $total += (float)($EXTRA_PRODUCTS[$extra_id]['price'] ?? 0);
            }
        }

        if ($weekend_active) {
            // FIX: Fixed bundle excl BTW, per kandidaat
            $total += 245.0; // Wordt later geschaald
        } else {
            // Normaal: Exam toevoegen
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

            // Material toevoegen als !=1
            if (!empty($material_type) && $material_type !== '1') {
                $combiKey = $material_type;
                if (in_array($material_type, ['2', '4', '5', '6', '7'], true)) {
                    $suffix = (strpos($exam_type_norm, 'vca-vol') !== false || strpos($exam_type_norm, 'vca-vil') !== false) ? 'vol' : 'basis';
                    $combiKey = "{$material_type}_{$suffix}";
                }

                if (isset($MATERIAL_COMBIS[$combiKey])) {
                    foreach ($MATERIAL_COMBIS[$combiKey] as $prodId) {
                        $add = (float)($MATERIAL_PRODUCTS[$prodId]['price'] ?? 0);
                        $total += $add;
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $add = (float)($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                    $total += $add;
                }
            }
        }

        $total_final = round($total * $candidate_count, 2); // FIX: * count hier, niet eerder

        if ($total_final < 0.01) {
            return 0.00;
        }

        return $total_final;
    }

    /**
     * Aangepast naar één BTW-tarief (21%) per 1 januari 2026
     * @param array $order_details
     * @return array incl/excl + BTW (alleen 21%)
     */
    public static function calculate_totals_with_vat(array $order_details): array
    {
        global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXTRA_PRODUCTS;

        // Vereist het producten-prijzen configuratiebestand
        if (
            !isset($EXAM_PRODUCTS) ||
            !isset($MATERIAL_PRODUCTS) ||
            !isset($MATERIAL_COMBIS) ||
            !isset($EXTRA_PRODUCTS)
        ) {
            if (defined('PONTIFEX_OI_PATH') && file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
                require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            } else {
                // Failsafe als pad niet gedefinieerd is
                return [
                    'excl'     => 0.00,
                    'vat21'    => 0.00,
                    'vat_total' => 0.00,
                    'incl'     => 0.00,
                ];
            }
        }

        $exam_type = $order_details['exam_type'] ?? '';
        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_type = pontifex_normalize_exam_key($exam_type);
        }

        $language      = $order_details['language'] ?? 'nl';
        $material_type = $order_details['material'] ?? '';
        $candidate_cnt = max(1, (int)($order_details['candidate_count'] ?? 1));
        $extra_options = is_array($order_details['extra_options'] ?? []) ? $order_details['extra_options'] : [];

        // Weekend-ids
        $WEEKEND_IDS = ['cursus-weekend','cursus-weekend-nl','cursus-weekend-en','weekend_dh'];

        // Sums zijn per kandidaat (ongeschaald door $candidate_cnt)
        $sum_excl  = 0.0;
        $sum_vat21 = 0.0;

        $aliases = [
            'los-examen-vil-vcu' => 'los-examen-vca-vil',
            'examen-vil'         => 'los-examen-vca-vil',
        ];
        $exam_type_norm = $aliases[$exam_type] ?? $exam_type;

        $weekend_selected = array_intersect($extra_options, $WEEKEND_IDS);
        $is_weekend_exam  = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);
        $weekend_active   = !empty($weekend_selected) || $material_type === 'cursus-weekend' || $is_weekend_exam;

        // ✅ Helper voor nauwkeurige afronding
        $precise_round = function($value, $precision = 2) {
            if (function_exists('bcadd')) {
                return (float) number_format((float) $value, $precision, '.', '');
            }
            return round($value, $precision);
        };

        // 1. Extras toevoegen (skip weekend IDs als weekend active)
        foreach ($extra_options as $extra_id) {
            if (in_array($extra_id, $WEEKEND_IDS, true) && $weekend_active) {
                continue; // FIX: Skip dubbele weekend extra
            }
            if (!isset($EXTRA_PRODUCTS[$extra_id])) continue;

            $p = (float)($EXTRA_PRODUCTS[$extra_id]['price'] ?? 0);
            $sum_excl  += $p;
            $sum_vat21 += $precise_round($p * 0.21, 4);
        }

        if ($weekend_active) {
            // FIX: Fixed bundle excl BTW, per kandidaat (21%)
            $bundle_excl = 245.00; // Prijs per kandidaat
            $sum_excl  += $bundle_excl;
            $sum_vat21 += $precise_round($bundle_excl * 0.21, 4);
        } else {
            // Normaal: Exam toevoegen (21%)
            $exam_price = 0.0;
            if (isset($EXAM_PRODUCTS[$exam_type_norm])) {
                $prices = $EXAM_PRODUCTS[$exam_type_norm]['prices'] ?? [];
                if ($exam_type_norm === 'los-examen-vca-vil' && in_array($language, ['nl','en'], true)) {
                    $exam_price = 139.0;
                } else {
                    $exam_price = (float)($prices[$language] ?? $prices['nl'] ?? 0);
                }
            }
            $sum_excl  += $exam_price;
            $sum_vat21 += $precise_round($exam_price * 0.21, 4);

            // Material toevoegen
            if (!empty($material_type) && $material_type !== '1') {
                $combiKey = $material_type;
                if (in_array($material_type, ['2','4','5','6','7'], true)) {
                    $suffix = (strpos($exam_type_norm, 'vca-vol') !== false || strpos($exam_type_norm, 'vca-vil') !== false) ? 'vol' : 'basis';
                    $combiKey = "{$material_type}_{$suffix}";
                }

                if (isset($MATERIAL_COMBIS[$combiKey])) {
                    foreach ($MATERIAL_COMBIS[$combiKey] as $matKey) {
                        if (!isset($MATERIAL_PRODUCTS[$matKey])) continue;
                        $mp = (float)($MATERIAL_PRODUCTS[$matKey]['price'] ?? 0);
                        $sum_excl  += $mp;
                        $sum_vat21 += $precise_round($mp * 0.21, 4);
                    }
                } elseif (isset($MATERIAL_PRODUCTS[$material_type])) {
                    $mp = (float)($MATERIAL_PRODUCTS[$material_type]['price'] ?? 0);
                    $sum_excl  += $mp;
                    $sum_vat21 += $precise_round($mp * 0.21, 4);
                }
            }
        }

        // 3. Schaal de totale ongeschaalde prijzen/BTW met het aantal kandidaten
        $final_excl     = $precise_round($sum_excl * $candidate_cnt, 2);
        $final_vat21    = $precise_round($sum_vat21 * $candidate_cnt, 2);

        // Totaal BTW = alleen 21%
        $total_vat      = $precise_round($final_vat21, 2);
        $total_incl     = $precise_round($final_excl + $total_vat, 2);

        return [
            'excl'      => $final_excl,
            'vat21'     => $final_vat21,
            'vat_total' => $total_vat,
            'incl'      => $total_incl,
        ];
    }

    /**
     * Creëert een Mollie-betaling.
     *
     * @param float $total_amount Het bedrag (incl BTW).
     * @param string $return_url Redirect URL na betaling.
     * @param array $order_details Bestelgegevens.
     * @param string $customer_email Klant e-mail.
     * @param string $description Betalingsbeschrijving.
     * @return \Mollie\Api\Resources\Payment|null
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

        // START VAN NIEUWE LOGGING & VALIDATIE
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Ingediende order_details: ' . print_r($order_details, true));
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Ingediende total_amount: ' . $total_amount);
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Form POST data: ' . print_r($_POST, true));

        // ✅ Gebruik het bedrag dat via het formulier is meegestuurd (inclusief BTW)
        $amount_incl = (float)$total_amount;
        $amountStr = number_format($amount_incl, 2, '.', '');

        // Herbereken alleen voor controle/logging (niet voor Mollie-bedrag)
        $totals = self::calculate_totals_with_vat($order_details);
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Herberekende totals (controle): ' . print_r($totals, true));

        // Log de Mollie amountStr
        error_log('[Pontifex OI DEBUG][' . $log_context . '] Mollie amountStr (ingediend bedrag): ' . $amountStr);

        // ✅ ZORG DAT DE JUISTE TAAL ALTIJD WORDT MEEGEGEVEN
        if (empty($order_details['language'])) {
            $order_details['language'] = $_POST['language'] ?? $_GET['language'] ?? 'nl';
            error_log('[Pontifex OI DEBUG][' . $log_context . '] Taal toegevoegd aan order_details: ' . $order_details['language']);
        }

        // Controleer op ongeldig bedrag
        if (!preg_match('/^\d+\.\d{2}$/', $amountStr) || (float)$amountStr < 0.01) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Ongeldig Bedrag Berekend (stop) → ' . $amountStr);
            throw new \Exception(__('Het bedrag kon niet worden berekend. Neem contact op met de administratie.', 'pontifex-oi'));
        }

        // Vergelijk ingediende total_amount met herberekende amount (met kleine marge voor afronding)
        $calculated_incl = (float)($totals['incl'] ?? 0.0);
        if (abs((float)$amountStr - $calculated_incl) > 0.01) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Mismatch tussen ingediende total_amount (' . $amountStr . ') en herberekende amount (' . $calculated_incl . ')');
            throw new \Exception(__('Ongeldig bedrag (BTW). Neem contact op met de administratie.', 'pontifex-oi'));
        }

        // ✅ Gecorrigeerde logica: Controleert op alle variabelen en vereist één bestand.
        if (
            !isset($EXAM_PRODUCTS) ||
            !isset($MATERIAL_PRODUCTS) ||
            !isset($EXTRA_PRODUCTS)
        ) {
            if (defined('PONTIFEX_OI_PATH') && file_exists(PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php')) {
                require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
            }
        }

        try {
            $usingTest = self::is_test_mode();

            // Profiteert van de aangepaste get_mollie_api_key() met robuuste fallbacks.
            $apiKey = self::get_mollie_api_key();

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->addVersionString('Pontifex OI/' . (defined('PONTIFEX_OI_VERSION') ? PONTIFEX_OI_VERSION : 'dev'));
            $mollie->setApiKey($apiKey);

            // Controle op API key consistentie
            if ($usingTest && strpos($apiKey, 'test_') !== 0) {
                throw new \RuntimeException('Testmodus staat aan, maar de sleutel is geen test_* sleutel.');
            }
            if (!$usingTest && strpos($apiKey, 'live_') !== 0) {
                throw new \RuntimeException('Live-modus staat aan, maar de sleutel is geen live_* sleutel.');
            }

            // ✅ BESCHRIJVING VOOR MOLLIE
            // Flow 1: "[Examensoort] - [Soort examen]" (bijv. "VCA Basis - Los examen")
            // Flow 2: "Inschrijving Certipro"
            $exam_type_raw  = $order_details['exam_type'] ?? '';
            $material_raw   = $order_details['material'] ?? '';
            $extra_options  = $order_details['extra_options'] ?? [];
            $date           = $order_details['date'] ?? '';
            $time           = $order_details['time'] ?? '';
            $location       = $order_details['location'] ?? '';

            // ✅ VERBETERDE FLOW DETECTIE:
            // Flow 2 = geen datum OF geen locatie (directe link via checkboxes)
            // Flow 1 = heeft datum EN locatie (via planning tabel)
            $is_flow2 = empty($date) || empty($location);

            // DEBUG LOGGING
            error_log('[Pontifex OI DEBUG][' . $log_context . '] Mollie beschrijving bepaling: ' . json_encode([
                'exam_type' => $exam_type_raw,
                'material'  => $material_raw,
                'date'      => $date,
                'time'      => $time,
                'location'  => $location,
                'is_flow2'  => $is_flow2
            ]));

            if ($is_flow2) {
                // Flow 2: Altijd simpele beschrijving
                $description = 'Inschrijving Certipro';
                error_log('[Pontifex OI DEBUG][' . $log_context . '] Flow 2 gedetecteerd → Beschrijving: ' . $description);
            } else {
                // Flow 1: Samenstellen uit dropdown-labels
                global $EXAM_PRODUCTS;

                // Mapping voor examensoort labels (matcht dropdown in stap 1)
                $exam_labels_map = [
                    'los-examen-vca-basis'     => 'VCA Basis',
                    'los-examen-vca-basis-groen' => 'VCA Basis Groen',
                    'los-examen-vca-vol'       => 'VCA Vol',
                    'los-examen-vca-vil'       => 'VCA VIL',
                    'vca-basis-weekend'        => 'Weekendcursus VCA Basis',
                    'vca-vol-weekend'          => 'Weekendcursus VCA Vol',
                ];

                // Mapping voor soort examen labels (matcht dropdown "material")
                $material_labels_map = [
                    '1'                => 'Los examen',
                    '2'                => 'Examen + boek',
                    '4'                => 'Examen + e-learning',
                    '5'                => 'Examen + proefexamens',
                    '6'                => 'Examen + boek + proefexamens',
                    '7'                => 'Examen + e-learning + proefexamens',
                    'cursus-weekend'   => 'Weekendcursus met examen',
                    'cursus-weekend-nl'=> 'Weekendcursus met examen',
                    'cursus-weekend-en'=> 'Weekend course with exam',
                ];

                $exam_label    = $exam_labels_map[$exam_type_raw] ?? ($EXAM_PRODUCTS[$exam_type_raw]['label'] ?? 'Examen');
                $material_label = $material_labels_map[$material_raw] ?? 'Los examen';

                // Format: "VCA Basis - Los examen"
                $description = $exam_label . ' - ' . $material_label;
                error_log('[Pontifex OI DEBUG][' . $log_context . '] Flow 1 gedetecteerd → Beschrijving: ' . $description);
            }

            // Fallback & sanity-check voor redirectUrl en webhookUrl
            $redirect_url = esc_url_raw($return_url ?: home_url('/bedankt-inschrijven/'));

            // START VAN DE GEWIJZIGDE CODE (Webhook URL)
            $webhook_url = esc_url_raw(rest_url('pontifex-oi/v1/webhook'));
            $secret = get_option('pontifex_oi_webhook_secret');
            if (!empty($secret)) {
                $webhook_url = add_query_arg('secret', rawurlencode($secret), $webhook_url);
            }
            // EINDE VAN DE GEWIJZIGDE CODE

            if (empty($redirect_url) || !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                error_log('[Pontifex OI ERROR][' . $log_context . '] Ongeldige Redirect URL: ' . $redirect_url);
                throw new \RuntimeException('Ongeldige redirect URL');
            }

            if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                error_log('[Pontifex OI ERROR][' . $log_context . '] Ongeldige Webhook URL: ' . $webhook_url);
                throw new \RuntimeException('Ongeldige webhook URL');
            }

            // wp_remote_head() check: Blijft conditioneel op WP_DEBUG
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $response = wp_remote_head($webhook_url);
                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) >= 400) {
                    error_log('[Pontifex OI WARNING][' . $log_context . '] Webhook URL niet bereikbaar: ' . $webhook_url . ' (Code: ' . (is_wp_error($response) ? 'WP_ERROR' : wp_remote_retrieve_response_code($response)) . ')');
                }
            }

            $mollie_payment_data = [
                "amount" => [
                    "currency" => "EUR",
                    // HIER wordt $amountStr (INCL. BTW) gebruikt (uit de validatiestap)
                    "value" => $amountStr,
                ],
                // Fallback met trim() om lege strings te vangen
                "description"  => trim($description) ?: 'Inschrijving Certipro',
                "redirectUrl"  => $redirect_url,
                "webhookUrl"   => $webhook_url,
                // START VAN DE GEWIJZIGDE CODE (Metadata) - geen vat9 meer
                "metadata" => array_merge($order_details, [
                    'calculated_price_excl' => number_format((float)$totals['excl'], 2, '.', ''),
                    'vat21'                 => number_format((float)$totals['vat21'], 2, '.', ''),
                    'vat_total'             => number_format((float)$totals['vat_total'], 2, '.', ''),
                    'total_incl'            => $amountStr,
                ]),
                // EINDE VAN DE GEWIJZIGDE CODE
            ];

            $payment = $mollie->payments->create($mollie_payment_data);

            if (!$payment || empty($payment->id)) {
                if (isset($payment) && is_object($payment)) {
                    // Log de response alleen bij een foutconditie
                    error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie Response Object (Fout): ' . print_r($payment, true));
                }
                error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie Payment Object is leeg of heeft geen ID!');
                return null;
            }

            return $payment;

        } catch (\Mollie\Api\Exceptions\ApiException $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie API Exception: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | Field: ' . (method_exists($e, 'getField') ? $e->getField() : 'onbekend'));
            return null;
        } catch (\Exception $e) {
            error_log('[Pontifex OI ERROR][' . $log_context . '] Algemene Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Haalt een Mollie-betaling op.
     *
     * @param string $payment_id Het Mollie-betaling ID.
     * @return \Mollie\Api\Resources\Payment|null
     */
    public static function get_mollie_payment(string $payment_id): ?\Mollie\Api\Resources\Payment
    {
        require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

        $log_context = self::get_log_context();

        try {
            $mollie = new \Mollie\Api\MollieApiClient();
            $apiKey = self::get_mollie_api_key(); // Profiteert van de cache

            if (empty($apiKey)) {
                error_log('[Pontifex OI Error][' . $log_context . '] Mollie API Sleutel ontbreekt bij ophalen betaling.');
                return null;
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
            error_log('[Pontifex OI ERROR][' . $log_context . '] Mollie API Exception bij ophalen Mollie Betaling ' . $payment_id . ': ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            // Logconsistentie: 'Algemene fout' met kleine 'f'
            error_log('[Pontifex OI ERROR][' . $log_context . '] Algemene fout bij ophalen Mollie Betaling ' . $payment_id . ': ' . $e->getMessage());
            return null;
        }
    }
}