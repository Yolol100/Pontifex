/**
 * sidebar.js
 *
 * Injects a mobile-only filter sidebar that mirrors your desktop filters
 * en houdt alles in sync, met AJAX “apply filters” (geen page reload).
 */
(function(window, $) {
  'use strict';

  const PontifexOI = (window.PontifexOI = window.PontifexOI || {});

  // Weekend-examens voor taal‐logica
  PontifexOI.examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

  // Taalopties (bewust hard gedefinieerd i.v.m. iOS select-bug)
  const languageOptions = [{
    id: '',
    name: 'Toon alles'
  }, {
    id: 'nl',
    name: 'Nederlands'
  }, {
    id: 'en',
    name: 'Engels'
  }, ];

  /**
   * Injecteert de sidebar + toggle-knop (alleen ≤ 480px) en vult selects.
   */
  PontifexOI.addAndPopulateSidebar = function() {
    const isMobile = $(window).width() <= 480;

    if (!isMobile) {
      $('.pontifex-oi-filters-toggle-btn, .pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').remove();
      return;
    }

    // Niet dubbel injecteren
    if ($('.pontifex-oi-filters-toggle-btn').length) return;

    // Markup met icoon in de toggle-knop én in “Filters toepassen”
    const sidebarHtml = `
      <button type="button" class="pontifex-oi-filters-toggle-btn">
        <i class="fas fa-filter" aria-hidden="true"></i>
        Filters
      </button>
      <div class="pontifex-oi-filters-sidebar-overlay"></div>
      <aside class="pontifex-oi-filters-sidebar" aria-modal="true" role="dialog" tabindex="0">
        <button class="pontifex-oi-filters-sidebar-close" aria-label="Sluit filters">&times;</button>
        <form class="pontifex-oi-filters-sidebar-form" autocomplete="off">
          <h2>Selecteer een examen</h2>
          <div class="sidebar-exam-row">
            <div>
              <label for="sidebar-exam_type">Examensoort</label>
              <select id="sidebar-exam_type" name="exam_type"></select>
            </div>
            <div>
              <label for="sidebar-language">Taal</label>
              <select id="sidebar-language" name="language"></select>
            </div>
          </div>

          <div class="sidebar-action-row">
            <button type="button" class="pontifex-oi-reset-filter pontifex-oi-sidebar-btn">
              Reset datumfilters
            </button>
          </div>

          <h2 style="margin-top:1.6rem;">Kies een datum en locatie</h2>
          <div class="sidebar-date-row">
            <div>
              <label for="sidebar-month">Maand</label>
              <select id="sidebar-month" name="month"></select>
            </div>
            <div>
              <label for="sidebar-province">Provincie</label>
              <select id="sidebar-province" name="province"></select>
            </div>
          </div>

          <div class="sidebar-location-row">
            <div>
              <label for="sidebar-location">Locatie</label>
              <select id="sidebar-location" name="location"></select>
            </div>
            <div>
              <label for="sidebar-timeslot">Dagsoort</label>
              <select id="sidebar-timeslot" name="timeslot"></select>
            </div>
          </div>

          <div class="sidebar-action-row">
            <button type="button" class="pontifex-oi-save-btn pontifex-oi-sidebar-btn">
              Filters toepassen <i class="fas fa-arrow-right" aria-hidden="true"></i>
            </button>
          </div>
        </form>
      </aside>
    `;

    // Invoegen vóór de desktop-filters
    $(sidebarHtml).insertBefore('.pontifex-oi-filters');

    // Desktop → sidebar (taal NIET kopiëren; die vullen we via JS i.v.m. iOS)
    const mapping = [
      ['#exam_type-select', 'sidebar-exam_type'],
      // ['#language-select', 'sidebar-language'], // bewust niet
      ['#month-select', 'sidebar-month'],
      ['#province-select', 'sidebar-province'],
      ['#location-select', 'sidebar-location'],
      ['#timeslot-select', 'sidebar-timeslot'],
    ];

    mapping.forEach(([desktopSel, sidebarId]) => {
      const $desk = $(desktopSel);
      const $side = $(`#${sidebarId}`);
      if ($desk.length && $side.length) {
        $side.html($desk.html());
        $side.val($desk.val());
      }
    });

    // Taalopties vullen / beperken (iOS-proof)
    updateSidebarLanguageOptions();
  };

  /**
   * Herbouwt de taal <select> op basis van exam_type (workaround iOS).
   */
  function updateSidebarLanguageOptions() {
    const $exam = $('#sidebar-exam_type');
    const $lang = $('#sidebar-language');
    const val = $exam.val();

    let optionsToShow = languageOptions;
    if (PontifexOI.examWeekend.includes(val)) {
      optionsToShow = languageOptions.filter((opt) => !opt.id || opt.id === 'nl');
    }

    const prevVal = $lang.val();

    $lang.empty();
    optionsToShow.forEach((opt) => {
      $lang.append($('<option>').val(opt.id).text(opt.name));
    });

    if (PontifexOI.examWeekend.includes(val)) {
      $lang.val('nl');
    } else if (optionsToShow.some((o) => o.id === prevVal)) {
      $lang.val(prevVal);
    } else {
      $lang.val('');
    }

    $lang.prop('disabled', !val);
  }

  /**
   * Events open/close/reset/apply
   */
  PontifexOI.registerSidebarEvents = function() {
    // Openen
    $(document).on('click', '.pontifex-oi-filters-toggle-btn', function(e) {
      e.preventDefault();
      ['exam_type', 'month', 'province', 'location', 'timeslot'].forEach((name) => {
        $(`#sidebar-${name}`).val($(`#${name}-select`).val());
      });
      updateSidebarLanguageOptions();
      $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').addClass('open');
      $('.pontifex-oi-filters-sidebar').focus();
    });

    // Sluiten
    $(document).on('click', '.pontifex-oi-filters-sidebar-overlay, .pontifex-oi-filters-sidebar-close', function(e) {
      e.preventDefault();
      $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
    });

    // ESC sluit
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && $('.pontifex-oi-filters-sidebar').hasClass('open')) {
        $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
      }
    });

    // Reset
    $(document).on('click', '.pontifex-oi-reset-filter', function(e) {
      e.preventDefault();
      $('#sidebar-month,#sidebar-province,#sidebar-location,#sidebar-timeslot').val('');
    });

    // Als exam_type verandert -> taalopties opnieuw toepassen
    $(document).on('change', '#sidebar-exam_type', updateSidebarLanguageOptions);

    // Apply filters via AJAX
    $(document).on('click', '.pontifex-oi-save-btn', function(e) {
      e.preventDefault();

      // 1) Sidebar → desktop
      ['exam_type', 'language', 'month', 'province', 'location', 'timeslot'].forEach((name) => {
        const v = $(`#sidebar-${name}`).val();
        $(`#${name}-select`).val(v).trigger('change');
      });

      // 2) Tabel/cards verversen
      if (PontifexOI.updatePontifexTable) {
        PontifexOI.updatePontifexTable(1);
      }

      // 3) Sluiten
      $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
    });
  };

  // Init
  $(function() {
    PontifexOI.addAndPopulateSidebar();
    PontifexOI.registerSidebarEvents();
  });

  // Op resize opnieuw injecteren/verwijderen
  $(window).on('resize', PontifexOI.addAndPopulateSidebar);
})(window, jQuery);