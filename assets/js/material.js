(function(window, $) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};
  const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
  const extraMaterialCheckboxes = cfg?.extraMaterialCheckboxes || {};
  const examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

  // Cached DOM elements
  const $prov = $('#province');
  const $loc = $('#location');
  const $totalPrice = $('#total-price');
  const $paymentAmount = $('#payment_amount');
  const $container = $('#extra-material-checkboxes');

  const getExamLangFromDOM = () => {
    const examVal = PontifexOI.normalizeExam(
      $('select[name="exam_type"]').val() ||
      $('#exam_type').val() ||
      PontifexOI.getUrlParams().exam_type || ''
    );
    const langVal = $('select[name="language"]').val() ||
      $('#language').val() ||
      PontifexOI.getUrlParams().language || '';
    return {
      examVal,
      langVal
    };
  };

  const buildCheckboxList = (exam, lang) => {
    const key = `${exam}_${lang}`;
    const raw = extraMaterialCheckboxes[key] || [];
    return raw.filter(opt => opt.id !== 'cursus-weekend' || PontifexOI.isWeekendAllowed(exam, lang));
  };

  const weekendChecked = () =>
    $('#extra-material-checkboxes input[value="cursus-weekend"]').is(':checked');

  // Main functions
  const updateWeekendLocks = () => {
    if (!$prov.length || !$loc.length) return;

    const {
      examVal
    } = getExamLangFromDOM();
    const isBasisOfVol = examVal === 'los-examen-vca-basis' || examVal === 'los-examen-vca-vol';

    if (weekendChecked() && isBasisOfVol) {
      if ($prov.data('prev') === undefined) $prov.data('prev', $prov.val());
      if ($loc.data('prev') === undefined) $loc.data('prev', $loc.val());
      $prov.val('Zuid-Holland');
      $loc.val('Den Haag');

      $('.besteloverzicht-stap2 .bo-item strong').each(function() {
        const label = $(this).text().trim().toLowerCase();
        if (label.startsWith('provincie')) $(this).next('span').text('Zuid-Holland');
        if (label.startsWith('locatie')) $(this).next('span').text('Den Haag');
      });
    } else {
      if ($prov.data('prev') !== undefined) $prov.val($prov.data('prev'));
      if ($loc.data('prev') !== undefined) $loc.val($loc.data('prev'));
      $prov.removeData('prev');
      $loc.removeData('prev');

      $('.besteloverzicht-stap2 .bo-item strong').each(function() {
        const label = $(this).text().trim().toLowerCase();
        if (label.startsWith('provincie')) $(this).next('span').text($prov.val() || '-');
        if (label.startsWith('locatie')) $(this).next('span').text($loc.val() || '-');
      });
    }
  };

  const updateOrderSummaryText = () => {
    const {
      examVal
    } = getExamLangFromDOM();
    const weekendOn = weekendChecked() &&
      (examVal === 'los-examen-vca-basis' || examVal === 'los-examen-vca-vol');

    const $targets = $('.besteloverzicht-stap2 .bo-item, .besteloverzicht .bo-item, .bo-item--exam, [data-summary="exam"]');

    $targets.each(function() {
      const $row = $(this);
      const $strong = $row.find('strong,label,.bo-label').first();
      const labelTxt = ($strong.text() || '').toLowerCase();
      if (!labelTxt.includes('examensoort') && !labelTxt.includes('examen')) return;

      const $val = $row.find('span,.bo-value').first();
      if (!$val.length) return;

      const base = $val.text().trim().replace(/\s+met.*$/i, '');
      $val.text(weekendOn ? `${base} met cursusweekend en examen` : `${base} met examen`);
    });
  };

  const updateTotalPriceWithCheckboxes = () => {
    if (!$totalPrice.length || !$paymentAmount.length) return;

    const {
      examVal
    } = getExamLangFromDOM();
    let basePrice = PontifexOI.parsePrice($paymentAmount.data('base-price') || $paymentAmount.val() || PontifexOI.getUrlParams().price);

    if (weekendChecked() && (examVal === 'los-examen-vca-basis' || examVal === 'los-examen-vca-vol')) {
      basePrice = 245;
    }

    let extraTotal = 0;
    $('.extra-material-checkbox:checked').each(function() {
      const id = String($(this).val());
      if (id === 'cursus-weekend') return;
      extraTotal += parseFloat($(this).data('price')) || 0;
    });

    const candidateCount = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row').length || 1;
    const newTotal = (basePrice + extraTotal) * candidateCount;

    $totalPrice.text(`€${newTotal.toFixed(2).replace('.', ',')}`);
    $paymentAmount.val(newTotal.toFixed(2));
  };

  const renderExtraMaterial = () => {
    if (!$container.length) return;

    const {
      examVal,
      langVal
    } = getExamLangFromDOM();
    const options = buildCheckboxList(examVal, langVal);

    let html = '<strong>Extra lesmateriaal nodig?</strong><div>';
    if (options.length) {
      options.forEach(opt => {
        const priceStr = Number(opt.price).toFixed(2).replace('.', ',');
        html += `<label style="display:block;margin:.3em 0;">
                    <input type="checkbox" class="extra-material-checkbox" name="extra_material[]" value="${opt.id}" data-price="${opt.price}" aria-label="${opt.label} (€${priceStr})">
                    ${opt.label} (€${priceStr})
                </label>`;
      });
    } else {
      html += 'Geen extra lesmateriaal beschikbaar.';
    }
    html += '</div>';

    $container.html(html).show();
    $container.off('change.material').on('change.material', '.extra-material-checkbox', () => {
      updateWeekendLocks();
      updateOrderSummaryText();
      updateTotalPriceWithCheckboxes();
    });

    updateWeekendLocks();
    updateOrderSummaryText();
    updateTotalPriceWithCheckboxes();
  };

  // Init
  $(document).ready(() => {
    renderExtraMaterial();
    updateOrderSummaryText();
  });

  // Re-render on changes
  $(document).on('change', 'select[name="exam_type"], select[name="language"]', renderExtraMaterial);
  $(document).on('change', '#extra-material-checkboxes input, select[name="exam_type"], select[name="language"]', updateOrderSummaryText);

  // Expose functions
  PontifexOI.updateExtraMaterialCheckboxes = renderExtraMaterial;
  PontifexOI.updateTotalPriceWithCheckboxes = updateTotalPriceWithCheckboxes;

})(window, jQuery);