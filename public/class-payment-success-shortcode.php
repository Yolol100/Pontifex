<?php
namespace PontifexOI\PublicPart;

use PontifexOI\Helpers\PaymentHelpers;

if (!defined('ABSPATH')) {
    exit;
}

require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

class PaymentSuccessShortcode {

    public static function register_shortcode() {
        add_shortcode('pontifex_oi_payment_success', [self::class, 'render']);
    }

    private static function get_material_label($id) {
        global $MATERIAL_PRODUCTS;
        return $MATERIAL_PRODUCTS[$id]['label'] ?? $id;
    }

    private static function get_exam_label($id) {
        global $EXAM_PRODUCTS;
        return $EXAM_PRODUCTS[$id]['label'] ?? $id;
    }

    private static function get_language_label($id) {
        $language_labels = [
            'nl' => 'Nederlands',
            'en' => 'Engels',
        ];
        return $language_labels[$id] ?? $id;
    }

    private static function get_material_combination_label($id, $exam_type = '') {
        $material_labels = [
            '1' => 'Los examen',
            '2' => 'Examen + boek',
            '4' => 'Examen + e-learning',
            '5' => 'Examen + proefexamens',
            '6' => 'Examen + boek + proefexamens',
            '7' => 'Examen + e-learning + proefexamens',
        ];
        if (in_array($exam_type, ['vca-basis-weekend', 'vca-vol-weekend'], true)) {
            return esc_html__('Niet van toepassing', 'pontifex-oi');
        }
        return $material_labels[$id] ?? esc_html__('Geen keuze', 'pontifex-oi');
    }

    private static function render_unverified(): string {
        ob_start();
        ?>
        <div class="pontifex-oi-payment-success-simplified">
            <h2 class="pontifex-oi-success-title"><?php esc_html_e('Betaling nog niet bevestigd', 'pontifex-oi'); ?></h2>
            <p class="pontifex-oi-success-message">
                <?php esc_html_e('We kunnen je betaling op dit moment niet bevestigen. Controleer de betaling en probeer het daarna opnieuw.', 'pontifex-oi'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/cursus-zoeken/')); ?>" class="pontifex-oi-home-button">
                <?php esc_html_e('Terug naar cursus zoeken', 'pontifex-oi'); ?>
                <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render($atts) {
        $order_token = sanitize_text_field($_GET['order_token'] ?? '');
        if ($order_token === '') {
            error_log('[Pontifex OI] Payment success token missing.');
            return self::render_unverified();
        }

        error_log('[Pontifex OI] Payment success token received.');
        $mollie_payment_id = get_transient('mollie_payment_id_for_token_' . $order_token);
        if (empty($mollie_payment_id)) {
            error_log('[Pontifex OI] Payment lookup reference missing.');
            return self::render_unverified();
        }

        error_log('[Pontifex OI] Payment lookup started.');
        $payment = PaymentHelpers::get_mollie_payment((string) $mollie_payment_id);
        if (!$payment) {
            error_log('[Pontifex OI] Payment lookup failed.');
            return self::render_unverified();
        }

        $status = strtolower((string) ($payment->status ?? ''));
        error_log('[Pontifex OI] Payment lookup completed; status=' . sanitize_key($status));

        $is_paid = method_exists($payment, 'isPaid') && $payment->isPaid();
        $has_refunds = method_exists($payment, 'hasRefunds') && $payment->hasRefunds();
        $has_chargebacks = method_exists($payment, 'hasChargebacks') && $payment->hasChargebacks();
        if (!$is_paid || $has_refunds || $has_chargebacks) {
            error_log('[Pontifex OI] Payment is not eligible for success confirmation.');
            return self::render_unverified();
        }

        delete_transient('mollie_payment_id_for_token_' . $order_token);
        error_log('[Pontifex OI] Payment lookup transient cleared.');

        ob_start();
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
