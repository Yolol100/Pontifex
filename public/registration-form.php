<?php
/**
 * Pontifex OI - Stap 2: Inschrijven
 *
 * Formulier voor het invoeren van kandidaat- en factuurgegevens.
 * Wordt getoond na selectie van een examenmoment.
 *
 * @package PontifexOI
 * @since   1.0.0
 */

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// 1. URL parameters veilig inlezen + normaliseren
// -----------------------------------------------------------------------------
$exam_type   = isset($_GET['exam_type'])   ? sanitize_text_field($_GET['exam_type'])   : '';
$language    = isset($_GET['language'])    ? sanitize_text_field($_GET['language'])    : 'nl';
$material    = isset($_GET['material'])    ? sanitize_text_field($_GET['material'])    : '1';
$date        = isset($_GET['date'])        ? sanitize_text_field($_GET['date'])        : '';
$time        = isset($_GET['time'])        ? sanitize_text_field($_GET['time'])        : '';
$location    = isset($_GET['location'])    ? sanitize_text_field($_GET['location'])    : '';
$province    = isset($_GET['province'])    ? sanitize_text_field($_GET['province'])    : '';
$spots       = isset($_GET['spots'])       ? sanitize_text_field($_GET['spots'])       : '';
$direct_link = isset($_GET['extra_option']) ? sanitize_text_field($_GET['extra_option']) : '';

// Extra beveiliging: prijs uit URL (voor custom pricing gevallen)
$url_price       = isset($_GET['price']) ? sanitize_text_field($_GET['price']) : '';
$url_price_numeric = 0.0;
if ($url_price !== '') {
    $clean = preg_replace('/[^\d.,]/', '', $url_price);
    $clean = str_replace(',', '.', $clean);
    $url_price_numeric = (float) $clean;
}

// Normalisatie directe link (backward compatibility)
if (in_array($direct_link, ['weekend_dh', 'cursus-weekend'], true)) {
    $direct_link = 'cursus-weekend-' . ($language ?: 'nl');
}

// Redirect als beide hoofdfilters ontbreken
if (empty($exam_type) && empty($direct_link)) {
    wp_safe_redirect(home_url('/cursus-zoeken/'));
    exit;
}

// -----------------------------------------------------------------------------
// 2. Configuratie laden
// -----------------------------------------------------------------------------
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $EXTRA_PRODUCTS, $MATERIAL_COMBIS;

// -----------------------------------------------------------------------------
// 3. Examennamen & labels (voor display)
// -----------------------------------------------------------------------------
$exam_type_labels = [
    'los-examen-vca-basis'       => __('VCA Basis', 'pontifex-oi'),
    'los-examen-vca-basis-groen' => __('VCA Basis Groen', 'pontifex-oi'),
    'los-examen-vca-vol'         => __('VCA Vol', 'pontifex-oi'),
    'los-examen-vca-vil'         => __('VCA VIL', 'pontifex-oi'),
    'vca-basis-weekend'          => __('Weekendcursus VCA Basis', 'pontifex-oi'),
    'vca-vol-weekend'            => __('Weekendcursus VCA Vol', 'pontifex-oi'),
];

$material_labels = [
    '1'                  => __('Los examen', 'pontifex-oi'),
    '2'                  => __('Examen + boek', 'pontifex-oi'),
    '4'                  => __('Examen + e-learning', 'pontifex-oi'),
    '5'                  => __('Examen + proefexamens', 'pontifex-oi'),
    '6'                  => __('Examen + boek + proefexamens', 'pontifex-oi'),
    '7'                  => __('Examen + e-learning + proefexamens', 'pontifex-oi'),
    'cursus-weekend'     => __('Weekendcursus met examen', 'pontifex-oi'),
    'cursus-weekend-nl'  => __('Weekendcursus met examen', 'pontifex-oi'),
    'cursus-weekend-en'  => __('Weekend course with exam', 'pontifex-oi'),
];

// Taal labels (voor weergave)
$language_labels = [
    'nl' => __('Nederlands', 'pontifex-oi'),
    'en' => __('Engels', 'pontifex-oi'),
    'de' => __('Duits', 'pontifex-oi'),
    'fr' => __('Frans', 'pontifex-oi'),
    // ... voeg hier eventueel meer toe
];

$exam_display     = $exam_type_labels[$exam_type]   ?? $exam_type;
$material_display = $material_labels[$material]     ?? $material;

// -----------------------------------------------------------------------------
// 4. Prijsberekening (voor 1 kandidaat)
// -----------------------------------------------------------------------------
$extra_options = !empty($direct_link) && isset($EXTRA_PRODUCTS[$direct_link])
    ? [$direct_link]
    : [];

$totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat([
    'exam_type'       => $exam_type,
    'language'        => $language,
    'material'        => $material,
    'candidate_count' => 1,
    'extra_options'   => $extra_options,
]);

$price_incl    = '€' . number_format((float)($totals['incl'] ?? 0), 2, ',', '.');
$base_excl     = (float)($totals['excl'] ?? 0);
$vat_total     = (float)($totals['vat_total'] ?? 0);
$price_numeric = number_format((float)($totals['incl'] ?? 0), 2, '.', '');

// -----------------------------------------------------------------------------
// 5. Flow detectie
// -----------------------------------------------------------------------------
$is_flow2 = !empty($direct_link);
?>

<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 2: Inschrijven', 'pontifex-oi'); ?>
</div>

<section class="pontifex-oi-section pontifex-oi-registration" id="step-2">
    <div class="pontifex-oi-title-row">
        <h2 class="pontifex-oi-section-label"><?php esc_html_e('Gegevens kandidaat', 'pontifex-oi'); ?></h2>
        <a href="<?php echo esc_url(home_url('/cursus-zoeken/')); ?>"
           class="pontifex-oi-back-link"
           aria-label="<?php esc_attr_e('Terug naar overzicht', 'pontifex-oi'); ?>">
            <?php esc_html_e('Terug', 'pontifex-oi'); ?>
        </a>
    </div>

    <form class="pontifex-oi-candidate-form"
          method="post"
          autocomplete="on"
          novalidate
          id="registration-form"
          data-custom-price="<?php echo $url_price_numeric > 0 ? '1' : '0'; ?>">

        <!-- Verborgen velden met geselecteerde filters -->
        <input type="hidden" name="exam_type"        value="<?php echo esc_attr($exam_type); ?>">
        <input type="hidden" name="language"         value="<?php echo esc_attr($language); ?>">
        <input type="hidden" name="material"         value="<?php echo esc_attr($material); ?>">
        <input type="hidden" name="date"             value="<?php echo esc_attr($date); ?>">
        <input type="hidden" name="time"             value="<?php echo esc_attr($time); ?>">
        <input type="hidden" name="location"         value="<?php echo esc_attr($location); ?>">
        <input type="hidden" name="province"         value="<?php echo esc_attr($province); ?>">
        <input type="hidden" name="spots"            value="<?php echo esc_attr($spots); ?>">
        <input type="hidden" name="extra_option_direct" value="<?php echo esc_attr($direct_link); ?>">
        <input type="hidden" name="nonce"            value="<?php echo esc_attr(wp_create_nonce('pontifex_oi_nonce')); ?>">

        <!-- Kandidaten lijst -->
        <div class="pontifex-oi-candidates-list">
            <div class="pontifex-oi-candidate-row">
                <div class="field">
                    <label for="candidate_fullname_1">
                        <?php esc_html_e('Voornaam', 'pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_fullname_1"
                           name="candidate_fullname[]"
                           required
                           placeholder="<?php esc_attr_e('Bijv. Jan', 'pontifex-oi'); ?>">
                </div>

                <div class="field">
                    <label for="candidate_infix_1"><?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?></label>
                    <input type="text"
                           id="candidate_infix_1"
                           name="candidate_infix[]"
                           placeholder="<?php esc_attr_e('Bijv. van der', 'pontifex-oi'); ?>">
                </div>

                <div class="field">
                    <label for="candidate_lastname_1">
                        <?php esc_html_e('Achternaam', 'pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_lastname_1"
                           name="candidate_lastname[]"
                           required
                           placeholder="<?php esc_attr_e('Bijv. Jansen', 'pontifex-oi'); ?>">
                </div>

                <div class="field pontifex-oi-candidate-birthdate-wrapper">
                    <label for="candidate_birthdate_1">
                        <?php esc_html_e('Geboortedatum', 'pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_birthdate_1"
                           name="candidate_birthdate[]"
                           required
                           pattern="\d{2}-\d{2}-\d{4}"
                           inputmode="numeric"
                           placeholder="<?php esc_attr_e('dd-mm-jjjj', 'pontifex-oi'); ?>">
                </div>

                <button type="button"
                        class="pontifex-oi-remove-candidate"
                        title="<?php esc_attr_e('Verwijder kandidaat', 'pontifex-oi'); ?>"
                        aria-label="<?php esc_attr_e('Verwijder deze kandidaat', 'pontifex-oi'); ?>">
                    ×
                </button>
            </div>
        </div>

        <div class="pontifex-oi-candidate-addrow">
            <button type="button" class="pontifex-oi-add-candidate">
                <?php esc_html_e('Kandidaat toevoegen', 'pontifex-oi'); ?>
            </button>
        </div>

        <!-- Twee kolommen layout: persoonsgegevens + overzicht -->
        <div class="pontifex-oi-flex-row">
            <!-- Persoonsgegevens -->
            <div class="pontifex-oi-order-form">
                <h2 class="pontifex-oi-section-label">
                    <?php esc_html_e('Jouw gegevens', 'pontifex-oi'); ?>
                </h2>

                <!-- Naam -->
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-name-row pontifex-oi-name-row-mobile">
                        <div class="field">
                            <label for="order_initials">
                                <?php esc_html_e('Voorletters', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_initials" 
                                   name="given-name" 
                                   autocomplete="given-name" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. J.A.', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_infix"><?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?></label>
                            <input type="text" 
                                   id="order_infix" 
                                   name="additional-name" 
                                   autocomplete="additional-name" 
                                   placeholder="<?php esc_attr_e('Bijv. van', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_lastname">
                                <?php esc_html_e('Achternaam', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_lastname" 
                                   name="family-name" 
                                   autocomplete="family-name" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. Jansen', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Adres -->
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_postcode">
                                <?php esc_html_e('Postcode', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_postcode" 
                                   name="postal-code" 
                                   autocomplete="postal-code" 
                                   required 
                                   pattern="[1-9][0-9]{3}\s?[A-Za-z]{2}"
                                   placeholder="<?php esc_attr_e('Bijv. 1234 AB', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_housenumber">
                                <?php esc_html_e('Huisnummer', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_housenumber" 
                                   name="address-line2" 
                                   autocomplete="address-line2" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. 12 of 12A', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_street">
                                <?php esc_html_e('Straat', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_street" 
                                   name="street-address" 
                                   autocomplete="street-address" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. Dorpsstraat', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_city">
                                <?php esc_html_e('Plaats', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_city" 
                                   name="address-level2" 
                                   autocomplete="address-level2" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. Amsterdam', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-contact-row two-cols">
                        <div class="field">
                            <label for="order_phone">
                                <?php esc_html_e('Telefoonnummer', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="tel" 
                                   id="order_phone" 
                                   name="tel" 
                                   autocomplete="tel" 
                                   inputmode="tel" 
                                   required 
                                   pattern="^\+?[0-9\s\-]{6,}$"
                                   placeholder="<?php esc_attr_e('Bijv. 0612345678', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_email">
                                <?php esc_html_e('E-mailadres', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   id="order_email" 
                                   name="order_email" 
                                   autocomplete="email" 
                                   inputmode="email" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. naam@bedrijf.nl', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Bedrijf (optioneel) -->
                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_company"><?php esc_html_e('Bedrijfsnaam', 'pontifex-oi'); ?></label>
                            <input type="text" 
                                   id="order_company" 
                                   name="organization" 
                                   autocomplete="organization" 
                                   placeholder="<?php esc_attr_e('Bijv. Bouw BV', 'pontifex-oi'); ?>">
                        </div>

                        <div class="field">
                            <label for="order_function">
                                <?php esc_html_e('Functie', 'pontifex-oi'); ?> <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="order_function" 
                                   name="organization-title" 
                                   autocomplete="organization-title" 
                                   required 
                                   placeholder="<?php esc_attr_e('Bijv. Voorman', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <!-- BTW optioneel -->
                <div class="pontifex-oi-row-group">
                    <div class="field">
                        <label for="order_vat"><?php esc_html_e('BTW-nummer', 'pontifex-oi'); ?></label>
                        <input type="text" 
                               id="order_vat" 
                               name="order_vat" 
                               placeholder="<?php esc_attr_e('Bijv. NL123456789B01', 'pontifex-oi'); ?>">
                    </div>
                </div>
            </div>

            <!-- Rechter kolom: overzicht -->
            <div class="pontifex-oi-order-summary">
                <h3 class="pontifex-oi-section-label">
                    <?php esc_html_e('Overzicht inschrijving', 'pontifex-oi'); ?>
                </h3>

                <div class="besteloverzicht-stap2">
                    <?php if (!$is_flow2): ?>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Examen', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($exam_display); ?></span>
                        </div>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Lesmateriaal', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($material_display); ?></span>
                        </div>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Taal', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($language_labels[$language] ?? ucfirst($language)); ?></span>
                        </div>
                        <?php if (!empty($date)): ?>
                            <div class="bo-item">
                                <strong><?php esc_html_e('Datum', 'pontifex-oi'); ?></strong>
                                <span><?php echo esc_html($date); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($time)): ?>
                            <div class="bo-item">
                                <strong><?php esc_html_e('Tijd', 'pontifex-oi'); ?></strong>
                                <span><?php echo esc_html($time); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($location)): ?>
                            <div class="bo-item">
                                <strong><?php esc_html_e('Locatie', 'pontifex-oi'); ?></strong>
                                <span><?php echo esc_html($location); ?></span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="bo-item">
                            <strong><?php esc_html_e('Type', 'pontifex-oi'); ?></strong>
                            <span><?php echo esc_html($exam_display); ?></span>
                        </div>

                        <div class="bo-item extra-options">
                            <strong><?php esc_html_e('Extra opties', 'pontifex-oi'); ?></strong>
                            <div class="pontifex-oi-extra-options-wrapper">
                                <!-- Nederlands -->
                                <div class="pontifex-oi-extra-column">
                                    <div class="pontifex-oi-extra-subtitle"><?php esc_html_e('Nederlands', 'pontifex-oi'); ?></div>
                                    <?php
                                    $nl_options = [
                                        'vca_proefexamen_nl' => __('VCA Proefexamen', 'pontifex-oi'),
                                        'vca_elearning_nl'   => __('VCA E-learning', 'pontifex-oi'),
                                        'boek_basis_nl'      => __('Boek VCA Basis', 'pontifex-oi'),
                                        'boek_combi_nl'      => __('Boek combi', 'pontifex-oi'),
                                        'cursus-weekend-nl'  => __('Weekendcursus met examen', 'pontifex-oi'),
                                    ];
                                    foreach ($nl_options as $id => $label):
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $checked = ($id === $direct_link);
                                        $price_excl = (float)($EXTRA_PRODUCTS[$id]['price'] ?? 0);
                                    ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo esc_attr(number_format($price_excl, 2, '.', '')); ?>"
                                                   <?php echo $checked ? 'checked disabled' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                            <span class="price">€<?php echo number_format($price_excl, 2, ',', '.'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Engels -->
                                <div class="pontifex-oi-extra-column">
                                    <div class="pontifex-oi-extra-subtitle"><?php esc_html_e('Engels', 'pontifex-oi'); ?></div>
                                    <?php
                                    $en_options = [
                                        'vca_elearning_en'   => __('VCA E-learning', 'pontifex-oi'),
                                        'boek_basis_en'      => __('Book VCA Basis', 'pontifex-oi'),
                                        'cursus-weekend-en'  => __('Weekend course with exam', 'pontifex-oi'),
                                    ];
                                    foreach ($en_options as $id => $label):
                                        if (!isset($EXTRA_PRODUCTS[$id])) continue;
                                        $checked = ($id === $direct_link);
                                        $price_excl = (float)($EXTRA_PRODUCTS[$id]['price'] ?? 0);
                                    ?>
                                        <label class="extra-option-label">
                                            <input type="checkbox"
                                                   class="extra-material-checkbox"
                                                   name="extra_options[]"
                                                   value="<?php echo esc_attr($id); ?>"
                                                   data-price="<?php echo esc_attr(number_format($price_excl, 2, '.', '')); ?>"
                                                   <?php echo $checked ? 'checked disabled' : ''; ?>>
                                            <?php echo esc_html($label); ?>
                                            <span class="price">€<?php echo number_format($price_excl, 2, ',', '.'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Gemeenschappelijke rijen -->
                    <div class="bo-item">
                        <strong><?php esc_html_e('Aantal kandidaten', 'pontifex-oi'); ?></strong>
                        <span id="candidate-count">1</span>
                    </div>

                    <div class="bo-item">
                        <strong><?php esc_html_e('BTW', 'pontifex-oi'); ?></strong>
                        <span id="vat-amount">
                            <?php echo '€' . number_format($vat_total, 2, ',', '.'); ?>
                        </span>
                    </div>

                    <div class="bo-item totaal">
                        <strong><?php esc_html_e('Totaal incl. btw', 'pontifex-oi'); ?></strong>
                        <span id="total-price"><?php echo esc_html($price_incl); ?></span>
                    </div>
                </div>

                <button type="submit" class="pontifex-oi-submit-order">
                    <?php esc_html_e('Inschrijving afronden', 'pontifex-oi'); ?>
                    <i class="fa fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Verborgen betalingsvelden -->
        <input type="hidden"
               id="payment_amount"
               name="payment_amount"
               value="<?php echo esc_attr($price_numeric); ?>"
               data-base-price="<?php echo esc_attr(number_format($base_excl, 2, '.', '')); ?>"
               data-base-vat="<?php echo esc_attr(number_format($vat_total, 2, '.', '')); ?>"
               data-custom-price="<?php echo $url_price_numeric > 0 ? '1' : '0'; ?>">

        <input type="hidden" name="exam_label"     value="<?php echo esc_attr($exam_display); ?>">
        <input type="hidden" name="material_label" value="<?php echo esc_attr($material_display); ?>">
    </form>
</section>