<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Hoofdproducten (examensoorten)
$EXAM_PRODUCTS = [
    'los-examen-vca-basis' => [
        'label'  => 'VCA Basis',
        'prices' => ['nl' => 129, 'en' => 129],
    ],
    'los-examen-vca-vol' => [
        'label'  => 'VCA Vol',
        'prices' => ['nl' => 139, 'en' => 139],
    ],
    'vca-basis-weekend' => [
        'label'  => 'VCA Basis Cursus Weekend',
        'prices' => ['nl' => 245],
    ],
    'vca-vol-weekend' => [
        'label'  => 'VCA Vol Cursus Weekend',
        'prices' => ['nl' => 245],
    ],
];

// Losse materialen (checkbox opties)
$MATERIAL_PRODUCTS = [
    'e-learning-vca-basis-nl'   => ['label' => 'E-learning VCA Basis (NL)',    'price' => 29],
    'vca-basis-proefexamens-nl' => ['label' => 'VCA Basis Proefexamens (NL)',  'price' => 25],
    'boek-vca-basis-nl'         => ['label' => 'Boek VCA Basis (NL)',          'price' => 36],
    'boek-vca-combi-nl'         => ['label' => 'Boek VCA Combi (NL)',          'price' => 49],

    'e-learning-vca-vol-nl'     => ['label' => 'E-learning VCA Vol (NL)',      'price' => 39],
    'vca-vol-proefexamens-nl'   => ['label' => 'VCA Vol Proefexamens (NL)',    'price' => 25],
    'boek-vca-vol-nl'           => ['label' => 'Boek VCA Vol (NL)',            'price' => 42],
    'boek-vca-combi-vol-nl'     => ['label' => 'Boek VCA Combi (NL)',          'price' => 49],

    'e-learning-vca-basis-en'   => ['label' => 'E-learning VCA Basis (EN)',    'price' => 49],
    'boek-vca-basis-en'         => ['label' => 'Boek VCA Basis (EN)',          'price' => 56],
    'boek-vca-combi-en'         => ['label' => 'Boek VCA Combi (EN)',          'price' => 69],

    'e-learning-vca-vol-en'     => ['label' => 'E-learning VCA Vol (EN)',      'price' => 59],
    'boek-vca-vol-en'           => ['label' => 'Boek VCA Vol (EN)',            'price' => 62],
    'boek-vca-combi-vol-en'     => ['label' => 'Boek VCA Combi (EN)',          'price' => 69],
];

// Mapping voor pakketten (dropdown "Lesmateriaal")
$MATERIAL_COMBIS = [
    '1'        => [], // Geen materiaal
    '2_basis'  => ['boek-vca-basis-nl'],
    '2_vol'    => ['boek-vca-vol-nl'],
    '4_basis'  => ['e-learning-vca-basis-nl'],
    '4_vol'    => ['e-learning-vca-vol-nl'],
    '5_basis'  => ['vca-basis-proefexamens-nl'],
    '5_vol'    => ['vca-vol-proefexamens-nl'],
    '6_basis'  => ['boek-vca-basis-nl', 'vca-basis-proefexamens-nl'],
    '6_vol'    => ['boek-vca-vol-nl', 'vca-vol-proefexamens-nl'],
    '7_basis'  => ['e-learning-vca-basis-nl', 'vca-basis-proefexamens-nl'],
    '7_vol'    => ['e-learning-vca-vol-nl', 'vca-vol-proefexamens-nl'],
];

// Vaste weekendexamens array voor prijscheck en frontend filtering
$EXAM_WEEKEND = ['vca-basis-weekend', 'vca-vol-weekend'];

/**
 * Haal de prijs op op basis van examen, taal en materiaal.
 */
function get_product_price($exam_type, $language = 'nl', $material = '') {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $EXAM_WEEKEND;

    // Vaste weekendprijs
    if (in_array($exam_type, $EXAM_WEEKEND, true)) {
        return 245;
    }

    $total = 0;

    if (isset($EXAM_PRODUCTS[$exam_type])) {
        $prices = $EXAM_PRODUCTS[$exam_type]['prices'];
        $exam_price = $prices[$language] ?? $prices['nl'] ?? 0;
        $total += $exam_price;
    }

    // Alleen examenprijs als materiaal leeg of '1'
    if (empty($material) || $material === '1') {
        return $total;
    }

    // Materiaalcombinatie key bepalen
    $combiKey = $material;
    if (in_array($material, ['2', '4', '5', '6', '7'], true)) {
        $suffix = (strpos($exam_type, 'vca-vol') !== false) ? 'vol' : 'basis';
        $combiKey = "{$material}_{$suffix}";
    }

    // Materiaalprijs optellen
    if (isset($MATERIAL_COMBIS[$combiKey])) {
        foreach ($MATERIAL_COMBIS[$combiKey] as $prod) {
            $item_price = $MATERIAL_PRODUCTS[$prod]['price'] ?? 0;
            $total += $item_price;
        }
    }

    return $total;
}

/**
 * Bereken totaalprijs inclusief extra checkbox materialen.
 */
function get_total_price_with_extras($exam_type, $language = 'nl', $material = '', $extra_material = []) {
    global $MATERIAL_PRODUCTS;

    $total = get_product_price($exam_type, $language, $material);

    if (!is_array($extra_material)) {
        $extra_material = [];
    }

    foreach ($extra_material as $extra_id) {
        $item_price = $MATERIAL_PRODUCTS[$extra_id]['price'] ?? 0;
        $total += $item_price;
    }

    return $total;
}

/**
 * Haal alle examensoorten op.
 */
function get_all_exam_products() {
    global $EXAM_PRODUCTS;
    return $EXAM_PRODUCTS;
}

/**
 * Haal alle losse materialen op.
 */
function get_all_material_products() {
    global $MATERIAL_PRODUCTS;
    return $MATERIAL_PRODUCTS;
}

/**
 * Haal extra materiaal opties (checkboxen) op, gesorteerd per examen en taal.
 */
function get_extra_material_options() {
    return [
        'los-examen-vca-basis' => [
            'nl' => [
                ['id' => 'e-learning-vca-basis-nl', 'label' => 'E-learning VCA Basis (NL)', 'price' => 29],
                ['id' => 'vca-basis-proefexamens-nl', 'label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-basis-nl', 'label' => 'Boek VCA Basis (NL)', 'price' => 36],
                ['id' => 'boek-vca-combi-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
            ],
            'en' => [
                ['id' => 'e-learning-vca-basis-en', 'label' => 'E-learning VCA Basis (EN)', 'price' => 49],
                ['id' => 'boek-vca-basis-en', 'label' => 'Boek VCA Basis (EN)', 'price' => 56],
                ['id' => 'boek-vca-combi-en', 'label' => 'Boek VCA Combi (EN)', 'price' => 69],
            ],
        ],
        'los-examen-vca-vol' => [
            'nl' => [
                ['id' => 'e-learning-vca-vol-nl', 'label' => 'E-learning VCA Vol (NL)', 'price' => 39],
                ['id' => 'vca-vol-proefexamens-nl', 'label' => 'VCA Vol Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-vol-nl', 'label' => 'Boek VCA Vol (NL)', 'price' => 42],
                ['id' => 'boek-vca-combi-vol-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
            ],
            'en' => [
                ['id' => 'e-learning-vca-vol-en', 'label' => 'E-learning VCA Vol (EN)', 'price' => 59],
                ['id' => 'boek-vca-vol-en', 'label' => 'Boek VCA Vol (EN)', 'price' => 62],
                ['id' => 'boek-vca-combi-vol-en', 'label' => 'Boek VCA Combi (EN)', 'price' => 69],
            ],
        ],
    ];
}

/**
 * Bereidt productdata voor ter localisatie naar JS.
 *
 * @return array
 */
function pontifex_oi_get_product_data_for_js() {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;

    // Voeg examWeekend toe voor frontend gebruik
    $examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

    // Maak simpele structuren voor JS (labels + prijzen)
    $exam_products_simple = [];
    foreach ($EXAM_PRODUCTS as $key => $item) {
        $exam_products_simple[$key] = [
            'label'  => $item['label'],
            'prices' => $item['prices'],
        ];
    }

    return [
        'examProducts'     => $exam_products_simple,
        'materialProducts' => $MATERIAL_PRODUCTS,
        'materialCombis'   => $MATERIAL_COMBIS,
        'examWeekend'      => $examWeekend,
    ];
}

/**
 * Prepareer extra materiaal checkboxes voor JS localisatie
 *
 * @return array
 */
function pontifex_oi_get_extra_material_checkboxes_for_js() {
    $php = get_extra_material_options();
    $flat = [];
    foreach ($php as $exam => $langs) {
        foreach ($langs as $lang => $arr) {
            $flat[$exam . '_' . $lang] = $arr;
        }
    }
    return $flat;
}