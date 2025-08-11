<?php
if (!defined('ABSPATH')) {
    exit;
}

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Helpers\MailHelpers;

add_action('rest_api_init', function () {
    register_rest_route('pontifex-oi/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    $payment_id = $request->get_param('id');

    if (empty($payment_id)) {
        error_log('[Pontifex OI Webhook Error] Geen Mollie Payment ID ontvangen.');
        return new \WP_REST_Response(['status' => 'error', 'message' => 'No payment ID received.'], 400);
    }

    require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';

    try {
        $mollie = new \Mollie\Api\MollieApiClient();
        $apiKey = PaymentHelpers::get_mollie_api_key();

        if (empty($apiKey)) {
            error_log('[Pontifex OI Webhook Error] Mollie API key ontbreekt.');
            return new \WP_REST_Response(['status' => 'error', 'message' => 'Mollie API key missing.'], 500);
        }
        $mollie->setApiKey($apiKey);

        $payment = $mollie->payments->get($payment_id);

        // Voorkom dubbele verwerking
        if (get_transient('pontifex_processed_' . $payment_id)) {
            return new \WP_REST_Response(['status' => 'ignored', 'message' => 'Already processed.'], 200);
        }

        if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
            $order_data = (array) ($payment->metadata ?? []);
            $order_email = $order_data['order_email'] ?? '';
            $exam_type_label = $order_data['exam_type'] ?? 'Examen';

            error_log("[Pontifex OI Webhook] Succesvol betaald: {$order_email} - {$exam_type_label}");

            // 1) SOAP-registratie naar Pontifex
            if (!class_exists(\PontifexOI\Api\SoapClient::class)) {
                require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
            }
            try {
                $soap = new \PontifexOI\Api\SoapClient();
                $ok   = $soap->sendRegistration($order_data);
                if (!$ok) {
                    error_log('[Pontifex OI Webhook] SOAP-registratie gaf false terug.');
                }
            } catch (\Exception $e) {
                error_log('[Pontifex OI Webhook] SOAP-registratie fout: ' . $e->getMessage());
                // Niet fatally: we mailen nog wel en geven success terug aan Mollie
            }

            // 2) Bevestigingsmails (klant + eigenaar met Excel-bijlage)
            if (class_exists(MailHelpers::class)) {
                try {
                    MailHelpers::send_inschrijving_mails($order_data);
                    error_log("[Pontifex OI Webhook] Bevestigingsmails verzonden voor {$order_email}");
                } catch (\Exception $e) {
                    error_log('[Pontifex OI Webhook] Fout bij versturen mails: ' . $e->getMessage());
                }
            }

            set_transient('pontifex_processed_' . $payment_id, true, 12 * HOUR_IN_SECONDS);
            return new \WP_REST_Response(['status' => 'success'], 200);

        } elseif ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
            error_log("[Pontifex OI Webhook] Betaling mislukt/geannuleerd/verlopen voor ID {$payment_id}");
            return new \WP_REST_Response(['status' => 'failed', 'message' => 'Payment failed or cancelled.'], 200);
        }

        error_log("[Pontifex OI Webhook] Betaling pending/geautoriseerd voor ID {$payment_id}");
        return new \WP_REST_Response(['status' => 'pending'], 200);

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        error_log("[Pontifex OI Webhook Mollie Error] {$e->getMessage()}");
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Mollie API error.'], 500);
    } catch (\Exception $e) {
        error_log("[Pontifex OI Webhook Fout] {$e->getMessage()}");
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Internal server error.'], 500);
    }
}