<?php
/**
 * Uninstall script for Pontifex OI plugin.
 * Do not use direct for anything anders dan via WP.
 * @package PontifexOI
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Heldere uitleg:
 * Dit bestand wordt aangeroepen wanneer de plugin permanent wordt verwijderd via de WordPress admin.
 * Het doel is om ALLE data en settings die door de plugin zijn toegevoegd, weer veilig op te ruimen,
 * conform AVG en WP-standaard.
 * Deuninstall.php draait in een “barebones” context – géén andere pluginfiles zijn geladen.
 */

/**
 * 1. Verwijder plugin-instellingen (options, transients)
 */
delete_option('pontifex_oi_settings');
delete_option('pontifex_oi_api_cache');
// Voeg hier alle andere opties toe die de plugin opslaat (optioneel: gebruik get_option_keys pattern).

/**
 * 2. Verwijder custom database tabellen
 * Alleen als de plugin custom tables maakt – check wpdb
 */
global $wpdb;
$prefix = $wpdb->prefix;
$tables = [
    $prefix . 'pontifex_oi_registrations',
    $prefix . 'pontifex_oi_logs',
    $prefix . 'pontifex_planning',
    // Voeg hier alle custom tables toe die je plugin gebruikt
];
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

/**
 * 3. Verwijder usermeta of postmeta (indien van toepassing)
 */
delete_metadata('user', 0, 'pontifex_oi_field', '', true);
delete_metadata('post', 0, 'pontifex_oi_linked_planning', '', true);

/**
 * 4. Eventueel: verwijderen van opties voor meerdere sites (Multisite Support)
 */
if (is_multisite()) {
    $sites = get_sites(['fields' => 'ids']);
    foreach ($sites as $site_id) {
        switch_to_blog($site_id);
        delete_option('pontifex_oi_settings');
        delete_option('pontifex_oi_soap_url');
        delete_option('pontifex_oi_soap_user_id');
        delete_option('pontifex_oi_soap_hash');
        delete_option('pontifex_oi_soap_company_id');
        delete_option('pontifex_oi_mollie_live_api_key');
        delete_option('pontifex_oi_mollie_test_api_key');
        delete_option('pontifex_oi_mollie_test_mode');
        // Herhaal voor andere opties/tables
        restore_current_blog();
    }
} else {
    delete_option('pontifex_oi_soap_url');
    delete_option('pontifex_oi_soap_user_id');
    delete_option('pontifex_oi_soap_hash');
    delete_option('pontifex_oi_soap_company_id');
    delete_option('pontifex_oi_mollie_live_api_key');
    delete_option('pontifex_oi_mollie_test_api_key');
    delete_option('pontifex_oi_mollie_test_mode');
}

/**
 * 5. (Best practice) Logging of archiveren van verwijderde data (optioneel)
 * Let op: vanwege AVG/GPDR niet zomaar data bewaren.
 */

/**
 * 6. (Optioneel) Remove scheduled events, cron jobs
 */
if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('pontifex_oi_cron_event');
}

// Einde uninstall.php