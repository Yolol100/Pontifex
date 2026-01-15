<?php
declare(strict_types=1);

namespace PontifexOI\Cron;

use PontifexOI\Api\SoapClient;
use Throwable;
use RuntimeException;

if (!defined('ABSPATH')) exit;

/**
 * Modernized Fetch Planning Cron
 */
add_action('pontifex_oi_cron_fetch_planning', function () {
    try {
        // 1. Dependency Loading
        if (!class_exists(SoapClient::class)) {
            $path = PONTIFEX_OI_PATH . 'includes/api/class-soap-client.php';
            if (file_exists($path)) require_once $path;
        }

        $soap = new SoapClient();
        
        // 2. Fetch & Store
        $updated_data = $soap->fetchAndStorePlanning();
        $count = is_array($updated_data) ? count($updated_data) : 0;

        if ($count === 0) {
            // We gebruiken een log in plaats van een Exception als 0 items een valide (maar zeldzame) status is
            error_log("PontifexOI CRON: Geen nieuwe planning-data ontvangen.");
            return;
        }

        // 3. Database Cleanup (Verleden opschonen)
        global $wpdb;
        $table = $wpdb->prefix . 'pontifex_planning';
        
        $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE planning_date < %s", current_time('mysql', 0))
        );

        // 4. Intelligente Cache Invalidation
        // GEEN self:: gebruiken hier, want we zitten niet in een class.
        invalidate_planning_caches($table);

        error_log("PontifexOI CRON: $count planningen succesvol bijgewerkt.");

    } catch (Throwable $e) {
        error_log(sprintf('PontifexOI CRON Error [%s]: %s', get_class($e), $e->getMessage()));
    }
});

/**
 * Helper functie om alle gerelateerde transients te wissen.
 */
function invalidate_planning_caches(string $table): void 
{
    global $wpdb;

    $transients_to_clear = [
        'pontifex_oi_filter_months',
        'pontifex_oi_filter_provinces',
        'pontifex_oi_filter_cities_all'
    ];

    foreach ($transients_to_clear as $t) {
        delete_transient($t);
    }

    // Haal provincies op om specifieke steden-caches te wissen
    $provinces = $wpdb->get_col("SELECT DISTINCT location_province FROM {$table} WHERE location_province > ''");
    
    if (!empty($provinces)) {
        foreach ($provinces as $province) {
            delete_transient('pontifex_oi_filter_cities_' . sanitize_title($province));
        }
    }
}