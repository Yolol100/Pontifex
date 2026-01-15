<?php
/**
 * Pontifex OI - Nieuwe Inschrijving E-mail Template
 *
 * Wordt gebruikt als HTML-body voor notificatie-mails naar de organisatie.
 *
 * @package PontifexOI
 * @since   1.0.0
 */

defined('ABSPATH') || exit;

use PontifexOI\Helpers\MailHelpers;

// ──────────────────────────────────────────────────────────────────────────────
// 1. Vereiste configuratie laden
// ──────────────────────────────────────────────────────────────────────────────
require_once PONTIFEX_OI_PATH . 'includes/config/producten-prijzen.php';

// ──────────────────────────────────────────────────────────────────────────────
// 2. Alle variabelen veilig voorbereiden (null-safe + type casting)
// ──────────────────────────────────────────────────────────────────────────────
$candidate_fullnames   = (array) ($order['candidate_fullname']   ?? []);
$candidate_infixes     = (array) ($order['candidate_infix']     ?? []);
$candidate_lastnames   = (array) ($order['candidate_lastname']   ?? []);
$candidate_birthdates  = (array) ($order['candidate_birthdate']  ?? []);

$order_initials        = (string) ($order['order_initials']      ?? '');
$order_infix           = (string) ($order['order_infix']         ?? '');
$order_lastname        = (string) ($order['order_lastname']      ?? '');
$order_postcode        = (string) ($order['order_postcode']      ?? '');
$order_housenumber     = (string) ($order['order_housenumber']   ?? '');
$order_street          = (string) ($order['order_street']        ?? '');
$order_city            = (string) ($order['order_city']          ?? '');
$order_phone           = (string) ($order['order_phone']         ?? '');
$order_email           = (string) ($order['order_email']         ?? '');
$order_company         = (string) ($order['order_company']       ?? '');
$order_vat             = (string) ($order['order_vat']           ?? '');
$order_function        = (string) ($order['order_function']      ?? '');

$exam_type             = (string) ($order['exam_type']           ?? '');
$language_label        = (string) ($order['language_label']      ?? '');
$material_label        = (string) ($order['material_label']      ?? '');
$location              = (string) ($order['location']            ?? '');
$date                  = (string) ($order['date']                ?? '');
$time                  = (string) ($order['time']                ?? '');

$amount                = max(1, (int) ($order['amount'] ?? count($candidate_fullnames)));
$price                 = (string) ($order['price']               ?? '');
$extra_material        = (array) ($order['extra_material']       ?? []);
$extras                = $extra_material; // alias voor leesbaarheid

$normalized_exam = function_exists('pontifex_normalize_exam_key')
    ? pontifex_normalize_exam_key($exam_type)
    : $exam_type;

$is_flow2 = !empty($order['extra_option_direct']);

// ──────────────────────────────────────────────────────────────────────────────
// 3. Fallback prijs + BTW berekening (indien ontbreekt)
// ──────────────────────────────────────────────────────────────────────────────
if ($price === '' || !isset($order['vat_total'])) {
    if (class_exists('\PontifexOI\Helpers\PaymentHelpers') &&
        method_exists('\PontifexOI\Helpers\PaymentHelpers', 'calculate_totals_with_vat')) {
        $totals = \PontifexOI\Helpers\PaymentHelpers::calculate_totals_with_vat($order);
        $price = '€' . number_format((float)($totals['incl'] ?? 0), 2, ',', '.');
        $order['vat_total'] = $totals['vat_total'] ?? 0;
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. Border-logica voor conditiele rijen
// ──────────────────────────────────────────────────────────────────────────────
$has_company   = !empty($order_company);
$has_function  = !empty($order_function);
$has_vat       = !empty($order_vat);

$email_border   = ($has_company || $has_function || $has_vat)   ? '1px solid #ddd' : 'none';
$company_border = ($has_function || $has_vat)                   ? '1px solid #ddd' : 'none';
$function_border = $has_vat                                     ? '1px solid #ddd' : 'none';
?>

<!DOCTYPE html>
<html lang="nl" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="x-apple-disable-message-reformatting">
    <title><?php esc_html_e('Nieuwe inschrijving', 'pontifex-oi'); ?></title>

    <style type="text/css">
        /* Reset & base */
        body { margin:0; padding:0; background:#f0f0f0; font-family:'Poppins',Arial,'Helvetica Neue',Helvetica,sans-serif; color:#333333; }
        table, td { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }
        img { border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; }

        /* Typography */
        h2 { font-size:24px; line-height:1.3; font-weight:700; margin:0 0 16px; color:#FF9900; }
        h3 { font-size:18px; line-height:1.4; font-weight:600; margin:24px 0 12px; color:#FF9900; }
        p  { font-size:15px; line-height:1.6; margin:0 0 20px; color:#555555; }

        /* Containers */
        .container       { max-width:700px; margin:40px auto; background:#ffffff; border-radius:8px; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
        .content-padding { padding:32px 24px; }
        .header-logo     { padding:32px 24px 16px; text-align:left; }
        .header-logo img { width:140px; height:auto; display:block; }

        /* Colors & utils */
        .text-primary  { color:#FF9900 !important; }
        .bg-light      { background-color:#f9f9f9; }
        .border-bottom { border-bottom:1px solid #dddddd; }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .container       { width:100% !important; margin:20px auto !important; }
            .content-padding { padding:20px !important; }
            .stack           { display:block !important; width:100% !important; }
            .stack td        { display:block !important; width:100% !important; padding-right:0 !important; }
            h2, h3           { text-align:center !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; background:#f0f0f0;">

<center style="width:100%; background:#f0f0f0; padding:40px 0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="container" style="max-width:700px; background:#ffffff; border-radius:8px;">
        <!-- Header / Logo -->
        <tr>
            <td class="header-logo">
                <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener">
                    <img src="https://test1.certipro.nl/wp-content/uploads/2025/07/Logo-1-4.png"
                         alt="CertiPro Logo"
                         width="140"
                         style="display:block; max-width:140px; height:auto;">
                </a>
            </td>
        </tr>

        <!-- Hoofdcontent -->
        <tr>
            <td class="content-padding">
                <h2><?php esc_html_e('Nieuwe inschrijving', 'pontifex-oi'); ?></h2>

                <p>
                    <?php esc_html_e('Er is een nieuwe inschrijving binnengekomen van', 'pontifex-oi'); ?>
                    <strong>
                        <?php
                        $first_candidate = trim(
                            ($candidate_fullnames[0] ?? '') . ' ' .
                            ($candidate_infixes[0] ?? '') . ' ' .
                            ($candidate_lastnames[0] ?? '')
                        );
                        echo esc_html($first_candidate ?: __('een kandidaat', 'pontifex-oi'));
                        ?>
                    </strong>.
                    <?php esc_html_e('Hieronder de details:', 'pontifex-oi'); ?>
                </p>

                <!-- Kandidaten blok -->
                <h3>
                    <?php echo count($candidate_fullnames) > 1
                        ? esc_html__('Gegevens kandidaten', 'pontifex-oi')
                        : esc_html__('Gegevens kandidaat', 'pontifex-oi'); ?>
                </h3>

                <?php foreach ($candidate_fullnames as $i => $fullname): ?>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:20px; background:#f9f9f9; border-radius:6px;">
                        <tr>
                            <td width="180" style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                <?php esc_html_e('Volledige naam', 'pontifex-oi'); ?>:
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #ddd;">
                                <?php echo esc_html(trim($fullname)); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                <?php esc_html_e('Tussenvoegsel', 'pontifex-oi'); ?>:
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #ddd;">
                                <?php echo esc_html($candidate_infixes[$i] ?? '—'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                <?php esc_html_e('Achternaam', 'pontifex-oi'); ?>:
                            </td>
                            <td style="padding:10px; border-bottom:1px solid #ddd;">
                                <?php echo esc_html($candidate_lastnames[$i] ?? '—'); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:10px; font-weight:600; color:#000000; border-bottom:none;">
                                <?php esc_html_e('Geboortedatum', 'pontifex-oi'); ?>:
                            </td>
                            <td style="padding:10px; border-bottom:none;">
                                <?php echo esc_html($candidate_birthdates[$i] ?? '—'); ?>
                            </td>
                        </tr>
                    </table>
                <?php endforeach; ?>

                <!-- Hoofd inschrijver + Betaalgegevens -->
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
                    <tr>
                        <!-- Linker kolom: Persoonsgegevens -->
                        <td width="50%" valign="top" class="stack" style="padding-right:12px;">
                            <h3><?php esc_html_e('Inschrijvingsgegevens', 'pontifex-oi'); ?></h3>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9f9f9; border-radius:6px;">
                                <tr>
                                    <td width="160" style="padding:10px; font-weight:600; color:#000000; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php esc_html_e('Naam', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php echo esc_html(trim("$order_initials $order_infix $order_lastname")); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                        <?php esc_html_e('Adres', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                        <?php echo esc_html(trim("$order_street $order_housenumber")); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                        <?php esc_html_e('Postcode & Plaats', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                        <?php echo esc_html("$order_postcode $order_city"); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php esc_html_e('Telefoon', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php echo esc_html($order_phone); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php esc_html_e('E-mail', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:<?php echo esc_attr($email_border); ?>">
                                        <?php echo esc_html($order_email); ?>
                                    </td>
                                </tr>

                                <?php if ($has_company): ?>
                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000; border-bottom:<?php echo esc_attr($company_border); ?>">
                                            <?php esc_html_e('Bedrijfsnaam', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:<?php echo esc_attr($company_border); ?>">
                                            <?php echo esc_html($order_company); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($has_function): ?>
                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000; border-bottom:<?php echo esc_attr($function_border); ?>">
                                            <?php esc_html_e('Functie', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:<?php echo esc_attr($function_border); ?>">
                                            <?php echo esc_html($order_function); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($has_vat): ?>
                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000;">
                                            <?php esc_html_e('BTW-nummer', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333;">
                                            <?php echo esc_html($order_vat); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </td>

                        <!-- Rechter kolom: Bestelgegevens -->
                        <td width="50%" valign="top" class="stack" style="padding-left:12px;">
                            <h3><?php esc_html_e('Bestelgegevens', 'pontifex-oi'); ?></h3>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9f9f9; border-radius:6px;">
                                <?php if (!$is_flow2): // Normaal flow ?>
                                    <tr>
                                        <td width="160" style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                            <?php esc_html_e('Examen', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                            <?php echo esc_html($order['exam_label'] ?? MailHelpers::format_exam_label_for_summary($order) ?? '—'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                            <?php esc_html_e('Lesmateriaal', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                            <?php echo esc_html($material_label ?: '—'); ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                            <?php esc_html_e('Taal', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                            <?php echo esc_html($language_label ?: '—'); ?>
                                        </td>
                                    </tr>
                                <?php else: // Flow 2 (weekend/direct) ?>
                                    <tr>
                                        <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                            <?php esc_html_e('Type', 'pontifex-oi'); ?>:
                                        </td>
                                        <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                            <?php
                                            $label = $order['exam_label'] ?? null;
                                            if (!$label) {
                                                $label = str_contains($order['extra_option_direct'] ?? '', 'cursus-weekend')
                                                    ? __('Weekendcursus met examen', 'pontifex-oi')
                                                    : __('VCA Basis/VOL', 'pontifex-oi');
                                            }
                                            echo esc_html($label);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                        <?php esc_html_e('Kandidaten', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                        <?php echo esc_html($amount); ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000; border-bottom:1px solid #ddd;">
                                        <?php esc_html_e('BTW', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; border-bottom:1px solid #ddd;">
                                        <?php
                                        $vat = $order['vat_total'] ?? 0;
                                        echo '€' . number_format((float)$vat, 2, ',', '.');
                                        ?>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:10px; font-weight:600; color:#000000;">
                                        <?php esc_html_e('Totaal incl. btw', 'pontifex-oi'); ?>:
                                    </td>
                                    <td style="padding:10px; color:#333333; font-weight:700;">
                                        <?php echo esc_html($price); ?>
                                    </td>
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