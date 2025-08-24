<?php
defined('ABSPATH') || exit;
?>
<div class="pontifex-admin-wrap">
  <div class="pontifex-admin-card">
    <h2><?php esc_html_e('Emails', 'pontifex-oi'); ?></h2>

    <?php if (!empty($_GET['settings-updated'])): ?>
      <div id="message" class="updated notice notice-success is-dismissible" style="margin:0 0 1rem 0 !important; width:85%;">
        <p><?php esc_html_e('Instellingen zijn opgeslagen.', 'pontifex-oi'); ?></p>
      </div>
    <?php endif; ?>

    <form method="post" action="options.php" autocomplete="off" id="pontifex-emails-form">
      <?php settings_fields('pontifex_oi_emails_group'); ?>

      <div class="pontifex-admin-row">
        <label for="pontifex_oi_owner_email" class="pontifex-admin-label">
          <?php esc_html_e('E-mailadres eigenaar', 'pontifex-oi'); ?>
        </label>
        <input type="email"
               id="pontifex_oi_owner_email"
               name="pontifex_oi_owner_email"
               class="pontifex-admin-input"
               placeholder="<?php esc_attr_e('bijv. planning@certipro.nl', 'pontifex-oi'); ?>"
               value="<?php echo esc_attr(get_option('pontifex_oi_owner_email', 'planning@certipro.nl')); ?>"
               required />
        <div class="pontifex-admin-desc">
          <?php esc_html_e('Nieuwe inschrijvingen (met XLS-bijlage) worden hierheen gemaild.', 'pontifex-oi'); ?>
        </div>
      </div>

      <div class="pontifex-admin-row" style="display:flex; justify-content:flex-end;">
        <button type="submit" class="pontifex-admin-submit"><?php esc_html_e('Opslaan', 'pontifex-oi'); ?></button>
      </div>
    </form>
  </div>
</div>