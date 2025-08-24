<?php
// File: wp-content/plugins/pontifex-oi/templates/step-2-inschrijven.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Alle $_GET waarden ophalen met fallback
$exam_type = isset($_GET['exam_type']) ? sanitize_text_field($_GET['exam_type']) : '';
$language  = isset($_GET['language']) ? sanitize_text_field($_GET['language']) : '';
$material  = isset($_GET['material']) ? sanitize_text_field($_GET['material']) : '';
$date      = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : '';
$time      = isset($_GET['time']) ? sanitize_text_field($_GET['time']) : '';
$location  = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
$province  = isset($_GET['province']) ? sanitize_text_field($_GET['province']) : '';
$spots     = isset($_GET['spots']) ? sanitize_text_field($_GET['spots']) : '';
$price_raw = isset($_GET['price']) ? sanitize_text_field($_GET['price']) : '';

require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

// Normaliseer key
$exam_type = pontifex_normalize_exam_key($exam_type);

// Labels voor examensoorten en talen
$exam_type_labels = [
    'los-examen-vca-basis' => 'VCA Basis',
    'los-examen-vca-basis-groen' => 'VCA Basis Groen',
    'los-examen-vca-vol' => 'VCA Vol',
    'los-examen-vca-vil' => 'VCA VIL',
    // legacy keys fallback
    'los-examen-vil-vcu' => 'VCA VIL',
    'vca-basis' => 'VCA Basis',
    'vca-vol' => 'VCA Vol',
];

$language_labels = [
    'nl' => 'Nederlands',
    'en' => 'Engels',
    'de' => 'Duits',
    'fr' => 'Frans',
    'ar' => 'Arabisch', 'bg' => 'Bulgaars', 'lt' => 'Litouws', 'pl' => 'Pools',
    'pt' => 'Portugees', 'ro' => 'Roemeens', 'ru' => 'Russisch', 'tr' => 'Turks',
    'el' => 'Grieks', 'hu' => 'Hongaars', 'it' => 'Italiaans', 'hr' => 'Kroatisch',
    'uk' => 'Oekraïens', 'sk' => 'Slowaaks', 'es' => 'Spaans', 'vi' => 'Vietnamees',
];

// Haal productconfig op
global $EXAM_PRODUCTS, $MATERIAL_PRODUCTS, $WEEKEND_ALLOWED_BY_EXAM;

$all_extra_options = get_extra_material_options();

// Maak vlakke array key: exam_taal => opties[]
$extra_material_options = [];
foreach ($all_extra_options as $exam_type_key => $langs) {
    foreach ($langs as $lang_key => $options) {
        $extra_material_options[$exam_type_key . '_' . $lang_key] = $options;
    }
}

// Weekendcheckbox alleen tonen als toegestaan
$weekend_allowed_langs = $WEEKEND_ALLOWED_BY_EXAM[$exam_type] ?? [];
$show_weekend_checkbox = in_array($language, $weekend_allowed_langs, true);

// Prijsberekening basis (zonder extra materiaal/checkboxen)
$price_numeric = $EXAM_PRODUCTS[$exam_type]['prices'][$language] ?? ($EXAM_PRODUCTS[$exam_type]['prices']['nl'] ?? 0);

$price_display = (is_numeric($price_numeric) && $price_numeric > 0)
    ? '€' . number_format($price_numeric, 2, ',', '.')
    : '-';
$price_for_input = number_format($price_numeric, 2, '.', ''); // voor hidden input

// Basis examweergave in besteloverzicht
$exam_key = pontifex_normalize_exam_key($exam_type);
$base_label = $EXAM_PRODUCTS[$exam_key]['label'] ?? '';
$exam_display = trim($base_label) ? ($base_label . ' met examen') : '';
?>

<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 2: Inschrijven', 'pontifex-oi'); ?>
</div>

<section class="pontifex-oi-section" id="step-2">
    <div class="pontifex-oi-title-row">
        <h2 class="pontifex-oi-section-label"><?php esc_html_e('Gegevens kandidaat', 'pontifex-oi'); ?></h2>
        <a href="/cursus-zoeken/" class="pontifex-oi-back-link" tabindex="0"><?php esc_html_e('Terug naar planning', 'pontifex-oi'); ?></a>
    </div>

    <form class="pontifex-oi-candidate-form" method="post" autocomplete="off" novalidate>
        <input type="hidden" id="exam_type" name="exam_type" value="<?php echo esc_attr($exam_type); ?>">
        <input type="hidden" id="language" name="language" value="<?php echo esc_attr($language); ?>">
        <input type="hidden" id="material" name="material" value="<?php echo esc_attr($material); ?>">
        <input type="hidden" id="date" name="date" value="<?php echo esc_attr($date); ?>">
        <input type="hidden" id="time" name="time" value="<?php echo esc_attr($time); ?>">
        <input type="hidden" id="location" name="location" value="<?php echo esc_attr($location); ?>">
        <input type="hidden" id="province" name="province" value="<?php echo esc_attr($province); ?>">
        <input type="hidden" id="spots" name="spots" value="<?php echo esc_attr($spots); ?>">

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
                <h2 class="pontifex-oi-section-label"><?php esc_html_e('Bestelgegevens', 'pontifex-oi'); ?></h2>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-name-row pontifex-oi-name-row-mobile">
                        <div class="field first-name-group">
                            <label for="order_initials"><?php esc_html_e('Voorletters', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_initials" name="order_initials" required placeholder="<?php esc_attr_e('Bijv. J.A.', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field middle-name-group">
                            <label for="order_infix"><?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?></label>
                            <input type="text" id="order_infix" name="order_infix" placeholder="<?php esc_attr_e('Bijv. van', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field last-name-group">
                            <label for="order_lastname"><?php esc_html_e('Achternaam', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_lastname" name="order_lastname" required placeholder="<?php esc_attr_e('Bijv. Jansen', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_postcode"><?php esc_html_e('Postcode', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_postcode" name="order_postcode" required placeholder="<?php esc_attr_e('Bijv. 1234 AB', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_housenumber"><?php esc_html_e('Huisnummer', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_housenumber" name="order_housenumber" required placeholder="<?php esc_attr_e('Bijv. 12 of 12A', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_street"><?php esc_html_e('Adres', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_street" name="order_street" required placeholder="<?php esc_attr_e('Bijv. Dorpsstraat 1', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_city"><?php esc_html_e('Plaats', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_city" name="order_city" required placeholder="<?php esc_attr_e('Bijv. Amsterdam', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-contact-row two-cols">
                        <div class="field">
                            <label for="order_phone"><?php esc_html_e('Telefoonnummer', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="tel" id="order_phone" name="order_phone" inputmode="tel" autocomplete="tel" required pattern="^\+?[0-9\s\-]{6,}$" placeholder="<?php esc_attr_e('Bijv. 0612345678 of +31 6 12345678', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_email"><?php esc_html_e('E-mailadres', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="email" id="order_email" name="order_email" inputmode="email" autocomplete="email" required placeholder="<?php esc_attr_e('Bijv. naam@bedrijf.nl', 'pontifex-oi'); ?>">
                        </div>
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-two-cols">
                        <div class="field">
                            <label for="order_company"><?php esc_html_e('Bedrijfsnaam', 'pontifex-oi'); ?></label>
                            <input type="text" id="order_company" name="order_company" placeholder="<?php esc_attr_e('Bijv. Bouw BV', 'pontifex-oi'); ?>">
                        </div>
                        <div class="field">
                            <label for="order_function"><?php esc_html_e('Functie', 'pontifex-oi'); ?> <span class="required">*</span></label>
                            <input type="text" id="order_function" name="order_function" required placeholder="<?php esc_attr_e('Bijv. Voorman', 'pontifex-oi'); ?>">
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
                <h3 class="pontifex-oi-section-label"><?php esc_html_e('Gegevens van uw bestelling', 'pontifex-oi'); ?></h3>
                <div class="besteloverzicht-stap2">
                    <div class="bo-item">
                        <strong><?php esc_html_e('Examen', 'pontifex-oi'); ?></strong>
                        <span class="bo-value js-exam-summary" data-summary="exam">
                            <?php echo esc_html($exam_display); ?>
                        </span>
                    </div>
                    <div class="bo-item"><strong><?php esc_html_e('Taal', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($language_labels[$language] ?? $language ?: '-'); ?></span></div>

                    <?php
                    $key = $exam_type . '_' . $language;
                    if (!empty($extra_material_options[$key])) {
                        echo '<div id="extra-material-checkboxes"><strong>' . esc_html__('Extra lesmateriaal nodig?', 'pontifex-oi') . '</strong>';
                        foreach ($extra_material_options[$key] as $opt) {
                            if ($opt['id'] === 'cursus-weekend' && !$show_weekend_checkbox) {
                                continue;
                            }
                            echo '<label><input type="checkbox" class="extra-material-checkbox" name="extra_material[]" value="' . esc_attr($opt['id']) . '" data-price="' . esc_attr($opt['price']) . '"> ' . esc_html($opt['label']) . ' (€' . number_format($opt['price'], 2, ',', '.') . ')</label>';
                        }
                        echo '</div>';
                    } else {
                        echo '<div class="bo-item"><strong>' . esc_html__('Lesmateriaal', 'pontifex-oi') . '</strong> <span>' . esc_html__('Geen extra opties voor deze taal', 'pontifex-oi') . '</span></div>';
                    }
                    ?>

                    <div class="bo-item"><strong><?php esc_html_e('Datum', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($date ?: '-'); ?></span></div>
                    <div class="bo-item"><strong><?php esc_html_e('Tijd', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($time ?: '-'); ?></span></div>
                    <div class="bo-item"><strong><?php esc_html_e('Locatie', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($location ?: '-'); ?></span></div>
                    <div class="bo-item"><strong><?php esc_html_e('Provincie', 'pontifex-oi'); ?></strong> <span><?php echo esc_html($province ?: '-'); ?></span></div>
                    <div class="bo-item"><strong><?php esc_html_e('Aantal kandidaten', 'pontifex-oi'); ?></strong> <span id="candidate-count">1</span></div>
                    <div class="bo-item totaal"><strong><?php esc_html_e('Prijs totaal', 'pontifex-oi'); ?></strong> <span id="total-price"><?php echo esc_html($price_display); ?></span></div>
                </div>
                <button type="submit" class="pontifex-oi-submit-order"><?php esc_html_e('Bestelling plaatsen', 'pontifex-oi'); ?> <i class="fa fa-arrow-right"></i></button>
            </div>
        </div>

        <input type="hidden" id="payment_amount" name="payment_amount" value="<?php echo esc_attr(number_format($price_numeric, 2, ',', '')); ?>" data-base-price="<?php echo esc_attr(number_format($price_numeric, 2, '.', '')); ?>">
    </form>
</section>