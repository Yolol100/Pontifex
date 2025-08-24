<?php
/**
 * Mollie Webhook Handler for Pontifex OI.
 *
 * This file registers a custom WordPress REST API endpoint to receive
 * and process webhook notifications from Mollie for payment status updates.
 *
 * @package PontifexOI
 * @subpackage Webhook
 */

if (!defined('ABSPATH')) {
    exit;
}

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Helpers\MailHelpers;

// Register the REST API endpoint for the Mollie webhook.
add_action('rest_api_init', function () {
    register_rest_route('pontifex-oi/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback' => '__return_true', // The endpoint must be publicly accessible for Mollie.
    ]);
});

/**
 * Main webhook handler function.
 *
 * @param \WP_REST_Request $request The request object.
 * @return \WP_REST_Response
 */
function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    // === Webhook Secret Check ===
    $configured_secret = get_option('pontifex_oi_webhook_secret', '');
    if (!empty($configured_secret)) {
        // Retrieve the secret from either a header or a querystring parameter.
        $provided_secret = $request->get_header('X-Pontifex-Secret') ?: $request->get_param('secret');

        // Use hash_equals() for a secure, time-safe comparison.
        if (empty($provided_secret) || !hash_equals($configured_secret, $provided_secret)) {
            error_log('[Pontifex OI Webhook] Secret mismatch or missing.');
            return new \WP_REST_Response(['status' => 'forbidden', 'message' => 'Forbidden'], 403);
        }
    }
    // === End Secret Check ===

    $payment_id = $request->get_param('id');

    // Validate that a payment ID was received.
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

        // Fetch the payment status from Mollie.
        $payment = $mollie->payments->get($payment_id);

        // Use a transient to prevent duplicate processing of the same payment ID.
        $processed_transient_key = 'pontifex_processed_' . $payment_id;
        if (get_transient($processed_transient_key)) {
            return new \WP_REST_Response(['status' => 'ignored', 'message' => 'Already processed.'], 200);
        }

        // Handle successful payments.
        if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
            $order_data      = (array) ($payment->metadata ?? []);
            $order_email     = $order_data['order_email'] ?? '';
            $exam_type_label = $order_data['exam_type'] ?? 'Examen';

            error_log("[Pontifex OI Webhook] Succesvol betaald: {$order_email} - {$exam_type_label}");

            // 1) SOAP-registratie naar Pontifex.
            if (!class_exists(\PontifexOI\Api\SoapClient::class)) {
                require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
            }
            try {
                $soap = new \PontifexOI\Api\SoapClient();
                $soap_ok = $soap->sendRegistration($order_data);
                if (!$soap_ok) {
                    error_log('[Pontifex OI Webhook] SOAP-registratie gaf false terug.');
                }
            } catch (\Exception $e) {
                error_log('[Pontifex OI Webhook] SOAP-registratie fout: ' . $e->getMessage());
                // Continue execution, as this is a non-fatal error for the webhook.
            }

            // 2) Send confirmation emails.
            if (class_exists(MailHelpers::class)) {
                try {
                    MailHelpers::send_inschrijving_mails($order_data);
                    error_log("[Pontifex OI Webhook] Bevestigingsmails verzonden voor {$order_email}");
                } catch (\Exception $e) {
                    error_log('[Pontifex OI Webhook] Fout bij versturen mails: ' . $e->getMessage());
                }
            }

            // Set the transient to prevent re-processing.
            set_transient($processed_transient_key, true, 12 * HOUR_IN_SECONDS);
            return new \WP_REST_Response(['status' => 'success'], 200);

        } elseif ($payment->isFailed() || $payment->isCanceled() || $payment->isExpired()) {
            // Handle failed, canceled, or expired payments.
            error_log("[Pontifex OI Webhook] Betaling mislukt/geannuleerd/verlopen voor ID {$payment_id}");
            return new \WP_REST_Response(['status' => 'failed', 'message' => 'Payment failed or cancelled.'], 200);

        } else {
            // Handle other statuses like pending, authorized, etc.
            error_log("[Pontifex OI Webhook] Betaling pending/geautoriseerd voor ID {$payment_id}");
            return new \WP_REST_Response(['status' => 'pending'], 200);
        }

    } catch (\Mollie\Api\Exceptions\ApiException $e) {
        error_log("[Pontifex OI Webhook Mollie Error] {$e->getMessage()}");
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Mollie API error.'], 500);
    } catch (\Exception $e) {
        error_log("[Pontifex OI Webhook Fout] {$e->getMessage()}");
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Internal server error.'], 500);
    }
}