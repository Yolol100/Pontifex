<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Alle $_GET waarden ophalen met fallback
$exam_type = isset($_GET['exam_type']) ? sanitize_text_field($_GET['exam_type']) : '';
$language  = isset($_GET['language'])  ? sanitize_text_field($_GET['language'])  : '';
$material  = isset($_GET['material'])  ? sanitize_text_field($_GET['material'])  : '';
$date      = isset($_GET['date'])      ? sanitize_text_field($_GET['date'])      : '';
$time      = isset($_GET['time'])      ? sanitize_text_field($_GET['time'])      : '';
$location  = isset($_GET['location'])  ? sanitize_text_field($_GET['location'])  : '';
$province  = isset($_GET['province'])  ? sanitize_text_field($_GET['province'])  : '';
$spots     = isset($_GET['spots'])     ? sanitize_text_field($_GET['spots'])     : '';
$price_raw = isset($_GET['price'])     ? sanitize_text_field($_GET['price'])     : '';

// Labels voor examensoorten en talen
$exam_type_labels = [
    'los-examen-vca-basis' => 'VCA Basis',
    'los-examen-vca-vol'   => 'VCA Vol',
    'vca-basis-weekend'    => 'VCA Basis Cursus Weekend',
    'vca-vol-weekend'      => 'VCA Vol Cursus Weekend',
];
$language_labels = [
    'nl' => 'Nederlands',
    'en' => 'Engels',
];

// Haal de extra materiaal opties op en de EXAM_PRODUCTS array
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
global $EXAM_PRODUCTS; // Zorg dat $EXAM_PRODUCTS globaal toegankelijk is

$all_extra_options = get_extra_material_options();

// Converteer naar het formaat dat de template verwacht
$extra_material_options = [];
foreach ($all_extra_options as $exam_type_key => $languages) {
    foreach ($languages as $lang_key => $options) {
        $key = $exam_type_key . '_' . $lang_key;
        $extra_material_options[$key] = $options;
    }
}

// Debug: log de opties
error_log('[Pontifex OI Debug] Extra material options: ' . print_r($extra_material_options, true));

// Default voor extra materiaal opties
if (!isset($extra_material_options) || !is_array($extra_material_options)) {
    $extra_material_options = [];
}

$is_weekend_cursus = in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true);

// Bepaal de numerieke prijs op basis van exam_type en language
$price_numeric = 0; // Standaardwaarde

if ($is_weekend_cursus) {
    $price_numeric = 245.00; // Hardcode voor weekendcursus
} else {
    // Zoek de basisprijs op in de $EXAM_PRODUCTS array
    if (isset($EXAM_PRODUCTS[$exam_type]['prices'][$language])) {
        $price_numeric = $EXAM_PRODUCTS[$exam_type]['prices'][$language];
    }
}

$price_display = (is_numeric($price_numeric) && $price_numeric > 0)
    ? '€' . number_format($price_numeric, 2, ',', '.')
    : '-';

$price_for_input = number_format($price_numeric, 2, '.', ''); // Voor JS (punt!)
?>

<div class="pontifex-oi-stepper" role="heading" aria-level="2" tabindex="0">
    <?php esc_html_e('Stap 2: Inschrijven','pontifex-oi'); ?>
</div>

<section class="pontifex-oi-section" id="step-2">
    <div class="pontifex-oi-title-row">
        <h2 class="pontifex-oi-section-label">
            <?php esc_html_e('Gegevens kandidaat','pontifex-oi'); ?>
        </h2>
        <a href="/cursus-zoeken/" class="pontifex-oi-back-link" tabindex="0">
            <?php esc_html_e('Terug naar planning','pontifex-oi'); ?>
        </a>
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
                    <label for="candidate_fullname_1">
                        <?php esc_html_e('Naam','pontifex-oi'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_fullname_1"
                           name="candidate_fullname[]"
                           required
                           placeholder="<?php esc_attr_e('Bijv. Jan','pontifex-oi'); ?>">
                </div>
                <div>
                    <label for="candidate_infix_1">
                        <?php esc_html_e('Tussenvoegsel','pontifex-oi'); ?>
                    </label>
                    <input type="text"
                           id="candidate_infix_1"
                           name="candidate_infix[]"
                           placeholder="<?php esc_attr_e('Tussenvoegsel','pontifex-oi'); ?>">
                </div>
                <div>
                    <label for="candidate_lastname_1">
                        <?php esc_html_e('Achternaam','pontifex-oi'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_lastname_1"
                           name="candidate_lastname[]"
                           required
                           placeholder="<?php esc_attr_e('Achternaam','pontifex-oi'); ?>">
                </div>
                <div class="pontifex-oi-candidate-birthdate-wrapper"
                     style="position:relative; min-width:180px;">
                    <label for="candidate_birthdate_1">
                        <?php esc_html_e('Geboortedatum','pontifex-oi'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text"
                           id="candidate_birthdate_1"
                           name="candidate_birthdate[]"
                           required
                           pattern="\d{2}-\d{2}-\d{4}"
                           placeholder="30-06-1992"
                           autocomplete="off">
                    <button type="button"
                            class="pontifex-oi-remove-candidate"
                            title="<?php esc_attr_e('Kandidaat verwijderen','pontifex-oi'); ?>"
                            style="display:none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="pontifex-oi-candidate-addrow">
            <button type="button" class="pontifex-oi-add-candidate" tabindex="0">
                <?php esc_html_e('Kandidaat toevoegen','pontifex-oi'); ?>
            </button>
        </div>

        <div class="pontifex-oi-flex-row">
            <div class="pontifex-oi-order-form">
                <h2 class="pontifex-oi-section-label">
                    <?php esc_html_e('Bestelgegevens','pontifex-oi'); ?>
                </h2>

                <div class="pontifex-oi-row-group">
                    <label for="order_initials">
                        <?php esc_html_e('Naam','pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <div class="pontifex-oi-name-row">
                        <input type="text"
                               id="order_initials"
                               name="order_initials"
                               required
                               placeholder="<?php esc_attr_e('Voorletters','pontifex-oi'); ?>">
                        <input type="text"
                               name="order_infix"
                               placeholder="<?php esc_attr_e('Tussenvoegsel (optioneel)','pontifex-oi'); ?>">
                        <input type="text"
                               id="order_lastname"
                               name="order_lastname"
                               required
                               placeholder="<?php esc_attr_e('Achternaam','pontifex-oi'); ?>">
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <label for="order_postcode">
                        <?php esc_html_e('Postcode en huisnummer','pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <div class="pontifex-oi-adres-row">
                        <input type="text"
                               id="order_postcode"
                               name="order_postcode"
                               required
                               placeholder="<?php esc_attr_e('1234AB','pontifex-oi'); ?>">
                        <input type="text"
                               name="order_housenumber"
                               required
                               placeholder="<?php esc_attr_e('Huisnummer','pontifex-oi'); ?>">
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <label for="order_street">
                        <?php esc_html_e('Adres','pontifex-oi'); ?> <span class="required">*</span>
                    </label>
                    <div class="pontifex-oi-adres-row">
                        <input type="text"
                               id="order_street"
                               name="order_street"
                               required
                               placeholder="<?php esc_attr_e('Straat','pontifex-oi'); ?>">
                        <input type="text"
                               id="order_city"
                               name="order_city"
                               required
                               placeholder="<?php esc_attr_e('Plaats','pontifex-oi'); ?>">
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <div class="pontifex-oi-contact-row-labels">
                        <label for="order_phone"><?php esc_html_e('Telefoonnummer','pontifex-oi'); ?> <span class="required">*</span></label>
                        <label for="order_email"><?php esc_html_e('E-mailadres','pontifex-oi'); ?> <span class="required">*</span></label>
                    </div>
                    <div class="pontifex-oi-contact-row">
                        <input type="text"
                               id="order_phone"
                               name="order_phone"
                               required
                               placeholder="<?php esc_attr_e('0612345678','pontifex-oi'); ?>">
                        <input type="email"
                               id="order_email"
                               name="order_email"
                               required
                               placeholder="<?php esc_attr_e('naam@domein.nl','pontifex-oi'); ?>">
                    </div>
                </div>

                <div class="pontifex-oi-row-group">
                    <label for="order_company"><?php esc_html_e('Bedrijfsnaam','pontifex-oi'); ?></label>
                    <input type="text"
                           id="order_company"
                           name="order_company"
                           placeholder="<?php esc_attr_e('Bedrijfsnaam','pontifex-oi'); ?>">
                </div>

                <div class="pontifex-oi-row-group">
                    <label for="order_vat"><?php esc_html_e('BTW-nummer','pontifex-oi'); ?></label>
                    <input type="text"
                           id="order_vat"
                           name="order_vat"
                           placeholder="<?php esc_attr_e('NL123456789B01','pontifex-oi'); ?>">
                </div>
            </div>

            <div class="pontifex-oi-order-summary">
                <h3 class="pontifex-oi-section-label">
                    <?php esc_html_e('Gegevens van uw bestelling','pontifex-oi'); ?>
                </h3>
                <div class="besteloverzicht-stap2">
                    <div class="bo-item">
                        <strong><?php esc_html_e('Examen','pontifex-oi'); ?></strong>
                        <span>
                            <?php
                            $examLabel = isset($exam_type_labels[$exam_type]) ? $exam_type_labels[$exam_type] : ($exam_type ? $exam_type : '-');
                            echo esc_html($examLabel);
                            ?>
                        </span>
                    </div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Taal','pontifex-oi'); ?></strong>
                        <span>
                            <?php
                            $lang = $language ?: ($is_weekend_cursus ? 'nl' : '');
                            $langLabel = isset($language_labels[$lang]) ? $language_labels[$lang] : ($lang ? $lang : '-');
                            echo esc_html($langLabel);
                            ?>
                        </span>
                    </div>

                    <?php if ($is_weekend_cursus): ?>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Lesmateriaal','pontifex-oi'); ?></strong>
                        <span><?php esc_html_e('Niet van toepassing','pontifex-oi'); ?></span>
                    </div>
                    <div class="bo-divider"></div>
                    <?php else:
                        $key = $exam_type . '_' . $language;
                        $heeft_extra = !empty($extra_material_options[$key]) && is_array($extra_material_options[$key]);
                        if ($heeft_extra): ?>
                        <div id="extra-material-checkboxes" style="margin:1em 0;">
                            <strong><?php esc_html_e('Extra lesmateriaal nodig?','pontifex-oi'); ?></strong>
                            <div>
                                <?php foreach ($extra_material_options[$key] as $opt): ?>
                                    <label style="display:block; margin:0.3em 0;">
                                        <input type="checkbox"
                                               class="extra-material-checkbox"
                                               name="extra_material[]"
                                               value="<?php echo esc_attr($opt['id']); ?>"
                                               data-price="<?php echo esc_attr($opt['price']); ?>">
                                        <?php echo esc_html($opt['label']); ?> (€<?php echo number_format($opt['price'],2,',','.'); ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bo-divider"></div>
                        <?php endif;
                    endif; ?>

                    <div class="bo-item">
                        <strong><?php esc_html_e('Datum','pontifex-oi'); ?></strong>
                        <span><?php echo esc_html($date ?: '-'); ?></span>
                    </div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Tijd','pontifex-oi'); ?></strong>
                        <span><?php echo esc_html($time ?: '-'); ?></span>
                    </div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Locatie','pontifex-oi'); ?></strong>
                        <span><?php echo esc_html($location ?: '-'); ?></span>
                    </div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Provincie','pontifex-oi'); ?></strong>
                        <span><?php echo esc_html($province ?: '-'); ?></span>
                    </div>
                    <div class="bo-item">
                        <strong><?php esc_html_e('Aantal kandidaten','pontifex-oi'); ?></strong>
                        <span id="candidate-count">1</span>
                    </div>
                    <div class="bo-item totaal">
                        <strong><?php esc_html_e('Prijs totaal','pontifex-oi'); ?></strong>
                        <span id="total-price"><?php echo esc_html($price_display); ?></span>
                    </div>
                </div>
                <button type="submit" class="pontifex-oi-submit-order">
                    <?php esc_html_e('Bestelling plaatsen','pontifex-oi'); ?>
                    <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <input type="hidden"
               id="payment_amount"
               name="payment_amount"
               value="<?php echo esc_attr($price_for_input); ?>"
               data-base-price="<?php echo esc_attr($price_for_input); ?>">
    </form>
</section>