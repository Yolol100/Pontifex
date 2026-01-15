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
        'methods'             => 'POST',
        'callback'            => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback' => '__return_true', // Public endpoint for Mollie.
    ]);
});

/**
 * Main webhook handler.
 *
 * @param \WP_REST_Request $request
 * @return \WP_REST_Response
 */
function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    $payment_id = $request->get_param('id');
    if (empty($payment_id)) {
        return new \WP_REST_Response(['status' => 'error'], 400);
    }

    try {
        // Gebruik de centrale helper voor betalingen
        $payment = \PontifexOI\Helpers\PaymentHelpers::get_mollie_payment($payment_id);
        
        if (!$payment) {
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        // Haal order_id uit de Mollie metadata
        $order_id = $payment->metadata['order_id'] ?? null;

        if ($payment->isPaid() && $order_id) {
            // 1. Update status naar 'paid'
            \PontifexOI\Helpers\Registrations::update_status($order_id, 'paid');
            
            // 2. Haal de opgeslagen data op voor de e-mail
            $order_data = \PontifexOI\Helpers\Registrations::get_by_order_id($order_id);
            
            // 3. Verstuur de bevestigingen
            if ($order_data) {
                \PontifexOI\Helpers\MailHelpers::send_confirmation_emails($order_data);
                
                // 4. Optioneel: Stuur direct door naar SOAP indien betaald
                if (class_exists('\\PontifexOI\\Api\\SoapClient')) {
                    $soap = new \PontifexOI\Api\SoapClient();
                    $soap->sendRegistration($order_data['data']);
                }
            }
        }

        return new \WP_REST_Response(['status' => 'ok'], 200);
    } catch (\Exception $e) {
        error_log('Pontifex OI Webhook Error: ' . $e->getMessage());
        return new \WP_REST_Response(null, 500);
    }
}