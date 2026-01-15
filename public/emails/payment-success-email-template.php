<?php
namespace PontifexOI\PublicPart;
if (!defined('ABSPATH')) exit;
// Zorg dat product/prijsdata beschikbaar is voor MailHelpers::get_material_label
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';
use PontifexOI\Helpers\MailHelpers;

// Variabelen van de bestelling ophalen en opschonen
$candidate_fullnames = (array) ($order['candidate_fullname'] ?? []);
$candidate_infixes = (array) ($order['candidate_infix'] ?? []);
$candidate_lastnames = (array) ($order['candidate_lastname'] ?? []);
$candidate_birthdates = (array) ($order['candidate_birthdate'] ?? []);
$order_initials = $order['order_initials'] ?? '';
$order_infix = $order['order_infix'] ?? '';
$order_lastname = $order['order_lastname'] ?? '';
$order_postcode = $order['order_postcode'] ?? '';
$order_housenumber = $order['order_housenumber'] ?? '';
$order_street = $order['order_street'] ?? '';
$order_city = $order['order_city'] ?? '';
$order_phone = $order['order_phone'] ?? '';
$order_email = $order['order_email'] ?? '';
$order_company = $order['order_company'] ?? '';
$order_vat = $order['order_vat'] ?? '';
$order_function = $order['order_function'] ?? '';
$exam_type = $order['exam_type'] ?? '';
$language_label = $order['language_label'] ?? '';
$material_label = $order['material_label'] ?? '';
$location = $order['location'] ?? '';
$date = $order['date'] ?? '';
$time = $order['time'] ?? '';
$amount = $order['amount'] ?? count($candidate_fullnames);
$price = $order['price'] ?? '';
$extra_material = (array) ($order['extra_material'] ?? []);

$normalized_exam = function_exists('pontifex_normalize_exam_key') ? pontifex_normalize_exam_key($exam_type) : $exam_type;

// Opgeschoonde logica
$is_weekend_cursus =
    in_array('cursus-weekend', $extra_material, true)
    && in_array($normalized_exam, ['los-examen-vca-basis','los-examen-vca-vol'], true);

// Fallback: bereken totaal (INCL. btw) + btw als 'price'/'vat_total' ontbreken
if ($price === '' || $price === null || !isset($order['vat_total'])) {
    if (class_exists('\PontifexOI\Helpers\PaymentHelpers') && method_exists('\PontifexOI\Helpers\PaymentHelpers','calculate_totals_with_vat')) {
        $totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat($order);
        if ($price === '' || $price === null) {
            // Als de prijs ontbreekt, stel deze in op het berekende totaal (incl. BTW)
            $price = '€' . number_format((float)$totals['incl'], 2, ',', '.');
        }
        if (!isset($order['vat_total'])) {
            // Als de BTW ontbreekt, stel deze in op het berekende BTW-totaal
            $order['vat_total'] = $totals['vat_total'];
        }
    }
}

$has_company = !empty($order_company);
$has_function = !empty($order_function);
$has_vat = !empty($order_vat);
$email_border = ($has_company || $has_function || $has_vat) ? '1px solid #ddd' : 'none';
$company_border = ($has_function || $has_vat) ? '1px solid #ddd' : 'none';
$function_border = $has_vat ? '1px solid #ddd' : 'none';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
   <title>Bedankt voor je inschrijving</title>
    <style>
        /* Global Reset & Base Styles */
        body { margin:0; padding:0; background:#f0f0f0; font-family:'Poppins', 'Open Sans', Arial, sans-serif; color:#333; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; border-collapse:collapse; }
        img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }
        /* Typography */
        h2 { color:#FF9900; font-weight:700; margin:0 0 15px 0; font-size:24px; line-height:1.2; }
        h3 { color:#000; font-weight:600; margin:20px 0 10px 0; font-size:18px; line-height:1.3; } /* Standaard h3, wordt later overschreven door inline style */
        p { color:#555; font-size:15px; line-height:1.6; margin:0 0 20px 0; }
        
        /* Layout & Structure */
        .content-container { background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.08); padding:40px 0; }
        .header-logo { padding:30px 24px 0 24px; text-align:left; }
        .header-logo img { width:120px; height:auto; display:inline-block; }
        .data-table-row td { padding:8px; border-bottom:1px solid #ddd; }
        .data-table-label { width:40%; font-size:14px; color:#333; }
        .data-table-value { font-size:14px; color:#333; }
        /* Utility */
        .apple-link a { color:inherit !important; text-decoration:none !important; }
        .btn table { width:auto !important; }
        .btn a { background:#FF9900; color:#fff !important; padding:12px 28px; border-radius:4px; text-decoration:none; font-weight:bold; display:inline-block; }
        
        /* Responsive */
        @media only screen and (max-width:600px) {
            .container { width:100% !important; min-width:100% !important; margin:20px auto !important; }
            .stack, .stack td { display:block !important; width:100% !important; max-width:100% !important; }
            .mobile-padding { padding:16px !important; }
            .mobile-center { text-align:center !important; }
            h2, h3 { text-align:center !important; }
            /* Zorg dat de inline-block kolommen stapelen op mobiel */
            .column-wrap { display:block !important; width:100% !important; max-width:100% !important; padding-right:0 !important; padding-left:0 !important; }
            .col-inner { padding:0 !important; } /* Verwijder de padding die voor de desktop lay-out is toegevoegd */
        }
    </style>
</head>
<body style="margin:0; padding:40px 0; background:#f0f0f0;">
    <center style="width:100%; background:#f0f0f0;">
        <table role="presentation" class="container content-container" width="700" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:50px auto; max-width:700px; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.08); width:100%; padding:40px;">
            <tr>
                <td class="header-logo">
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" style="display:inline-block;">
                        <img src="https://test1.certipro.nl/wp-content/uploads/2025/07/Logo-1-4.png"
                             alt="CertiPro"
                             width="120"
                             style="display:block; height:auto; border:0; outline:none; text-decoration:none;">
                    </a>
                </td>
            </tr>
            
            <tr>
                <td style="padding:24px;" class="mobile-padding">
                    <!-- TITEL: Nieuwe inschrijving (Oranje) -->
                    <h2 style="color:#FF9900;">Nieuwe inschrijving</h2>
                    <!-- TEKST voor interne notificatie -->
                    <p>
                        Bedankt voor je inschrijving.
                        Er zal meer informatie volgen via mail. Hier een overzicht van je inschrijving.
                    </p>
                    <p style="margin-top:20px;">Met vriendelijke groet,<br>CertiPro</p>
                    
                    <!-- Gegevens kandidaten - Oranje titel -->
                    <h3 style="margin-top:30px; color:#FF9900;">
                        <?php echo count($candidate_fullnames) > 1 ? 'Gegevens kandidaten' : 'Gegevens kandidaat'; ?>
                    </h3>
                    <?php foreach ($candidate_fullnames as $idx => $candidate_fullname): ?>
                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px; background:#f9f9f9; border-radius:6px; border-collapse:collapse;">
                        <tr>
                            <!-- Label font-weight:600 -->
                            <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Naam en achternaam:</td>
                            <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                <?php echo htmlspecialchars($candidate_fullname); ?>
                            </td>
                        </tr>
                        <tr>
                            <!-- Label font-weight:600 -->
                            <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Tussenvoegsel (optioneel):</td>
                            <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                <?php echo htmlspecialchars($candidate_infixes[$idx] ?? ''); ?>
                            </td>
                        </tr>
                        <tr>
                            <!-- Label font-weight:600 -->
                            <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Achternaam:</td>
                            <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                <?php echo htmlspecialchars($candidate_lastnames[$idx] ?? ''); ?>
                            </td>
                        </tr>
                        <tr>
                            <!-- Label font-weight:600 -->
                            <td style="padding:8px; font-weight:600; color:#000; border-bottom:none;">Geboortedatum:</td>
                            <td style="padding:8px; color:#333; border-bottom:none;">
                                <?php echo htmlspecialchars($candidate_birthdates[$idx] ?? ''); ?>
                            </td>
                        </tr>
                    </table>
                    <?php endforeach; ?>
                    
                    <!-- TABLE-BASED LAYOUT for EMAIL CLIENTS -->
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:30px;">
                        <tr>
                            <!-- Inschrijvingsgegevens Kolom -->
                            <td width="50%" valign="top" style="padding-right:10px;">
                                <!-- Inschrijvingsgegevens - Oranje titel -->
                                <h3 style="margin:0 0 10px 0; color:#FF9900;">Inschrijvingsgegevens</h3>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                             style="background:#f9f9f9; border-radius:6px; border-collapse:collapse; width:100%;">
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Naam:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars(trim($order_initials . ' ' . $order_infix . ' ' . $order_lastname)); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Straat:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($order_street); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Huisnummer:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($order_housenumber); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Postcode:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($order_postcode); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Plaats:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($order_city); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Telefoonnummer:</td><td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($order_phone); ?></td></tr>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:<?php echo $email_border; ?>;">E-mailadres:</td><td style="padding:8px; color:#333; border-bottom:<?php echo $email_border; ?>;"><?php echo htmlspecialchars($order_email); ?></td></tr>
                                    <?php if (!empty($order_company)) : ?>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:<?php echo $company_border; ?>;">Bedrijfsnaam:</td><td style="padding:8px; color:#333; border-bottom:<?php echo $company_border; ?>;"><?php echo htmlspecialchars($order_company); ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($order_function)) : ?>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:<?php echo $function_border; ?>;">Functie:</td><td style="padding:8px; color:#333; border-bottom:<?php echo $function_border; ?>;"><?php echo htmlspecialchars($order_function); ?></td></tr>
                                    <?php endif; ?>
                                    <?php if (!empty($order_vat)) : ?>
                                    <tr><td style="padding:8px; font-weight:bold; color:#000; border-bottom:none;">BTW-nummer:</td><td style="padding:8px; color:#333; border-bottom:none;"><?php echo htmlspecialchars($order_vat); ?></td></tr>
                                    <?php endif; ?>
                                </table>
                            </td>
                            <!-- Betaalgegevens Kolom (UPDATED) -->
                            <td width="50%" valign="top" style="padding-left:10px;">
                                <!-- Betaalgegevens - Oranje titel -->
                                <h3 style="margin:0 0 10px 0; color:#FF9900;">Betaalgegevens</h3>
                                <?php
                                // Detecteer Flow 2 (directe optie) met de schone logica
                                $is_flow2 = isset($order['extra_option_direct']) && !empty($order['extra_option_direct']);
                                ?>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                             style="background:#f9f9f9; border-radius:6px; border-collapse:collapse; width:100%;">
                                    <?php if (!$is_flow2): // FLOW 1: Normaal examen/cursus - UPDATED ?>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Examen:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                            <?php echo htmlspecialchars($order['exam_label'] ?? $exam_type ?? '-'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Lesmateriaal:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                            <?php echo htmlspecialchars($order['material_label'] ?? $material_label ?? '-'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Taal:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($language_label); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Locatie:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($location); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Datum:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($date); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Tijd:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($time); ?></td>
                                    </tr>
                                    <?php else: // FLOW 2: Weekendcursus / directe link (Gebruikt de nieuwe logica) ?>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Examen:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                            <?php echo str_starts_with($order['extra_option_direct'], 'cursus-weekend') ? 'Weekendcursus met examen' : (strpos($order['extra_option_direct'], 'vol') !== false ? 'VCA Vol' : 'VCA Basis'); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Lesmateriaal:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                            <?php
                                            $labels = [];
                                            // $EXTRA_PRODUCTS is gedefinieerd in de includes/config/producten-prijzen.php include
                                            foreach (($order['extra_material'] ?? []) as $opt_id) {
                                                if (isset($EXTRA_PRODUCTS[$opt_id])) {
                                                    $labels[] = $EXTRA_PRODUCTS[$opt_id]['label'];
                                                }
                                            }
                                            echo !empty($labels) ? implode(' + ', $labels) : 'Examen';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Taal:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo strtoupper($language_label ?: 'NL'); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <!-- Rijen die voor beide flows gelden -->
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">Kandidaten:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($amount); ?></td>
                                    </tr>
                                    <!-- DEZE RIJ IS AANGEPAST OM VAT_TOTAL TE GEBRUIKEN EN CORRECT TE FORMATTEREN -->
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:1px solid #ddd;">BTW:</td>
                                        <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;">
                                            <?php
                                                $vat_value = $order['vat_total'] ?? $order['vat'] ?? '';
                                                echo $vat_value !== '' ? '€' . number_format((float)$vat_value, 2, ',', '.') : '€0,00';
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px; font-weight:bold; color:#000; border-bottom:none;">Totaal:</td>
                                        <td style="padding:8px; color:#333; border-bottom:none;"><?php echo htmlspecialchars($price); ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </center>
</body>
</html>