// material.js
(function(window, $) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  // --- Constants / Config (kun je ook vanuit een extern config-bestand laden) ---
  const examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

  const extraMaterialCheckboxes = {
    'los-examen-vca-basis_nl': [
      { id: 'e-learning-vca-basis-nl', label: 'E-learning VCA Basis (NL)', price: 29 },
      { id: 'vca-basis-proefexamens-nl', label: 'VCA Basis Proefexamens (NL)', price: 25 },
      { id: 'boek-vca-basis-nl', label: 'Boek VCA Basis (NL)', price: 36 },
      { id: 'boek-vca-combi-nl', label: 'Boek VCA Combi (NL)', price: 49 },
    ],
    'los-examen-vca-vol_nl': [
      { id: 'e-learning-vca-vol-nl', label: 'E-learning VCA Vol (NL)', price: 39 },
      { id: 'vca-vol-proefexamens-nl', label: 'VCA Vol Proefexamens (NL)', price: 25 },
      { id: 'boek-vca-vol-nl', label: 'Boek VCA Vol (NL)', price: 42 },
      { id: 'boek-vca-combi-vol-nl', label: 'Boek VCA Combi (NL)', price: 49 },
    ],
    'los-examen-vca-basis_en': [
      { id: 'e-learning-vca-basis-en', label: 'E-learning VCA Basis (EN)', price: 49 },
      { id: 'boek-vca-basis-en', label: 'Boek VCA Basis (EN)', price: 56 },
      { id: 'boek-vca-combi-en', label: 'Boek VCA Combi (EN)', price: 69 },
    ],
    'los-examen-vca-vol_en': [
      { id: 'e-learning-vca-vol-en', label: 'E-learning VCA Vol (EN)', price: 59 },
      { id: 'boek-vca-vol-en', label: 'Boek VCA Vol (EN)', price: 62 },
      { id: 'boek-vca-combi-vol-en', label: 'Boek VCA Combi (EN)', price: 69 },
    ],
  };

  // --- Hulpfuncties ---

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

    // Optioneel: als er een globale updatefunctie bestaat, trigger die
    if (typeof PontifexOI.updateTotalPriceWithCheckboxes === 'function' && PontifexOI.updateTotalPriceWithCheckboxes !== updateTotalPriceWithCheckboxes) {
      PontifexOI.updateTotalPriceWithCheckboxes();
    }
  }

  // --- Main functie om checkboxes bij te werken ---

  function updateExtraMaterialCheckboxes() {
    const $container = $('#extra-material-checkboxes');
    if (!$container.length) return;

    let examVal = $('select[name="exam_type"]').val() || $('#exam_type').val() || getUrlParams().exam_type || '';
    let langVal = $('select[name="language"]').val() || $('#language').val() || getUrlParams().language || '';

    // Normaliseer oude shortnames
    if (examVal === 'vca-basis') examVal = 'los-examen-vca-basis';
    if (examVal === 'vca-vol') examVal = 'los-examen-vca-vol';

    if (examWeekend.includes(examVal)) {
      $container.hide().empty();
      updateTotalPriceWithCheckboxes();
      return;
    }

    const key = `${examVal}_${langVal}`;
    const options = extraMaterialCheckboxes[key] || [];

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

  // --- Initialisatie ---

  $(document).ready(function () {
    updateExtraMaterialCheckboxes();
  });

  $(document).on('change', 'select[name="exam_type"], select[name="language"]', function () {
    updateExtraMaterialCheckboxes();
  });

  // Export functies in global namespace
  PontifexOI.updateExtraMaterialCheckboxes = updateExtraMaterialCheckboxes;
  PontifexOI.updateTotalPriceWithCheckboxes = updateTotalPriceWithCheckboxes;

})(window, jQuery);