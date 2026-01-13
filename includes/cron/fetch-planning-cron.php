<?php
// includes/cron/fetch-planning-cron.php
if (!defined('ABSPATH')) exit;

add_action('pontifex_oi_cron_fetch_planning', function () {
    if (!defined('ABSPATH')) return;

    try {
        if (!class_exists('\PontifexOI\Api\SoapClient') && file_exists(PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php')) {
            require_once PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
        }

        $soap = new \PontifexOI\Api\SoapClient();
        
        // CORRECTIE: fetchAndStorePlanning() retourneert een array.
        $updated = $soap->fetchAndStorePlanning();
        $count   = is_array($updated) ? count($updated) : 0;

        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';

        // (Optioneel) verleden weggooien: geen oude maanden/steden meer tonen
        $wpdb->query("DELETE FROM {$table} WHERE planning_date < CURDATE()");

        // De logica voor het opschonen van 'stale' toekomstige planningen is nu
        // verplaatst naar de SoapClient::fetchAndStorePlanning() methode.
        // Dit blok is daarom niet meer nodig in dit bestand.

        // Bust filter-caches voor Stap 1
        delete_transient('pontifex_oi_filter_months');
        delete_transient('pontifex_oi_filter_provinces');
        // Nieuwe cache keys voor steden zijn per-provincie (suffix)
        // Wis in elk geval de 'all' variant; extra: bekende provincies ook
        delete_transient('pontifex_oi_filter_cities_all');
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        $provs = $wpdb->get_col("
            SELECT DISTINCT location_province 
            FROM {$table}
            WHERE location_province IS NOT NULL AND location_province <> ''
        ");
        if ($provs) {
            foreach ($provs as $p) {
                // Let op: 'sanitize_title' (zoals gebruikt in de SoapClient) is hier beter dan strtolower(trim())
                $k = 'pontifex_oi_filter_cities_' . sanitize_title($p); 
                delete_transient($k);
            }
        }

        if ($count === 0) {
            error_log('PontifexOI CRON: geen planning-data ontvangen.');
        } else {
            error_log("PontifexOI CRON: $count planningen bijgewerkt.");
        }

    } catch (\Throwable $e) {
        error_log('PontifexOI CRON fout: ' . $e->getMessage());
    }
});