<?php
// File: wp-content/plugins/pontifex-oi/templates/step-2-inschrijven.php
if (!defined('ABSPATH')) { exit; }
// --- 1) EERST: parameters uit URL lezen ---
$exam_type = isset($_GET['exam_type']) ? sanitize_text_field($_GET['exam_type']) : '';
$language = isset($_GET['language']) ? sanitize_text_field($_GET['language']) : '';
$material = isset($_GET['material']) ? sanitize_text_field($_GET['material']) : '1';
$date = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$time = isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '';
$location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
$province = isset($_GET['province']) ? sanitize_text_field($_GET['province']) : '';
$spots = isset($_GET['spots']) ? sanitize_text_field($_GET['spots']) : '';
// Speciaal: directe link modus (extra_option)
$direct_link_mode = isset($_GET['extra_option']) ? sanitize_text_field($_GET['extra_option']) : '';
// Backwards compatibility / normalisatie van directe link keys
if ($direct_link_mode === 'weekend_dh' || $direct_link_mode === 'cursus-weekend') {
    $direct_link_mode = 'cursus-weekend-' . ($language ?: 'nl');
}
if (empty($language) && !empty($direct_link_mode) && str_starts_with($direct_link_mode, 'cursus-weekend-')) {
    $language = 'nl';
    $direct_link_mode = 'cursus-weekend-nl';
} elseif (empty($language) && empty($direct_link_mode) && str_starts_with($exam_type, 'vca-weekend')) {
    $language = 'nl';
}
// --- 2) DAN: redirecten als beide ontbreken ---
if (empty($exam_type) && empty($direct_link_mode)) {
    wp_safe_redirect( home_url('/cursus-zoeken/') );
    exit;
}
// Laad gecombineerde productconfiguratie (inclusief EXTRA_PRODUCTS)
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
// ✅ BELANGRIJK: maak globals zichtbaar binnen deze scope
global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $WEEKEND_ALLOWED_BY_EXAM, $EXTRA_PRODUCTS, $MATERIAL_COMBIS;
// ✅ Normaliseer exam key direct hier
$exam_type = pontifex_normalize_exam_key($exam_type);
// Bepalen welke optie vooraf aangevinkt moet zijn
$prechecked_option = '';
$extra_options_for_calc = []; // Initialiseer voor calculate_total_price
// AANPASSING: Gebruik direct $direct_link_mode (matcht nu key in EXTRA_PRODUCTS)
if (!empty($direct_link_mode) && isset($EXTRA_PRODUCTS[$direct_link_mode])) {
    $prechecked_option = $direct_link_mode;
    // AANPASSING: Voeg altijd toe aan calc (inclusief weekend, voor Flow 2 prijs)
    $extra_options_for_calc[] = $direct_link_mode;
}
$language_labels = [
    'nl' => 'Nederlands',
    'en' => 'Engels',
    'de' => 'Duits',
    'fr' => 'Frans',
    'ar' => 'Arabisch',
    'bg' => 'Bulgaars',
    'lt' => 'Litouws',
    'pl' => 'Pools',
    'pt' => 'Portugees',
    'ro' => 'Roemeens',
    'ru' => 'Russisch',
    'tr' => 'Turks',
    'el' => 'Grieks',
    'hu' => 'Hongaars',
    'it' => 'Italiaans',
    'hr' => 'Kroatisch',
    'uk' => 'Oekraïens',
    'sk' => 'Slowaaks',
    'es' => 'Spaans',
    'vi' => 'Vietnamees',
];
// ✅ NIEUW: Mapping voor examensoort labels (gebruikt voor stap 2 display)
$exam_type_labels = [
    'los-examen-vca-basis' => 'VCA Basis',
    'los-examen-vca-basis-groen' => 'VCA Basis Groen',
    'los-examen-vca-vol' => 'VCA Vol',
    'los-examen-vca-vil' => 'VCA VIL',
    'vca-basis-weekend' => 'Weekendcursus VCA Basis',
    'vca-vol-weekend' => 'Weekendcursus VCA Vol',
];
// ✅ NIEUW: Mapping voor soort examen labels (gebruikt voor stap 2 display)
$material_type_labels = [
    '1' => 'Los examen',
    '2' => 'Examen + boek',
    '4' => 'Examen + e-learning',
    '5' => 'Examen + proefexamens',
    '6' => 'Examen + boek + proefexamens',
    '7' => 'Examen + e-learning + proefexamens',
    'cursus-weekend' => 'Weekendcursus met examen',
    'cursus-weekend-nl' => 'Weekendcursus met examen',
    'cursus-weekend-en' => 'Weekend course with exam',
];
// ✅ Bepaal de labels voor display in stap 2
$exam_display_label = $exam_type_labels[$exam_type] ?? $exam_type;
$material_display_label = $material_type_labels[$material] ?? $material;
$url_price = isset($_GET['price']) ? sanitize_text_field($_GET['price']) : '';
$url_price_numeric = 0;
if (!empty($url_price)) {
    // Gebruik van een robuustere methode om de prijs uit de URL te halen
    $clean_price = preg_replace('/[^\d.,]/', '', $url_price);
    $clean_price = str_replace(',', '.', $clean_price);
    $url_price_numeric = floatval($clean_price);
}
function starts_with($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}
try {
    // DEZE CALCULATIE IS GEBRUIKT VOOR BACKWARDS COMPATIBILITY.
    // De nieuwe logica gebruikt calculate_totals_with_vat direct hieronder.
    if (!empty($direct_link_mode)) {
        $base_price = 0;
        foreach ($extra_options_for_calc as $opt_id) {
            if (isset($EXTRA_PRODUCTS[$opt_id])) {
                $base_price += (float) $EXTRA_PRODUCTS[$opt_id]['price'];
            }
        }
        $calculated_price = $base_price;
    } else {
        $calc = \PontifexOI\Helpers\PaymentHelpers::calculate_total_price([
            'exam_type' => $exam_type,
            'language' => $language,
            'material' => $material,
            'candidate_count' => 1,
            'extra_options' => $extra_options_for_calc,
        ]);
        $calculated_price = is_array($calc) && isset($calc['total']) ? (float) $calc['total'] : (float) $calc;
    }
} catch (\Exception $e) {
    $calculated_price = 0;
}
// FIX: Bereken totals voor display en data attrs (voor 1 kandidaat)
// Gebruik een count van 1 om de basisprijs per kandidaat te krijgen
$totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat([
    'exam_type' => $exam_type,
    'language' => $language,
    'material' => $material,
    'candidate_count' => 1,
    'extra_options' => $extra_options_for_calc,
]);
// ----------------------------------------------------------------------
// ✅ CORRECTIE: NIEUWE PRIJSLOGICA
// ----------------------------------------------------------------------
// Totaalbedragen (gebruikt in UI)
$price_display = '€' . number_format((float)$totals['incl'], 2, ',', '.'); // zichtbaar in scherm (Incl. BTW)
$price_for_input = number_format((float)$totals['incl'], 2, '.', ''); // gebruikt bij Mollie/payment (Incl. BTW)
// Nieuw: gebruik aparte variabele voor JS basisprijs (Excl. BTW)
$base_price_excl = (float)$totals['excl']; // voor data-base-price attribuut
// ----------------------------------------------------------------------
// De oude weekend forcing logica is niet meer nodig nu we $base_price_excl gebruiken
$__is_weekend = in_array($material, ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en'], true)
    || (!empty($direct_link_mode) && in_array($direct_link_mode, ['cursus-weekend-nl','cursus-weekend-en', 'weekend_dh', 'cursus-weekend'], true))
    || in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend']);
$exam_key = $exam_type;
$base_label = $EXAM_PRODUCTS[$exam_key]['label'] ?? '';
$exam_display = trim($base_label) ? ($base_label . ' met examen') : '';
?>
<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 2: Inschrijven', 'pontifex-oi'); ?>
</div>
<section class="pontifex-oi-section pontifex-oi-registration" id="step-2">
    <div class="pontifex-oi-title-row">
        <h2 class="pontifex-oi-section-label"><?php esc_html_e('Gegevens kandidaat', 'pontifex-oi'); ?></h2>
        <a href="/cursus-zoeken/" class="pontifex-oi-back-link" tabindex="0"><?php esc_html_e('Terug', 'pontifex-oi'); ?></a>
    </div>
    <form class="pontifex-oi-candidate-form" method="post" autocomplete="on" novalidate id="registration-form" data-custom-price="<?php echo esc_attr($url_price_numeric > 0 ? 1 : 0); ?>">
        <input type="hidden" id="exam_type" name="exam_type" value="<?php echo esc_attr($exam_type); ?>">
        <input type="hidden" id="language" name="language" value="<?php echo esc_attr($language); ?>">
        <input type="hidden" id="material" name="material" value="<?php echo esc_attr($material); ?>">
        <input type="hidden" id="date" name="date" value="<?php echo esc_attr($date); ?>">
        <input type="hidden" id="time" name="time" value="<?php echo esc_attr($time); ?>">
        <input type="hidden" id="location" name="location" value="<?php echo esc_attr($location); ?>">
        <input type="hidden" id="province" name="province" value="<?php echo esc_attr($province); ?>">
        <input type="hidden" id="spots" name="spots" value="<?php echo esc_attr($spots); ?>">
        <input type="hidden" id="extra_option_direct" name="extra_option_direct" value="<?php echo esc_attr($direct_link_mode); ?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce('pontifex_oi_nonce') ); ?>">
        <div class="pontifex-oi-candidates-list">
            <div class="pontifex-oi-candidate-row">
                <div>
                    <label for="candidate_fullname_1"><?php esc_html_e('Voornaam', 'pontifex-oi'); ?> <span class="required">*</span></label>
                    <input type="text" id="candidate_fullname_1" name="candidate_fullname[]" required placeholder="<?php esc_attr_e('Bijv. Jan', 'pontifex-oi'); ?>">
                </div>
                <div>
                    <label for="candidate_infix_1"><?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?></label>
                    <input type="text" id="candidate_infix_1" name="candidate_infix[]" placeholder="<?php esc_attr_e('Bijv. van der', 'pontifex-oi'); ?>">
                </div>
                <div>
                    <label for="candidate_lastname_1"><?php esc_html_e('Achternaam', 'pontifex-oi'); ?> <span class="required">*</span></label>
                    <input type="text" id="candidate_lastname_1" name="candidate_lastname[]" required placeholder="<?php esc_attr_e('Bijv. Jansen', 'pontifex-oi'); ?>">
                </div>
                <div class="pontifex-oi-candidate-birthdate-wrapper">
                    <label for="candidate_birthdate_1"><?php esc_html_e('Geboortedatum', 'pontifex-oi'); ?> <span class="required">*</span></label>
                    <input type="text" id="candidate_birthdate_1" name="candidate_birthdate[]" required pattern="\d{2}-\d{2}-\d{4}" placeholder="<?php esc_attr_e('dd-mm-jjjj (bijv. 30-06-1992)', 'pontifex-oi'); ?>">
                </div>
                <button type="button" class="pontifex-oi-remove-candidate" title="<?php esc_attr_e('Verwijder kandidaat', 'pontifex-oi'); ?>">×</button>
            </div>
        </div>
        <div class="pontifex-oi-candidate-addrow">
            <button type="button" class="pontifex-oi-add-candidate"><?php esc_html_e('Kandidaat toevoegen', 'pontifex-oi'); ?></button>
        </div>
        <div class="pontifex-oi-flex-row">
            <div class="pontifex-oi-order-form">
                <h2 class="pontifex-oi-section-label"><?php esc_html_e('Inschrijvingsgegevens', 'pontifex-oi'); ?></h2>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-name-row pontifex-oi-name-row-mobile">
                        <div class="field first-name-group">
                            <label for="order_initials"><?php esc_html_e('Voorletters', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_initials" name="given-name" autocomplete="given-name" required placeholder="<?php esc_attr_e('Bijv. J.A.', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field middle-name-group">
                            <label for="order_infix"><?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?></label>
                            <input type="text" id="order_infix" name="additional-name" autocomplete="additional-name" placeholder="<?php esc_attr_e('Bijv. van', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field last-name-group">
                            <label for="order_lastname"><?php esc_html_e('Achternaam', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_lastname" name="family-name" autocomplete="family-name" required placeholder="<?php esc_attr_e('Bijv. Jansen', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_postcode"><?php esc_html_e('Postcode', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_postcode" name="postal-code" autocomplete="postal-code" required placeholder="<?php esc_attr_e('Bijv. 1234 AB', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_housenumber"><?php esc_html_e('Huisnummer', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_housenumber" name="address-line2" autocomplete="address-line2" required placeholder="<?php esc_attr_e('Bijv. 12 of 12A', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_street"><?php esc_html_e('Straat', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_street" name="street-address" autocomplete="street-address" required placeholder="<?php esc_attr_e('Bijv. Dorpsstraat', 'pontifex-oi'); ?>" >
                        </div>
                        <div class="field">
                            <label for="order_city"><?php esc_html_e('Plaats', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_city" name="address-level2" autocomplete="address-level2" required placeholder="<?php esc_attr_e('Bijv. Amsterdam', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-contact-row two-cols">
                        <div class="field">
                            <label for="order_phone"><?php esc_html_e('Telefoonnummer', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="tel" id="order_phone" name="tel" autocomplete="tel" inputmode="tel" required pattern="^\+?[0-9\s\-]{6,}$" placeholder="<?php esc_attr_e('Bijv. 0612345678 of +31 6 12345678', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_email"><?php esc_html_e('E-mailadres', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="email" id="order_email" name="order_email" autocomplete="email" inputmode="email" required placeholder="<?php esc_attr_e('Bijv. naam@bedrijf.nl', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_company"><?php esc_html_e('Bedrijfsnaam', 'pontifex-oi'); ?></label>
                            <input type="text" id="order_company" name="organization" autocomplete="organization" placeholder="<?php esc_attr_e('Bijv. Bouw BV', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_function"><?php esc_html_e('Functie', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_function" name="organization-title" autocomplete="organization-title" required placeholder="<?php esc_attr_e('Bijv. Voorman', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_vat"><?php esc_html_e('BTW-nummer', 'pontifex-oi'); ?></label>
                            <input type="text" id="order_vat" name="order_vat" placeholder="<?php esc_attr_e('Bijv. NL123456789B01', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field"></div>
                    </div>
                </div>
            </div>
            <div class="pontifex-oi-order-summary">
                <h3 class="pontifex-oi-section-label"><?php esc_html_e('Gegevens van uw inschrijving', 'pontifex-oi'); ?></h3>
                <div class="besteloverzicht-stap2">
                    <?php $is_flow2 = !empty($direct_link_mode);
                    // 🔹 FLOW 1: reguliere inschrijving
                    if (!$is_flow2) : ?>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Examen', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($exam_display_label ?: '-'); ?></span>
                        </div>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Lesmateriaal', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($material_display_label ?: '-'); ?></span>
                        </div>
                        <div class="bo-item"><strong><?php esc_html_e('Taal', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($language_labels[$language] ?? $language); ?></span></div>
                        <?php if (empty($direct_link_mode)) : // Datum, tijd, locatie, provincie alleen voor Flow 1 ?>
                            <div class="bo-item"><strong><?php esc_html_e('Datum', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($date ?: '-'); ?></span></div>
                            <div class="bo-item"><strong><?php esc_html_e('Tijd', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($time ?: '-'); ?></span></div>
                            <div class="bo-item"><strong><?php esc_html_e('Locatie', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($location ?: '-'); ?></span></div>
                            <div class="bo-item"><strong><?php esc_html_e('Provincie', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($province ?: '-'); ?></span></div>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php // HIER START HET AANGEPASTE BLOK VOOR EXTRA OPTIES (uit originele code, nu verplaatst)
                        // Dit blok verschijnt alleen in Flow 2 (directe link) ?>
                        <div class="bo-item extra-options">
                            <strong><?php esc_html_e('Extra opties', 'pontifex-oi'); ?></strong>
                            <span class="pontifex-oi-extra-options-wrapper">
                                <div class="pontifex-oi-extra-column">
                                    <div class="pontifex-oi-extra-subtitle">Nederlands</div>
                                    <div class="pontifex-oi-extra-mini">VCA Basis</div>
                                    <?php foreach (['vca_proefexamen_nl','vca_elearning_nl','boek_basis_nl','boek_combi_nl'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option); // Check pre-check logic
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="pontifex-oi-extra-mini">VCA Vol</div>
                                    <?php foreach (['vca_vol_proefexamen_nl','vca_vol_elearning_nl','boek_vol_nl','boek_combi_vol_nl'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option); // Check pre-check logic
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="pontifex-oi-extra-mini">Weekendcursus</div>
                                    <?php foreach (['cursus-weekend-nl'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option);
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="pontifex-oi-extra-column">
                                    <div class="pontifex-oi-extra-subtitle">Engels</div>
                                    <div class="pontifex-oi-extra-mini">VCA Basis</div>
                                    <?php foreach (['vca_elearning_en','boek_basis_en','boek_combi_en'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option); // Check pre-check logic
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="pontifex-oi-extra-mini">VCA Vol</div>
                                    <?php foreach (['vca_vol_elearning_en','boek_vol_en','boek_combi_vol_en'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option); // Check pre-check logic
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="pontifex-oi-extra-mini">Weekend course</div>
                                    <?php foreach (['cursus-weekend-en'] as $id) :
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $product = $EXTRA_PRODUCTS[$id];
                                        $is_prechecked = ($id === $prechecked_option);
                                        $dataPriceExcl = (float) $product['price'];
                                        $labelPrice = '€' . number_format($product['price'], 2, ',', '.');
                                        ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo number_format($dataPriceExcl, 2, '.', ''); ?>"
                                                   <?php if ($is_prechecked): ?>checked="checked" data-prechecked="1" data-auto-select="1"<?php endif; ?>>
                                                  <?php echo esc_html($product['label']); ?> (<?php echo $labelPrice; ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="bo-item"><strong><?php esc_html_e('Aantal kandidaten', 'pontifex-oi'); ?></strong> <span id="candidate-count">1</span></div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('BTW', 'pontifex-oi'); ?></strong>
                        <span id="vat-amount"><?php echo '€' . number_format((float)$totals['vat_total'], 2, ',', '.'); ?></span>
                    </div>
                    <div class="bo-item totaal"><strong><?php esc_html_e('Prijs totaal', 'pontifex-oi'); ?></strong> <span id="total-price"><?php echo $price_display; ?></span></div>
                </div>
                <button type="submit" class="pontifex-oi-submit-order"><?php esc_html_e('Inschrijving afronden', 'pontifex-oi'); ?> <i class="fa fa-arrow-right"></i></button>
            </div>
        </div>
        <input
            type="hidden"
            id="payment_amount"
            name="payment_amount"
            value="<?php echo esc_attr($price_for_input); ?>"
            data-base-price="<?php echo esc_attr(number_format((float)$base_price_excl, 2, '.', '')); ?>"
            data-base-vat="<?php echo esc_attr(number_format((float)($totals['vat_total'] ?? 0), 2, '.', '')); ?>"
            data-base-vat9="<?php echo esc_attr(number_format((float)($totals['vat9'] ?? 0), 2, '.', '')); ?>"
            data-custom-price="<?php echo esc_attr($url_price_numeric > 0 ? 1 : 0); ?>"
        >
        <input type="hidden" name="exam_label" value="<?php echo esc_attr($exam_display_label); ?>">
        <input type="hidden" name="material_label" value="<?php echo esc_attr($material_display_label); ?>">
    </form>
</section>