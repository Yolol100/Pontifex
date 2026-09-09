<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) exit;

// Charset voor uitgaande pluginmail.
add_filter('wp_mail_charset', fn() => 'UTF-8');

// Log alleen foutcodes; WP_Error kan volledige maildata en ontvangers bevatten.
add_action('wp_mail_failed', function($wp_error) {
    $codes = is_wp_error($wp_error) ? implode(',', $wp_error->get_error_codes()) : 'unknown';
    error_log('[Pontifex OI Plugin] Mail failed; codes: ' . $codes);
});

require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

class MailHelpers
{
    // Labels voor examens, talen en materiaal
    public static function get_exam_label($id) {
        $exam_labels = [
            'los-examen-vca-basis' => 'VCA Basis',
            'los-examen-vca-vol'   => 'VCA Vol',
            'vca-basis-weekend'    => 'VCA Basis Cursus Weekend',
            'vca-vol-weekend'      => 'VCA Vol Cursus Weekend',
        ];
        return $exam_labels[$id] ?? $id;
    }

    public static function get_language_label($id) {
        $language_labels = ['nl' => 'Nederlands', 'en' => 'Engels'];
        return $language_labels[$id] ?? $id;
    }

    public static function get_material_combination_label($id) {
        $material_labels = [
            '1' => 'Examen',
            '2' => 'Examen + boek',
            '4' => 'Examen + e-learning',
            '5' => 'Examen + proefexamens',
            '6' => 'Examen + boek + proefexamens',
            '7' => 'Examen + e-learning + proefexamens',
        ];
        return $material_labels[$id] ?? 'Geen keuze';
    }

    public static function get_material_label($id)
    {
        global $MATERIAL_PRODUCTS;
        return $MATERIAL_PRODUCTS[$id]['label'] ?? $id;
    }

    /**
     * Stuur bevestigingsmails zonder gevoelige orderdata te loggen.
     */
    public static function send_inschrijving_mails($order): bool
    {
        error_log('[Pontifex OI] Mail dispatch started.');

        // --- Normaliseer formulier-keys ---
        $map = [
            'order_initials'   => $order['given-name']      ?? null,
            'order_infix'      => $order['additional-name'] ?? null,
            'order_lastname'   => $order['family-name']     ?? null,
            'order_postcode'   => $order['postal-code']     ?? null,
            'order_housenumber'=> $order['address-line2']   ?? null,
            'order_street'     => $order['street-address']  ?? null,
            'order_city'       => $order['address-level2']  ?? null,
            'order_phone'      => $order['tel']             ?? null,
            'order_email'      => $order['order_email']     ?? ($order['email'] ?? null),
            'order_company'    => $order['organization']    ?? null,
            'order_function'   => $order['organization-title'] ?? null,
            'order_vat'        => $order['order_vat'] ?? null,
        ];
        foreach ($map as $k => $v) {
            if (!isset($order[$k]) && $v !== null) $order[$k] = $v;
        }

        // --- Labels en extra opties ---
        $order['exam_label']     = $order['exam_label']     ?? self::get_exam_label($order['exam_type'] ?? '');
        $order['language_label'] = $order['language_label'] ?? self::get_language_label($order['language'] ?? '');
        $order['material_label'] = $order['material_label'] ?? self::get_material_combination_label($order['material'] ?? '');

        $extra = [];
        if (!empty($order['extra_material'])) $extra = array_merge($extra, (array) $order['extra_material']);
        if (!empty($order['extra_options']))  $extra = array_merge($extra, (array) $order['extra_options']);
        $order['extra_material'] = array_values(array_unique($extra));

        $order['_has_weekend'] = false;
        foreach ($order['extra_material'] as $id) {
            if (strpos($id, 'cursus-weekend') === 0) {
                $order['_has_weekend'] = true;
                break;
            }
        }

        /**
         * KLANTMAIL
         */
        ob_start();
        include PONTIFEX_OI_PATH . 'public/emails/payment-success-email-template.php';
        $klantmail = ob_get_clean();

        $customer_ok = true;
        $to_klant = $order['order_email'] ?? $order['email'] ?? '';
        if (!empty($to_klant)) {
            $subject_klant = 'Bevestiging inschrijving - ' . ($order['exam_label'] ?? 'VCA Examen');
            $headers_klant = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Certipro <info@certipro.nl>'
            ];
            $customer_ok = (bool) wp_mail($to_klant, $subject_klant, $klantmail, $headers_klant);
            if (!$customer_ok) {
                error_log('[Pontifex OI] Customer mail failed.');
            }
        }

        /**
         * EIGENAARSMAIL
         */
        ob_start();
        include PONTIFEX_OI_PATH . 'public/emails/owner-notification-email-template.php';
        $eigenaarmail = ob_get_clean();

        $candidate_fullname_arr = $order['candidate_fullname'] ?? [];
        $candidate_lastname_arr = $order['candidate_lastname'] ?? [];
        $kandidaat_fullname     = $candidate_fullname_arr[0] ?? '';
        $kandidaat_achternaam   = $candidate_lastname_arr[0] ?? '';

        $subject_owner = 'Nieuwe inschrijving van ' . trim($kandidaat_fullname . ' ' . $kandidaat_achternaam);
        $to_owner = 'planning@certipro.nl';
        $headers_owner = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Certipro <info@certipro.nl>'
        ];

        $owner_ok = (bool) wp_mail($to_owner, $subject_owner, $eigenaarmail, $headers_owner);
        if (!$owner_ok) {
            error_log('[Pontifex OI] Owner mail failed.');
        } else {
            error_log('[Pontifex OI] Owner mail sent successfully.');
        }

        return $customer_ok && $owner_ok;
    }

    public static function format_exam_label_for_summary($order)
    {
        $examRaw = $order['exam_type'] ?? '';
        $examKey = function_exists('pontifex_normalize_exam_key')
            ? pontifex_normalize_exam_key($examRaw)
            : $examRaw;

        global $EXAM_PRODUCTS;
        $label = $EXAM_PRODUCTS[$examKey]['label']
            ?? ($order['exam_label'] ?? 'Examen');

        $extrasAll = array_merge(
            (array) ($order['extra_material'] ?? []),
            (array) ($order['extra_options'] ?? [])
        );

        $weekend = false;
        foreach ($extrasAll as $eid) {
            if (strpos($eid, 'cursus-weekend') === 0) {
                $weekend = true;
                break;
            }
        }

        return $weekend ? ($label . ' met cursusweekend en examen') : ($label . ' met examen');
    }
}
