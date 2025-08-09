// material.js
(function(window, $) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};
  const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
  const examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

  const extraMaterialCheckboxes = (cfg && cfg.extraMaterialCheckboxes) || {};

  function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
      exam_type: params.get('exam_type') || '',
      language: params.get('language') || ''
    };
  }

  function parsePrice(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
  }

  function isWeekendAllowed(exam, lang) {
    const map = cfg.weekendAllowedByExam || {};
    const allowed = map[exam] || [];
    return allowed.includes(lang) || examWeekend.includes(exam);
  }

  function updateTotalPriceWithCheckboxes() {
    const $totalPrice = $('#total-price');
    const $paymentAmount = $('#payment_amount');
    let basePrice = parsePrice($paymentAmount.data('base-price') || $paymentAmount.val() || getUrlParams().price);
    if (isNaN(basePrice)) basePrice = 0;

    let extraTotal = 0;
    $('.extra-material-checkbox:checked').each(function () {
      extraTotal += parseFloat($(this).data('price')) || 0;
    });

    const candidateCount = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row').length || 1;
    const newTotal = (basePrice + extraTotal) * candidateCount;

    $totalPrice.text('€' + newTotal.toFixed(2).replace('.', ','));
    $paymentAmount.val(newTotal.toFixed(2));

    if (typeof PontifexOI.updateTotalPriceWithCheckboxes === 'function' && PontifexOI.updateTotalPriceWithCheckboxes !== updateTotalPriceWithCheckboxes) {
      PontifexOI.updateTotalPriceWithCheckboxes();
    }
  }

  function buildCheckboxList(exam, lang) {
    const key = `${exam}_${lang}`;
    const raw = extraMaterialCheckboxes[key] || [];

    return raw.filter(opt => {
      if (opt.id === 'cursus-weekend') {
        return isWeekendAllowed(exam, lang);
      }
      return true;
    });
  }

  function updateExtraMaterialCheckboxes() {
    const $container = $('#extra-material-checkboxes');
    if (!$container.length) return;

    let examVal = $('select[name="exam_type"]').val() || $('#exam_type').val() || getUrlParams().exam_type || '';
    let langVal = $('select[name="language"]').val() || $('#language').val() || getUrlParams().language || '';

    if (examVal === 'vca-basis') examVal = 'los-examen-vca-basis';
    if (examVal === 'vca-vol')   examVal = 'los-examen-vca-vol';
    if (examVal === 'los-examen-vil-vcu') examVal = 'los-examen-vca-vil';

    const options = buildCheckboxList(examVal, langVal);

    let html = '<strong>Extra lesmateriaal nodig?</strong><div>';
    if (options.length > 0) {
      options.forEach(opt => {
        html += `<label style="display:block; margin:0.3em 0;">
            <input type="checkbox" class="extra-material-checkbox" name="extra_material[]" value="${opt.id}" data-price="${opt.price}">
            ${opt.label} (€${opt.price.toFixed(2).replace('.', ',')})
          </label>`;
      });
    } else {
      html += 'Geen extra lesmateriaal beschikbaar.';
    }
    html += '</div>';

    $container.html(html).show();

    $container.off('change.material').on('change.material', '.extra-material-checkbox', updateTotalPriceWithCheckboxes);

    updateTotalPriceWithCheckboxes();
  }

  $(document).ready(function () {
    updateExtraMaterialCheckboxes();
  });

  $(document).on('change', 'select[name="exam_type"], select[name="language"]', function () {
    updateExtraMaterialCheckboxes();
  });

  PontifexOI.updateExtraMaterialCheckboxes = updateExtraMaterialCheckboxes;
  PontifexOI.updateTotalPriceWithCheckboxes = updateTotalPriceWithCheckboxes;

})(window, jQuery);