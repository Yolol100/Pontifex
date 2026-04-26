<?php
/**
 * Mollie Webhook Handler for Pontifex OI.
 *
 * Registers a WordPress REST API endpoint to receive
 * and process webhook notifications from Mollie for payment updates.
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
        'methods'               => 'POST',
        'callback'              => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback'   => 'pontifex_oi_mollie_webhook_permission',
    ]);
});

/**
 * Permission callback voor webhook endpoint.
 *
 * @param \WP_REST_Request $request
 * @return bool
 */
function pontifex_oi_mollie_webhook_permission(\WP_REST_Request $request): bool {
    $expected_secret = (string) get_option('pontifex_oi_webhook_secret', '');
    if ($expected_secret === '') {
        return false;
    }

    $expected_token = hash_hmac('sha256', 'pontifex-webhook', $expected_secret);
    $received_token = (string) ($request->get_param('token') ?? '');
    $received_secret = (string) ($request->get_header('x-pontifex-secret') ?: $request->get_param('secret'));

    if ($received_token !== '' && hash_equals($expected_token, $received_token)) {
        return true;
    }

    if ($received_secret !== '' && hash_equals($expected_secret, $received_secret)) {
        return true;
    }

    return false;
}

/**
 * Main webhook handler.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response
 */
function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    if (!pontifex_oi_mollie_webhook_permission($request)) {
        error_log('[Pontifex OI Webhook Error] Ongeautoriseerde webhook call.');
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $payment_id = sanitize_text_field((string) $request->get_param('id'));
    if (empty($payment_id)) {
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Missing payment id'], 400);
    }

    try {
        // Load Mollie API
        if (!class_exists('\Mollie\Api\MollieApiClient')) {
            if (file_exists(PONTIFEX_OI_PATH . 'vendor/autoload.php')) {
                require_once PONTIFEX_OI_PATH . 'vendor/autoload.php';
            } else {
                error_log('[Pontifex OI Webhook Error] vendor/autoload.php ontbreekt.');
                return new \WP_REST_Response(['status' => 'error', 'message' => 'Mollie SDK missing'], 500);
            }
        }

        // API Key
        $apiKey = PaymentHelpers::get_mollie_api_key();
        if (empty($apiKey)) {
            error_log('[Pontifex OI Webhook Error] Mollie API sleutel ontbreekt.');
            return new \WP_REST_Response(['status' => 'error', 'message' => 'Mollie API key missing'], 500);
        }

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($apiKey);

        // Persistente duplicate guard.
        if (!class_exists('\PontifexOI\Helpers\Registrations')) {
            require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
        }
        if (\PontifexOI\Helpers\Registrations::exists_by_order_id($payment_id)) {
            return new \WP_REST_Response(['status' => 'ignored', 'message' => 'Already processed'], 200);
        }

        $payment = $mollie->payments->get($payment_id);

        // Handle PAID
        if ($payment->isPaid() && !$payment->hasRefunds() && !$payment->hasChargebacks()) {
            
            // ✅ Verbeterde logregel voor visibility
            error_log('[Pontifex OI Webhook] Betaling verwerkt: ' . $payment_id);

            $order = (array) ($payment->metadata ?? []);

            // (Veilig) normaliseren van kandidaatgegevens naar array
            $order['candidate_fullname']  = isset($order['candidate_fullname']) ? (array) $order['candidate_fullname'] : [];
            $order['candidate_infix']     = isset($order['candidate_infix']) ? (array) $order['candidate_infix'] : [];
            $order['candidate_lastname']  = isset($order['candidate_lastname']) ? (array) $order['candidate_lastname'] : [];
            $order['candidate_birthdate'] = isset($order['candidate_birthdate']) ? (array) $order['candidate_birthdate'] : [];

            // --- NIEUWE LOGICA: REGISTRATIE OPSLAAN IN DATABASE ---
            // Zorg dat Registrations helper beschikbaar is
            if (!class_exists('\PontifexOI\Helpers\Registrations')) {
                require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
            }
            try {
                // Gebruik Mollie payment id als order_id; planning_identifier indien aanwezig
                $planning_identifier = isset($order['planning_identifier']) ? sanitize_text_field((string) $order['planning_identifier']) : null;
                \PontifexOI\Helpers\Registrations::save($payment->id, $order, $planning_identifier);
            } catch (\Throwable $e) {
                error_log('[Pontifex OI] Registrations save failed: ' . $e->getMessage());
            }
            // -----------------------------------------------------

            // SOAP-registratie uitvoeren
            try {
                if (!class_exists('\PontifexOI\Api\SoapClient')) {
                    require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
                }
                $soap = new \PontifexOI\Api\SoapClient();
                $ok    = $soap->sendRegistration($order);

                if (!$ok) {
                    error_log('[Pontifex OI Webhook] SOAP registratie gaf false terug.');
                }
            } catch (\Throwable $e) {
                error_log('[Pontifex OI Webhook SOAP Error] ' . $e->getMessage());
            }

            // ✅ KLEINE AANBEVELING: Zorg dat order_email altijd een waarde heeft voor de mail
            $order['order_email'] = $order['order_email'] ?? ($order['email'] ?? null);

            // Bevestigingsmails sturen (indien geconfigureerd)
            try {
                $order['order_email'] = $order['order_email'] ?? ($order['email'] ?? null);
                MailHelpers::send_inschrijving_mails($order);
            } catch (\Throwable $e) {
                error_log('[Pontifex OI Mail Error] ' . $e->getMessage());
            }
        }

        return new \WP_REST_Response(['status' => 'ok', 'id' => $payment_id], 200);
    } catch (\Throwable $e) {
        error_log('[Pontifex OI Webhook Fatal] ' . $e->getMessage());
        return new \WP_REST_Response(['status' => 'error', 'message' => 'exception'], 500);
    }
}
