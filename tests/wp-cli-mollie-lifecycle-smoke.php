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
echo 'Mollie pure status scenarios + durable pending-order lifecycle: OK' . PHP_EOL;
