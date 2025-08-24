<?php
defined('ABSPATH') || exit;
$prices = get_option('pontifex_oi_prices', ['courses' => []]);
?>
<div class="pontifex-admin-wrap">
  <div class="pontifex-admin-card">
    <h2><?php esc_html_e('Prijzen', 'pontifex-oi'); ?></h2>

    <?php if (!empty($_GET['settings-updated'])): ?>
      <div id="message" class="updated notice notice-success is-dismissible" style="margin:0 0 1rem 0 !important; width:85%;">
        <p><?php esc_html_e('Instellingen zijn opgeslagen.', 'pontifex-oi'); ?></p>
      </div>
    <?php endif; ?>

    <form method="post" action="options.php" autocomplete="off" id="pontifex-prices-form">
      <?php settings_fields('pontifex_oi_prices_group'); ?>

      <div class="pontifex-prices-table">
        <div class="pontifex-prices-head">
          <div><?php esc_html_e('Naam', 'pontifex-oi'); ?></div>
          <div><?php esc_html_e('Taal', 'pontifex-oi'); ?></div>
          <div><?php esc_html_e('Prijs', 'pontifex-oi'); ?></div>
          <div class="pontifex-prices-actions-col"></div>
        </div>

        <div id="pontifex-prices-app" data-initial="<?php echo esc_attr(json_encode($prices)); ?>"></div>

        <div class="pontifex-prices-foot">
          <button type="button" class="pontifex-icon-btn pontifex-add-course" aria-label="<?php esc_attr_e('Cursus toevoegen', 'pontifex-oi'); ?>">+</button>
        </div>
      </div>

      <input type="hidden" name="pontifex_oi_prices" id="pontifex_oi_prices" value="<?php echo esc_attr(json_encode($prices)); ?>" />

      <div class="pontifex-admin-row" style="display:flex; justify-content:flex-end;">
        <button type="submit" class="pontifex-admin-submit"><?php esc_html_e('Opslaan', 'pontifex-oi'); ?></button>
      </div>
    </form>
  </div>
</div>