<?php
/**
 * Handler voor het ophalen en opslaan van SOAP planning via AJAX in de admin.
 * Plaatsen in: includes/helpers/ajax-soap-fetch-handler.php
 * Zorg dat dit bestand wordt ingeladen in je plugin main file of admin-class!
 */

if (!defined('ABSPATH')) exit;

// Handler alleen voor beheerders!
add_action('wp_ajax_pontifex_oi_fetch_soap_data', function () {
    // Optioneel: extra beveiliging voor beheerders
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Geen toegang.', 'pontifex-oi')]);
        exit;
    }

    // Eventueel kun je POST-waarden ophalen, maar in deze structuur haal je gewoon de opties op.
    try {
        // Zorg dat de SoapClient class is geladen!
        if (!class_exists('\PontifexOI\Api\SoapClient')) {
            require_once dirname(__FILE__, 2) . '/api/class-soap-client.php';
        }
        $soap = new \PontifexOI\Api\SoapClient();
        $result = $soap->fetchAndStorePlanning();

        if ($result === true) {
            wp_send_json_success(['message' => __('Gegevens succesvol opgehaald en opgeslagen.', 'pontifex-oi')]);
        } else {
            wp_send_json_error(['message' => __('Ophalen is mislukt, geen data ontvangen.', 'pontifex-oi')]);
        }
    } catch (\Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
    exit;
});