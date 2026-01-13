<?php
/**
 * Pontifex Order/Submission Helper Functions
 *
 * Bevat logica voor het omzetten van ruwe payload data naar:
 * 1. CSV-rijen (pontifex_oi_order_to_rows)
 * 2. Platte, gelabelde velden voor detailweergave (pontifex_oi_flatten_payload)
 * 3. Gestructureerde data voor UI kaarten (pontifex_oi_format_for_cards)
 *
 * Dit bestand combineert de oorspronkelijke functies met de verbeterde label-
 * en opschoonlogica van de "Nieuwe submissions-helpers".
 *
 * @package Pontifex
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
    function pontifex_oi_order_to_rows(array $order): array {
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

            $count = max(count($c_full), count($c_last)); // Bepaal max rijen
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    $c_full[$i]  ?? '',
                    $c_infix[$i] ?? '',
                    $c_last[$i]  ?? '',
                    $c_birth[$i] ?? '',
                    $order['postal-code'] ?? '',
                    $order['address-line2'] ?? '',
                    $order['street-address'] ?? '',
                    $order['address-level2'] ?? '',
                    $order['tel'] ?? '',
                    $order['order_email'] ?? '',
                    $order['organization'] ?? '',
                    $order['organization-title'] ?? '',
                    $order['order_vat'] ?? '',
                    $order['exam_type'] ?? '',
                    $order['language'] ?? '',
                    $order['material'] ?? '',
                    implode(', ', $order['extra_options'] ?? []),
                    $order['location'] ?? '',
                    $order['date'] ?? '',
                    $order['time'] ?? '',
                    $order['payment_amount'] ?? ($order['calculated_price'] ?? ''),
                ];
            }
            return $rows;
        }

        return [];
    }
}

if (!function_exists('pontifex_oi_flatten_payload')) {
    function pontifex_oi_flatten_payload(array $payload, array $meta = []): array {

        // 1️⃣ Titel bepalen (met veilige verwerking van arrays)
        $toText = function ($val): string {
            if (is_array($val)) return implode(' ', array_filter(array_map('strval', $val)));
            return (string)$val;
        };

        $voornaam = $toText($payload['first_name'] ?? $payload['given_name'] ?? $payload['candidate_firstname'] ?? '');
        $tussenvoegsel = $toText($payload['candidate_infix'] ?? '');
        $achternaam = $toText($payload['last_name'] ?? $payload['family_name'] ?? $payload['candidate_lastname'] ?? '');
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

        // 2️⃣ Flatten arrays
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
                    foreach ($v as $vv) { if (is_array($vv) || is_object($vv)) { $allScalar = false; break; } }
                    if ($allScalar) {
                        $flat[$key] = implode(', ', array_map('strval', array_filter($v, fn($x)=>$x!=='' && $x!==null)));
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

        // 3️⃣ Mooie labels en filtering
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

            // 👇 Nieuwe & BTW-velden
            'extra_option_direct'  => 'Extra lesmateriaal 1',
            'extra_options'        => 'Extra lesmateriaal 2',
            'calculated_price_excl'=> 'Prijs zonder btw',
            'vat9'                 => 'BTW (9%)',
            'vat21'                => 'BTW (21%)',
            'total'                => 'Totaal',
            'payment_amount'       => 'Totaal',
            'calculated_price'     => 'Totaal',
        ];

        foreach ($flat as $k => $v) {
            if ($v === '' || $v === null || trim($v) === '') continue;

            // 🧹 Technische keys overslaan
            if (in_array($k, [
            'given_name','given-name','family_name','family-name',
            'order_id','organization','flow','nonce',
            'spots','candidate_count','vat_total','total_incl'
        ], true)) continue;

            $label = $labelMap[$k] ?? ucwords(preg_replace('/[_\-\.]+/', ' ', $k));
            $pretty[] = ['label' => $label, 'value' => $v];
        }

        // ✅ Forceer extra_option_direct indien aanwezig in payload maar niet getoond
        if (isset($payload['extra_option_direct']) && !array_filter($pretty, fn($f)=>$f['label']==='Extra lesmateriaal 1')) {
            $pretty[] = ['label' => 'Extra lesmateriaal 1', 'value' => (string)$payload['extra_option_direct']];
        }

        // 4️⃣ Meta bovenaan
        if (!empty($meta['created_at'])) {
            array_unshift($pretty, ['label' => 'Aangemaakt', 'value' => $meta['created_at']]);
        }

        // 5️⃣ Dubbele Totaal verwijderen
        $seenTotals = false;
        $pretty = array_values(array_filter($pretty, function ($f) use (&$seenTotals) {
            if ($f['label'] === 'Totaal') {
                if ($seenTotals) return false;
                $seenTotals = true;
            }
            return true;
        }));

        // 6️⃣ Totaal als laatste
        usort($pretty, function($a, $b) {
            if ($a['label'] === 'Totaal') return 1;
            if ($b['label'] === 'Totaal') return -1;
            return 0;
        });

        return ['title' => $title, 'fields' => $pretty];
    }
}

// ====================================================================
// --- UI CARD FORMATTING HELPER FUNCTIONS ----------------------------
// ====================================================================

if (!function_exists('pontifex_oi_cards_blacklist_keys')) {
    /**
     * Welke payload keys NIET tonen in Inzendingen-kaarten.
     */
    function pontifex_oi_cards_blacklist_keys(): array {
        return [
            'nonce','_nonce','security',
            'spots','spot','spot_count','beschikbare_spots',
            'order_id','orderId','mollie_id','payment_id','paymentId',
            'transaction_id','tr_id','flow','flow2','flow_version',
            'name', // Toegevoegd vanuit de nieuwe helpers
        ];
    }
}

if (!function_exists('pontifex_oi_exam_label')) {
    /**
     * Vertaal exam key naar nette label.
     *
     * @param string $exam_key De sleutel van het examen.
     * @return string Het nette label.
     */
    function pontifex_oi_exam_label(string $exam_key): string {
        // Normaliseer eventuele alias-keys
        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_key = pontifex_normalize_exam_key($exam_key);
        }

        // Probeer EXAM_PRODUCTS te gebruiken
        if (function_exists('get_all_exam_products')) {
            $all = get_all_exam_products();
            if (!empty($all[$exam_key]['label'])) {
                $base = $all[$exam_key]['label'];
                // Suffix “(los examen)” als de key dat aangeeft
                if (str_starts_with($exam_key, 'los-examen-')) {
                    return $base . ' (los examen)';
                }
                return $base;
            }
        }

        // Fallbacks voor bekende keys
        $map = [
            'los-examen-vca-basis'         => 'VCA Basis (los examen)',
            'los-examen-vca-basis-groen' => 'VCA Basis Groen (los examen)',
            'los-examen-vca-vol'           => 'VCA Vol (los examen)',
            'los-examen-vca-vil'           => 'VCA VIL (los examen)',
        ];
        return $map[$exam_key] ?? $exam_key;
    }
}

if (!function_exists('pontifex_oi_material_info')) {
    /**
     * Geef nette labels voor gekozen MATERIAAL (incl. combi’s).
     *
     * @param string|null $material_key De sleutel van het gekozen materiaal.
     * @param string $exam_key De sleutel van het examen (nodig voor combi's).
     * @return array
     */
    function pontifex_oi_material_info(?string $material_key, string $exam_key = ''): array {
        if (!$material_key || $material_key === '1') {
            return ['label' => 'Geen lesmateriaal', 'items' => [], 'sum' => 0.0];
        }

        if (function_exists('pontifex_normalize_exam_key')) {
            $exam_key = pontifex_normalize_exam_key($exam_key ?: '');
        }

        $items = [];
        $sum   = 0.0;

        if (function_exists('get_all_material_products')) {
            $MATERIAL_PRODUCTS = get_all_material_products();
        } else {
            $MATERIAL_PRODUCTS = [];
        }

        // Combi’s bepalen
        $combiKey = $material_key;
        if (in_array($material_key, ['2', '4', '5', '6', '7'], true)) {
            $suffix = (strpos($exam_key, 'vol') !== false || strpos($exam_key, 'vil') !== false) ? 'vol' : 'basis';
            $combiKey = "{$material_key}_{$suffix}";
        }

        // Probeer MATERIAL_COMBIS op te halen
        $MATERIAL_COMBIS = [];
        if (function_exists('pontifex_oi_get_product_data_for_js')) {
            $all = pontifex_oi_get_product_data_for_js();
            $MATERIAL_COMBIS = $all['materialCombis'] ?? [];
        }

        // 1) is dit een combi?
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

        // 2) geen combi → direct single product
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
     *
     * @param mixed $extra_options De ruwe extra opties data.
     * @return array
     */
    function pontifex_oi_extra_info($extra_options): array {
        if (function_exists('get_all_extra_products')) {
            $EXTRA_PRODUCTS = get_all_extra_products();
        } else {
            $EXTRA_PRODUCTS = [];
        }

        $items = [];
        $sum   = 0.0;

        if (empty($extra_options)) {
            return ['items' => [], 'sum' => 0.0];
        }

        // Normaliseer naar lijst van keys
        $keys = [];
        if (is_array($extra_options)) {
            foreach ($extra_options as $k => $v) {
                // formats: ['vca_proefexamen_nl' => 1] of ['0'=>'vca_proefexamen_nl'] of [['key'=>'vca_proefexamen_nl']]
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
     *
     * @param array $payload De ruwe order/submission payload.
     * @return array De gestructureerde kaartdata.
     */
    function pontifex_oi_format_for_cards(array $payload): array {
        $blk = array_flip(pontifex_oi_cards_blacklist_keys());

        // Naamvelden (nieuw of oud)
        $name = '';
        if (!empty($payload['first_name']) || !empty($payload['last_name'])) {
            $name = trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? ''));
        } elseif (!empty($payload['candidate_fullname']) || !empty($payload['candidate_lastname'])) {
            $fn  = is_array($payload['candidate_fullname']) ? ($payload['candidate_fullname'][0] ?? '') : ($payload['candidate_fullname'] ?? '');
            $inf = is_array($payload['candidate_infix']) ? ($payload['candidate_infix'][0] ?? '') : ($payload['candidate_infix'] ?? '');
            $ln  = is_array($payload['candidate_lastname']) ? ($payload['candidate_lastname'][0] ?? '') : ($payload['candidate_lastname'] ?? '');
            $name = trim($fn . ' ' . $inf . ' ' . $ln);
        }

        // E-mail uit nieuw/oud
        $email = $payload['email'] ?? ($payload['order_email'] ?? '');

        // Exam label
        $exam_key = $payload['exam_type'] ?? '';
        $exam_label = $exam_key ? pontifex_oi_exam_label($exam_key) : '';

        // Datum/tijd (nieuw of oud)
        $date = $payload['planning_date'] ?? ($payload['date'] ?? '');
        $time = $payload['planning_time'] ?? ($payload['time'] ?? '');

        // Locatie
        $location = $payload['location'] ?? '';

        // Totaal
        $total = $payload['total'] ?? ($payload['payment_amount'] ?? ($payload['calculated_price'] ?? ''));

        // Verzamel overige zichtbare velden (excl. blacklist & de velden die we al apart tonen)
        $hide = array_merge(
            array_keys($blk),
            ['first_name','last_name','candidate_fullname','candidate_infix','candidate_lastname',
             'email','order_email','exam_type','planning_date','date','planning_time','time',
             'location','total','payment_amount','calculated_price']
        );

        // Voeg raw keys toe aan hide-lijst voor materiaal en extra opties
        $hide = array_merge($hide, ['material','extra_options']);

        // Haal materiaal en extra info op
        $material_info = pontifex_oi_material_info($payload['material'] ?? '', $exam_key);
        $extras_info   = pontifex_oi_extra_info($payload['extra_options'] ?? []);

        $extra = [];
        foreach ($payload as $k => $v) {
            if (in_array($k, $hide, true)) continue;
            // Eenvoudige array-conversie voor de 'extra' velden
            $val = is_array($v) ? implode(', ', array_map('strval', $v)) : (string)$v;
            // Sla lege waarden over
            if ($val === '' || $val === null || (is_string($val) && trim($val) === '')) continue;

            $label = ucwords(str_replace(['_', '-'], ' ', $k)); // simpele label
            $extra[] = ['label' => $label, 'value' => $val];
        }

        // Aangepaste return array
        return [
            'name'           => $name,
            'email'          => $email,
            'exam_label'     => $exam_label,
            'date'           => $date,
            'time'           => $time,
            'location'       => $location,
            'total'          => $total,
            'material_label' => $material_info['label'],
            'material_items' => $material_info['items'],
            'material_sum'   => $material_info['sum'],
            'extra_items'    => $extras_info['items'],
            'extra_sum'      => $extras_info['sum'],
            'extra'          => $extra, // overige vrije velden die niet op de blacklist staan
        ];
    }
}