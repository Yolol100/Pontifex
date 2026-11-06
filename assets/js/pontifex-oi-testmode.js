jQuery(function($) {
  'use strict';

  const isTestMode = !!(window.PontifexOIFrontend && PontifexOIFrontend.testMode);
  const $form = $('#registration-form');
  if (!$form.length) return;

  const $requiredFields = $form.find('input[required], select[required], textarea[required]');

  if (isTestMode) {
    console.warn('[Pontifex OI] Testmodus actief — verplichte velden uitgeschakeld en * verwijderd.');
    $requiredFields.each(function() {
      $(this).data('was-required', true).removeAttr('required');
    });

    $form.find('label .required').each(function() {
      $(this).addClass('hide-required');
    });

  } else {
    $requiredFields.each(function() {
      if ($(this).data('was-required')) {
        $(this).attr('required', 'required');
      }
    });

    $form.find('label .required').removeClass('hide-required');
  }
});