(function(window, $) {
  'use strict';

  // Only run if jQuery is available.
  if (!$) return;

  // Maximum number of candidates that can be added.
  const maxCandidates = 4;

  /**
   * Updates the number of candidates in the UI and recalculates the total price.
   */
  function updateCandidateCountAndPrice() {
    const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
    const count = $rows.length;
    $('#candidate-count').text(count);

    let baseRaw = $('#payment_amount').data('base-price') || PontifexOI.getUrlParams().price;
    if (!baseRaw) {
      baseRaw = $('#payment_amount').val();
      $('#payment_amount').data('base-price', baseRaw);
    }
    
    let basePrice = PontifexOI.parsePrice(baseRaw);
    if (isNaN(basePrice)) basePrice = 0;
    const total = basePrice * count;

    const totalStr = total.toFixed(2);
    if ($('#total-price').length) {
      $('#total-price').text('€' + totalStr.replace('.', ','));
    }
    $('#payment_amount').val(totalStr);

    // If 'material.js' exists and needs to account for extras, let it handle the final total.
    // This is the improved logic from the new version.
    if (typeof window.PontifexOI?.updateTotalPriceWithCheckboxes === 'function') {
      window.PontifexOI.updateTotalPriceWithCheckboxes();
    }
  }

  /**
   * Updates the titles of each candidate row (e.g., 'Kandidaat 1').
   * It also shows/hides the 'remove' button for the first candidate and the 'add' button
   * when the maximum limit is reached.
   */
  function updateCandidateTitles() {
    const $list = $('.pontifex-oi-candidates-list');
    $list.find('h3.kandidaat-titel').remove();
    
    $list.find('.pontifex-oi-candidate-row').each(function(index) {
      const $title = $('<h3>').addClass('kandidaat-titel').text('Kandidaat ' + (index + 1));
      $(this).before($title);

      if (index === 0) {
        $(this).find('.pontifex-oi-remove-candidate').hide();
      } else {
        $(this).find('.pontifex-oi-remove-candidate').show();
      }
    });
    
    // Probleem 2: Fix voor inconsistente class-namen
    $('.pontifex-oi-add-candidate').toggle($list.find('.pontifex-oi-candidate-row').length < maxCandidates);
  }

  /**
   * Initializes all event handlers for adding and removing candidate rows.
   */
  function initCandidatesEvents() {
    // Event handler for the 'add candidate' button.
    $(document).on('click', '.pontifex-oi-add-candidate', function() {
      const $list = $('.pontifex-oi-candidates-list');
      const count = $list.find('.pontifex-oi-candidate-row').length;

      if (count >= maxCandidates) return;
      
      // Clone the first row.
      const $firstRow = $list.find('.pontifex-oi-candidate-row').first();
      const $newRow = $firstRow.clone();

      // Probleem 3: Klonen met unieke name/id + reset checkboxes
      $newRow.find('input').each(function() {
        const $el = $(this);
        // leegmaken + uncheck
        if ($el.is(':checkbox,:radio')) { $el.prop('checked', false); }
        else { $el.val(''); }

        // id/for/name bijwerken
        const oldId = $el.attr('id');
        if (oldId) $el.attr('id', oldId.replace(/\d+$/, count + 1) || (oldId + '-' + (count + 1)));

        const oldName = $el.attr('name');
        if (oldName) {
          // support zowel naam_1 als naam[1]
          if (/\[\d+\]$/.test(oldName)) {
            $el.attr('name', oldName.replace(/\[\d+\]$/, '[' + (count + 1) + ']'));
          } else if (/\d+$/.test(oldName)) {
            $el.attr('name', oldName.replace(/\d+$/, (count + 1)));
          } else {
            $el.attr('name', oldName + '[' + (count + 1) + ']');
          }
        }
      });
      $newRow.find('label[for]').each(function() {
        const $lb = $(this);
        const oldFor = $lb.attr('for');
        if (oldFor) $lb.attr('for', oldFor.replace(/\d+$/, count + 1) || (oldFor + '-' + (count + 1)));
      });
      
      $list.append($newRow);
      
      // Update the count, price, and titles after adding a row.
      updateCandidateCountAndPrice();
      updateCandidateTitles();
    });

    // Event handler for the 'remove candidate' button.
    $(document).on('click', '.pontifex-oi-remove-candidate', function() {
      const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
      if ($rows.length > 1) {
        $(this).closest('.pontifex-oi-candidate-row').remove();
      }
      
      // Update the count, price, and titles after removing a row.
      updateCandidateCountAndPrice();
      updateCandidateTitles();
    });
  }

  // Make the functions available in the global 'PontifexOI' namespace.
  window.PontifexOI = window.PontifexOI || {};
  window.PontifexOI.updateCandidateCountAndPrice = updateCandidateCountAndPrice;
  window.PontifexOI.initCandidatesEvents = initCandidatesEvents;
  window.PontifexOI.updateCandidateTitles = updateCandidateTitles;

  // Initialize events and titles automatically when the page is fully loaded.
  $(function() {
    initCandidatesEvents();
    updateCandidateTitles();
  });

})(window, window.jQuery);