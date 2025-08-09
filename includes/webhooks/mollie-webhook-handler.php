<?php
// Voorkom directe toegang
if (!defined('ABSPATH')) {
    exit;
}

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Helpers\MailHelpers;

// REST API route voor Mollie webhook
add_action('rest_api_init', function () {
    register_rest_route('pontifex-oi/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback' => '__return_true', // Mollie heeft geen authenticatie, dus iedereen mag deze route aanroepen
    ]);
});

/**
 * Handler voor Mollie webhook.
 * Controleert betalingstatus en handelt daar naar.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response
 */
function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    // Mollie Payment ID uit POST-request
    $payment_id = $request->get_param('id');

    if (empty($payment_id)) {
        error_log('[Pontifex OI Webhook Error] Geen Mollie Payment ID ontvangen in webhook.');
        return new \WP_REST_Response([
            'status'  => 'error',
            'message' => 'No payment ID received.',
        ], 400);
    }

    // Laad Mollie SDK
    require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

    try {
        $mollie = new \Mollie\Api\MollieApiClient();
        $apiKey = PaymentHelpers::get_mollie_api_key();
        if (empty($apiKey)) {
            error_log('[Pontifex OI Webhook Error] Mollie API sleutel ontbreekt in webhook handler (geen sleutel ingesteld in admin?).');
            return new \WP_REST_Response([
                'status'  => 'error',
                'message' => 'Mollie API key missing in admin settings.',
            ], 500);
        }
        $mollie->setApiKey($apiKey);

        $payment = $mollie->payments->get($payment_id);

        if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
            error_log('[Pontifex OI Webhook] Betaling succesvol voor ID: ' . $payment_id);

            $order_data = (array) ($payment->metadata ?? []);
            $order_email = $order_data['order_email'] ?? 'onbekend@voorbeeld.nl';
            $exam_type_label = $order_data['exam_type'] ?? 'Onbekend Examen';

            error_log('[Pontifex OI Webhook] Verwerken inschrijving voor e-mail: ' . $order_email . ' - Examen: ' . $exam_type_label);

            // Hier eventueel registratie in SOAP API verwerken
            // \PontifexOI\Api\SoapClient::get_instance()->send_registration_to_pontifex($order_data);

            // Bevestigingsmail versturen (eventueel implementeren)
            if (class_exists(MailHelpers::class)) {
                // MailHelpers::send_payment_confirmation_email($order_data, $payment->amount->value);
                error_log('[Pontifex OI Webhook] Bevestigingsmail (simulatie) verzonden voor ID: ' . $payment_id);
            }

            return new \WP_REST_Response(['status' => 'success'], 200);

        } elseif ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
            error_log('[Pontifex OI Webhook] Betaling mislukt/geannuleerd/verlopen voor ID: ' . $payment_id . ' Status: ' . $payment->status);
            return new \WP_REST_Response([
                'status'  => 'failed',
                'message' => 'Payment failed or cancelled.',
            ], 200);
        }

        error_log('[Pontifex OI Webhook] Betaling in afwachting/geautoriseerd voor ID: ' . $payment_id . ' Status: ' . $payment->status);
        return new \WP_REST_Response([
            'status'  => 'pending',
            'message' => 'Payment still pending or authorized.',
        ], 200);

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        error_log('[Pontifex OI Webhook Mollie Error] ' . $e->getMessage() . ' voor Mollie ID: ' . $payment_id);
        return new \WP_REST_Response([
            'status'  => 'error',
            'message' => 'Mollie API error.',
        ], 500);
    } catch (\Exception $e) {
        error_log('[Pontifex OI Webhook Algemene Fout] ' . $e->getMessage() . ' voor Mollie ID: ' . $payment_id);
        return new \WP_REST_Response([
            'status'  => 'error',
            'message' => 'Internal server error.',
        ], 500);
    }
}