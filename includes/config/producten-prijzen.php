<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Examensoorten (4 stuks) met talen/prijzen.
 */
$EXAM_PRODUCTS = [
    // VCA Basis
    'los-examen-vca-basis' => [
        'label'  => 'VCA Basis',
        'prices' => [
            // Kern-talen
            'nl' => 129,
            'de' => 129,
            'en' => 129,
            'fr' => 129,
            // Groep 1 (149)
            'ar' => 149, 'bg' => 149, 'lt' => 149, 'pl' => 149,
            'pt' => 149, 'ro' => 149, 'ru' => 149, 'tr' => 149,
            // Groep 2 (184)
            'el' => 184, 'hu' => 184, 'it' => 184, 'hr' => 184,
            'uk' => 184, 'sk' => 184, 'es' => 184, 'vi' => 184,
        ],
    ],

    // VCA Basis Groen (alleen NL)
    'los-examen-vca-basis-groen' => [
        'label'  => 'VCA Basis Groen',
        'prices' => ['nl' => 129],
    ],

    // VCA VOL
    'los-examen-vca-vol' => [
        'label'  => 'VCA Vol',
        'prices' => [
            'nl' => 129,
            'de' => 139,
            'en' => 139,
            'fr' => 139,
        ],
    ],

    // VCA VIL
    'los-examen-vca-vil' => [
        'label'  => 'VCA VIL',
        'prices' => [
            'nl' => 129,
            'en' => 139,
        ],
    ],
];

/**
 * Los materiaal (checkbox opties).
 * Let op: Checkbox "cursus-weekend" is alleen toegestaan voor NL/EN (zie $WEEKEND_ALLOWED_BY_EXAM)
 */
$MATERIAL_PRODUCTS = [
    // BASIS NL
    'e-learning-vca-basis-nl'    => ['label' => 'E-learning VCA Basis (NL)',    'price' => 29],
    'vca-basis-proefexamens-nl' => ['label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
    'boek-vca-basis-nl'          => ['label' => 'Boek VCA Basis (NL)',          'price' => 36],
    'boek-vca-combi-nl'          => ['label' => 'Boek VCA Combi (NL)',          'price' => 49],

    // VOL NL
    'e-learning-vca-vol-nl'      => ['label' => 'E-learning VCA Vol (NL)',      'price' => 39],
    'vca-vol-proefexamens-nl'    => ['label' => 'VCA Vol Proefexamens (NL)',    'price' => 25],
    'boek-vca-vol-nl'            => ['label' => 'Boek VCA Vol (NL)',            'price' => 42],
    'boek-vca-combi-vol-nl'      => ['label' => 'Boek VCA Combi (NL)',          'price' => 49],

    // BASIS EN
    'e-learning-vca-basis-en'    => ['label' => 'E-learning VCA Basis (EN)',    'price' => 49],
    'boek-vca-basis-en'          => ['label' => 'Boek VCA Basis (EN)',          'price' => 56],
    'boek-vca-combi-en'          => ['label' => 'Boek VCA Combi (EN)',          'price' => 69],

    // VOL EN
    'e-learning-vca-vol-en'      => ['label' => 'E-learning VCA Vol (EN)',      'price' => 59],
    'boek-vca-vol-en'            => ['label' => 'Boek VCA Vol (EN)',            'price' => 62],
    'boek-vca-combi-vol-en'      => ['label' => 'Boek VCA Combi (EN)',          'price' => 69],

    // WEEKEND (checkbox extra)
    'cursus-weekend'             => ['label' => 'Cursus weekend',               'price' => 245],
];

/**
 * Mapping voor pakketten (dropdown "Lesmateriaal").
 */
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

/**
 * Weekend toegestaan per examensoort (checkbox zichtbaar) — alleen NL en EN.
 */
$WEEKEND_ALLOWED_BY_EXAM = [
    'los-examen-vca-basis'         => ['nl','en'],
    'los-examen-vca-basis-groen' => ['nl'],       // Alleen NL omdat dit examensoort alleen NL heeft
    'los-examen-vca-vol'           => ['nl','en'],
    'los-examen-vca-vil'           => ['nl','en'],
];

/**
 * Prijsberekening examen + materiaal.
 */
function get_product_price($exam_type, $language = 'nl', $material = '') {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;

    // Normaliseer keys
    $exam_type = pontifex_normalize_exam_key($exam_type);

    $total = 0;
    if (isset($EXAM_PRODUCTS[$exam_type])) {
        $prices = $EXAM_PRODUCTS[$exam_type]['prices'];
        $exam_price = $prices[$language] ?? $prices['nl'] ?? 0;
        $total += $exam_price;
    }

    if (empty($material) || $material === '1') {
        return $total;
    }

    $combiKey = $material;
    if (in_array($material, ['2', '4', '5', '6', '7'], true)) {
        $suffix = (strpos($exam_type, 'vol') !== false) ? 'vol' : 'basis';
        $combiKey = "{$material}_{$suffix}";
    }

    if (isset($MATERIAL_COMBIS[$combiKey])) {
        foreach ($MATERIAL_COMBIS[$combiKey] as $prod) {
            $item_price = $MATERIAL_PRODUCTS[$prod]['price'] ?? 0;
            $total += $item_price;
        }
    }
    return $total;
}

/**
 * Prijsberekening inclusief extra's (checkboxen).
 */
function get_total_price_with_extras($exam_type, $language = 'nl', $material = '', $extra_material = []) {
    global $MATERIAL_PRODUCTS, $WEEKEND_ALLOWED_BY_EXAM;

    // Normaliseer keys
    $exam_type = pontifex_normalize_exam_key($exam_type);

    $total = get_product_price($exam_type, $language, $material);
    if (!is_array($extra_material)) {
        $extra_material = [];
    }

    foreach ($extra_material as $extra_id) {
        if ($extra_id === 'cursus-weekend') {
            $allowed = $WEEKEND_ALLOWED_BY_EXAM[$exam_type] ?? [];
            if (!in_array($language, $allowed, true)) {
                continue;
            }
        }
        $item_price = $MATERIAL_PRODUCTS[$extra_id]['price'] ?? 0;
        $total += $item_price;
    }
    return $total;
}

/**
 * Alle examenproducten.
 */
function get_all_exam_products() {
    global $EXAM_PRODUCTS;
    return $EXAM_PRODUCTS;
}

/**
 * Alle losse materialen.
 */
function get_all_material_products() {
    global $MATERIAL_PRODUCTS;
    return $MATERIAL_PRODUCTS;
}

/**
 * Extra materiaal-opties per examen+taal.
 * Weekend wordt bij NL én EN aangeboden waar toegestaan.
 */
function get_extra_material_options() {
    return [
        'los-examen-vca-basis' => [
            'nl' => [
                ['id' => 'e-learning-vca-basis-nl', 'label' => 'E-learning VCA Basis (NL)', 'price' => 29],
                ['id' => 'vca-basis-proefexamens-nl', 'label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-basis-nl', 'label' => 'Boek VCA Basis (NL)', 'price' => 36],
                ['id' => 'boek-vca-combi-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
            'en' => [
                ['id' => 'e-learning-vca-basis-en', 'label' => 'E-learning VCA Basis (EN)', 'price' => 49],
                ['id' => 'boek-vca-basis-en', 'label' => 'Boek VCA Basis (EN)', 'price' => 56],
                ['id' => 'boek-vca-combi-en', 'label' => 'Boek VCA Combi (EN)', 'price' => 69],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
        ],
        'los-examen-vca-basis-groen' => [
            'nl' => [
                ['id' => 'e-learning-vca-basis-nl', 'label' => 'E-learning VCA Basis (NL)', 'price' => 29],
                ['id' => 'vca-basis-proefexamens-nl', 'label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-basis-nl', 'label' => 'Boek VCA Basis (NL)', 'price' => 36],
                ['id' => 'boek-vca-combi-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
        ],
        'los-examen-vca-vol' => [
            'nl' => [
                ['id' => 'e-learning-vca-vol-nl', 'label' => 'E-learning VCA Vol (NL)', 'price' => 39],
                ['id' => 'vca-vol-proefexamens-nl', 'label' => 'VCA Vol Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-vol-nl', 'label' => 'Boek VCA Vol (NL)', 'price' => 42],
                ['id' => 'boek-vca-combi-vol-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
            'en' => [
                ['id' => 'e-learning-vca-vol-en', 'label' => 'E-learning VCA Vol (EN)', 'price' => 59],
                ['id' => 'boek-vca-vol-en', 'label' => 'Boek VCA Vol (EN)', 'price' => 62],
                ['id' => 'boek-vca-combi-vol-en', 'label' => 'Boek VCA Combi (EN)', 'price' => 69],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
        ],
        'los-examen-vca-vil' => [
            'nl' => [
                ['id' => 'e-learning-vca-vol-nl', 'label' => 'E-learning VCA Vol (NL)', 'price' => 39],
                ['id' => 'vca-vol-proefexamens-nl', 'label' => 'VCA Vol Proefexamens (NL)', 'price' => 25],
                ['id' => 'boek-vca-vol-nl', 'label' => 'Boek VCA Vol (NL)', 'price' => 42],
                ['id' => 'boek-vca-combi-vol-nl', 'label' => 'Boek VCA Combi (NL)', 'price' => 49],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
            'en' => [
                ['id' => 'e-learning-vca-vol-en', 'label' => 'E-learning VCA Vol (EN)', 'price' => 59],
                ['id' => 'boek-vca-vol-en', 'label' => 'Boek VCA Vol (EN)', 'price' => 62],
                ['id' => 'boek-vca-combi-vol-en', 'label' => 'Boek VCA Combi (EN)', 'price' => 69],
                ['id' => 'cursus-weekend', 'label' => 'Cursus weekend', 'price' => 245],
            ],
        ],
    ];
}

/**
 * Localize data naar JS.
 */
function pontifex_oi_get_product_data_for_js() {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $WEEKEND_ALLOWED_BY_EXAM;

    $exam_products_simple = [];
    foreach ($EXAM_PRODUCTS as $key => $item) {
        $exam_products_simple[$key] = [
            'label'  => $item['label'],
            'prices' => $item['prices'],
        ];
    }

    return [
        'examProducts'       => $exam_products_simple,
        'materialProducts' => $MATERIAL_PRODUCTS,
        'materialCombis'   => $MATERIAL_COMBIS,
        'weekendAllowedByExam' => $WEEKEND_ALLOWED_BY_EXAM,
    ];
}

/**
 * Extra materiaal-opties voor JS (vlak).
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

/**
 * Normaliseer externe/legacy exam keys naar interne canonical keys.
 */
function pontifex_normalize_exam_key($key) {
    $map = [
        'vca-basis'           => 'los-examen-vca-basis',
        'vca-vol'             => 'los-examen-vca-vol',
        'los-examen-vil-vcu'  => 'los-examen-vca-vil',
        'vca-basis-weekend'   => 'los-examen-vca-basis',
        'vca-vol-weekend'     => 'los-examen-vca-vol',
    ];
    return $map[$key] ?? $key;
}