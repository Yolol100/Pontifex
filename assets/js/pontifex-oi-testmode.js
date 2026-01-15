(function (window, document, $) {
  'use strict';
  if (typeof $ !== 'function') return;

  $(function () {
    const isTestMode = Boolean(window.PontifexOIFrontend?.testMode);
    const $form = $('#registration-form');
    if (!$form.length) return;

    const $requiredFields = $form.find('input[required], select[required], textarea[required]');

    if (isTestMode) {
      console.warn('[Pontifex OI] Testmodus actief — verplichte velden uitgeschakeld en * verwijderd.');
      $requiredFields.each(function () {
        $(this).data('was-required', true).removeAttr('required');
      });

      // Verberg de sterretjes in labels
      $form.find('label .required').each(function () {
        $(this).addClass('hide-required');
      });
    } else {
      console.log('[Pontifex OI] Productiemodus — verplichte velden ingeschakeld en * zichtbaar.');
      $requiredFields.each(function () {
        if ($(this).data('was-required')) {
          $(this).attr('required', 'required');
        }
      });

      // Toon de sterretjes weer
      $form.find('label .required').removeClass('hide-required');
    }
  });
})(window, document, window.jQuery);