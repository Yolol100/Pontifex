<?php
/** Mollie lifecycle decision and durable pending-order regression in WordPress. */
if (!defined('ABSPATH')) {
    throw new RuntimeException('WordPress runtime required.');
}

class PontifexSmokePayment {
    private bool $paid;
    private bool $refund;
    private bool $chargeback;
    public function __construct(bool $paid, bool $refund = false, bool $chargeback = false) {
        $this->paid = $paid;
        $this->refund = $refund;
        $this->chargeback = $chargeback;
    }
    public function isPaid(): bool { return $this->paid; }
    public function hasRefunds(): bool { return $this->refund; }
    public function hasChargebacks(): bool { return $this->chargeback; }
}
$scenarios = [
    [[true, false, false], [], 'process'],
    [[true, false, false], ['complete' => true], 'already_complete'],
    [[false, false, false], [], 'not_paid'],
    [[true, true, false], ['complete' => true], 'refund_review'],
    [[true, false, true], ['complete' => true], 'chargeback_review'],
    [[false, true, true], [], 'chargeback_review'],
];
foreach ($scenarios as [$flags, $state, $expected]) {
    $decision = pontifex_oi_webhook_decision(new PontifexSmokePayment(...$flags), $state);
    if ($decision !== $expected) {
        throw new RuntimeException('Mollie refund/chargeback post-completion decision regression.');
    }
}

$token = 'pontifex_order_' . bin2hex(random_bytes(16));
$payment = 'tr_ci_test_' . substr(hash('sha256', $token), 0, 12);
$order = ['order_email' => 'pending@example.test', 'candidate_fullname' => ['Candidate']];
if (!\PontifexOI\Helpers\PendingOrders::create($token, $order)) {
    throw new RuntimeException('Durable pending storage must succeed.');
}
if (\PontifexOI\Helpers\PendingOrders::get_order($token, 'tr_wrongref') !== null) {
    throw new RuntimeException('A foreign payment id must not read pending candidate details.');
}
if (!\PontifexOI\Helpers\PendingOrders::set_payment_id($token, $payment)
    || \PontifexOI\Helpers\PendingOrders::payment_id($token) !== $payment
    || \PontifexOI\Helpers\PendingOrders::get_order($token, $payment) !== $order) {
    throw new RuntimeException('Payment id and pending candidate order must be bound durably.');
}
\PontifexOI\Helpers\PendingOrders::mark_complete($token);
if (\PontifexOI\Helpers\PendingOrders::get_order($token, $payment) !== null
    || \PontifexOI\Helpers\PendingOrders::payment_id($token) !== $payment) {
    throw new RuntimeException('Completed orders must drop transient candidate PII and retain lookup reference.');
}
\PontifexOI\Helpers\PendingOrders::discard($token);
if (\PontifexOI\Helpers\PendingOrders::payment_id($token) !== '') {
    throw new RuntimeException('Pending option cleanup failed.');
}
global $wpdb;
$index = $wpdb->get_var(
    "SHOW INDEX FROM {$wpdb->prefix}pontifex_oi_registrations WHERE Key_name = 'pontifex_unique_order_id'"
);
if ($index === null) {
    throw new RuntimeException('Clean WordPress must have an atomic unique payment ID index.');
}
$registered_id = 'tr_retry_' . substr(hash('sha256', wp_generate_uuid4()), 0, 16);
$first = \PontifexOI\Helpers\Registrations::save($registered_id, ['order_email' => 'original@example.test']);
$second = \PontifexOI\Helpers\Registrations::save($registered_id, ['order_email' => 'changed@example.test']);
$still_original = \PontifexOI\Helpers\Registrations::find_order($registered_id);
$wpdb->delete($wpdb->prefix . 'pontifex_oi_registrations', ['order_id' => $registered_id], ['%s']);
if (!$first || $second !== $first || ($still_original['order_email'] ?? '') !== 'original@example.test') {
    throw new RuntimeException('A webhook replay must never overwrite an existing buyer record.');
}

// Pending PII expiry creates only a payment-id reconciliation marker.
$expiring_token = 'pontifex_order_' . bin2hex(random_bytes(16));
$expiring_id = 'tr_expire_' . substr(hash('sha256', $expiring_token), 0, 10);
\PontifexOI\Helpers\PendingOrders::create($expiring_token, ['order_email' => 'expiring@example.test']);
\PontifexOI\Helpers\PendingOrders::set_payment_id($expiring_token, $expiring_id);
$hash = hash('sha256', $expiring_token);
$pending_key = 'pontifex_pending_' . $hash;
$record = get_option($pending_key);
$record['created_at'] = time() - 31 * DAY_IN_SECONDS;
update_option($pending_key, $record, false);
\PontifexOI\Helpers\PendingOrders::cleanup_expired();
$review = get_option('pontifex_review_' . $hash);
if (get_option($pending_key, false) !== false
    || !is_array($review)
    || ($review['payment_id'] ?? '') !== $expiring_id) {
    throw new RuntimeException('Expired pending buyer data must be erased, leaving only provider reconciliation.');
}
delete_option('pontifex_review_' . $hash);

if (pontifex_oi_webhook_mail_decision([]) !== 'send'
    || pontifex_oi_webhook_mail_decision(['mail_attempted' => true]) !== 'needs_review'
    || pontifex_oi_webhook_mail_decision(['mail_sent' => true]) !== 'already_sent') {
    throw new RuntimeException('Uncertain mail delivery must never trigger automatic retry.');
}
$soap_stub = new \PontifexOI\Api\SoapClient();
if ($soap_stub->sendRegistration(['test' => true]) !== false) {
    throw new RuntimeException('Unimplemented SOAP registration must never report success.');
}

echo 'Mollie pure status scenarios + durable pending-order lifecycle: OK' . PHP_EOL;
