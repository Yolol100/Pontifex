// filters.js
(function(window, $) {
  'use strict';

  const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
  if (!window.PontifexOI) window.PontifexOI = {};
  const PontifexOI = window.PontifexOI;

  const weekendAllowedByExam = cfg.weekendAllowedByExam || {};
  const examProducts = cfg.examProducts || cfg.EXAM_PRODUCTS || {};
  const ALL_LANGS = cfg.availableLanguages || [
    'nl','en','de','fr','ar','bg','lt','pl','pt','ro','ru','tr','el','hu','it','hr','uk','sk','es','vi'
  ];

  // ---- Helpers --------------------------------------------------------------

  function normalizeExam(v) {
    if (!v) return v;
    switch (v) {
      case 'vca-basis': return 'los-examen-vca-basis';
      case 'vca-vol':   return 'los-examen-vca-vol';
      case 'los-examen-vil-vcu': return 'los-examen-vca-vil';
      default: return v;
    }
  }

  function getLanguagesForExam(examType) {
    const ex = normalizeExam(examType);
    // Voor VCA Basis: toon alle talen (zoals gevraagd)
    if (ex === 'los-examen-vca-basis') return ALL_LANGS.slice();
    const prices = (examProducts[ex] && examProducts[ex].prices) ? examProducts[ex].prices : {};
    const fromPrices = Object.keys(prices);
    return fromPrices.length ? fromPrices : ALL_LANGS.slice();
  }

  // Bouw desktop taal-select (eenmalig/idempotent)
  function ensureDesktopLanguageOptions() {
    const $lang = $('#language-select, select[name="language"]');
    if (!$lang.length) return;

    // als al eerder gedaan, of al genoeg opties, niets doen
    if ($lang.data('pfLangInit') === '1' && $lang.find('option').length > 5) return;

    const labels = (cfg.availableLanguageLabels || {});
    const all = (cfg.availableLanguages || ALL_LANGS);
    let html = '<option value="">Toon alles</option>';
    all.forEach(code => {
      const label = labels[code] || code.toUpperCase();
      html += `<option value="${code}">${label}</option>`;
    });

    $lang.html(html);
    $lang.attr('data-pfLangInit', '1');
  }

  // ---- Data ophalen + render -----------------------------------------------

  PontifexOI.updatePontifexTable = function(page = 1) {
    const filters = {
      exam_type: normalizeExam($('select[name="exam_type"], #exam_type-select').val()),
      language:  $('select[name="language"], #language-select').val(),
      month:     $('select[name="month"], #month-select').val(),
      province:  $('select[name="province"], #province-select').val(),
      location:  $('select[name="location"], #location-select').val(),
      timeslot:  $('select[name="timeslot"], #timeslot-select').val(),
      page
    };

    $.post((cfg.ajaxUrl || ''), {
      action: 'pontifex_oi_get_planning',
      filters
    }).done(function(res) {
      if (!res || !res.success) return;

      const planning = res.data?.planning || [];
      const cur = res.data?.current_page || 1;
      const tot = res.data?.total_pages || 1;
      const regUrl = (cfg.registrationPageUrl || '/cursus-inschrijven/');

      if (typeof PontifexOI.renderTableRows === 'function') {
        PontifexOI.renderTableRows(planning, cur, tot, regUrl);
      }
      if (typeof PontifexOI.renderCards === 'function') {
        PontifexOI.renderCards(planning, regUrl);
      }
      if (typeof PontifexOI.updateAllDynamicPrices === 'function') {
        PontifexOI.updateAllDynamicPrices();
      }
      if (typeof PontifexOI.updateFormInputsInTableRows === 'function') {
        PontifexOI.updateFormInputsInTableRows();
      }
      if (typeof PontifexOI.renderPagination === 'function') {
        PontifexOI.renderPagination(cur, tot);
      }
      if (typeof PontifexOI.initPaginationEvents === 'function') {
        PontifexOI.initPaginationEvents(function(nextPage) {
          PontifexOI.updatePontifexTable(nextPage);
        });
      }
    });
  };

  // ---- UI state -------------------------------------------------------------

  function updateFiltersState() {
    const $exam = $('select[name="exam_type"], #exam_type-select');
    const $lang = $('select[name="language"], #language-select');

    const examVal = normalizeExam($exam.val() || '');
    const prevLang = $lang.val() || '';

    $lang.prop('disabled', false);
    $lang.find('option').show();

    if (!examVal) {
      toggleWeekendCheckbox(false);
      return;
    }

    const allowedLangs = getLanguagesForExam(examVal);
    const shouldFilter = allowedLangs.length > 0 && allowedLangs.length !== ALL_LANGS.length;

    $lang.find('option').each(function() {
      const v = $(this).val();
      if (!v) { $(this).show(); return; }
      $(this).toggle(!shouldFilter || allowedLangs.includes(v));
    });

    if (prevLang && !allowedLangs.includes(prevLang)) {
      if (allowedLangs.includes('nl')) $lang.val('nl');
      else if (allowedLangs.length) $lang.val(allowedLangs[0]);
      else $lang.val('');
    }

    const currentLang = $lang.val() || '';
    const allowedForWeekend = weekendAllowedByExam[examVal] || [];
    toggleWeekendCheckbox(allowedForWeekend.includes(currentLang));
  }

  function toggleWeekendCheckbox(show) {
    const $opt1 = $('.extra-material input[value="cursus-weekend"]').closest('.extra-option');
    const $opt2 = $('#extra-material-checkboxes input[value="cursus-weekend"]').closest('label');
    if (show) {
      $opt1.show();
      $opt2.show();
    } else {
      $opt1.hide().find('input').prop('checked', false);
      $opt2.hide().find('input').prop('checked', false);
    }
  }

  function bindFilterHandlers() {
    $(document).on(
      'change',
      '.pontifex-oi-filter, select[name="exam_type"], select[name="language"], #exam_type-select, #language-select, #month-select, #province-select, #location-select, #timeslot-select',
      function() {
        // als het examen wijzigt → eerst zeker weten dat alle taalopties aanwezig zijn
        if ($(this).is('select[name="exam_type"], #exam_type-select')) {
          ensureDesktopLanguageOptions();
        }
        updateFiltersState();
        PontifexOI.updatePontifexTable(1);
      }
    );

    $(document).on('submit', '.pontifex-oi-filters', function(e) {
      e.preventDefault();
      updateFiltersState();
      PontifexOI.updatePontifexTable(1);
    });
  }

  // expose
  window.PontifexOI.updatePontifexTable = PontifexOI.updatePontifexTable;
  window.PontifexOI.updateFiltersState = updateFiltersState;
  window.PontifexOI.bindFilterHandlers = bindFilterHandlers;

  // init
  $(document).ready(function() {
    ensureDesktopLanguageOptions();
    bindFilterHandlers();
    updateFiltersState();
  });

})(window, jQuery);