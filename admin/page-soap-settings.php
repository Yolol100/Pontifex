<?php
/**
 * Pontifex OI - SOAP Instellingen Pagina
 *
 * Beheert de SOAP webservice credentials en biedt een test-knop om gegevens op te halen.
 *
 * @package PontifexOI
 * @since   1.0.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// Haal de huidige waarden op (altijd string, veilige defaults)
$soap_url = esc_attr((string) get_option(
    'pontifex_oi_soap_url',
    'https://staging-webservice.pontifexcertificatie.nl/service.php/?wsdl'
));

$user_id = esc_attr((string) get_option('pontifex_oi_soap_user_id', ''));

$company_id = esc_attr((string) get_option('pontifex_oi_soap_company_id', ''));

$hash = esc_attr((string) get_option('pontifex_oi_soap_hash', ''));
?>

<div class="pontifex-admin-wrap">
    <div class="pontifex-admin-card">
        <h2><?php esc_html_e('SOAP Webservice Instellingen', 'pontifex-oi'); ?></h2>
        <p class="description">
            <?php esc_html_e('Vul hier de gegevens in die nodig zijn om te communiceren met de Pontifex certificatie SOAP-service.', 'pontifex-oi'); ?>
        </p>

        <form method="post" action="options.php" autocomplete="off" id="pontifex-soap-form">
            <?php settings_fields('pontifex_oi_soap_group'); ?>
            <?php do_settings_sections('pontifex_oi_soap_group'); ?>

            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div id="message"
                     class="notice notice-success is-dismissible pontifex-success-notice">
                    <p><?php esc_html_e('Instellingen succesvol opgeslagen.', 'pontifex-oi'); ?></p>
                </div>
            <?php endif; ?>

            <div class="pontifex-admin-row">
                <label for="pontifex_oi_soap_url" class="pontifex-admin-label required">
                    <?php esc_html_e('SOAP API URL', 'pontifex-oi'); ?>
                </label>
                <input type="url"
                       id="pontifex_oi_soap_url"
                       name="pontifex_oi_soap_url"
                       class="pontifex-admin-input regular-text code"
                       value="<?php echo $soap_url; ?>"
                       placeholder="https://.../service.php/?wsdl"
                       required />
                <p class="description">
                    <?php esc_html_e('De volledige URL naar de WSDL van de SOAP-service (staging of productie).', 'pontifex-oi'); ?>
                </p>
            </div>

            <div class="pontifex-admin-row">
                <label for="pontifex_oi_soap_user_id" class="pontifex-admin-label required">
                    <?php esc_html_e('User Identifier', 'pontifex-oi'); ?>
                </label>
                <input type="text"
                       id="pontifex_oi_soap_user_id"
                       name="pontifex_oi_soap_user_id"
                       class="pontifex-admin-input regular-text"
                       value="<?php echo $user_id; ?>"
                       required />
                <p class="description">
                    <?php esc_html_e('Je persoonlijke user ID voor authenticatie bij de SOAP-service.', 'pontifex-oi'); ?>
                </p>
            </div>

            <div class="pontifex-admin-row">
                <label for="pontifex_oi_soap_company_id" class="pontifex-admin-label required">
                    <?php esc_html_e('Company Identifier', 'pontifex-oi'); ?>
                </label>
                <input type="text"
                       id="pontifex_oi_soap_company_id"
                       name="pontifex_oi_soap_company_id"
                       class="pontifex-admin-input regular-text"
                       value="<?php echo $company_id; ?>"
                       required />
                <p class="description">
                    <?php esc_html_e('Het ID van het bedrijf/organisatie waarmee geauthenticeerd wordt.', 'pontifex-oi'); ?>
                </p>
            </div>

            <div class="pontifex-admin-row">
                <label for="pontifex_oi_soap_hash" class="pontifex-admin-label required">
                    <?php esc_html_e('Authenticatie Hash', 'pontifex-oi'); ?>
                </label>
                <input type="password"
                       id="pontifex_oi_soap_hash"
                       name="pontifex_oi_soap_hash"
                       class="pontifex-admin-input regular-text"
                       value="<?php echo $hash; ?>"
                       autocomplete="new-password"
                       required />
                <p class="description">
                    <?php esc_html_e('SHA256 hash van <code>user_id + company_id</code>. Wordt gebruikt voor beveiligde authenticatie.', 'pontifex-oi'); ?>
                </p>
            </div>

            <div class="pontifex-admin-btn-row">
                <?php submit_button(
                    __('Instellingen opslaan', 'pontifex-oi'),
                    'primary pontifex-admin-submit',
                    'submit',
                    false
                ); ?>

                <button type="button"
                        id="pontifex-soap-fetch-btn"
                        class="button button-secondary pontifex-admin-submit">
                    <span class="dashicons dashicons-cloud-saved"></span>
                    <?php esc_html_e('Testverbinding & haal gegevens op', 'pontifex-oi'); ?>
                </button>
            </div>

            <div id="pontifex-soap-fetch-feedback"
                 class="pontifex-feedback-container"
                 aria-live="polite"></div>
        </form>
    </div>

    <div class="pontifex-admin-card pontifex-info-card" style="margin-top: 2rem;">
        <h3><?php esc_html_e('Belangrijke opmerkingen', 'pontifex-oi'); ?></h3>
        <ul class="pontifex-bullet-list">
            <li><?php esc_html_e('Gebruik altijd de productie-URL zodra je live gaat.', 'pontifex-oi'); ?></li>
            <li><?php esc_html_e('De hash moet exact overeenkomen met de berekening aan serverzijde.', 'pontifex-oi'); ?></li>
            <li><?php esc_html_e('Test altijd eerst met de staging-omgeving voordat je live gaat.', 'pontifex-oi'); ?></li>
        </ul>
    </div>
</div>