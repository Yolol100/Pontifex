<?php
/**
 * Registration listing bounds regression (disposable WordPress/MySQL only).
 * No Mollie, SOAP, SMTP, external URL or existing customer data is involved.
 */
$order_id = 'tr_ci_page_' . substr(hash('sha256', wp_generate_uuid4()), 0, 18);
$fixture_id = \PontifexOI\Helpers\Registrations::save($order_id, [
    'order_email' => 'ci-registration@example.invalid',
    'given-name' => 'CI',
    'family-name' => 'Only',
], 'ci-planning');
if (!$fixture_id) {
    throw new RuntimeException('Could not insert isolated listing fixture.');
}

global $wpdb;
$table = $wpdb->prefix . 'pontifex_oi_registrations';
try {
    foreach ([[0, 0], [-7, -15], [1, 1], [1, 20]] as $args) {
        [$requested_page, $requested_per_page] = $args;
        $result = \PontifexOI\Helpers\Registrations::list($requested_page, $requested_per_page, $order_id);
        $expected_page = max(1, $requested_page);
        $expected_per_page = max(1, $requested_per_page);
        if ($result['page'] !== $expected_page || $result['per_page'] !== $expected_per_page) {
            throw new RuntimeException('Unsafe pagination metadata for ' . json_encode($args));
        }
        if ($result['pages'] < 1 || $result['total'] !== 1 || count($result['rows']) !== 1
            || $result['rows'][0]['order_id'] !== $order_id) {
            throw new RuntimeException('Registration listing lost a row for ' . json_encode($args));
        }
    }
    $second_page = \PontifexOI\Helpers\Registrations::list(2, 1, $order_id);
    if ($second_page['page'] !== 2 || $second_page['rows'] !== []) {
        throw new RuntimeException('Valid out-of-range listing page changed.');
    }
    echo "Pontifex registration list pagination bounds: OK\n";
} finally {
    $wpdb->delete($table, ['order_id' => $order_id], ['%s']);
}
