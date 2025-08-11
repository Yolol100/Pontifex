<?php
/**
 * Handler voor het ophalen en opslaan van SOAP planning via AJAX in de admin.
 * LET OP: Dit bestand registreert GEEN add_action meer om dubbele registraties te voorkomen.
 * De hook-registratie gebeurt in admin/class-admin.php:
 *
 * add_action('wp_ajax_pontifex_oi_fetch_soap_data', 'pontifex_oi_fetch_soap_data_handler');
 */

if (!defined('ABSPATH')) exit;

// Herbruikbare handlerfunctie (aanroepen via add_action in admin/class-admin.php)
if (!function_exists('pontifex_oi_fetch_soap_data_handler')) {
    function pontifex_oi_fetch_soap_data_handler() {
        // Alleen voor beheerders
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Geen toegang.', 'pontifex-oi')]);
        }

        try {
            // Zorg dat de SoapClient class is geladen
            if (!class_exists('\PontifexOI\Api\SoapClient')) {
                require_once dirname(__FILE__, 2) . '/api/class-soap-client.php';
            }

            $soap   = new \PontifexOI\Api\SoapClient();
            $result = $soap->fetchAndStorePlanning();

            if ($result === true) {
                wp_send_json_success(['message' => __('Gegevens succesvol opgehaald en opgeslagen.', 'pontifex-oi')]);
            } else {
                wp_send_json_error(['message' => __('Ophalen is mislukt, geen data ontvangen.', 'pontifex-oi')]);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}