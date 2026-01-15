<?php
/**
 * Pontifex Order/Submission Helper Functions
 *
 * Bevat logica voor het omzetten van ruwe payload data naar:
 * 1. CSV-rijen (pontifex_oi_order_to_rows)
 * 2. Platte, gelabelde velden voor detailweergave (pontifex_oi_flatten_payload)
 * 3. Gestructureerde data voor UI kaarten (pontifex_oi_format_for_cards)
 *
 * @package Pontifex
 * @since   2026   (enkel 21% BTW wordt nog ondersteund)
 */
defined('ABSPATH') || exit;

// ====================================================================
// --- CSV EXPORT & FLATTENING ----------------------------------------
// ====================================================================

if (!function_exists('pontifex_oi_order_to_rows')) {
    /**
     * Zet order payload om naar een geïndexeerde array van rijen,
     * consistent met de CSV-export header.
     *
     * @param array $order De order payload data.
     * @return array Array van rijen, waarbij elke rij een geïndexeerde array van velden is.
     */
    function pontifex_oi_order_to_rows(array $order): array
    {
        $rows = [];

        // Structuur 1: nieuw formulier (enkele kandidaat)
        if (isset($order['first_name'])) {
            $rows[] = [
                $order['first_name'] ?? '',
                '', // Tussenvoegsel
                $order['last_name'] ?? '',
                '', // Geboortedatum
                $order['postcode'] ?? '',
                $order['number'] ?? '',
                $order['street'] ?? '',
                $order['city'] ?? '',
                $order['phone'] ?? '',
                $order['email'] ?? '',
                '', // Bedrijfsnaam
                '', // Functie
                '', // BTW-nummer
                $order['exam_type'] ?? '',
                $order['language'] ?? '',
                $order['material'] ?? '',
                '', // Extra lesmateriaal (Niet in deze structuur)
                $order['location'] ?? '',
                $order['planning_date'] ?? '',
                $order['planning_time'] ?? '',
                $order['total'] ?? ''
            ];
            return $rows;
        }

        // Structuur 2: oude JSON-structuur (met meervoudige kandidaten)
        if (isset($order['candidate_fullname']) && isset($order['postal-code'])) {
            $c_full  = (array)($order['candidate_fullname'] ?? []);
            $c_infix = (array)($order['candidate_infix'] ?? []);
            $c_last  = (array)($order['candidate_lastname'] ?? []);
            $c_birth = (array)($order['candidate_birthdate'] ?? []);

            $count = max(count($c_full), count($c_last));

            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    $c_full[$i]  ?? '',
                    $c_infix[$i] ?? '',
                    $c_last[$i]  ?? '',
                    $c_birth[$i] ?? '',
                    $order['postal-code']       ?? '',
                    $order['address-line2']     ?? '',
                    $order['street-address']    ?? '',
                    $order['address-level2']    ?? '',
                    $order['tel']               ?? '',
                    $order['order_email']       ?? '',
                    $order['organization']      ?? '',
                    $order['organization-title'] ?? '',
                    $order['order_vat']         ?? '',
                    $order['exam_type']         ?? '',
                    $order['language']          ?? '',
                    $order['material']          ?? '',
                    implode(', ', $order['extra_options'] ?? []),
                    $order['location']          ?? '',
                    $order['date']              ?? '',
                    $order['time']              ?? '',
                    $order['payment_amount'] ?? ($order['calculated_price'] ?? ''),
                ];
            }
            return $rows;
        }

        return [];
    }
}

if (!function_exists('pontifex_oi_flatten_payload')) {
    /**
     * Maakt een leesbare, gelabelde lijst van de belangrijkste velden
     * uit de ruwe payload (voor detailweergave in admin).
     *
     * @param array $payload De ruwe order/submission data
     * @param array $meta    Eventuele metadata (o.a. created_at)
     * @return array         ['title' => string, 'fields' => array]
     */
    function pontifex_oi_flatten_payload(array $payload, array $meta = []): array
    {
        // Hulpfunctie: array → string
        $toText = function ($val): string {
            if (is_array($val)) {
                return implode(' ', array_filter(array_map('strval', $val)));
            }
            return (string)$val;
        };

        // Naam samenstellen (meerdere mogelijke formaten)
        $voornaam      = $toText($payload['first_name'] ?? $payload['given_name'] ?? $payload['candidate_firstname'] ?? '');
        $tussenvoegsel = $toText($payload['candidate_infix'] ?? '');
        $achternaam    = $toText($payload['last_name'] ?? $payload['family_name'] ?? $payload['candidate_lastname'] ?? '');

        $volledige_naam = trim(implode(' ', array_filter([$voornaam, $tussenvoegsel, $achternaam])));

        if ($volledige_naam === '') {
            if (!empty($payload['candidate_fullname'])) {
                $val = $payload['candidate_fullname'];
                $volledige_naam = is_array($val) ? implode(', ', array_filter($val)) : (string)$val;
            } elseif (!empty($payload['name'])) {
                $volledige_naam = (string)$payload['name'];
            } else {
                $volledige_naam = 'Onbekend';
            }
        }

        $title = $volledige_naam;

        // Flatten de hele payload recursief
        $flat = [];
        $stack = [[$payload, '']];

        while ($stack) {
            [$node, $prefix] = array_pop($stack);
            if (!is_array($node)) continue;

            foreach ($node as $k => $v) {
                if (is_int($k) && $prefix === '') continue;

                $key = ltrim($prefix . $k, '.');

                if (is_array($v)) {
                    $allScalar = true;
                    foreach ($v as $vv) {
                        if (is_array($vv) || is_object($vv)) {
                            $allScalar = false;
                            break;
                        }
                    }

                    if ($allScalar) {
                        $flat[$key] = implode(', ', array_map('strval', array_filter($v, fn($x) => $x !== '' && $x !== null)));
                    } else {
                        $stack[] = [$v, $key . '.'];
                    }
                } elseif (is_object($v)) {
                    $stack[] = [get_object_vars($v), $key . '.'];
                } else {
                    $flat[$key] = (string)$v;
                }
            }
        }

        // Mooie labels + filtering
        $pretty = [];

        $labelMap = [
            'exam_type'            => 'Examen',
            'language'             => 'Taal',
            'material'             => 'Lesmateriaal',
            'planning_date'        => 'Datum',
            'planning_time'        => 'Tijd',
            'date'                 => 'Datum',
            'time'                 => 'Tijd',
            'location'             => 'Locatie',
            'candidate_fullname'   => 'Naam kandidaat',
            'candidate_lastname'   => 'Kandidaat achternaam',
            'candidate_birthdate'  => 'Geboortedatum kandidaat',
            'postal-code'          => 'Postcode',
            'address-line2'        => 'Huisnummer',
            'street-address'       => 'Straat',
            'address-level2'       => 'Stad',
            'tel'                  => 'Telefoonnummer',
            'phone'                => 'Telefoonnummer',
            'order_email'          => 'E-mailadres',
            'email'                => 'E-mailadres',
            'organization-title'   => 'Functie',

            // Alleen nog 21% BTW
            'calculated_price_excl' => 'Prijs zonder btw',
            'vat21'                => 'BTW (21%)',
            'total'                => 'Totaal',
            'payment_amount'       => 'Totaal',
            'calculated_price'     => 'Totaal',
        ];

        foreach ($flat as $k => $v) {
            if ($v === '' || $v === null || trim($v) === '') {
                continue;
            }

            // Technische / interne keys overslaan
            if (in_array($k, [
                'given_name', 'given-name', 'family_name', 'family-name',
                'order_id', 'organization', 'flow', 'nonce',
                'spots', 'candidate_count', 'vat_total', 'total_incl',
                'vat9',                 // expliciet niet tonen
            ], true)) {
                continue;
            }

            $label = $labelMap[$k] ?? ucwords(preg_replace('/[_\-\.]+/', ' ', $k));
            $pretty[] = ['label' => $label, 'value' => $v];
        }

        // Extra optie direct (fallback indien niet via extra_options)
        if (isset($payload['extra_option_direct']) && !array_filter($pretty, fn($f) => $f['label'] === 'Extra lesmateriaal 1')) {
            $pretty[] = [
                'label' => 'Extra lesmateriaal 1',
                'value' => (string)$payload['extra_option_direct']
            ];
        }

        // Meta bovenaan (meestal created_at)
        if (!empty($meta['created_at'])) {
            array_unshift($pretty, ['label' => 'Aangemaakt', 'value' => $meta['created_at']]);
        }

        // Voorkom dubbele totaal-regels
        $seenTotals = false;
        $pretty = array_values(array_filter($pretty, function ($f) use (&$seenTotals) {
            if ($f['label'] === 'Totaal') {
                if ($seenTotals) return false;
                $seenTotals = true;
            }
            return true;
        }));

        // Totaal altijd als laatste regel
        usort($pretty, function ($a, $b) {
            if ($a['label'] === 'Totaal') return 1;
            if ($b['label'] === 'Totaal') return -1;
            return 0;
        });

        return [
            'title'  => $title,
            'fields' => $pretty
        ];
    }
}

if (!function_exists('pontifex_oi_cards_blacklist_keys')) {
    /**
     * Welke payload keys NIET tonen in Inzendingen-kaarten.
     */
    function pontifex_oi_cards_blacklist_keys(): array
    {
        return [
            'nonce', '_nonce', 'security',
            'spots', 'spot', 'spot_count', 'beschikbare_spots',
            'order_id', 'orderId', 'mollie_id', 'payment_id', 'paymentId',
            'transaction_id', 'tr_id', 'flow', 'flow2', 'flow_version',
            'name',
            'vat9',                 // expliciet niet tonen
        ];
    }
}

if (!function_exists('pontifex_oi_exam_label')) {
    /**
     * Vertaal exam key naar nette label.
     */
    function pontifex_oi_exam_label(string $exam_key): string
    {
        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_key = pontifex_normalize_exam_key($exam_key);
        }

        if (function_exists('get_all_exam_products')) {
            $all = get_all_exam_products();
            if (!empty($all[$exam_key]['label'])) {
                $base = $all[$exam_key]['label'];
                if (str_starts_with($exam_key, 'los-examen-')) {
                    return $base . ' (los examen)';
                }
                return $base;
            }
        }

        $map = [
            'los-examen-vca-basis'       => 'VCA Basis (los examen)',
            'los-examen-vca-basis-groen' => 'VCA Basis Groen (los examen)',
            'los-examen-vca-vol'         => 'VCA Vol (los examen)',
            'los-examen-vca-vil'         => 'VCA VIL (los examen)',
        ];

        return $map[$exam_key] ?? $exam_key;
    }
}

if (!function_exists('pontifex_oi_material_info')) {
    /**
     * Geef nette labels voor gekozen MATERIAAL (incl. combi’s).
     */
    function pontifex_oi_material_info(?string $material_key, string $exam_key = ''): array
    {
        if (!$material_key || $material_key === '1') {
            return ['label' => 'Geen lesmateriaal', 'items' => [], 'sum' => 0.0];
        }

        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_key = pontifex_normalize_exam_key($exam_key ?: '');
        }

        $items = [];
        $sum = 0.0;

        $MATERIAL_PRODUCTS = function_exists('get_all_material_products')
            ? get_all_material_products()
            : [];

        $combiKey = $material_key;
        if (in_array($material_key, ['2', '4', '5', '6', '7'], true)) {
            $suffix = (strpos($exam_key, 'vol') !== false || strpos($exam_key, 'vil') !== false) ? 'vol' : 'basis';
            $combiKey = "{$material_key}_{$suffix}";
        }

        $MATERIAL_COMBIS = [];
        if (function_exists('pontifex_oi_get_product_data_for_js')) {
            $all = pontifex_oi_get_product_data_for_js();
            $MATERIAL_COMBIS = $all['materialCombis'] ?? [];
        }

        if (!empty($MATERIAL_COMBIS[$combiKey]) && is_array($MATERIAL_COMBIS[$combiKey])) {
            foreach ($MATERIAL_COMBIS[$combiKey] as $prodKey) {
                $label = $MATERIAL_PRODUCTS[$prodKey]['label'] ?? $prodKey;
                $price = (float)($MATERIAL_PRODUCTS[$prodKey]['price'] ?? 0);
                $items[] = ['label' => $label, 'price' => $price];
                $sum += $price;
            }
            $label = implode(', ', array_column($items, 'label'));
            return ['label' => $label, 'items' => $items, 'sum' => $sum];
        }

        // Single product
        $label = $MATERIAL_PRODUCTS[$material_key]['label'] ?? $material_key;
        $price = (float)($MATERIAL_PRODUCTS[$material_key]['price'] ?? 0);
        $items[] = ['label' => $label, 'price' => $price];
        $sum = $price;

        return ['label' => $label, 'items' => $items, 'sum' => $sum];
    }
}

if (!function_exists('pontifex_oi_extra_info')) {
    /**
     * Geef nette labels voor EXTRA opties (checkboxen).
     */
    function pontifex_oi_extra_info($extra_options): array
    {
        $EXTRA_PRODUCTS = function_exists('get_all_extra_products')
            ? get_all_extra_products()
            : [];

        $items = [];
        $sum = 0.0;

        if (empty($extra_options)) {
            return ['items' => [], 'sum' => 0.0];
        }

        $keys = [];
        if (is_array($extra_options)) {
            foreach ($extra_options as $k => $v) {
                if (is_string($v)) {
                    $keys[] = $v;
                } elseif (is_int($k) && is_string($v)) {
                    $keys[] = $v;
                } elseif (is_string($k) && ($v === '1' || $v === 1 || $v === true)) {
                    $keys[] = $k;
                } elseif (is_array($v) && !empty($v['key'])) {
                    $keys[] = (string)$v['key'];
                }
            }
        }

        $keys = array_values(array_unique(array_filter($keys)));

        foreach ($keys as $key) {
            $label = $EXTRA_PRODUCTS[$key]['label'] ?? $key;
            $price = (float)($EXTRA_PRODUCTS[$key]['price'] ?? 0);
            $items[] = ['label' => $label, 'price' => $price];
            $sum += $price;
        }

        return ['items' => $items, 'sum' => $sum];
    }
}

if (!function_exists('pontifex_oi_format_for_cards')) {
    /**
     * Maak een nette “kaart-data” uit de ruwe payload, specifiek voor UI weergave.
     */
    function pontifex_oi_format_for_cards(array $payload): array
    {
        $blk = array_flip(pontifex_oi_cards_blacklist_keys());

        // Naam
        $name = '';
        if (!empty($payload['first_name']) || !empty($payload['last_name'])) {
            $name = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
        } elseif (!empty($payload['candidate_fullname']) || !empty($payload['candidate_lastname'])) {
            $fn   = is_array($payload['candidate_fullname'])   ? ($payload['candidate_fullname'][0]   ?? '') : ($payload['candidate_fullname']   ?? '');
            $inf  = is_array($payload['candidate_infix'])      ? ($payload['candidate_infix'][0]      ?? '') : ($payload['candidate_infix']      ?? '');
            $ln   = is_array($payload['candidate_lastname'])   ? ($payload['candidate_lastname'][0]   ?? '') : ($payload['candidate_lastname']   ?? '');
            $name = trim($fn . ' ' . $inf . ' ' . $ln);
        }

        // Email
        $email = $payload['email'] ?? ($payload['order_email'] ?? '');

        // Examen
        $exam_key = $payload['exam_type'] ?? '';
        $exam_label = $exam_key ? pontifex_oi_exam_label($exam_key) : '';

        // Datum/tijd
        $date = $payload['planning_date'] ?? ($payload['date'] ?? '');
        $time = $payload['planning_time'] ?? ($payload['time'] ?? '');

        // Locatie
        $location = $payload['location'] ?? '';

        // Totaalbedrag
        $total = $payload['total'] ?? ($payload['payment_amount'] ?? ($payload['calculated_price'] ?? ''));

        // Velden die we apart tonen → verbergen
        $hide = array_merge(
            array_keys($blk),
            ['first_name','last_name','candidate_fullname','candidate_infix','candidate_lastname',
             'email','order_email','exam_type','planning_date','date','planning_time','time',
             'location','total','payment_amount','calculated_price']
        );

        $hide = array_merge($hide, ['material','extra_options']);

        $material_info = pontifex_oi_material_info($payload['material'] ?? '', $exam_key);
        $extras_info   = pontifex_oi_extra_info($payload['extra_options'] ?? []);

        $extra = [];
        foreach ($payload as $k => $v) {
            if (in_array($k, $hide, true)) continue;

            $val = is_array($v) ? implode(', ', array_map('strval', $v)) : (string)$v;
            if ($val === '' || $val === null || trim($val) === '') continue;

            $label = ucwords(str_replace(['_', '-'], ' ', $k));
            $extra[] = ['label' => $label, 'value' => $val];
        }

        return [
            'name'            => $name,
            'email'           => $email,
            'exam_label'      => $exam_label,
            'date'            => $date,
            'time'            => $time,
            'location'        => $location,
            'total'           => $total,
            'material_label'  => $material_info['label'],
            'material_items'  => $material_info['items'],
            'material_sum'    => $material_info['sum'],
            'extra_items'     => $extras_info['items'],
            'extra_sum'       => $extras_info['sum'],
            'extra'           => $extra,
        ];
    }
}