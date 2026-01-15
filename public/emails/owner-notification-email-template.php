<?php
namespace PontifexOI\PublicPart;
if (!defined('ABSPATH')) exit;

// Zorg dat product/prijsdata beschikbaar is voor MailHelpers::get_material_label
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

use PontifexOI\Helpers\MailHelpers;

// Variabelen van de bestelling ophalen en opschonen
$candidate_fullnames   = (array) ($order['candidate_fullname'] ?? []);
$candidate_infixes     = (array) ($order['candidate_infix'] ?? []);
$candidate_lastnames   = (array) ($order['candidate_lastname'] ?? []);
$candidate_birthdates  = (array) ($order['candidate_birthdate'] ?? []);
$order_initials        = $order['order_initials'] ?? '';
$order_infix           = $order['order_infix'] ?? '';
$order_lastname        = $order['order_lastname'] ?? '';
$order_postcode        = $order['order_postcode'] ?? '';
$order_housenumber     = $order['order_housenumber'] ?? '';
$order_street          = $order['order_street'] ?? '';
$order_city            = $order['order_city'] ?? '';
$order_phone           = $order['order_phone'] ?? '';
$order_email           = $order['order_email'] ?? '';
$order_company         = $order['order_company'] ?? '';
$order_vat             = $order['order_vat'] ?? '';
$order_function        = $order['order_function'] ?? '';
$exam_type             = $order['exam_type'] ?? '';
$language_label        = $order['language_label'] ?? '';
$material_label        = $order['material_label'] ?? '';
$location              = $order['location'] ?? '';
$date                  = $order['date'] ?? '';
$time                  = $order['time'] ?? '';
$amount                = $order['amount'] ?? count($candidate_fullnames);
$price                 = $order['price'] ?? '';
$extra_material        = (array) ($order['extra_material'] ?? []);
$extras                = (array) ($order['extra_material'] ?? []);

$normalized_exam = function_exists('pontifex_normalize_exam_key') ? pontifex_normalize_exam_key($exam_type) : $exam_type;

// Fallback totaalprijs + btw als die ontbreken (gebruikt alleen de centrale berekening)
if ($price === '' || $price === null || !isset($order['vat_total'])) {
    if (class_exists('\PontifexOI\Helpers\PaymentHelpers') && method_exists('\PontifexOI\Helpers\PaymentHelpers', 'calculate_totals_with_vat')) {
        $totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat($order);
        if ($price === '' || $price === null) {
            $price = '€' . number_format((float)$totals['incl'], 2, ',', '.');
        }
        if (!isset($order['vat_total'])) {
            $order['vat_total'] = $totals['vat_total'] ?? 0;
        }
    }
}

// Dynamische border logica
$has_company  = !empty($order_company);
$has_function = !empty($order_function);
$has_vat      = !empty($order_vat);

$email_border   = ($has_company || $has_function || $has_vat) ? '1px solid #ddd' : 'none';
$company_border = ($has_function || $has_vat) ? '1px solid #ddd' : 'none';
$function_border = $has_vat ? '1px solid #ddd' : 'none';

// Logica voor Flow 2 (directe link/weekendcursus)
$is_flow2 = isset($order['extra_option_direct']) && !empty($order['extra_option_direct']);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="x-apple-disable-message-reformatting">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light dark">
<title>Nieuwe inschrijving</title>
<style>
/* Global Reset & Base Styles */
body { margin:0; padding:0; background:#f0f0f0; font-family:'Poppins', 'Open Sans', Arial, sans-serif; color:#333; }
table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; border-collapse:collapse; }
img { -ms-interpolation-mode:bicubic; border:0; outline:none; text-decoration:none; }
/* Typography */
h2 { font-weight:700; margin:0 0 15px 0; font-size:24px; line-height:1.2; }
h3 { font-weight:600; margin:20px 0 10px 0; font-size:18px; line-height:1.3; color:#000; }
p { color:#555; font-size:15px; line-height:1.6; margin:0 0 20px 0; }
/* Layout & Structure */
.content-container { background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.08); padding:40px 0; }
.header-logo { padding:30px 24px 0 24px; text-align:left; }
.header-logo img { width:120px; height:auto; display:inline-block; }
/* Utility & Color */
.apple-link a { color:inherit !important; text-decoration:none !important; }
.text-primary { color: #FF9900; }
.bg-light { background: #f9f9f9; }
.border-subtle { border-bottom: 1px solid #ddd; }
/* Responsive */
@media only screen and (max-width:600px) {
    .container { width:100% !important; min-width:100% !important; margin:20px auto !important; }
    .stack, .stack td { display:block !important; width:100% !important; max-width:100% !important; }
    .mobile-padding { padding:16px !important; }
    .mobile-center { text-align:center !important; }
    h2, h3 { text-align:center !important; }
    .stack-padding-right { padding-right:0 !important; }
    .stack-padding-left { padding-left:0 !important; padding-top:20px !important; }
    .stack-table-margin { margin-bottom: 20px; }
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
            <h2 style="color:#FF9900;">Nieuwe inschrijving</h2>
            <p>
                Er is een nieuwe inschrijving van
                <strong>
                    <?php echo htmlspecialchars(trim(
                        ($candidate_fullnames[0] ?? '') . ' ' .
                        ($candidate_infixes[0] ?? '') . ' ' .
                        ($candidate_lastnames[0] ?? '')
                    )); ?>
                </strong>.<br>
                Hieronder vind je de details van de inschrijving.
            </p>

            <h3 style="margin-top:30px; color:#FF9900;">
                <?php echo count($candidate_fullnames) > 1 ? 'Gegevens kandidaten' : 'Gegevens kandidaat'; ?>
            </h3>

            <?php foreach ($candidate_fullnames as $idx => $candidate_fullname): ?>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px; background:#f9f9f9; border-radius:6px; border-collapse:collapse;">
                <tr>
                    <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Naam en achternaam:</td>
                    <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($candidate_fullname); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Tussenvoegsel (optioneel):</td>
                    <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($candidate_infixes[$idx] ?? ''); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px; font-weight:600; color:#000; border-bottom:1px solid #ddd;">Achternaam:</td>
                    <td style="padding:8px; color:#333; border-bottom:1px solid #ddd;"><?php echo htmlspecialchars($candidate_lastnames[$idx] ?? ''); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px; font-weight:600; color:#000; border-bottom:none;">Geboortedatum:</td>
                    <td style="padding:8px; color:#333; border-bottom:none;"><?php echo htmlspecialchars($candidate_birthdates[$idx] ?? ''); ?></td>
                </tr>
            </table>
            <?php endforeach; ?>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:30px;">
                <tr>
                    <td class="stack stack-padding-right" width="50%" valign="top" style="padding-right:10px;">
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

                    <td class="stack stack-padding-left" width="50%" valign="top" style="padding-left:10px;">
                        <?php $border_style = 'border-bottom:1px solid #ddd;'; ?>
                        <h3 style="margin:0 0 10px 0; color:#FF9900;">Betaalgegevens</h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                               style="background:#f9f9f9; border-radius:6px; border-collapse:collapse; width:100%;">
                            <?php if (!$is_flow2): // FLOW 1: Normaal examen/cursus ?>
                            <tr>
                                <td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Examen:</td>
                                <td style="padding:8px; color:#333; <?php echo $border_style; ?>">
                                    <?php echo htmlspecialchars($order['exam_label'] ?? MailHelpers::format_exam_label_for_summary($order)); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Lesmateriaal:</td>
                                <td style="padding:8px; color:#333; <?php echo $border_style; ?>">
                                    <?php echo htmlspecialchars($order['material_label'] ?? '-'); ?>
                                </td>
                            </tr>
                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Taal:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars($language_label); ?></td></tr>
                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Locatie:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars($location); ?></td></tr>
                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Datum:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars($date); ?></td></tr>
                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Tijd:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars($time); ?></td></tr>
                            <?php else: // FLOW 2: Weekendcursus / directe link ?>
                            <tr>
                                <td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Examen:</td>
                                <td style="padding:8px; color:#333; <?php echo $border_style; ?>">
                                    <?php
                                    $exam_label_flow2 = $order['exam_label'] ?? null;
                                    if (!$exam_label_flow2) {
                                        if (str_starts_with($order['extra_option_direct'] ?? '', 'cursus-weekend')) {
                                            $exam_label_flow2 = 'Weekendcursus met examen';
                                        } elseif (strpos($order['extra_option_direct'] ?? '', 'vol') !== false) {
                                            $exam_label_flow2 = 'VCA Vol';
                                        } else {
                                            $exam_label_flow2 = 'VCA Basis';
                                        }
                                    }
                                    echo htmlspecialchars($exam_label_flow2);
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Lesmateriaal:</td>
                                <td style="padding:8px; color:#333; <?php echo $border_style; ?>">
                                    <?php
                                    $labels = [];
                                    foreach (($order['extra_material'] ?? []) as $opt_id) {
                                        if (isset($EXTRA_PRODUCTS[$opt_id])) {
                                            $labels[] = $EXTRA_PRODUCTS[$opt_id]['label'];
                                        }
                                    }
                                    echo !empty($labels) ? htmlspecialchars(implode(' + ', $labels)) : 'Geen lesmateriaal';
                                    ?>
                                </td>
                            </tr>
                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Taal:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars(strtoupper($language_label ?: 'NL')); ?></td></tr>
                            <?php endif; ?>

                            <tr><td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">Kandidaten:</td><td style="padding:8px; color:#333; <?php echo $border_style; ?>"><?php echo htmlspecialchars($amount); ?></td></tr>
                            <tr>
                                <td style="padding:8px; font-weight:bold; color:#000; <?php echo $border_style; ?>">BTW (21%):</td>
                                <td style="padding:8px; color:#333; <?php echo $border_style; ?>">
                                    <?php
                                    $vat_value = $order['vat_total'] ?? '';
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