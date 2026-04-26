<?php
// Zorg dat WordPress niet direct toegang geeft
defined('ABSPATH') || exit;

// Haal huidige waarden op
$soap_url   = esc_attr(get_option('pontifex_oi_soap_url', 'https://staging-webservice.pontifexcertificatie.nl/service.php/?wsdl'));
$user_id    = esc_attr(get_option('pontifex_oi_soap_user_id', ''));
$company_id = esc_attr(get_option('pontifex_oi_soap_company_id', ''));
$hash        = esc_attr(get_option('pontifex_oi_soap_hash', ''));
?>

<div class="pontifex-admin-wrap">
  <div class="pontifex-admin-card">
    <form method="post" action="options.php" autocomplete="off" id="pontifex-soap-form">
      <?php settings_fields('pontifex_oi_soap_group'); ?>

      <?php if (isset($_GET['settings-updated']) && wp_validate_boolean(wp_unslash($_GET['settings-updated']))) : ?>
        <div id="message"
             class="updated notice notice-success is-dismissible"
             style="margin: 0 0 1rem 0 !important; width:89%;">
          <p><?php esc_html_e('Instellingen zijn opgeslagen.', 'pontifex-oi'); ?></p>
        </div>
      <?php endif; ?>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_url" class="pontifex-admin-label"><?php esc_html_e('Soap API URL', 'pontifex-oi'); ?></label>
        <input type="text"
               id="pontifex_oi_soap_url"
               name="pontifex_oi_soap_url"
               class="pontifex-admin-input"
               value="<?php echo $soap_url; ?>" />
        <div class="pontifex-admin-desc">
          <?php esc_html_e('De URL naar de SOAP-webservice (staging of productie).', 'pontifex-oi'); ?>
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_user_id" class="pontifex-admin-label"><?php esc_html_e('User Identifier', 'pontifex-oi'); ?></label>
        <input type="text"
               id="pontifex_oi_soap_user_id"
               name="pontifex_oi_soap_user_id"
               class="pontifex-admin-input"
               value="<?php echo $user_id; ?>" />
        <div class="pontifex-admin-desc">
          <?php esc_html_e('Je user ID voor authenticatie.', 'pontifex-oi'); ?>
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_company_id" class="pontifex-admin-label"><?php esc_html_e('Company Identifier', 'pontifex-oi'); ?></label>
        <input type="text"
               id="pontifex_oi_soap_company_id"
               name="pontifex_oi_soap_company_id"
               class="pontifex-admin-input"
               value="<?php echo $company_id; ?>" />
        <div class="pontifex-admin-desc">
          <?php esc_html_e('Je company ID voor authenticatie.', 'pontifex-oi'); ?>
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_hash" class="pontifex-admin-label"><?php esc_html_e('Hash', 'pontifex-oi'); ?></label>
        <input type="password"
               id="pontifex_oi_soap_hash"
               name="pontifex_oi_soap_hash"
               class="pontifex-admin-input"
               value="<?php echo $hash; ?>" />
        <div class="pontifex-admin-desc">
          <?php esc_html_e('SHA256 hash van user ID + company ID.', 'pontifex-oi'); ?>
        </div>
      </div>

      <div class="pontifex-admin-btn-row">
        <button type="submit" class="pontifex-admin-submit">
          <?php esc_html_e('Gegevens opslaan', 'pontifex-oi'); ?>
        </button>
        <button type="button"
                id="pontifex-soap-fetch-btn"
                class="pontifex-admin-submit">
          <?php esc_html_e('Haal gegevens op', 'pontifex-oi'); ?>
        </button>
      </div>
      <div id="pontifex-soap-fetch-feedback"></div>
    </form>
  </div>
</div>
