<?php
/**
 * Handler voor het ophalen en opslaan van SOAP planning via AJAX in de admin.
 * Dit bestand wordt aangeroepen via de 'wp_ajax_pontifex_oi_fetch_soap_data' hook.
 *
 * @package PontifexOI
 * @subpackage Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the AJAX request to fetch and store SOAP planning data.
 *
 * This function performs security checks, calls the SOAP client, and
 * returns a JSON response to the front-end. It is hooked in the main
 * admin class file.
 *
 * @since 1.0.0
 */
if (!function_exists('pontifex_oi_fetch_soap_data_handler')) {
    function pontifex_oi_fetch_soap_data_handler() {
        // Step 1: Security Check - Verify user and nonce.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Geen toegang.', 'pontifex-oi')]);
        }

        // Verify the security token (nonce) to prevent CSRF attacks.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'pontifex_oi_admin')) {
            wp_send_json_error(['message' => __('Ongeldige beveiligingstoken.', 'pontifex-oi')]);
        }

        try {
            // Step 2: Load the necessary class.
            if (!class_exists('\PontifexOI\Api\SoapClient')) {
                require_once dirname(__FILE__, 2) . '/api/class-soap-client.php';
            }

            // Step 3: Execute the SOAP request.
            $soap = new \PontifexOI\Api\SoapClient();
            $result = $soap->fetchAndStorePlanning();

            // Step 4: Return the appropriate JSON response.
            if ($result === true) {
                wp_send_json_success(['message' => __('Gegevens succesvol opgehaald en opgeslagen.', 'pontifex-oi')]);
            } else {
                wp_send_json_error(['message' => __('Ophalen is mislukt, geen data ontvangen.', 'pontifex-oi')]);
            }

        } catch (\Exception $e) {
            // Step 5: Handle and report any exceptions.
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        // Always die at the end of an AJAX handler.
        wp_die();
    }
}