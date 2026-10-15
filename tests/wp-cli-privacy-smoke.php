<?php
/** WordPress privacy integration regression; only synthetic WP-CLI records. */
if (!defined('ABSPATH')) {
    throw new RuntimeException('Activate WordPress before this privacy test.');
}
global $wpdb;

$key = 'tr_privacy_' . substr(hash('sha256', wp_generate_uuid4()), 0, 16);
$order = [
    'order_email' => 'privacy-buyer@example.test',
    'given-name' => 'Mevrouw',
    'family-name' => 'Koper',
    'candidate_fullname' => ['Andere Persoon'],
    'candidate_birthdate' => ['03-04-1993'],
];
$id = \PontifexOI\Helpers\Registrations::save($key, $order);
$exporters = apply_filters('wp_privacy_personal_data_exporters', []);
$erasers = apply_filters('wp_privacy_personal_data_erasers', []);
if (!$id || !isset($exporters['pontifex-oi'], $erasers['pontifex-oi'])) {
    throw new RuntimeException('Pontifex WordPress privacy hooks must be registered.');
}
$result = \PontifexOI\Helpers\Registrations::export_personal_data('privacy-buyer@example.test');
$text = wp_json_encode($result);
if (empty($result['data']) || !is_string($text)
    || strpos($text, 'Koper') === false
    || strpos($text, 'Andere Persoon') !== false
    || strpos($text, '03-04-1993') !== false) {
    throw new RuntimeException('Buyer export must exclude separate candidate PII.');
}
$default = \PontifexOI\Helpers\Registrations::erase_personal_data('privacy-buyer@example.test');
if (!$default['items_retained'] || $default['items_removed']) {
    throw new RuntimeException('Accounting records must not be erased without approval.');
}
$approve = static fn ($allowed, $payment_id) => $payment_id === $key;
add_filter('pontifex_oi_allow_buyer_data_erasure', $approve, 10, 2);
$erased = \PontifexOI\Helpers\Registrations::erase_personal_data('privacy-buyer@example.test');
remove_filter('pontifex_oi_allow_buyer_data_erasure', $approve, 10);
$recovered = \PontifexOI\Helpers\Registrations::find_order($key);
$wpdb->delete($wpdb->prefix . 'pontifex_oi_registrations', ['order_id' => $key], ['%s']);
if (!$erased['items_removed'] || !is_array($recovered)
    || isset($recovered['order_email'])
    || isset($recovered['family-name'])
    || ($recovered['candidate_fullname'][0] ?? '') !== 'Andere Persoon') {
    throw new RuntimeException('Approved buyer-only erasure failed.');
}
echo 'Pontifex privacy export and protected erasure: OK' . PHP_EOL;
