<?php
/**
 * Mollie webhook handler for Pontifex OI.
 *
 * @package PontifexOI
 * @subpackage Webhook
 */

if (!defined('ABSPATH')) {
    exit;
}

use PontifexOI\Helpers\PaymentHelpers;
use PontifexOI\Helpers\MailHelpers;

add_action('rest_api_init', function () {
    register_rest_route('pontifex-oi/v1', '/webhook', [
        'methods' => 'POST',
        'callback' => 'pontifex_oi_mollie_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Persist a bounded webhook stage state so retries continue after the last
 * confirmed side effect instead of repeating already completed work.
 *
 * @param string $state_key Transient key.
 * @param array  $state     Stage state.
 * @return void
 */
function pontifex_oi_webhook_store_state(string $state_key, array $state): void {
    set_transient($state_key, $state, 7 * DAY_IN_SECONDS);
}

/**
 * Main webhook handler.
 *
 * @param \WP_REST_Request $request Request object.
 * @return \WP_REST_Response
 */
function pontifex_oi_mollie_webhook_handler(\WP_REST_Request $request) {
    $expected_secret = (string) get_option('pontifex_oi_webhook_secret', '');
    $received_secret = (string) $request->get_param('secret');

    if ($expected_secret !== '' && ($received_secret === '' || !hash_equals($expected_secret, $received_secret))) {
        error_log('[Pontifex OI Webhook] Rejected request with invalid webhook secret.');
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Forbidden'], 403);
    }

    $payment_id = trim((string) $request->get_param('id'));
    if ($payment_id === '' || strlen($payment_id) > 80 || !preg_match('/^[A-Za-z0-9_-]+$/', $payment_id)) {
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Invalid payment id'], 400);
    }

    $event_hash = hash('sha256', $payment_id);
    $state_key = 'pontifex_wh_state_' . $event_hash;
    $lock_key = 'pontifex_wh_lock_' . $event_hash;
    $state = get_transient($state_key);
    $state = is_array($state) ? $state : [];

    if (!empty($state['complete'])) {
        return new \WP_REST_Response(['status' => 'ignored', 'message' => 'Already processed'], 200);
    }

    $now = time();
    $existing_lock = (int) get_option($lock_key, 0);
    if ($existing_lock > 0 && $existing_lock < ($now - 5 * MINUTE_IN_SECONDS)) {
        delete_option($lock_key);
        $existing_lock = 0;
    }

    if ($existing_lock > 0 || !add_option($lock_key, $now, '', false)) {
        return new \WP_REST_Response(['status' => 'busy', 'message' => 'Processing in progress'], 202);
    }

    try {
        if (!class_exists('\\Mollie\\Api\\MollieApiClient')) {
            $autoload = PONTIFEX_OI_PATH . 'vendor/autoload.php';
            if (!is_readable($autoload)) {
                throw new \RuntimeException('mollie_sdk_missing');
            }
            require_once $autoload;
        }

        $api_key = PaymentHelpers::get_mollie_api_key();
        if ($api_key === '') {
            throw new \RuntimeException('mollie_api_key_missing');
        }

        $mollie = new \Mollie\Api\MollieApiClient();
        $mollie->setApiKey($api_key);
        $payment = $mollie->payments->get($payment_id);

        if (!$payment->isPaid() || $payment->hasRefunds() || $payment->hasChargebacks()) {
            return new \WP_REST_Response(['status' => 'ok', 'message' => 'No paid action required'], 200);
        }

        $metadata = (array) ($payment->metadata ?? []);
        $order_token = isset($metadata['order_token']) ? sanitize_text_field((string) $metadata['order_token']) : '';
        $order = [];

        if ($order_token !== '') {
            $stored_order = get_transient('pontifex_order_data_for_token_' . $order_token);
            if (is_array($stored_order)) {
                $order = $stored_order;
            }
        }

        if (empty($order)) {
            $order = $metadata;
        }

        $order['candidate_fullname'] = isset($order['candidate_fullname']) ? (array) $order['candidate_fullname'] : [];
        $order['candidate_infix'] = isset($order['candidate_infix']) ? (array) $order['candidate_infix'] : [];
        $order['candidate_lastname'] = isset($order['candidate_lastname']) ? (array) $order['candidate_lastname'] : [];
        $order['candidate_birthdate'] = isset($order['candidate_birthdate']) ? (array) $order['candidate_birthdate'] : [];
        $order['order_email'] = $order['order_email'] ?? ($order['email'] ?? null);

        if (empty($state['registration_saved'])) {
            if (!class_exists('\\PontifexOI\\Helpers\\Registrations')) {
                require_once PONTIFEX_OI_PATH . 'includes/helpers/class-registrations.php';
            }
            $planning_identifier = isset($order['planning_identifier']) ? (string) $order['planning_identifier'] : null;
            $registration_id = \PontifexOI\Helpers\Registrations::save((string) $payment->id, $order, $planning_identifier);
            if ($registration_id === false) {
                throw new \RuntimeException('registration_persistence_failed');
            }
            $state['registration_saved'] = true;
            pontifex_oi_webhook_store_state($state_key, $state);
        }

        if (empty($state['soap_sent'])) {
            if (!class_exists('\\PontifexOI\\Api\\SoapClient')) {
                require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
            }
            $soap = new \PontifexOI\Api\SoapClient();
            if (!$soap->sendRegistration($order)) {
                throw new \RuntimeException('soap_registration_failed');
            }
            $state['soap_sent'] = true;
            pontifex_oi_webhook_store_state($state_key, $state);
        }

        if (empty($state['mail_sent'])) {
            $mail_result = MailHelpers::send_inschrijving_mails($order);
            if ($mail_result === false) {
                throw new \RuntimeException('confirmation_mail_failed');
            }
            $state['mail_sent'] = true;
            pontifex_oi_webhook_store_state($state_key, $state);
        }

        $state['complete'] = true;
        $state['completed_at'] = time();
        pontifex_oi_webhook_store_state($state_key, $state);

        if ($order_token !== '') {
            delete_transient('pontifex_order_data_for_token_' . $order_token);
        }

        return new \WP_REST_Response(['status' => 'ok'], 200);
    } catch (\Throwable $e) {
        error_log('[Pontifex OI Webhook] Processing failed (' . get_class($e) . ').');
        return new \WP_REST_Response(['status' => 'error', 'message' => 'Processing failed'], 500);
    } finally {
        delete_option($lock_key);
    }
}
