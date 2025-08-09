(function(window, $) {
  'use strict';

  // Only run if jQuery is available.
  if (!$) return;

  // Maximum number of candidates that can be added.
  const maxCandidates = 4;

  /**
   * Parses a price string by removing non-numeric characters and converting it
   * to a float.
   *
   * @param {string} str - The price string to be parsed.
   * @returns {number} The parsed price as a float, or 0 if the string is invalid.
   */
  function parsePrice(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
  }

  /**
   * Retrieves specific URL parameters.
   *
   * @returns {object} An object with the 'price' parameter from the URL.
   */
  function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return { price: params.get('price') || '' };
  }

  /**
   * Updates the number of candidates in the UI and recalculates the total price.
   */
  function updateCandidateCountAndPrice() {
    const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
    const count = $rows.length;
    $('#candidate-count').text(count);

    let baseRaw = $('#payment_amount').data('base-price') || getUrlParams().price;
    if (!baseRaw) {
      baseRaw = $('#payment_amount').val();
      $('#payment_amount').data('base-price', baseRaw);
    }
    
    let basePrice = parsePrice(baseRaw);
    if (isNaN(basePrice)) basePrice = 0;
    const total = basePrice * count;

    if ($('#total-price').length) {
      $('#total-price').text('€' + total.toFixed(2).replace('.', ','));
    }
    $('#payment_amount').val(total.toFixed(2).replace('.', ','));

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

    if ($list.find('.pontifex-oi-candidate-row').length >= maxCandidates) {
      $('.pontifex-oi-candidate-addrow').hide();
    } else {
      $('.pontifex-oi-candidate-addrow').show();
    }
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
      
      // Clone the first row, clear the fields, and update IDs and 'for' attributes.
      const $firstRow = $list.find('.pontifex-oi-candidate-row').first();
      const $newRow = $firstRow.clone();
      $newRow.find('input').val('');
      
      $newRow.find('label').each(function() {
        const oldFor = $(this).attr('for');
        if (oldFor) $(this).attr('for', oldFor.replace(/\d+$/, count + 1));
      });
      
      $newRow.find('input').each(function() {
        const oldId = $(this).attr('id');
        if (oldId) $(this).attr('id', oldId.replace(/\d+$/, count + 1));
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