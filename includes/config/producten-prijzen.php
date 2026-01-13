<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/* -------------------------------------------
 * EXTRA OPTIES ($EXTRA_PRODUCTS)
 * Wordt gebruikt voor losse checkbox-opties.
 * ------------------------------------------- */
$EXTRA_PRODUCTS = [
    // 🇳🇱 Basis NL
    'vca_proefexamen_nl' => [
        'label' => 'Proefexamen',
        'price' => 25.00,
    ],
    'vca_elearning_nl' => [
        'label' => 'E-learning met proefexamen',
        'price' => 29.00,
    ],
    'boek_basis_nl' => [
        'label' => 'Boek',
        'price' => 36.00,
    ],
    'boek_combi_nl' => [
        'label' => 'Boek combi',
        'price' => 49.00,
    ],
    // 🇬🇧 Basis EN
    'vca_proefexamen_en' => [
        'label' => 'Practice exams',
        'price' => 25.00,
    ],
    'vca_elearning_en' => [
        'label' => 'E-learning with practice exam',
        'price' => 49.00,
    ],
    'boek_basis_en' => [
        'label' => 'Book',
        'price' => 56.00,
    ],
    'boek_combi_en' => [
        'label' => 'Book combi',
        'price' => 69.00,
    ],
    // 🇳🇱 Vol NL
    'vca_vol_proefexamen_nl' => [
        'label' => 'Proefexamen',
        'price' => 25.00,
    ],
    'vca_vol_elearning_nl' => [
        'label' => 'E-learning met proefexamen',
        'price' => 39.00,
    ],
    'boek_vol_nl' => [
        'label' => 'Boek',
        'price' => 42.00,
    ],
    'boek_combi_vol_nl' => [
        'label' => 'Boek combi',
        'price' => 49.00,
    ],
    // 🇬🇧 Vol EN
    'vca_vol_proefexamen_en' => [
        'label' => 'Practice exams',
        'price' => 25.00,
    ],
    'vca_vol_elearning_en' => [
        'label' => 'E-learning with practice exam',
        'price' => 59.00,
    ],
    'boek_vol_en' => [
        'label' => 'Book',
        'price' => 62.00,
    ],
    'boek_combi_vol_en' => [
        'label' => 'Book combi',
        'price' => 69.00,
    ],
    // Weekendcursus
    'cursus-weekend-nl' => [
        'label' => 'Met examen',
        'price' => 245.00,
    ],
    'cursus-weekend-en' => [
        'label' => 'With exam',
        'price' => 245.00,
    ],
    // Vertaalde/aliassen
    'cursus-weekend' => ['label' => 'Weekendcursus met examen', 'price' => 245],
    'vca-basis-proefexamens-nl' => ['label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
    'vca-vol-proefexamens-nl' => ['label' => 'VCA Vol Proefexamens (NL)', 'price' => 25],
    'proefexamens-en' => ['label' => 'Proefexamens (EN)', 'price' => 25.00],
];
// ---------------------------------------------------------------------------------------------------------------------
/* -------------------------------------------
 * EXAMENSOORTEN ($EXAM_PRODUCTS)
 * Examenprijzen bevatten geen 'vat'-sleutel; deze worden behandeld als 21% in de PaymentHelpers.
 * ------------------------------------------- */
$EXAM_PRODUCTS = [
    // VCA Basis
    'los-examen-vca-basis' => [
        'label' => 'VCA Basis',
        'prices' => [
            // Kern-talen
            'nl' => 129, 'de' => 129, 'en' => 129, 'fr' => 129,
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
        'label' => 'VCA Basis Groen',
        'prices' => ['nl' => 129],
    ],
    // VCA VOL
    'los-examen-vca-vol' => [
        'label' => 'VCA Vol',
        'prices' => [
            'nl' => 139, 'de' => 139, 'en' => 139, 'fr' => 139,
        ],
    ],
   
    // VCA VIL
    'los-examen-vca-vil' => [
        'label' => 'VCA VIL',
        'prices' => [
            'nl' => 139, 'en' => 139,
        ],
    ],
   
    // Toegevoegde weekend optie (Flow 1)
    'vca-weekend-nl' => [
        'label' => 'WeekendCursus (NL)',
        'prices' => ['nl' => 245],
    ],
];
// ---------------------------------------------------------------------------------------------------------------------
/* -------------------------------------------
 * LOS MATERIAAL ($MATERIAL_PRODUCTS)
 * ------------------------------------------- */
$MATERIAL_PRODUCTS = [
    // BASIS NL
    'e-learning-vca-basis-nl' => ['label' => 'E-learning met proefexamen (NL)', 'price' => 29],
    'vca-basis-proefexamens-nl' => ['label' => 'VCA Basis Proefexamens (NL)', 'price' => 25],
    'boek-vca-basis-nl' => ['label' => 'Boek VCA Basis (NL)', 'price' => 36],
    'boek-vca-combi-nl' => ['label' => 'Boek VCA Combi (NL)', 'price' => 49],
    // VOL NL
    'e-learning-vca-vol-nl' => ['label' => 'E-learning met proefexamen (NL)', 'price' => 39],
    'vca-vol-proefexamens-nl' => ['label' => 'VCA Vol Proefexamens (NL)', 'price' => 25],
    'boek-vca-vol-nl' => ['label' => 'Boek VCA Vol (NL)', 'price' => 42],
    'boek-vca-combi-vol-nl' => ['label' => 'Boek VCA Combi (NL)', 'price' => 49],
    // BASIS EN
    'e-learning-vca-basis-en' => ['label' => 'E-learning met proefexamen (EN)', 'price' => 49],
    'boek-vca-basis-en' => ['label' => 'Boek VCA Basis (EN)', 'price' => 56],
    'boek-vca-combi-en' => ['label' => 'Boek VCA Combi (EN)', 'price' => 69],
    // VOL EN
    'e-learning-vca-vol-en' => ['label' => 'E-learning met proefexamen (EN)', 'price' => 59],
    'boek-vca-vol-en' => ['label' => 'Boek VCA Vol (EN)', 'price' => 62],
    'boek-vca-combi-vol-en' => ['label' => 'Boek VCA Combi (EN)', 'price' => 69],
   
    // Weekend + losse extras
    'cursus-weekend' => ['label' => 'Weekendcursus met examen', 'price' => 245],
    'proefexamens-en' => ['label' => 'Proefexamens (EN)', 'price' => 25.00],
];
// ---------------------------------------------------------------------------------------------------------------------
/* -------------------------------------------
 * PRODUCT PAKKETTEN ($MATERIAL_COMBIS)
 * Geen wijziging nodig.
 * ------------------------------------------- */
$MATERIAL_COMBIS = [
    '1' => [],
    '2_basis' => ['boek-vca-basis-nl'],
    '2_vol' => ['boek-vca-vol-nl'],
    '4_basis' => ['e-learning-vca-basis-nl'],
    '4_vol' => ['e-learning-vca-vol-nl'],
    '5_basis' => ['vca-basis-proefexamens-nl'],
    '5_vol' => ['vca-vol-proefexamens-nl'],
    '6_basis' => ['boek-vca-basis-nl', 'vca-basis-proefexamens-nl'],
    '6_vol' => ['boek-vca-vol-nl', 'vca-vol-proefexamens-nl'],
    '7_basis' => ['e-learning-vca-basis-nl', 'vca-basis-proefexamens-nl'],
    '7_vol' => ['e-learning-vca-vol-nl', 'vca-vol-proefexamens-nl'],
];
// ---------------------------------------------------------------------------------------------------------------------
/* -------------------------------------------
 * WEEKEND BESCHIKBAARHEID ($WEEKEND_ALLOWED_BY_EXAM)
 * Geen wijziging nodig.
 * ------------------------------------------- */
$WEEKEND_ALLOWED_BY_EXAM = [
    'los-examen-vca-basis' => ['nl','en'],
    'los-examen-vca-vol' => ['nl','en'],
    'los-examen-vca-basis-groen' => [],
    'los-examen-vca-vil' => [],
];
// ---------------------------------------------------------------------------------------------------------------------
/* -------------------------------------------
 * HULPFUNCTIES
 * Geen wijziging nodig.
 * ------------------------------------------- */
/**
 * Normaliseer externe/legacy exam keys naar interne canonical keys.
 */
function pontifex_normalize_exam_key($key) {
    $map = [
        'vca-basis' => 'los-examen-vca-basis',
        'vca-vol' => 'los-examen-vca-vol',
        'los-examen-vil-vcu' => 'los-examen-vca-vil',
        'vca-weekend-nl' => 'vca-weekend-nl',
    ];
    return $map[$key] ?? $key;
}
/**
 * Prijsberekening examen + materiaal.
 */
function get_product_price($exam_type, $language = 'nl', $material = '') {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS;
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
        $suffix = (strpos($exam_type, 'vol') !== false || strpos($exam_type, 'vil') !== false) ? 'vol' : 'basis';
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
 * Alle extra materialen (nieuw na samenvoeging).
 */
function get_all_extra_products() {
    global $EXTRA_PRODUCTS;
    return $EXTRA_PRODUCTS;
}
/**
 * Localize data naar JS.
 */
function pontifex_oi_get_product_data_for_js() {
    global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $MATERIAL_COMBIS, $WEEKEND_ALLOWED_BY_EXAM, $EXTRA_PRODUCTS;
    $exam_products_simple = [];
    foreach ($EXAM_PRODUCTS as $key => $item) {
        $exam_products_simple[$key] = [
            'label' => $item['label'],
            'prices' => $item['prices'],
        ];
    }
    // 🔒 Filter: toon “proefexamen(s)” alleen in NL
    $extra_filtered = [];
    foreach ($EXTRA_PRODUCTS as $k => $v) {
        // Controleer op 'proefexamen' in de key of 'proef'/'practice' in het label
        $isProef = (stripos($k, 'proefexamen') !== false) || (stripos($v['label'] ?? '', 'proef') !== false) || (stripos($v['label'] ?? '', 'practice') !== false);
       
        // Alleen NL-versies (of items die geen duidelijke taal-suffix hebben) toestaan
        if ($isProef && (stripos($k, '_nl') === false) && (stripos($k, '-nl') === false)) {
            // Als het Proefexamen is maar GEEN NL-suffix heeft, dan negeren (zoals vca_proefexamen_en en proefexamens-en)
            continue;
        }
        $extra_filtered[$k] = $v;
    }
    // 🔒 Filter ook de losse materialen (veiligheidshalve)
    $material_filtered = $MATERIAL_PRODUCTS;
    foreach ($material_filtered as $k => $v) {
        // Controleer op 'proefexamen' in de key of 'proef'/'practice' in het label
        $isProef = (stripos($k, 'proefexamen') !== false) || (stripos($v['label'] ?? '', 'proef') !== false) || (stripos($v['label'] ?? '', 'practice') !== false);
       
        if ($isProef && (stripos($k, '-nl') === false) && (stripos($k, '_nl') === false)) {
            // Als het Proefexamen is maar GEEN NL-suffix heeft, dan verwijderen
            unset($material_filtered[$k]);
        }
    }
    return [
        'examProducts' => $exam_products_simple,
        'materialProducts' => $material_filtered,
        'materialCombis' => $MATERIAL_COMBIS,
        'extraProducts' => $extra_filtered,
        'weekendAllowedByExam' => $WEEKEND_ALLOWED_BY_EXAM,
    ];
}