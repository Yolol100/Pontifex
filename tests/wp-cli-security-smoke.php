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

echo 'Pontifex admin export registration and CSV safety: OK' . PHP_EOL;
