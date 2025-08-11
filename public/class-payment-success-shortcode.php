<?php
namespace PontifexOI\PublicPart;

use PontifexOI\Helpers\PaymentHelpers;

if (!defined('ABSPATH')) {
    exit;
}

// Zorg dat de globals beschikbaar zijn
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

class PaymentSuccessShortcode {

    public static function register_shortcode() {
        add_shortcode('pontifex_oi_payment_success', [self::class, 'render']);
    }

    // Helper om label te krijgen uit materiaal ID
    private static function get_material_label($id) {
        global $MATERIAL_PRODUCTS;
        return $MATERIAL_PRODUCTS[$id]['label'] ?? $id;
    }

    // Helper om label te krijgen uit examen ID
    private static function get_exam_label($id) {
        global $EXAM_PRODUCTS; // Gebruik de global hier
        return $EXAM_PRODUCTS[$id]['label'] ?? $id;
    }

    // Helper om label te krijgen uit taal ID
    private static function get_language_label($id) {
        $language_labels = [
            'nl' => 'Nederlands',
            'en' => 'Engels',
        ];
        return $language_labels[$id] ?? $id;
    }

    // Helper om label te krijgen uit materiaal-combinatie ID
    private static function get_material_combination_label($id, $exam_type = '') {
        $material_labels = [
            '1' => 'Los examen',
            '2' => 'Examen + boek',
            '4' => 'Examen + e-learning',
            '5' => 'Examen + proefexamens',
            '6' => 'Examen + boek + proefexamens',
            '7' => 'Examen + e-learning + proefexamens',
        ];
        // Speciale logica voor weekendcursussen: ze hebben geen 'lesmateriaal' dropdown
        if (in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'])) {
            return esc_html__('Niet van toepassing', 'pontifex-oi'); // Gebruik esc_html__ voor vertaalbaarheid
        }
        return $material_labels[$id] ?? esc_html__('Geen keuze', 'pontifex-oi'); // Gebruik esc_html__ voor vertaalbaarheid
    }


    public static function render($atts) {
        ob_start();

        // Haal onze unieke order_token op uit de URL
        $order_token = sanitize_text_field($_GET['order_token'] ?? '');
        $mollie_payment_id = '';
        $order_data = [];
        $total_price_display = esc_html__('Onbekend', 'pontifex-oi'); // Not strictly needed for the simplified page, but kept for consistency

        if (!empty($order_token)) {
            error_log('[Pontifex OI Debug] PaymentSuccessShortcode: order_token gevonden: ' . $order_token);

            // Haal het Mollie payment ID op via de transient
            $mollie_payment_id = get_transient('mollie_payment_id_for_token_' . $order_token);

            if (!empty($mollie_payment_id)) {
                error_log('[Pontifex OI Debug] PaymentSuccessShortcode: Mollie payment ID opgehaald via transient: ' . $mollie_payment_id);
                $payment = PaymentHelpers::get_mollie_payment($mollie_payment_id);

                if ($payment) {
                    error_log('[Pontifex OI Debug] PaymentSuccessShortcode: Mollie betaling opgehaald. Status: ' . $payment->status);
                    $order_data = (array) ($payment->metadata ?? []); // Still fetch metadata for logging/potential future use
                    error_log('[Pontifex OI Debug] PaymentSuccessShortcode: Mollie metadata inhoud: ' . print_r($order_data, true));
                    $total_price_display = '€' . number_format((float)($payment->amount->value ?? 0), 2, ',', '.'); // Still calculate for logging/completeness

                    // Verwijder de transient na succesvol gebruik om op te ruimen
                    delete_transient('mollie_payment_id_for_token_' . $order_token);
                    error_log('[Pontifex OI Debug] Transient verwijderd voor token: ' . $order_token);

                } else {
                    error_log('[Pontifex OI Error] PaymentSuccessShortcode: Kon Mollie betaling niet ophalen met ID: ' . $mollie_payment_id);
                }
            } else {
                error_log('[Pontifex OI Error] PaymentSuccessShortcode: Geen Mollie payment ID gevonden in transient voor token: ' . $order_token);
            }
        } else {
            error_log('[Pontifex OI Error] PaymentSuccessShortcode: Geen order_token gevonden in URL.');
        }

        // De vereenvoudigde lay-out toont geen gedetailleerde order_data meer,
        // dus de 'if (empty($order_data))' check is minder kritiek voor de weergave,
        // maar kan blijven voor foutafhandeling.
        ?>
             <div class="pontifex-oi-payment-success-simplified">
            <div class="pontifex-oi-check-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                </svg>
            </div>
            <h2 class="pontifex-oi-success-title"><?php esc_html_e('Je aanmelding is gelukt!', 'pontifex-oi'); ?></h2>
            <p class="pontifex-oi-success-message">
                <?php esc_html_e('Bedankt voor je aanmelding.', 'pontifex-oi'); ?><br>
                <?php esc_html_e('Check je mail voor de details van je aanmelding.', 'pontifex-oi'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="pontifex-oi-home-button">
                <?php esc_html_e('Terugkeren naar home', 'pontifex-oi'); ?>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <?php

        return ob_get_clean();
    }
}