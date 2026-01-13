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

      <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
        <div id="message"
             class="updated notice notice-success is-dismissible"
             style="margin: 0 0 1rem 0 !important; width:89%;">
          <p>Instellingen zijn opgeslagen.</p>
        </div>
      <?php endif; ?>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_url" class="pontifex-admin-label">Soap API URL</label>
        <input type="text"
               id="pontifex_oi_soap_url"
               name="pontifex_oi_soap_url"
               class="pontifex-admin-input"
               value="<?php echo $soap_url; ?>" />
        <div class="pontifex-admin-desc">
          De URL naar de SOAP-webservice (staging of productie).
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_user_id" class="pontifex-admin-label">User Identifier</label>
        <input type="text"
               id="pontifex_oi_soap_user_id"
               name="pontifex_oi_soap_user_id"
               class="pontifex-admin-input"
               value="<?php echo $user_id; ?>" />
        <div class="pontifex-admin-desc">
          Je user ID voor authenticatie.
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_company_id" class="pontifex-admin-label">Company Identifier</label>
        <input type="text"
               id="pontifex_oi_soap_company_id"
               name="pontifex_oi_soap_company_id"
               class="pontifex-admin-input"
               value="<?php echo $company_id; ?>" />
        <div class="pontifex-admin-desc">
          Je company ID voor authenticatie.
        </div>
      </div>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_soap_hash" class="pontifex-admin-label">Hash</label>
        <input type="password"
               id="pontifex_oi_soap_hash"
               name="pontifex_oi_soap_hash"
               class="pontifex-admin-input"
               value="<?php echo $hash; ?>" />
        <div class="pontifex-admin-desc">
          SHA256 hash van user ID + company ID.
        </div>
      </div>

      <div class="pontifex-admin-btn-row">
        <button type="submit" class="pontifex-admin-submit">
          Gegevens opslaan
        </button>
        <button type="button"
                id="pontifex-soap-fetch-btn"
                class="pontifex-admin-submit">
          Haal gegevens op
        </button>
      </div>
      <div id="pontifex-soap-fetch-feedback"></div>
    </form>
  </div>
</div>