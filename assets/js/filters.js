// filters.js
(function(window, $) {
  'use strict';

  // Gebruik centrale config (ajaxUrl, examWeekend) uit localized JS variabele
  const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
  const PontifexOI = window.PontifexOI || {};
  const weekendExams = Array.isArray(cfg.examWeekend) ? cfg.examWeekend : [];

  /**
   * Haalt planning via AJAX op en update tabel + kaarten + paginatie + prijzen.
   */
  PontifexOI.updatePontifexTable = function(page = 1) {
    const filters = {
      exam_type: $('select[name="exam_type"], #exam_type-select').val(),
      language:  $('select[name="language"], #language-select').val(),
      month:     $('select[name="month"]').val(),
      province:  $('select[name="province"]').val(),
      location:  $('select[name="location"]').val(),
      timeslot:  $('select[name="timeslot"]').val(),
      page
    };

    $.post(cfg.ajaxUrl, {
      action: 'pontifex_oi_get_planning',
      filters
    }).done(function(res) {
      if (!res.success) return;

      const planning = res.data.planning || [];

      // Render tabel + kaarten
      PontifexOI.renderTableRows(planning, res.data.current_page, res.data.total_pages);
      PontifexOI.renderCards(planning);

      // Prijzen één keer na beide renders
      if (PontifexOI.updateAllDynamicPrices) PontifexOI.updateAllDynamicPrices();

      // Paginatie tekenen + events binden (events via pagination.js)
      if (typeof PontifexOI.renderPagination === 'function') {
        PontifexOI.renderPagination(res.data.current_page, res.data.total_pages);
      }
      if (typeof PontifexOI.initPaginationEvents === 'function') {
        PontifexOI.initPaginationEvents(function(nextPage) {
          PontifexOI.updatePontifexTable(nextPage);
        });
      }
    }).fail(function() {
      console.warn('Ajax request voor planning is mislukt, overweeg pagina reload als fallback.');
    });
  };

  /**
   * Filterstate updaten en weekend-examens uitsluiten Engels.
   */
  function updateFiltersState() {
    const $exam = $('select[name="exam_type"], #exam_type-select');
    const $lang = $('select[name="language"], #language-select');
    const examVal = $exam.val();
    const langVal = $lang.val();

    if (!examVal) {
      $lang.val('').prop('disabled', true).find('option').show();
      return;
    }

    $lang.prop('disabled', false);

    if (weekendExams.includes(examVal)) {
      $lang.find('option').each(function() {
        const v = $(this).val();
        $(this).toggle(v === '' || v === 'nl');
      });
      // FIX: geen trigger('change') om dubbele fetch te voorkomen
      if (langVal !== 'nl') {
        $lang.val('nl'); // alleen waarde zetten
      }
    } else {
      $lang.find('option').show();
      if (!langVal) $lang.val('');
    }
  }

  /**
   * Alle event handlers binden.
   */
  function bindFilterHandlers() {
    // Als een filter wijzigt => state bijwerken + opnieuw laden (pagina 1)
    $(document).on(
      'change',
      '.pontifex-oi-filter, select[name="exam_type"], select[name="language"], #exam_type-select, #language-select',
      function() {
        updateFiltersState();
        PontifexOI.updatePontifexTable(1);
      }
    );

    // Form submit via AJAX
    $(document).on('submit', '.pontifex-oi-filters', function(e) {
      e.preventDefault();
      updateFiltersState();
      PontifexOI.updatePontifexTable(1);
    });

    // FIX: géén losse paginatie click handler hier — dat doet pagination.js
  }

  // Expose functies op global
  if (!window.PontifexOI) window.PontifexOI = {};
  window.PontifexOI.updatePontifexTable = PontifexOI.updatePontifexTable;
  window.PontifexOI.updateFiltersState = updateFiltersState;
  window.PontifexOI.bindFilterHandlers = bindFilterHandlers;

  // Init filters (zonder initiale fetch!)
  $(document).ready(function() {
    bindFilterHandlers();
    updateFiltersState();
  });

})(window, jQuery);