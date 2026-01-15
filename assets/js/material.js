// ============================================================================
// Material Select Logic + Extra Material Checkboxes
// ============================================================================
(function (window, $) {
  'use strict';

  if (typeof $ !== 'function') return;

  const cfg = window.PontifexOIConfigData ?? window.PontifexOIConfig ?? {};
  const PontifexOI = (window.PontifexOI = window.PontifexOI || {});

  // Selectors helpers
  const $materialSelects = () =>
    $('#material-select, #sidebar-material, select[name="material"]');

  const getExam = () => $('select[name="exam_type"], #exam_type-select, #sidebar-exam_type').val() || '';
  const getLanguage = () => $('select[name="language"], #language-select, #sidebar-language').val() || cfg.defaultLanguage || 'nl';

  // ============================================================================
  // Material dropdown vullen + filteren
  // ============================================================================
  function populateMaterialSelect() {
    const $all = $materialSelects();
    if (!$all.length) return;

    const exam = getExam();
    const lang = getLanguage();

    const baseOptions = [
      { id: '1', label: 'Los examen' },
      { id: '2', label: 'Examen + boek' },
      { id: '4', label: 'Examen + e-learning' },
      { id: '5', label: 'Examen + proefexamens' },
      { id: '6', label: 'Examen + boek + proefexamens' },
      { id: '7', label: 'Examen + e-learning + proefexamens' }
    ];

    // Weekendcursus optie alleen voor VCA Basis/VOL in NL/EN
    const showWeekend = 
      (exam === 'los-examen-vca-basis' || exam === 'los-examen-vca-vol') &&
      (lang === 'nl' || lang === 'en');

    let options = baseOptions;
    if (showWeekend) {
      options = [...options, { id: 'cursus-weekend', label: 'Weekendcursus met examen' }];
    }

    // Proefexamens verwijderen bij niet-Nederlands
    if (lang !== 'nl') {
      options = options.filter(opt => 
        !/proefexamen|practice/i.test(opt.label)
      );
    }

    // Bouw HTML
    let html = '<option value="" disabled selected>Maak een keuze</option>';
    options.forEach(opt => {
      html += `<option value="${opt.id}">${opt.label}</option>`;
    });

    // Update alle material selects & probeer waarde te behouden
    $all.each(function () {
      const $sel = $(this);
      const prevValue = $sel.val() || $sel.attr('data-selected-value') || '';

      $sel.html(html).prop('disabled', false);

      // Herstel waarde als die nog bestaat
      if (prevValue && $sel.find(`option[value="${prevValue}"]`).length) {
        $sel.val(prevValue);
      } else {
        $sel.val('');
      }

      // Zorg dat data-selected-value altijd up-to-date is
      if ($sel.val()) {
        $sel.attr('data-selected-value', $sel.val());
      }
    });

    // Trigger prijs- en formulier updates
    PontifexOI.updateAllDynamicPrices?.();
    PontifexOI.updateFormInputsInTableRows?.();
  }

  // ============================================================================
  // Extra Material Checkboxes + Weekend logica
  // ============================================================================
  function renderExtraMaterial() {
    const $container = $('#extra-material-checkboxes');
    if (!$container.length) return;

    const exam = getExam();
    const lang = getLanguage();

    // Extra opties uit config (verwacht flat array)
    const rawOptions = Object.values(cfg.extraOptions ?? {});

    // Filter weekend optie
    const filtered = rawOptions.filter(opt => {
      if (opt.id === 'cursus-weekend') {
        return PontifexOI.isWeekendAllowed?.(exam, lang) ?? 
               (exam.includes('vca-basis') || exam.includes('vca-vol'));
      }
      // Proefexamens alleen in NL
      if (/proefexamen|practice/i.test(opt.id || opt.label)) {
        return lang === 'nl';
      }
      return true;
    });

    let html = '<strong>Extra lesmateriaal nodig?</strong><div>';
    
    if (filtered.length === 0) {
      html += 'Geen extra lesmateriaal beschikbaar.';
    } else {
      filtered.forEach(opt => {
        const priceStr = Number(opt.price ?? 0).toFixed(2).replace('.', ',');
        html += `
          <label style="display:block; margin:0.4em 0;">
            <input type="checkbox" class="extra-material-checkbox" 
                   name="extra_options[]" value="${opt.id}" 
                   data-price="${opt.price ?? 0}"
                   aria-label="${opt.label} (€${priceStr})">
            ${opt.label} (€${priceStr})
          </label>`;
      });
    }
    html += '</div>';

    $container.html(html);

    // Probeer waarde van stap 1 te herstellen via URL
    const urlParams = new URLSearchParams(window.location.search);
    const preSelected = urlParams.get('material');
    if (preSelected && preSelected !== '1') {
      $container.find(`input[value="${preSelected}"]`).prop('checked', true);
    }

    $container.off('change.extra-material').on('change.extra-material', '.extra-material-checkbox', handleExtraMaterialChange);

    // Initial sync
    handleExtraMaterialChange();
  }

  function handleExtraMaterialChange() {
    const weekendChecked = $('.extra-material-checkbox[value="cursus-weekend"]').is(':checked');
    const exam = getExam();

    const isBasisOrVol = ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(exam);
    const isFixedWeekend = exam.includes('vca-basis-weekend') || exam.includes('vca-vol-weekend');

    const useWeekendPrice = weekendChecked && isBasisOrVol || isFixedWeekend;

    if (useWeekendPrice) {
      applyWeekendPrice(245);
    } else {
      restoreNormalPricing();
    }

    updateWeekendLocks();
    updateOrderSummaryText();
    updateTotalPriceWithCheckboxes();
    PontifexOI.updateCandidateCountAndPrice?.();
  }

  function applyWeekendPrice(fixedPrice = 245) {
    const candidateCount = Math.max(1, $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row').length || 1);
    const total = fixedPrice * candidateCount;

    $('#total-price').text(`€${total.toFixed(2).replace('.', ',')}`);
    $('#payment_amount').val(total.toFixed(2)).data('base-price', fixedPrice.toFixed(2));

    $('.pontifex-oi-dynamic-price').each(function () {
      const $el = $(this);
      $el.find('.pontifex-oi-price-amount').text(`€${fixedPrice},00`);
      $el.find('.pontifex-oi-price-loader').hide();
      $el.addClass('is-hydrated');
      
      const $input = $el.closest('tr, .pontifex-oi-card, .acc-item')
                       .find('input[name="price"], .pontifex-oi-price-input');
      if ($input.length) $input.val(fixedPrice.toFixed(2));
    });

    // Cache leegmaken om conflicten te voorkomen
    if (PontifexOI.__priceCache) PontifexOI.__priceCache = {};
  }

  function restoreNormalPricing() {
    const $payment = $('#payment_amount');
    const original = $payment.data('original-base-price') || $payment.data('base-price');

    if (original) {
      $payment.data('base-price', original);
    }

    PontifexOI.updateAllDynamicPrices?.();
  }

  function updateWeekendLocks() {
    const $prov = $('#province');
    const $loc = $('#location');

    if (!$prov.length || !$loc.length) return;

    const exam = getExam();
    const weekendOn = 
      $('.extra-material-checkbox[value="cursus-weekend"]').is(':checked') &&
      (exam === 'los-examen-vca-basis' || exam === 'los-examen-vca-vol') ||
      exam.includes('vca-basis-weekend') || exam.includes('vca-vol-weekend');

    if (weekendOn) {
      if (!$prov.data('prev')) $prov.data('prev', $prov.val() || '');
      if (!$loc.data('prev')) $loc.data('prev', $loc.val() || '');

      $prov.val('Zuid-Holland');
      $loc.val('Den Haag');

      updateSummaryFields('provincie', 'Zuid-Holland');
      updateSummaryFields('locatie', 'Den Haag');
    } else {
      if ($prov.data('prev')) $prov.val($prov.data('prev'));
      if ($loc.data('prev')) $loc.val($loc.data('prev'));

      $prov.removeData('prev');
      $loc.removeData('prev');

      updateSummaryFields('provincie', $prov.val() || '-');
      updateSummaryFields('locatie', $loc.val() || '-');
    }
  }

  function updateSummaryFields(labelText, newValue) {
    $('.besteloverzicht-stap2 .bo-item, .besteloverzicht .bo-item').each(function () {
      const $strong = $(this).find('strong, label, .bo-label').first();
      if ($strong.text().trim().toLowerCase().startsWith(labelText)) {
        $(this).find('span, .bo-value').text(newValue);
      }
    });
  }

  function updateOrderSummaryText() {
    const exam = getExam();
    const isWeekendExam = exam.includes('vca-basis-weekend') || exam.includes('vca-vol-weekend');
    const weekendExtra = $('.extra-material-checkbox[value="cursus-weekend"]').is(':checked') &&
                        (exam === 'los-examen-vca-basis' || exam === 'los-examen-vca-vol');

    const showWeekend = isWeekendExam || weekendExtra;

    $('.besteloverzicht-stap2 .bo-item, .besteloverzicht .bo-item, [data-summary="exam"]').each(function () {
      const $row = $(this);
      const $label = $row.find('strong, label, .bo-label').first();
      if (!/examensoort|examen/i.test($label.text())) return;

      const $value = $row.find('span, .bo-value').first();
      if (!$value.length) return;

      const base = $value.text().trim().replace(/\s+met.*$/i, '');
      $value.text(showWeekend ? 'WeekendCursus met examen' : `${base} met examen`);
    });
  }

  function updateTotalPriceWithCheckboxes() {
    const $total = $('#total-price');
    const $payment = $('#payment_amount');

    if (!$total.length || !$payment.length) return;

    const weekendOn = 
      $('.extra-material-checkbox[value="cursus-weekend"]').is(':checked') &&
      ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(getExam()) ||
      getExam().includes('vca-basis-weekend') || getExam().includes('vca-vol-weekend');

    const candidateCount = Math.max(1, $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row').length);

    let total = 0;

    if (weekendOn) {
      total = 245 * candidateCount;
    } else {
      const base = PontifexOI.parsePrice?.(
        $payment.data('original-base-price') || 
        $payment.data('base-price') || 
        $payment.val()
      ) || 0;

      let extras = 0;
      $('.extra-material-checkbox:checked').each(function () {
        if (this.value !== 'cursus-weekend') {
          extras += parseFloat($(this).data('price') || 0);
        }
      });

      total = (base + extras) * candidateCount;
    }

    $total.text(`€${total.toFixed(2).replace('.', ',')}`);
    $payment.val(total.toFixed(2));
  }

  // ============================================================================
  // Event binding
  // ============================================================================
  $(document).ready(() => {
    populateMaterialSelect();
    renderExtraMaterial();
    updateOrderSummaryText();
    updateTotalPriceWithCheckboxes();
  });

  // Exam / taal wijziging → material & extra opties updaten
  $(document).on('change', 
    'select[name="exam_type"], #exam_type-select, #sidebar-exam_type, ' +
    'select[name="language"], #language-select, #sidebar-language',
    function () {
      populateMaterialSelect();
      renderExtraMaterial();
    }
  );

  // Debounced total recalc op material keuze
  const debounce = (fn, wait = 180) => {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), wait);
    };
  };

  $(document).on('change', 'select[name="material"]', debounce(() => {
    $(document).trigger('pontifex:recalc-total');
  }, 200));

  // Form submit → material waarde meesturen
  $(document).on('submit', '.pontifex-oi-aanmeld-form', function () {
    const val = $materialSelects().first().val() || '1';
    $(this).find('input[name="material"]').val(val);
  });

  // Public exports
  PontifexOI.updateExtraMaterialCheckboxes = renderExtraMaterial;
  PontifexOI.updateTotalPriceWithCheckboxes = updateTotalPriceWithCheckboxes;
  PontifexOI.populateMaterialSelect = populateMaterialSelect;

})(window, jQuery);