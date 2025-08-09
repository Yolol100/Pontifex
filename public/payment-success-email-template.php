<?php
namespace PontifexOI\PublicPart;
if (!defined('ABSPATH')) exit;

require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

use PontifexOI\Helpers\MailHelpers;

$candidate_fullnames  = (array) ($order['candidate_fullname'] ?? []);
$candidate_infixes    = (array) ($order['candidate_infix'] ?? []);
$candidate_lastnames  = (array) ($order['candidate_lastname'] ?? []);
$candidate_birthdates = (array) ($order['candidate_birthdate'] ?? []);

$order_initials    = $order['order_initials'] ?? '';
$order_infix       = $order['order_infix'] ?? '';
$order_lastname    = $order['order_lastname'] ?? '';
$order_postcode    = $order['order_postcode'] ?? '';
$order_housenumber = $order['order_housenumber'] ?? '';
$order_street      = $order['order_street'] ?? '';
$order_city        = $order['order_city'] ?? '';
$order_phone       = $order['order_phone'] ?? '';
$order_email       = $order['order_email'] ?? '';
$order_company     = $order['order_company'] ?? '';
$order_vat         = $order['order_vat'] ?? '';

$exam_type      = $order['exam_type'] ?? '';
$exam_label     = $order['exam_label'] ?? '';
$language_label = $order['language_label'] ?? '';
$material_label = $order['material_label'] ?? '';

$location       = $order['location'] ?? '';
$date           = $order['date'] ?? '';
$time           = $order['time'] ?? '';
$amount         = $order['amount'] ?? count($candidate_fullnames);
$price          = $order['price'] ?? '';
$extra_material = (array) ($order['extra_material'] ?? []);

$is_weekend_cursus = in_array($exam_type, ['vca-basis-weekend','vca-vol-weekend'], true);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta name="color-scheme" content="light dark">
    <title>Betaling Succesvol</title>
    <style>
        body { margin:0; padding:0; background:#F0F0F0; }
        table, td { mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { -ms-interpolation-mode:bicubic; }
        .apple-link a { color:inherit !important; text-decoration:none !important; }
        @media only screen and (max-width: 600px) {
            .container { width:100% !important; min-width:100% !important; }
            .stack, .stack td { display:block !important; width:100% !important; max-width:100% !important; }
            .mobile-padding { padding:16px !important; }
            .mobile-center { text-align:center !important; }
        }
    </style>
</head>
<body style="background:#F0F0F0; margin:0; padding:0; font-family:'Open Sans', Arial, sans-serif;">
<center style="width:100%; background:#F0F0F0;">
<!--[if mso]><table role="presentation" width="700" align="center" cellpadding="0" cellspacing="0"><tr><td><![endif]-->
<table class="container" align="center" cellpadding="0" cellspacing="0" border="0" width="700" style="max-width:700px; margin:30px auto; background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); width:100%;">
    <tr>
        <td style="padding:32px 24px 24px 24px;" class="mobile-padding">
            <h2 style="font-size:24px; color:#333; margin:0 0 15px 0;">Betaling succesvol</h2>
            <p style="font-size:16px; color:#555; margin:0 0 20px 0;">
                Bedankt voor je inschrijving.<br>
                Je betaling is succesvol ontvangen. Een overzicht van je bestelling is naar je e-mailadres gestuurd.
            </p>

            <h3 style="font-size:18px; color:#FF9900; margin:20px 0 10px 0;">
                <?php echo count($candidate_fullnames) > 1 ? 'Gegevens kandidaten' : 'Gegevens kandidaat'; ?>
            </h3>

            <?php foreach ($candidate_fullnames as $idx => $candidate_fullname): ?>
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:18px; background:#f9f9f9; border-radius:6px;">
                <tr>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;" width="40%"><strong>Naam en achternaam:</strong></td>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;">
                        <?php echo htmlspecialchars($candidate_fullname); ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;"><strong>Tussenvoegsel (optioneel):</strong></td>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;">
                        <?php echo htmlspecialchars($candidate_infixes[$idx] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;"><strong>Achternaam:</strong></td>
                    <td style="font-size:14px; color:#333; padding:10px; border-bottom:1px solid #ddd;">
                        <?php echo htmlspecialchars($candidate_lastnames[$idx] ?? ''); ?>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:14px; color:#333; padding:10px;"><strong>Geboortedatum:</strong></td>
                    <td style="font-size:14px; color:#333; padding:10px;">
                        <?php echo htmlspecialchars($candidate_birthdates[$idx] ?? ''); ?>
                    </td>
                </tr>
            </table>
            <?php endforeach; ?>

            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <!-- Bestelgegevens -->
                    <td class="stack" width="48%" valign="top" style="padding-right:1%; vertical-align:top;">
                        <h3 style="font-size:18px; color:#444; margin:0 0 10px 0;">Bestelgegevens</h3>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9f9f9; border-radius:6px;">
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Naam:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars(trim($order_initials . ' ' . $order_infix . ' ' . $order_lastname)); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Postcode en huisnummer:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($order_postcode . ' ' . $order_housenumber); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Adres:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($order_street . ', ' . $order_city); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Telefoonnummer:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($order_phone); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>E-mailadres:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($order_email); ?>
                                </td>
                            </tr>
                            <?php if (!empty($order_company)) : ?>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Bedrijfsnaam:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($order_company); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($order_vat)) : ?>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px;"><strong>BTW-nummer:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px;">
                                    <?php echo htmlspecialchars($order_vat); ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
                    <td class="stack" width="4%" style="font-size:0; line-height:0;">&nbsp;</td>
                    <!-- Betaalgegevens -->
                    <td class="stack" width="48%" valign="top" style="padding-left:1%; vertical-align:top;">
                        <h3 style="font-size:18px; color:#444; margin:0 0 10px 0;">Betaalgegevens</h3>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9f9f9; border-radius:6px;">
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Examen:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($exam_label); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Taal:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($language_label); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Lesmateriaal:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo $is_weekend_cursus ? 'Niet van toepassing' : htmlspecialchars($material_label); ?>
                                </td>
                            </tr>
                            <?php if (!$is_weekend_cursus && !empty($extra_material)) : ?>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; vertical-align:top;"><strong>Extra lesmateriaal:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px;">
                                    <ul style="margin:0; padding-left:20px; color:#555;">
                                        <?php foreach ($extra_material as $mat_id) : ?>
                                            <li><?php echo htmlspecialchars(MailHelpers::get_material_label($mat_id)); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Locatie:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($location); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Datum:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($date); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Tijd:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($time); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;"><strong>Kandidaten:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px; border-bottom:1px solid #ddd;">
                                    <?php echo htmlspecialchars($amount); ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-size:14px; color:#333; padding:8px;"><strong>Totaal:</strong></td>
                                <td style="font-size:14px; color:#333; padding:8px;">
                                    <?php echo htmlspecialchars($price); ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
</center>
</body>
</html>