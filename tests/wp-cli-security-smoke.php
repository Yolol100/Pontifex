<?php
/**
 * Small runtime checks for registration-export security boundaries.
 *
 * Run with WP-CLI after activating Pontifex OI in a clean WordPress site:
 *   wp eval-file wp-content/plugins/pontifex-oi/tests/wp-cli-security-smoke.php
 *
 * @package PontifexOI
 */

if (!defined('ABSPATH') || !defined('PONTIFEX_OI_PATH')) {
    throw new RuntimeException('This test must run inside activated WordPress.');
}

global $wpdb;

require_once PONTIFEX_OI_PATH . 'admin/class-admin.php';
require_once PONTIFEX_OI_PATH . 'includes/helpers/submissions-helpers.php';

// WP-CLI may not load the admin controller automatically (is_admin() is false).
\PontifexOI\Admin\Admin::get_instance();

if (!has_action('admin_post_pontifex_oi_export_submissions')) {
    throw new RuntimeException('Admin-only CSV export hook is missing.');
}

if (has_action('admin_post_nopriv_pontifex_oi_export_submissions')) {
    throw new RuntimeException('Export must never register a guest endpoint.');
}

$cases = [
    ['Gewone tekst', 'Gewone tekst'],
    ['=1+1', "'=1+1"],
    [' +SUM(1,2)', "' +SUM(1,2)"],
    ['-100', "'-100"],
    ['@malicious', "'@malicious"],
    ["\t=1+1", "'\t=1+1"],
    ["\r@formula", "'\r@formula"],
    ['example@example.test', 'example@example.test'],
    ['042', '042'],
    [null, ''],
    [['unexpected' => 'array'], ''],
];

foreach ($cases as [$input, $expected]) {
    $actual = pontifex_oi_csv_safe_cell($input);
    if ($actual !== $expected) {
        throw new RuntimeException('CSV spreadsheet-formula protection regression.');
    }

    $stream = fopen('php://temp', 'w+');
    if ($stream === false) {
        throw new RuntimeException('CSV test stream unavailable.');
    }
    fputcsv($stream, [$actual], ',', '"', '');
    rewind($stream);
    $roundtrip = fgetcsv($stream, 0, ',', '"', '');
    fclose($stream);
    if (!is_array($roundtrip) || ($roundtrip[0] ?? '') !== $expected) {
        throw new RuntimeException('CSV roundtrip unexpectedly changed the safe content.');
    }
}


/**
 * Candidate rows must remain aligned even when a middle name is blank.
 */
$valid_order = [
    'candidate_fullname'  => ['Eva', 'Lina'],
    'candidate_infix'     => ['', 'van'],
    'candidate_lastname'  => ['Vis', 'Berg'],
    'candidate_birthdate' => ['01-01-1995', '02-02-1996'],
];
$normalized = \PontifexOI\Helpers\RegistrationValidator::normalize_candidates($valid_order);
if (is_wp_error($normalized)
    || ($normalized['candidate_count'] ?? null) !== 2
    || ($normalized['candidate_infix'][0] ?? null) !== ''
    || ($normalized['candidate_infix'][1] ?? null) !== 'van'
    || ($normalized['candidate_lastname'][1] ?? null) !== 'Berg') {
    throw new RuntimeException('Candidate fields must not shift when one value is blank.');
}

$invalid_orders = [
    ['candidate_fullname' => 'not-an-array'],
    ['candidate_fullname' => []],
    ['candidate_fullname' => array_fill(0, 13, 'Candidate')],
    ['candidate_fullname' => ['']],
    ['candidate_fullname' => [['unexpected' => 'nested']]],
];
foreach ($invalid_orders as $invalid_order) {
    if (!is_wp_error(\PontifexOI\Helpers\RegistrationValidator::normalize_candidates($invalid_order))) {
        throw new RuntimeException('Malformed candidate input must fail validation.');
    }
}


/**
 * Malformed unauthenticated REST filter data must return 400 instead of
 * reaching PHP string-offset errors or the SOAP planning backend.
 */
$invalid_filters = [
    'not-an-array',
    ['month' => ['nested-array']],
    ['page' => 'not-a-page'],
    ['per_page' => -1],
    ['location' => str_repeat('x', 192)],
];

foreach ($invalid_filters as $invalid_filter) {
    $request = new \WP_REST_Request('POST', '/pontifex-oi/v1/planning');
    $request->set_param('filters', $invalid_filter);
    $response = rest_do_request($request);
    if ($response->get_status() !== 400) {
        throw new RuntimeException('Planning REST route must reject malformed filter values.');
    }
}

// Weekend planning is local: this valid test never calls the external SOAP API.
$request = new \WP_REST_Request('POST', '/pontifex-oi/v1/planning');
$request->set_param('filters', [
    'material' => 'cursus-weekend',
    'month' => wp_date('Y-m'),
    'page' => 1,
    'per_page' => 1,
]);
$response = rest_do_request($request);
$result = $response->get_data();
if ($response->get_status() !== 200
    || !is_array($result)
    || !isset($result['planning'])
    || !is_array($result['planning'])
    || !isset($result['current_page'])
    || $result['current_page'] !== 1) {
    throw new RuntimeException('Legitimate weekend-planning REST request must keep working.');
}


/**
 * The public AJAX and REST planning handlers share one normalization routine.
 * Verify that it strips unknown keys and returns a safe typed filter array.
 */
$planning_method = (new ReflectionClass(\PontifexOI\PublicPart\Frontend::class))->getMethod('normalize_planning_filters');
$normalized_filters = $planning_method->invoke(null, [
    'province' => '<b>Zuid-Holland</b>',
    'page' => '2',
    'per_page' => '25',
    'unexpected_option' => 'ignored',
]);
if (is_wp_error($normalized_filters)
    || ($normalized_filters['province'] ?? '') !== 'Zuid-Holland'
    || ($normalized_filters['page'] ?? null) !== 2
    || ($normalized_filters['per_page'] ?? null) !== 25
    || isset($normalized_filters['unexpected_option'])) {
    throw new RuntimeException('Public planning filters must normalize allowed values consistently.');
}

/**
 * Pricing reads must reject nonscalar filters and preserve valid calculations.
 */
$invalid_prices = [
    ['exam_type' => ['unexpected']],
    ['language' => ['unexpected']],
    ['material' => ['unexpected']],
    ['material' => str_repeat('x', 192)],
];

foreach ($invalid_prices as $invalid) {
    $request = new \WP_REST_Request('POST', '/pontifex-oi/v1/price');
    foreach ($invalid as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    if ($response->get_status() !== 400) {
        throw new RuntimeException('REST price must reject malformed filters.');
    }
}

$request = new \WP_REST_Request('POST', '/pontifex-oi/v1/price');
$request->set_param('exam_type', 'los-examen-vca-basis');
$request->set_param('language', 'nl');
$request->set_param('material', '1');
$response = rest_do_request($request);
$payload = $response->get_data();
if ($response->get_status() !== 200
    || !is_array($payload)
    || !isset($payload['raw_price'])
    || !is_numeric($payload['raw_price'])
    || (float) $payload['raw_price'] <= 0) {
    $status = $response->get_status();
    $code = is_array($payload) ? (string) ($payload['code'] ?? 'no_error_code') : 'unexpected_payload';
    $amount = is_array($payload) ? (string) ($payload['raw_price'] ?? 'missing') : 'missing';
    throw new RuntimeException(
        'Valid public REST price regression: HTTP ' . $status . ', code=' . $code . ', amount=' . $amount
        . ', request_exam=' . (string) $request->get_param('exam_type')
        . ', direct_price=' . (string) \PontifexOI\Helpers\PaymentHelpers::calculate_total_price([
            'exam_type' => 'los-examen-vca-basis', 'language' => 'nl', 'material' => '1',
        ])
        . ', catalog=' . implode('', array_map(static function ($key) {
            return isset($GLOBALS[$key]) && is_array($GLOBALS[$key]) ? '1' : '0';
        }, ['EXAM_PRODUCTS', 'MATERIAL_PRODUCTS', 'MATERIAL_COMBIS', 'EXTRA_PRODUCTS']))
    );
}


/**
 * Pricing catalog must remain global even when an include runs in local scope.
 * Verify unit prices, two candidates and 21% VAT without a Mollie API request.
 */
foreach (['EXAM_PRODUCTS', 'EXTRA_PRODUCTS', 'MATERIAL_PRODUCTS', 'MATERIAL_COMBIS', 'WEEKEND_ALLOWED_BY_EXAM'] as $catalog_key) {
    if (!isset($GLOBALS[$catalog_key]) || !is_array($GLOBALS[$catalog_key])) {
        throw new RuntimeException('A required Pontifex pricing catalog is not globally available.');
    }
}

$base_excl = (float) ($GLOBALS['EXAM_PRODUCTS']['los-examen-vca-basis']['prices']['nl'] ?? 0);
$two_candidate_totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat([
    'exam_type' => 'los-examen-vca-basis',
    'language' => 'nl',
    'material' => '1',
    'candidate_count' => 2,
    'extra_options' => [],
]);
$expected_excl = round($base_excl * 2, 2);
$expected_vat21 = round($expected_excl * 0.21, 2);
$expected_incl = round($expected_excl + $expected_vat21, 2);
if ($base_excl <= 0
    || abs((float) ($two_candidate_totals['excl'] ?? 0) - $expected_excl) > 0.005
    || abs((float) ($two_candidate_totals['vat21'] ?? 0) - $expected_vat21) > 0.005
    || abs((float) ($two_candidate_totals['incl'] ?? 0) - $expected_incl) > 0.005) {
    throw new RuntimeException('Candidate total and VAT calculations diverge from the catalog.');
}

/**
 * SOAP logging must never keep raw requests/responses containing credentials.
 * This checks configuration only; it does not access the external provider.
 */
if (!class_exists(\PontifexOI\Api\SoapClient::class)) {
    throw new RuntimeException('Pontifex SOAP client is unavailable.');
}
$soap_defaults = (new ReflectionClass(\PontifexOI\Api\SoapClient::class))->getDefaultProperties();
if (($soap_defaults['options']['trace'] ?? null) !== 0) {
    throw new RuntimeException('SOAP request/response tracing must be disabled.');
}


/**
 * A payment may only be started with a complete and valid buyer/order payload.
 */
$valid_buyer = array_merge($valid_order, [
    'given-name' => 'E.',
    'family-name' => 'Vis',
    'postal-code' => '1234 AB',
    'address-line2' => '12',
    'street-address' => 'Voorbeeldstraat',
    'address-level2' => 'Amsterdam',
    'tel' => '+31612345678',
    'organization-title' => 'Medewerker',
    'order_email' => 'inschrijving@example.test',
    'exam_type' => 'los-examen-vca-basis',
    'language' => 'nl',
    'material' => '1',
    'extra_options' => [],
]);
$validated = \PontifexOI\Helpers\RegistrationValidator::normalize_candidates($valid_buyer);
$validated = is_wp_error($validated) ? $validated : \PontifexOI\Helpers\RegistrationValidator::normalize_order_fields($validated);
if (is_wp_error($validated) || $validated['order_email'] !== 'inschrijving@example.test') {
    throw new RuntimeException('A valid buyer must remain eligible for registration.');
}

foreach ([
    ['order_email' => 'not-an-email'],
    ['order_email' => ['unexpected']],
    ['tel' => 'abc'],
    ['given-name' => ''],
    ['extra_options' => [['nested']]],
    ['extra_options' => ['unknown-optional-product']],
    ['exam_type' => 'fake-exam'],
    ['language' => ['unexpected']],
] as $bad) {
    $order = array_replace($valid_buyer, $bad);
    $result = \PontifexOI\Helpers\RegistrationValidator::normalize_order_fields($order);
    if (!is_wp_error($result)) {
        throw new RuntimeException('Incomplete or tampered buyer/order input passed validation.');
    }
}
foreach ([
    ['candidate_lastname' => ['Vis', '']],
    ['candidate_birthdate' => ['32-02-2020', '02-02-1996']],
    ['candidate_birthdate' => ['31-12-2999', '02-02-1996']],
] as $bad) {
    if (!is_wp_error(\PontifexOI\Helpers\RegistrationValidator::normalize_candidates(
        array_replace($valid_buyer, $bad)
    ))) {
        throw new RuntimeException('Candidate surname or date-of-birth regression.');
    }
}

/**
 * Webhook state must survive transient expiry. Legacy flags remain readable.
 * Use a synthetic key and never contact the Mollie API.
 */
$state_key = 'pontifex_wh_state_' . hash('sha256', 'runtime-smoke');
$fixture = ['registration_saved' => true, 'mail_sent' => true, 'complete' => true];
delete_option($state_key);
pontifex_oi_webhook_store_state($state_key, $fixture);
delete_transient($state_key);
if (pontifex_oi_webhook_load_state($state_key) !== $fixture) {
    throw new RuntimeException('Webhook state must be durable.');
}
delete_option($state_key);
set_transient($state_key, $fixture, MINUTE_IN_SECONDS);
if (pontifex_oi_webhook_load_state($state_key) !== $fixture
    || get_option($state_key, null) !== $fixture) {
    throw new RuntimeException('Old webhook transient state migration failed.');
}
delete_option($state_key);
delete_transient($state_key);


/**
 * A confirmed local payment ID must map to one order, and the persisted payload
 * must be recoverable after a transient disappears.
 */
$test_payment_id = 'tr_pontifex_ci_' . substr(hash('sha256', wp_generate_uuid4()), 0, 16);
$first_id = \PontifexOI\Helpers\Registrations::save($test_payment_id, $validated, 'ci-plan');
$second_id = \PontifexOI\Helpers\Registrations::save($test_payment_id, $validated, 'ci-plan');
$restored_order = \PontifexOI\Helpers\Registrations::find_order($test_payment_id);
$count = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}pontifex_oi_registrations WHERE order_id = %s",
    $test_payment_id
));
$wpdb->delete($wpdb->prefix . 'pontifex_oi_registrations', ['order_id' => $test_payment_id], ['%s']);
if (!$first_id || $first_id !== $second_id || $count !== 1
    || !is_array($restored_order)
    || ($restored_order['order_email'] ?? null) !== 'inschrijving@example.test') {
    throw new RuntimeException('Local webhook registration upsert and recovery regression.');
}

echo 'Pontifex security, lifecycle and pricing smoke tests: OK' . PHP_EOL;
