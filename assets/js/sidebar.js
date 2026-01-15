(function (window, $) {
  'use strict';

  if (typeof $ !== 'function' || !window) return;

  const cfg = window.PontifexOIConfigData ?? window.PontifexOIConfig ?? {};
  const PontifexOI = (window.PontifexOI = window.PontifexOI || {});

  const examProducts = cfg.examProducts ?? cfg.EXAM_PRODUCTS ?? {};
  const ALL_LANGS = cfg.availableLanguages ?? [];
  const LABELS = cfg.availableLanguageLabels ?? {};

  const mq = window.matchMedia('(max-width: 480px)');

  let isBound = false;
  let _isSyncing = false;
  PontifexOI._isSyncing = false; // voor debugging / externe controle

  // ====================
  //  Helpers
  // ====================
  function getExamLanguages(exam) {
    const ex = PontifexOI.normalizeExam?.(exam) ?? exam;
    const prices = examProducts[ex]?.prices ?? {};
    const langs = Object.keys(prices);
    return langs.length ? langs : ALL_LANGS.slice();
  }

  function buildLanguageOptionsHtml(allowed) {
    const options = ['', ...allowed];
    return options
      .map(code => {
        if (!code) return '<option value="">Kies taal</option>';
        const label = LABELS[code] ?? code.toUpperCase();
        return `<option value="${code}">${label}</option>`;
      })
      .join('');
  }

  // ====================
  //  Sidebar opbouwen
  // ====================
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
        <button class="pontifex-oi-filters-sidebar-close" aria-label="Sluit filters">×</button>
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
          <div class="sidebar-material-row">
            <label for="sidebar-material">Soort cursus</label>
            <select id="sidebar-material" name="material"></select>
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
              <label for="sidebar-timeslot">Dagdeel</label>
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
  }

  // ====================
  //  Sync & populatie logica
  // ====================
  function populateAndSyncSidebarLanguageOptions() {
    const $exam = $('#sidebar-exam_type');
    const $lang = $('#sidebar-language');
    const $desktopLang = $('#language-select');

    if (!$exam.length || !$lang.length) return;

    const examVal = $exam.val() || '';
    const allowed = getExamLanguages(examVal);

    $lang.html(buildLanguageOptionsHtml(allowed));

    const desktopVal = $desktopLang.val() || '';

    if (!desktopVal) {
      $lang.val(allowed.includes('nl') ? 'nl' : (allowed[0] ?? ''));
    } else if (allowed.includes(desktopVal)) {
      $lang.val(desktopVal);
    } else {
      $lang.val(allowed.includes('nl') ? 'nl' : (allowed[0] ?? ''));
    }

    $lang.prop('disabled', !examVal);
  }

  function updateSidebarLanguageOptions() {
    const $exam = $('#sidebar-exam_type');
    const $lang = $('#sidebar-language');

    if (!$exam.length || !$lang.length) return;

    const current = $lang.val();
    const allowed = getExamLanguages($exam.val() || '');

    $lang.html(buildLanguageOptionsHtml(allowed));

    if (current && allowed.includes(current)) {
      $lang.val(current);
    } else {
      $lang.val(allowed.includes('nl') ? 'nl' : (allowed[0] ?? ''));
    }

    $lang.prop('disabled', !$exam.val());
  }

  function syncSidebarToDesktop() {
    if (_isSyncing) return;

    _isSyncing = PontifexOI._isSyncing = true;

    const fields = ['exam_type', 'language', 'material', 'month', 'province', 'location', 'timeslot'];

    fields.forEach(name => {
      const val = $(`#sidebar-${name}`).val() ?? '';
      const $desk = $(`#${name}-select`);

      if (!$desk.length) return;

      if (name === 'material') {
        $desk.attr('data-selected-value', val);
      }

      if ($desk.val() !== val) {
        $desk.val(val).trigger('change');
      }
    });

    setTimeout(() => {
      _isSyncing = PontifexOI._isSyncing = false;
    }, 80);
  }

  // ====================
  //  Events (eenmalig binden)
  // ====================
  function bindEventsOnce() {
    if (isBound) return;
    isBound = true;

    // Open sidebar + sync van desktop → sidebar
    $(document).on('click', '.pontifex-oi-filters-toggle-btn', function (e) {
      e.preventDefault();

      // Sync huidige desktop waarden naar sidebar
      ['exam_type', 'language', 'month', 'province', 'location', 'timeslot'].forEach(name => {
        const v = $(`#${name}-select`).val();
        $(`#sidebar-${name}`).val(v);
      });

      // Speciale behandeling material
      const $sideMat = $('#sidebar-material');
      const remember =
        $sideMat.val() ||
        $sideMat.attr('data-selected-value') ||
        $('#material-select').attr('data-selected-value') ||
        $('#material-select').val() ||
        '';

      if (remember) $sideMat.val(remember).attr('data-selected-value', remember);

      updateSidebarLanguageOptions();
      setOpen(true);
    });

    // Sluiten via overlay / kruisje / ESC → mét toepassen
    const closeAndApply = (e) => {
      if (e) e.preventDefault();
      syncSidebarToDesktop();
      PontifexOI.updatePontifexTable?.(1);
      setOpen(false);
    };

    $(document).on('click', '.pontifex-oi-filters-sidebar-overlay, .pontifex-oi-filters-sidebar-close', closeAndApply);

    $(document).on('keydown', e => {
      if (e.key === 'Escape' && $('.pontifex-oi-filters-sidebar').hasClass('open')) {
        closeAndApply(e);
      }
    });

    // Taal bijwerken bij exam-keuze
    $(document).on('change', '#sidebar-exam_type', updateSidebarLanguageOptions);

    // Save knop
    $(document).on('click', '.pontifex-oi-save-btn', function (e) {
      e.preventDefault();
      syncSidebarToDesktop();
      PontifexOI.updatePontifexTable?.(1);
      setOpen(false);
    });

    // Weekendcursus automatisering (met MutationObserver fix)
    $(document).on('change', '#sidebar-material', function () {
      const val = (this.value || '').toLowerCase();
      if (!val.includes('weekend')) {
        $('#sidebar-province, #sidebar-location, #sidebar-timeslot')
          .prop('disabled', false)
          .val('');
        PontifexOI.updatePontifexTable?.(1);
        return;
      }

      _isSyncing = PontifexOI._isSyncing = true;

      const lock = $el => ($el.prop('disabled', true), $el);

      $('#sidebar-province').val('Zuid-Holland').trigger('change');

      const $loc = $('#sidebar-location');
      const obs = new MutationObserver(() => {
        if ($loc.find('option').length > 1) {
          obs.disconnect();
          const $denHaag = $loc.find('option').filter((_, el) =>
            (el.textContent || '').toLowerCase().includes('den haag')
          ).first();

          $loc.val($denHaag.length ? $denHaag.val() : 'Den Haag');
          lock($loc);
          lock($('#sidebar-timeslot').val('ochtend')).trigger('change');
        }
      });

      if ($loc[0]) obs.observe($loc[0], { childList: true });

      setTimeout(() => { _isSyncing = PontifexOI._isSyncing = false; }, 150);
    });

    // Optioneel: desktop → sidebar sync bij desktop wijziging (voor taal)
    $(document).on('change', '#exam_type-select', function () {
      $('#sidebar-exam_type').val(this.value);
      updateSidebarLanguageOptions();
    });
  }

  function setOpen(open) {
    const $aside = $('.pontifex-oi-filters-sidebar');
    const $overlay = $('.pontifex-oi-filters-sidebar-overlay');

    if (!$aside.length) return;

    if (open) {
      $aside.removeAttr('hidden').addClass('open').focus();
      $overlay.removeAttr('hidden').addClass('open');
    } else {
      $aside.addClass('closing');
      $overlay.addClass('closing');
      setTimeout(() => {
        $aside.removeClass('open closing').attr('hidden', '');
        $overlay.removeClass('open closing').attr('hidden', '');
      }, 160);
    }
  }

  function destroySidebar() {
    $('.pontifex-oi-filters-toggle-btn, .pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').remove();
  }

  // ====================
  //  Public API / Init
  // ====================
  PontifexOI.addAndPopulateSidebar = function () {
    if (mq.matches) {
      buildSidebar();
      // Vul selects met desktop waarden
      const mapping = [
        ['#exam_type-select', '#sidebar-exam_type'],
        ['#material-select', '#sidebar-material'],
        ['#month-select', '#sidebar-month'],
        ['#province-select', '#sidebar-province'],
        ['#location-select', '#sidebar-location'],
        ['#timeslot-select', '#sidebar-timeslot'],
      ];

      mapping.forEach(([deskSel, sideSel]) => {
        const $desk = $(deskSel);
        const $side = $(sideSel);
        if (!$desk.length || !$side.length) return;

        $side.html($desk.html());
        const val = $desk.val();
        if (val) $side.val(val);
      });

      populateAndSyncSidebarLanguageOptions();
    } else {
      destroySidebar();
    }
  };

  PontifexOI.registerSidebarEvents = bindEventsOnce;

  // Initialisatie
  $(() => {
    PontifexOI.addAndPopulateSidebar();
    PontifexOI.registerSidebarEvents();

    // Zorg dat material altijd een stabiele waarde heeft
    const $mat = $('#material-select');
    if ($mat.length && !$mat.attr('data-selected-value')) {
      $mat.attr('data-selected-value', $mat.val() || '');
    }
  });

  // Resize handling met veilige debounce fallback
  const safeDebounce = PontifexOI.debounce ?? (fn => fn);
  const handleResize = safeDebounce(PontifexOI.addAndPopulateSidebar, 120);

  $(window).on('resize', handleResize);

  if (mq.addEventListener) {
    mq.addEventListener('change', PontifexOI.addAndPopulateSidebar);
  } else if (mq.addListener) {
    mq.addListener(PontifexOI.addAndPopulateSidebar);
  }

})(window, jQuery);