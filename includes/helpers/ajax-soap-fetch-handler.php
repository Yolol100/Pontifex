<?php
declare(strict_types=1);

/**
 * Handler voor het ophalen en opslaan van SOAP planning via AJAX in de admin.
 * Modernized for PHP 8.4+ Standards.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pontifex_oi_fetch_soap_data_handler')) {
    /**
     * AJAX handler voor het ophalen van SOAP data.
     */
    function pontifex_oi_fetch_soap_data_handler(): void {
        // Stap 1: Beveiligingscontrole - Controleer rechten.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Geen toegang.', 'pontifex-oi')]);
        }

        // Controleer de security token (nonce).
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce((string)$nonce, 'pontifex_oi_admin')) {
            wp_send_json_error(['message' => __('Ongeldige beveiligingstoken.', 'pontifex-oi')]);
        }

        try {
            // Stap 2: Laad de SOAP client via modern pathing.
            if (!class_exists('\PontifexOI\Api\SoapClient')) {
                require_once dirname(__DIR__, 1) . '/api/class-soap-client.php';
            }

            // Stap 3: Voer het SOAP-verzoek uit.
            $soap    = new \PontifexOI\Api\SoapClient();
            $updated = $soap->fetchAndStorePlanning();
            $count   = is_array($updated) ? count($updated) : 0;

            // Stap 4: Geef een heldere response terug zonder logging.
            if ($count === 0) {
                wp_send_json_error([
                    'message' => __('Ophalen gelukt, maar er zijn geen nieuwe of gewijzigde items (0).', 'pontifex-oi')
                ]);
            } else {
                wp_send_json_success([
                    'message' => sprintf(__('Planning opgehaald en verwerkt: %d items.', 'pontifex-oi'), $count),
                    'count'   => $count
                ]);
            }

        } catch (\Throwable $e) {
            // Gebruik Throwable voor 2026 standaarden om ook Errors op te vangen.
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        wp_die();
    }
}