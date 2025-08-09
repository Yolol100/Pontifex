<?php
namespace PontifexOI\Helpers;

if (!defined('ABSPATH')) exit;

class MailHelpers
{
    // Helper om label te krijgen uit examen ID
    private static function get_exam_label($id) {
        $exam_labels = [
            'los-examen-vca-basis' => 'VCA Basis',
            'los-examen-vca-vol' => 'VCA Vol',
            'vca-basis-weekend' => 'VCA Basis Cursus Weekend',
            'vca-vol-weekend' => 'VCA Vol Cursus Weekend',
        ];
        return $exam_labels[$id] ?? $id;
    }

    // Helper om label te krijgen uit taal ID
    private static function get_language_label($id) {
        $language_labels = [
            'nl' => 'Nederlands',
            'en' => 'Engels',
        ];
        return $language_labels[$id] ?? $id;
    }

    // Helper om label te krijgen uit materiaal-combinatie ID
    private static function get_material_combination_label($id) {
        $material_labels = [
            '1' => 'Los examen',
            '2' => 'Examen + boek',
            '4' => 'Examen + e-learning',
            '5' => 'Examen + proefexamens',
            '6' => 'Examen + boek + proefexamens',
            '7' => 'Examen + e-learning + proefexamens',
        ];
        return $material_labels[$id] ?? 'Geen keuze';
    }

    // Helper om label te krijgen uit materiaal ID
    private static function get_material_label($id) {
        global $MATERIAL_PRODUCTS;
        return $MATERIAL_PRODUCTS[$id]['label'] ?? $id;
    }

    /**
     * Stuur zowel de klantmail als de eigenaarsmail na een inschrijving/betaling,
     * inclusief een Excel-bijlage met alle inschrijvingsgegevens naar de eigenaar.
     * 
     * @param array $order  Alle ingevulde orderdata van de inschrijving.
     */
    public static function send_inschrijving_mails($order)
    {
        // Derive labels from IDs
        $order['exam_label'] = self::get_exam_label($order['exam_type'] ?? '');
        $order['language_label'] = self::get_language_label($order['language'] ?? '');
        $order['material_label'] = self::get_material_combination_label($order['material'] ?? '');

        // -----------------
        // KLANTMAIL (betaling is gelukt)
        // -----------------
        ob_start();
        include PONTIFEX_OI_PATH . 'public/payment-success-email-template.php';
        $klantmail = ob_get_clean();

        $to_klant = $order['order_email'] ?? '';
        $subject_klant = 'Betaling is gelukt';
        $headers_klant = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Certipro <info@certipro.nl>'
        ];

        wp_mail($to_klant, $subject_klant, $klantmail, $headers_klant);

        // -----------------
        // EIGENAAR/EIGENAARSMAIL (notificatie naar planning@certipro.nl) + Excel-bijlage
        // -----------------
        ob_start();
        include PONTIFEX_OI_PATH . 'public/owner-notification-email-template.php';
        $eigenaarmail = ob_get_clean();

        // Voor onderwerp naam + achternaam van eerste kandidaat (voor in de subject)
        $kandidaat_fullname = $order['candidate_fullname'][0] ?? '';
        $kandidaat_achternaam = $order['candidate_lastname'][0] ?? '';

        // Bouw de HTML table (Excel) als string
        $htmlTable = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';

        // Header kandidaatgegevens
        $htmlTable .= '<tr><th colspan="2" style="background:#ffc107;">Inschrijving kandidaatgegevens</th></tr>';

        // Kandidaten data (herhalen)
        foreach ($order['candidate_fullname'] as $index => $fullname) {
            $infix = $order['candidate_infix'][$index] ?? '';
            $lastname = $order['candidate_lastname'][$index] ?? '';
            $birthdate = $order['candidate_birthdate'][$index] ?? '';

            $htmlTable .= '<tr><td><strong>Naam en achternaam</strong></td><td>' . htmlspecialchars(trim("$fullname $infix $lastname")) . '</td></tr>';
            $htmlTable .= '<tr><td><strong>Geboortedatum</strong></td><td>' . htmlspecialchars($birthdate) . '</td></tr>';
            $htmlTable .= '<tr><td colspan="2">&nbsp;</td></tr>'; // lege rij voor spacing
        }

        // Bestelgegevens
        $htmlTable .= '<tr><th colspan="2" style="background:#007bff; color:#fff;">Bestelgegevens</th></tr>';
        $htmlTable .= '<tr><td>Naam</td><td>' . htmlspecialchars(trim($order['order_initials'] . ' ' . $order['order_infix'] . ' ' . $order['order_lastname'])) . '</td></tr>';
        $htmlTable .= '<tr><td>Postcode en huisnummer</td><td>' . htmlspecialchars($order['order_postcode'] . ' ' . $order['order_housenumber']) . '</td></tr>';
        $htmlTable .= '<tr><td>Adres</td><td>' . htmlspecialchars($order['order_street'] . ', ' . $order['order_city']) . '</td></tr>';
        $htmlTable .= '<tr><td>Telefoonnummer</td><td>' . htmlspecialchars($order['order_phone']) . '</td></tr>';
        $htmlTable .= '<tr><td>E-mailadres</td><td>' . htmlspecialchars($order['order_email']) . '</td></tr>';
        if (!empty($order['order_company'])) {
            $htmlTable .= '<tr><td>Bedrijfsnaam</td><td>' . htmlspecialchars($order['order_company']) . '</td></tr>';
        }
        if (!empty($order['order_vat'])) {
            $htmlTable .= '<tr><td>BTW-nummer</td><td>' . htmlspecialchars($order['order_vat']) . '</td></tr>';
        }

        // Betaalgegevens
        $htmlTable .= '<tr><th colspan="2" style="background:#28a745; color:#fff;">Betaalgegevens</th></tr>';
        $htmlTable .= '<tr><td>Examen</td><td>' . htmlspecialchars($order['exam_label']) . '</td></tr>';
        $htmlTable .= '<tr><td>Taal</td><td>' . htmlspecialchars($order['language_label']) . '</td></tr>';
        $htmlTable .= '<tr><td>Lesmateriaal</td><td>' . htmlspecialchars($order['material_label']) . '</td></tr>';
        $htmlTable .= '<tr><td>Locatie</td><td>' . htmlspecialchars($order['location'] ?? '') . '</td></tr>';
        $htmlTable .= '<tr><td>Datum</td><td>' . htmlspecialchars($order['date'] ?? '') . '</td></tr>';
        $htmlTable .= '<tr><td>Tijd</td><td>' . htmlspecialchars($order['time'] ?? '') . '</td></tr>';
        $htmlTable .= '<tr><td>Aantal kandidaten</td><td>' . count($order['candidate_fullname']) . '</td></tr>';

        // Extra lesmateriaal
        if (!empty($order['extra_material']) && is_array($order['extra_material'])) {
            $extraLabels = [];
            foreach ($order['extra_material'] as $extraId) {
                $extraLabels[] = self::get_material_label($extraId);
            }
            if ($extraLabels) {
                $htmlTable .= '<tr><td>Extra lesmateriaal</td><td>' . htmlspecialchars(implode(', ', $extraLabels)) . '</td></tr>';
            }
        }

        $htmlTable .= '<tr><td>Totaal</td><td>' . htmlspecialchars($order['price'] ?? '') . '</td></tr>';

        $htmlTable .= '</table>';

        // Maak tijdelijk bestand aan (html als .xls extensie, Excel kan het openen)
        $upload_dir = wp_upload_dir();
        $filename = 'inschrijving-' . time() . '.xls';
        $filepath = $upload_dir['basedir'] . '/' . $filename;

        file_put_contents($filepath, $htmlTable);

        // Email headers + attachment via WordPress wp_mail
        $to_owner = 'planning@certipro.nl';
        $subject_owner = 'Nieuwe inschrijving van ' . trim($kandidaat_fullname . ' ' . $kandidaat_achternaam);
        $headers_owner = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Certipro <info@certipro.nl>'
        ];

        $attachments = [$filepath];

        wp_mail($to_owner, $subject_owner, $eigenaarmail, $headers_owner, $attachments);

        // Verwijder tijdelijk bestand na versturen
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}