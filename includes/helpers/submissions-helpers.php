<?php
/**
 * Submission Helpers - Verwerking van inzendingen payloads
 *
 * Centrale class voor het omzetten van ruwe order/submission data naar:
 * - CSV-rijen
 * - Platte gelabelde velden (detailweergave)
 * - Gestructureerde data voor inzendingen-kaarten
 *
 * @package PontifexOI
 * @since   2026-01-15
 */

declare(strict_types=1);

namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class SubmissionHelpers
{
    /**
     * Centrale mapping van interne veldnamen naar mooie labels
     * en alternatieve keys (voor oude/nieuwe formulieren)
     */
    private const FIELD_MAP = [
        'first_name'           => ['label' => 'Voornaam',           'aliases' => ['given_name', 'candidate_firstname']],
        'infix'                => ['label' => 'Tussenvoegsel',      'aliases' => ['candidate_infix']],
        'last_name'            => ['label' => 'Achternaam',         'aliases' => ['family_name', 'candidate_lastname']],
        'birthdate'            => ['label' => 'Geboortedatum',      'aliases' => ['candidate_birthdate']],
        'postcode'             => ['label' => 'Postcode',           'aliases' => ['postal-code']],
        'housenumber'          => ['label' => 'Huisnummer',         'aliases' => ['address-line2', 'number']],
        'street'               => ['label' => 'Straat',             'aliases' => ['street-address']],
        'city'                 => ['label' => 'Plaats',             'aliases' => ['address-level2']],
        'phone'                => ['label' => 'Telefoonnummer',     'aliases' => ['tel']],
        'email'                => ['label' => 'E-mailadres',        'aliases' => ['order_email']],
        'exam_type'            => ['label' => 'Examen'],
        'language'             => ['label' => 'Taal'],
        'material'             => ['label' => 'Lesmateriaal'],
        'location'             => ['label' => 'Locatie'],
        'planning_date'        => ['label' => 'Datum',              'aliases' => ['date']],
        'planning_time'        => ['label' => 'Tijd',               'aliases' => ['time']],
        'extra_options'        => ['label' => 'Extra lesmateriaal'],
        'extra_option_direct'  => ['label' => 'Extra lesmateriaal'],
        'calculated_price_excl'=> ['label' => 'Prijs excl. BTW'],
        'vat21'                => ['label' => 'BTW (21%)'],
        'total'                => ['label' => 'Totaal',             'aliases' => ['payment_amount', 'calculated_price']],
    ];

    /**
     * Keys die nooit getoond moeten worden in kaarten / details
     */
    private const BLACKLIST_KEYS = [
        'nonce', '_nonce', 'security',
        'spots', 'spot', 'spot_count', 'beschikbare_spots',
        'order_id', 'orderId', 'mollie_id', 'payment_id', 'paymentId',
        'transaction_id', 'tr_id',
        'flow', 'flow2', 'flow_version',
        'name', 'organization', 'organization-title',
        'vat_total', 'total_incl', 'candidate_count',
    ];

    /**
     * Haalt een waarde op uit de payload, rekening houdend met alias keys
     */
    private static function get_val(array $payload, string $key): mixed
    {
        if (isset($payload[$key])) {
            return $payload[$key];
        }

        $map = self::FIELD_MAP[$key] ?? [];
        foreach ($map['aliases'] ?? [] as $alias) {
            if (isset($payload[$alias])) {
                return $payload[$alias];
            }
        }

        return null;
    }

    /**
     * Probeert volledige naam te reconstrueren (nieuw of oud formulier)
     */
    private static function get_full_name(array $payload): string
    {
        $voornaam = (string) self::get_val($payload, 'first_name');
        $tussen   = (string) self::get_val($payload, 'infix');
        $achter   = (string) self::get_val($payload, 'last_name');

        $name = trim(implode(' ', array_filter([$voornaam, $tussen, $achter])));

        if ($name === '') {
            $candidate_full = self::get_val($payload, 'candidate_fullname');
            if (is_array($candidate_full)) {
                $name = implode(' ', array_filter($candidate_full));
            } elseif (is_string($candidate_full)) {
                $name = $candidate_full;
            }
        }

        return $name !== '' ? $name : 'Onbekend';
    }

    /**
     * Extraheert kandidaten (ondersteunt zowel enkel als meervoudig)
     */
    private static function extract_candidates(array $payload): array
    {
        $candidates = [];

        // Nieuw formulier: enkele kandidaat
        if (isset($payload['first_name']) || isset($payload['last_name'])) {
            $candidates[] = [
                'first_name'   => self::get_val($payload, 'first_name'),
                'infix'        => self::get_val($payload, 'infix'),
                'last_name'    => self::get_val($payload, 'last_name'),
                'birthdate'    => self::get_val($payload, 'birthdate'),
            ];
        }
        // Oud formulier: meerdere kandidaten in arrays
        elseif (isset($payload['candidate_fullname']) || isset($payload['candidate_lastname'])) {
            $full   = (array) ($payload['candidate_fullname'] ?? []);
            $infix  = (array) ($payload['candidate_infix'] ?? []);
            $last   = (array) ($payload['candidate_lastname'] ?? []);
            $birth  = (array) ($payload['candidate_birthdate'] ?? []);

            $count = max(count($full), count($last), 1);

            for ($i = 0; $i < $count; $i++) {
                $candidates[] = [
                    'first_name'   => $full[$i] ?? '',
                    'infix'        => $infix[$i] ?? '',
                    'last_name'    => $last[$i] ?? '',
                    'birthdate'    => $birth[$i] ?? '',
                ];
            }
        }

        return $candidates;
    }

    /**
     * Converteert order naar CSV-rijen (één rij per kandidaat)
     */
    public static function order_to_rows(array $order): array
    {
        $rows = [];
        $candidates = self::extract_candidates($order);

        foreach ($candidates as $candidate) {
            $rows[] = [
                $candidate['first_name']   ?? '',
                $candidate['infix']        ?? '',
                $candidate['last_name']    ?? '',
                $candidate['birthdate']    ?? '',
                self::get_val($order, 'postcode')     ?? '',
                self::get_val($order, 'housenumber')  ?? '',
                self::get_val($order, 'street')       ?? '',
                self::get_val($order, 'city')         ?? '',
                self::get_val($order, 'phone')        ?? '',
                self::get_val($order, 'email')        ?? '',
                $order['organization']                ?? '',
                $order['organization-title']          ?? '',
                $order['order_vat']                   ?? '',
                $order['exam_type']                   ?? '',
                $order['language']                    ?? '',
                $order['material']                    ?? '',
                implode(', ', (array)($order['extra_options'] ?? [])),
                $order['location']                    ?? '',
                $order['planning_date'] ?? $order['date'] ?? '',
                $order['planning_time'] ?? $order['time'] ?? '',
                $order['total'] ?? $order['payment_amount'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * Maakt platte lijst met gelabelde velden voor detailweergave
     */
    public static function flatten_payload(array $payload, array $meta = []): array
    {
        $pretty = [];

        // Aangemaakt datum bovenaan
        if (!empty($meta['created_at'])) {
            $pretty[] = ['label' => 'Aangemaakt', 'value' => $meta['created_at']];
        }

        // Volledige naam
        $name = self::get_full_name($payload);
        if ($name !== 'Onbekend') {
            $pretty[] = ['label' => 'Naam', 'value' => $name];
        }

        // Centrale loop over bekende velden
        foreach (self::FIELD_MAP as $key => $info) {
            $value = self::get_val($payload, $key);

            // Speciale behandeling voor extra_options
            if ($key === 'extra_options' && is_array($value)) {
                $value = implode(', ', array_filter($value));
            }

            if (empty($value) || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $pretty[] = [
                'label' => $info['label'],
                'value' => is_array($value) ? implode(', ', $value) : (string)$value,
            ];
        }

        // Overige velden die niet in FIELD_MAP staan (maar niet op blacklist)
        $blacklist = array_flip(self::BLACKLIST_KEYS);
        foreach ($payload as $k => $v) {
            if (isset($blacklist[$k])) {
                continue;
            }
            if (array_key_exists($k, self::FIELD_MAP)) {
                continue; // al behandeld
            }
            if (empty($v) || (is_string($v) && trim($v) === '')) {
                continue;
            }
            $label = ucwords(str_replace(['_', '-'], ' ', $k));
            $pretty[] = ['label' => $label, 'value' => is_array($v) ? implode(', ', $v) : (string)$v];
        }

        // Totaal altijd onderaan
        $total = self::get_val($payload, 'total');
        if ($total !== null && $total !== '') {
            $pretty[] = ['label' => 'Totaal', 'value' => (string)$total];
        }

        return [
            'title'  => $name,
            'fields' => $pretty,
        ];
    }

    /**
     * Formatteert data specifiek voor inzendingen-kaarten
     */
    public static function format_for_cards(array $payload): array
    {
        $blacklist = array_flip(self::BLACKLIST_KEYS);

        $data = [
            'name'         => self::get_full_name($payload),
            'email'        => self::get_val($payload, 'email') ?? '',
            'exam_label'   => self::exam_label(self::get_val($payload, 'exam_type') ?? ''),
            'date'         => self::get_val($payload, 'planning_date') ?? self::get_val($payload, 'date') ?? '',
            'time'         => self::get_val($payload, 'planning_time') ?? self::get_val($payload, 'time') ?? '',
            'location'     => self::get_val($payload, 'location') ?? '',
            'total'        => self::get_val($payload, 'total') ?? '',
            'material'     => self::material_info(
                self::get_val($payload, 'material') ?? '',
                self::get_val($payload, 'exam_type') ?? ''
            ),
            'extras'       => self::extra_info(self::get_val($payload, 'extra_options') ?? []),
            'extra_fields' => [],
        ];

        // Overige velden die niet in de hoofdstructuur zitten
        foreach ($payload as $k => $v) {
            if (isset($blacklist[$k])) {
                continue;
            }
            if (array_key_exists($k, self::FIELD_MAP)) {
                continue;
            }
            if (empty($v)) {
                continue;
            }

            $label = ucwords(str_replace(['_', '-'], ' ', $k));
            $data['extra_fields'][] = [
                'label' => $label,
                'value' => is_array($v) ? implode(', ', $v) : (string)$v,
            ];
        }

        return $data;
    }

    // === HULPFUNCTIES (zoals in origineel) ==================================

    private static function exam_label(string $exam_key): string
    {
        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_key = pontifex_normalize_exam_key($exam_key);
        }

        $map = [
            'los-examen-vca-basis'         => 'VCA Basis (los examen)',
            'los-examen-vca-basis-groen'   => 'VCA Basis Groen (los examen)',
            'los-examen-vca-vol'           => 'VCA Vol (los examen)',
            'los-examen-vca-vil'           => 'VCA VIL (los examen)',
            'vca-basis-weekend'            => 'Weekendcursus VCA Basis',
            'vca-vol-weekend'              => 'Weekendcursus VCA Vol',
        ];

        return $map[$exam_key] ?? $exam_key;
    }

    private static function material_info(?string $material_key, string $exam_key): array
    {
        if (!$material_key || $material_key === '1') {
            return ['label' => 'Geen lesmateriaal', 'items' => [], 'sum' => 0.0];
        }

        $items = [];
        $sum   = 0.0;

        $combi_key = $material_key;
        if (in_array($material_key, ['2','4','5','6','7'], true)) {
            $suffix = (str_contains($exam_key, 'vol') || str_contains($exam_key, 'vil')) ? 'vol' : 'basis';
            $combi_key = "{$material_key}_{$suffix}";
        }

        // Probeer combi’s (fallback op lege array)
        $combi = $GLOBALS['MATERIAL_COMBIS'][$combi_key] ?? [];
        if (!empty($combi) && is_array($combi)) {
            foreach ($combi as $prod_key) {
                $label = $GLOBALS['MATERIAL_PRODUCTS'][$prod_key]['label'] ?? $prod_key;
                $price = (float) ($GLOBALS['MATERIAL_PRODUCTS'][$prod_key]['price'] ?? 0);
                $items[] = ['label' => $label, 'price' => $price];
                $sum += $price;
            }
        } else {
            $label = $GLOBALS['MATERIAL_PRODUCTS'][$material_key]['label'] ?? $material_key;
            $price = (float) ($GLOBALS['MATERIAL_PRODUCTS'][$material_key]['price'] ?? 0);
            $items[] = ['label' => $label, 'price' => $price];
            $sum = $price;
        }

        return ['label' => implode(', ', array_column($items, 'label')), 'items' => $items, 'sum' => $sum];
    }

    private static function extra_info(mixed $extra_options): array
    {
        $items = [];
        $sum   = 0.0;

        $keys = [];
        if (is_array($extra_options)) {
            foreach ($extra_options as $k => $v) {
                if (is_string($v)) {
                    $keys[] = $v;
                } elseif (is_string($k) && ($v === true || $v === 1 || $v === '1')) {
                    $keys[] = $k;
                }
            }
        }

        $keys = array_unique(array_filter($keys));

        foreach ($keys as $key) {
            $label = $GLOBALS['EXTRA_PRODUCTS'][$key]['label'] ?? $key;
            $price = (float) ($GLOBALS['EXTRA_PRODUCTS'][$key]['price'] ?? 0);
            $items[] = ['label' => $label, 'price' => $price];
            $sum += $price;
        }

        return ['items' => $items, 'sum' => $sum];
    }
}