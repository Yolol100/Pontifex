/**
 * sidebar.js
 *
 * Mobiele filter-sidebar; talen worden dynamisch beperkt o.b.v. beschikbare prijzen per examensoort.
 */
(function (window, $) {
  'use strict';

  if (!$ || !window) return;

  const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
  const PontifexOI = (window.PontifexOI = window.PontifexOI || {});
  const examProducts = cfg.examProducts || cfg.EXAM_PRODUCTS || {};

  const ALL_LANGS = cfg.availableLanguages || [];
  const LABELS = cfg.availableLanguageLabels || {};

  const mq = window.matchMedia('(max-width: 480px)');
  let isBound = false;

  function debounce(fn, wait) {
    let t;
    return function () {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, arguments), wait);
    };
  }

  function normalizeExam(v) {
    if (!v) return v;
    switch (v) {
      case 'vca-basis': return 'los-examen-vca-basis';
      case 'vca-vol':   return 'los-examen-vca-vol';
      case 'los-examen-vil-vcu': return 'los-examen-vca-vil';
      default: return v;
    }
  }

  function getExamLanguages(exam) {
    const ex = normalizeExam(exam);
    const prices = examProducts[ex] && examProducts[ex].prices ? examProducts[ex].prices : {};
    const fromPrices = Object.keys(prices);
    // Als er geen lijst in prijzen zit (of leeg) → val terug op alle talen
    return fromPrices.length ? fromPrices : ALL_LANGS.slice();
  }

  function buildLanguageOptionsHtml(allowed) {
    const options = [''].concat(allowed); // '' = Toon alles
    return options.map((code) => {
      if (code === '') return `<option value="">Toon alles</option>`;
      const label = LABELS[code] || code.toUpperCase();
      return `<option value="${code}">${label}</option>`;
    }).join('');
  }

  function buildSidebar() {
    if ($('.pontifex-oi-filters-toggle-btn').length) return;

    const $anchor = $('.pontifex-oi-filters');
    if (!$anchor.length) return;

    const sidebarHtml = `
      <button type="button" class="pontifex-oi-filters-toggle-btn">
        <i class="fas fa-filter" aria-hidden="true"></i> Filters
      </button>
      <div class="pontifex-oi-filters-sidebar-overlay" hidden></div>
      <aside class="pontifex-oi-filters-sidebar" aria-modal="true" role="dialog" tabindex="0" hidden>
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

    $(sidebarHtml).insertBefore($anchor);

    const mapping = [
      ['#exam_type-select', 'sidebar-exam_type'],
      ['#month-select', 'sidebar-month'],
      ['#province-select', 'sidebar-province'],
      ['#location-select', 'sidebar-location'],
      ['#timeslot-select', 'sidebar-timeslot']
    ];

    mapping.forEach(([desktopSel, sidebarId]) => {
      const $desk = $(desktopSel);
      const $side = $(`#${sidebarId}`);
      if ($desk.length && $side.length) {
        $side.html($desk.html());
        $side.val($desk.val());
      }
    });

    populateAndSyncSidebarLanguageOptions();
  }

  function destroySidebar() {
    $('.pontifex-oi-filters-toggle-btn, .pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').remove();
  }

  function setOpen(open) {
    const $aside = $('.pontifex-oi-filters-sidebar');
    const $overlay = $('.pontifex-oi-filters-sidebar-overlay');
    if (!$aside.length || !$overlay.length) return;

    if (open) {
      $aside.removeAttr('hidden').addClass('open').focus();
      $overlay.removeAttr('hidden').addClass('open');
    } else {
      $aside.addClass('closing');
      $overlay.addClass('closing');
      setTimeout(() => {
        $aside.removeClass('open closing').attr('hidden', '');
        $overlay.removeClass('open closing').attr('hidden', '');
      }, 150);
    }
  }

  function populateAndSyncSidebarLanguageOptions() {
    const $exam = $('#sidebar-exam_type');
    const $lang = $('#sidebar-language');
    const $desktopLang = $('#language-select');

    if (!$exam.length || !$lang.length) return;

    const examVal = $exam.val();
    const allowed = getExamLanguages(examVal);

    $lang.html(buildLanguageOptionsHtml(allowed));

    const desktopLangVal = $desktopLang.length ? $desktopLang.val() : '';
    if (desktopLangVal && (desktopLangVal === '' || allowed.includes(desktopLangVal))) {
      $lang.val(desktopLangVal);
    } else if (allowed.length) {
      $lang.val(allowed.includes('nl') ? 'nl' : allowed[0]);
    } else {
      $lang.val('');
    }

    $lang.prop('disabled', !examVal);
  }

  function updateSidebarLanguageOptions() {
    const $exam = $('#sidebar-exam_type');
    const $lang = $('#sidebar-language');
    if (!$exam.length || !$lang.length) return;

    const examId = normalizeExam($exam.val() || '');
    const prev = $lang.val();
    const allowed = getExamLanguages(examId);

    $lang.html(buildLanguageOptionsHtml(allowed));

    if (prev && allowed.includes(prev)) {
      $lang.val(prev);
    } else if (allowed.length) {
      $lang.val(allowed.includes('nl') ? 'nl' : allowed[0]);
    } else {
      $lang.val('');
    }

    $lang.prop('disabled', !examId);
  }

  function bindEventsOnce() {
    if (isBound) return;
    isBound = true;

    $(document).on('click', '.pontifex-oi-filters-toggle-btn', function (e) {
      e.preventDefault();
      ['exam_type', 'month', 'province', 'location', 'timeslot', 'language'].forEach(
        (name) => {
          const $desk = $(`#${name}-select`);
          const $side = $(`#sidebar-${name}`);
          if ($desk.length && $side.length) $side.val($desk.val());
        }
      );
      updateSidebarLanguageOptions();
      setOpen(true);
    });

    $(document).on(
      'click',
      '.pontifex-oi-filters-sidebar-overlay, .pontifex-oi-filters-sidebar-close',
      function (e) {
        e.preventDefault();
        setOpen(false);
      }
    );

    $(document).on('keydown', function (e) {
      if (e.key === 'Escape' && $('.pontifex-oi-filters-sidebar').hasClass('open')) {
        setOpen(false);
      }
    });

    $(document).on('click', '.pontifex-oi-reset-filter', function (e) {
      e.preventDefault();
      $('#sidebar-month, #sidebar-province, #sidebar-location, #sidebar-timeslot').val('');
    });

    $(document).on('change', '#sidebar-exam_type', updateSidebarLanguageOptions);

    $(document).on('click', '.pontifex-oi-save-btn', function (e) {
      e.preventDefault();

      ['exam_type', 'language', 'month', 'province', 'location', 'timeslot'].forEach(
        (name) => {
          const v = $(`#sidebar-${name}`).val();
          $(`#${name}-select`).val(v).trigger('change');
        }
      );

      if (window.PontifexOI && typeof window.PontifexOI.updatePontifexTable === 'function') {
        window.PontifexOI.updatePontifexTable(1);
      }

      setOpen(false);
    });

    $(document).on('change', '#exam_type-select', function () {
      const v = $(this).val();
      $('#sidebar-exam_type').val(v);
      updateSidebarLanguageOptions();
    });
  }

  PontifexOI.addAndPopulateSidebar = function () {
    if (mq.matches) {
      buildSidebar();
    } else {
      destroySidebar();
    }
  };

  PontifexOI.registerSidebarEvents = function () {
    bindEventsOnce();
  };

  $(function () {
    PontifexOI.addAndPopulateSidebar();
    PontifexOI.registerSidebarEvents();
  });

  const handleResize = debounce(PontifexOI.addAndPopulateSidebar, 120);
  $(window).on('resize', handleResize);
  if (mq.addEventListener) {
    mq.addEventListener('change', PontifexOI.addAndPopulateSidebar);
  } else if (mq.addListener) {
    mq.addListener(PontifexOI.addAndPopulateSidebar);
  }
})(window, jQuery);