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

echo 'Pontifex CSV, candidate, REST and SOAP diagnostic safety: OK' . PHP_EOL;
