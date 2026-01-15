/* pontifex-oi-admin.js — 2026 style (behavior-preserving)
 * Generated: 2026-01-15
 * Doel: Beheer prijzen, Mollie instellingen, SOAP fetch en inzendingen in WP admin
 */
jQuery(function ($) {
  'use strict';

  // Alleen uitvoeren in wp-admin
  if (!window.location.pathname.includes('/wp-admin/')) {
    console.log('[Pontifex OI] Not in admin — skipping admin logic');
    return;
  }

  console.log('[Pontifex OI] Admin detected — running admin logic');

  // ===================== Config & Globals =====================
  const I18N = window.POI_PRICES_DATA?.i18n ?? {};
  const t = (key, fallback) => I18N[key] ?? fallback ?? key;

  const ajaxurl = window.PontifexOIAdmin?.ajaxUrl ?? '';
  const ajaxNonce = window.PontifexOIAdmin?.nonce ?? '';

  let currentSubmissionRequest = null;

  // ===================== Utils =====================
  const debounce = (fn, delay = 150) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  };

  // ===================== Prices Management =====================
  const $app = $('#pontifex-prices-app');
  const $hiddenInput = $('#pontifex_oi_prices');

  let state = (() => {
    try {
      const raw = $app.data('initial');
      const parsed = raw ? JSON.parse(raw) : {};
      return (parsed && typeof parsed === 'object') ? parsed : {};
    } catch {
      return {};
    }
  })();

  function ensureState() {
    if (!state || typeof state !== 'object') state = {};
    if (!Array.isArray(state.courses)) state.courses = [];

    if (state.courses.length === 0) {
      state.courses.push({
        name: '',
        languages: { nl: '' },
        _currentLang: 'nl'
      });
    }

    state.courses.forEach(course => {
      if (!course || typeof course !== 'object') return;
      if (!course.languages || typeof course.languages !== 'object') {
        course.languages = { nl: '' };
      }
      const keys = Object.keys(course.languages);
      if (!course._currentLang || !keys.includes(course._currentLang)) {
        course._currentLang = keys[0] || 'nl';
      }
    });
  }

  const saveHidden = debounce(() => {
    try {
      $hiddenInput.val(JSON.stringify(state));
    } catch {}
  }, 150);

  function addCourse(afterIndex) {
    const newCourse = {
      name: '',
      languages: { nl: '' },
      _currentLang: 'nl'
    };

    if (typeof afterIndex === 'number' && afterIndex >= 0) {
      state.courses.splice(afterIndex + 1, 0, newCourse);
    } else {
      state.courses.push(newCourse);
    }
  }

  function renderPrices() {
    ensureState();
    $app.empty();

    state.courses.forEach((course, idx) => {
      const $row = $('<div class="pontifex-row" />');

      // Naam
      const $nameInput = $('<input type="text" class="pontifex-admin-input" />')
        .attr('placeholder', t('course', 'Cursus'))
        .val(course.name || '')
        .on('input', function () {
          course.name = this.value;
          saveHidden();
        });

      // Taal selectie
      const $langSelect = $('<select class="pontifex-admin-input" />')
        .on('change', function () {
          course._currentLang = this.value;
          $priceInput.val(course.languages[this.value] || '');
          saveHidden();
        });

      Object.keys(course.languages).forEach(code => {
        $langSelect.append(
          $('<option>').val(code).text(code.toUpperCase())
        );
      });
      $langSelect.val(course._currentLang);

      // Taal knoppen
      const $addLangBtn = $('<button type="button" class="pontifex-icon-btn" title="' + t('add', 'Toevoegen') + '">+</button>')
        .on('click', () => {
          // TODO: Vervang prompt door nette modal in productie
          const codeRaw = prompt(t('add_lang_prompt', 'Voer taalcode in (bijv. nl, en, fr):'));
          if (!codeRaw) return;

          const code = codeRaw.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
          if (!code || code in course.languages) {
            alert(t('lang_exists', 'Taalcode ongeldig of bestaat al.'));
            return;
          }

          course.languages[code] = '';
          course._currentLang = code;
          renderPrices();
          saveHidden();
        });

      const $removeLangBtn = $('<button type="button" class="pontifex-icon-btn" title="' + t('remove', 'Verwijderen') + '">−</button>')
        .on('click', () => {
          const keys = Object.keys(course.languages);
          if (keys.length <= 1) {
            alert(t('need_one_lang', 'Minimaal één taal verplicht.'));
            return;
          }
          // TODO: Vervang confirm door nette modal
          if (!confirm(t('confirm_remove_lang', 'Weet u zeker?'))) return;

          delete course.languages[course._currentLang];
          course._currentLang = Object.keys(course.languages)[0];
          renderPrices();
          saveHidden();
        });

      const $langActions = $('<div class="pontifex-lang-actions" />')
        .append($langSelect, $addLangBtn, $removeLangBtn);

      // Prijs
      const $priceInput = $('<input type="number" step="0.01" min="0" class="pontifex-admin-input" />')
        .attr('placeholder', t('price', 'Prijs'))
        .val(course.languages[course._currentLang] || '')
        .on('input', function () {
          course.languages[course._currentLang] = this.value;
          saveHidden();
        })
        .on('blur', function () {
          let val = this.value.trim();
          if (!val) return;

          val = val.replace(',', '.');
          const num = parseFloat(val);
          if (!isNaN(num) && num >= 0) {
            const fixed = num.toFixed(2);
            this.value = fixed;
            course.languages[course._currentLang] = fixed;
            saveHidden();
          }
        });

      // Cursus actieknoppen
      const $addCourseBtn = $('<button type="button" class="pontifex-icon-btn" title="' + t('add', 'Toevoegen') + '">+</button>')
        .on('click', () => {
          addCourse(idx);
          renderPrices();
          saveHidden();
        });

      const $removeCourseBtn = $('<button type="button" class="pontifex-icon-btn" title="' + t('remove', 'Verwijderen') + '">−</button>')
        .on('click', () => {
          if (state.courses.length <= 1) {
            alert(t('need_one_course', 'Minimaal één cursus verplicht.'));
            return;
          }
          // TODO: Vervang confirm door nette modal
          if (!confirm(t('confirm_remove_course', 'Weet u zeker?'))) return;

          state.courses.splice(idx, 1);
          renderPrices();
          saveHidden();
        });

      $row.append(
        $('<div>').append($nameInput),
        $('<div>').append($langActions),
        $('<div>').append($priceInput),
        $('<div class="pontifex-prices-actions-col">').append($addCourseBtn, $removeCourseBtn)
      );

      $app.append($row);
    });

    $('.pontifex-add-course')
      .off('click.pontifex')
      .on('click.pontifex', () => {
        addCourse();
        renderPrices();
        saveHidden();
      });

    saveHidden();
  }

  // ===================== Overige Admin Setup =====================
  function setupSuccessNotice() {
    const $notice = $('#message.updated.notice-success');
    if ($notice.length) {
      setTimeout(() => {
        $notice.addClass('hide')
          .one('transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', () => $notice.remove());
      }, 3500);
    }
  }

  function setupTestModeToggle() {
    const $checkbox = $('#pontifex_oi_mollie_test_mode');
    const $switch = $checkbox.parent('.pontifex-toggle-switch');
    const $label = $('.pontifex-toggle-label');
    const $hidden = $('input[type="hidden"][name="pontifex_oi_mollie_test_mode"]');

    if (!$checkbox.length || !$switch.length) return;

    const updateUI = (checked) => {
      $switch.toggleClass('checked', checked);
      $label.text(checked ? t('enabled', 'ingeschakeld') : t('disabled', 'uitgeschakeld'));
      $hidden.val(checked ? '1' : '0');
    };

    updateUI($checkbox.prop('checked'));

    $checkbox.on('change', function () {
      updateUI(this.checked);
    });
  }

  function setupMollieValidation() {
    $('form[action="options.php"]').on('submit', function () {
      const liveKey = $('#pontifex_oi_mollie_live_api_key').val()?.trim() ?? '';
      const testKey = $('#pontifex_oi_mollie_test_api_key').val()?.trim() ?? '';

      if (liveKey && !liveKey.startsWith('live_')) {
        console.warn(t('live_key_warning', 'Live API key begint normaal met "live_"'));
      }
      if (testKey && !testKey.startsWith('test_')) {
        console.warn(t('test_key_warning', 'Test API key begint normaal met "test_"'));
      }

      return true; // altijd doorlaten
    });
  }

  function setupSoapFetch() {
    const $btn = $('#pontifex-soap-fetch-btn');
    const $feedback = $('#pontifex-soap-fetch-feedback');

    if (!$btn.length) return;

    let timeoutId;

    const showMessage = (html, isError = false) => {
      clearTimeout(timeoutId);
      $feedback.html(html).addClass('active').show();

      timeoutId = setTimeout(() => {
        $feedback.removeClass('active')
          .one('transitionend', () => $feedback.html('').hide());
      }, 5000);
    };

    $btn.on('click', function (e) {
      e.preventDefault();
      $btn.prop('disabled', true).text(t('processing', 'Bezig...'));

      const data = {
        action: 'pontifex_oi_fetch_soap_data',
        nonce: ajaxNonce,
        soap_url: $('#pontifex_oi_soap_url').val() ?? '',
        user_id: $('#pontifex_oi_soap_user_id').val() ?? '',
        company_id: $('#pontifex_oi_soap_company_id').val() ?? '',
        hash: $('#pontifex_oi_soap_hash').val() ?? ''
      };

      $.ajax({
        url: ajaxurl,
        method: 'POST',
        dataType: 'json',
        data,
        success: (res) => {
          $btn.prop('disabled', false).text(t('fetch_data', 'Haal gegevens op'));

          const msg = res?.success
            ? `<div class="pontifex-soap-feedback success"><span class="icon">✔</span> ${t('fetch_success', 'Gegevens succesvol opgehaald')}</div>`
            : `<div class="pontifex-soap-feedback error"><span class="icon">✖</span> ${res?.data?.message ?? t('fetch_failed', 'Ophalen mislukt')}</div>`;

          showMessage(msg, !res?.success);
        },
        error: (xhr) => {
          $btn.prop('disabled', false).text(t('fetch_data', 'Haal gegevens op'));
          showMessage(
            `<div class="pontifex-soap-feedback error"><span class="icon">✖</span> ${xhr?.responseJSON?.data?.message ?? t('fetch_failed', 'Ophalen mislukt')}</div>`,
            true
          );
        }
      });
    });
  }

  // ===================== Inzendingen / Submissions =====================
  function setupSubmissionsAjax() {
    const $grid = $('#poi_sub_cardgrid');
    if (!$grid.length) return;

    const $pagination = $('#poi_sub_pagination');
    const $pageInfo = $('#poi_sub_pageinfo');
    const $perPage = $('#poi_sub_perpage');
    const $search = $('#poi_sub_s');

    function fetchSubmissions(page = 1) {
      $grid.attr('aria-busy', 'true').html(`
        <div class="pontifex-oi-card-item pontifex-loading">
          <div class="pontifex-oi-card-header">${t('loading', 'Laden...')}</div>
          <div class="pontifex-oi-card-body">${t('please_wait', 'Even geduld...')}</div>
        </div>
      `);

      if (currentSubmissionRequest?.readyState !== 4) {
        currentSubmissionRequest.abort();
      }

      currentSubmissionRequest = $.ajax({
        url: ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'pontifex_oi_fetch_submissions',
          nonce: ajaxNonce,
          paged: page,
          per_page: parseInt($perPage.val(), 10) || 10,
          s: $search.val()?.trim() ?? ''
        }
      })
      .done(res => {
        $grid.removeAttr('aria-busy');

        if (!res?.success) {
          $grid.html(`
            <div class="pontifex-oi-card-item">
              <div class="pontifex-oi-card-header">${t('error', 'Fout')}</div>
              <div class="pontifex-oi-card-body">${res?.data?.message ?? t('loading_error_generic', 'Er ging iets mis')}</div>
            </div>
          `);
          return;
        }

        const data = {
          rows: res.data?.rows ?? [],
          total: res.data?.total ?? 0,
          page: res.data?.page ?? 1,
          pages: res.data?.pages ?? 1
        };

        renderCards(data);

        $pageInfo.text(`${data.page} / ${data.pages}`);
        $pagination.toggle(data.pages > 1);
        $pagination.find('[data-direction="prev"]').prop('disabled', data.page <= 1);
        $pagination.find('[data-direction="next"]').prop('disabled', data.page >= data.pages);
      })
      .fail((xhr, status) => {
        if (status === 'abort') return;
        $grid.removeAttr('aria-busy').html(`
          <div class="pontifex-oi-card-item">
            <div class="pontifex-oi-card-header">${t('error', 'Fout')}</div>
            <div class="pontifex-oi-card-body">${t('loading_error_generic', 'Fout bij laden')} (${xhr.status})</div>
          </div>
        `);
      });
    }

    function renderCards({ rows = [] }) {
      $grid.empty();

      if (rows.length === 0) {
        $grid.html(`
          <div class="pontifex-oi-card-item">
            <div class="pontifex-oi-card-body">
              <strong>${t('no_submissions_found', 'Geen inzendingen gevonden')}</strong><br>
              <em>${t('try_another_search', 'Probeer een andere zoekopdracht')}</em>
            </div>
          </div>
        `);
        return;
      }

      rows.forEach((row, i) => {
        if (!row) return;

        const title = row.title ?? `${t('submission', 'Inzending')} #${i + 1}`;
        const fields = Array.isArray(row.fields) ? row.fields : [];

        const content = fields
          .map(f => {
            const label = f?.label ?? '';
            const value = f?.value ?? '';
            return label || value ? `<div><strong>${label}:</strong> ${value}</div>` : '';
          })
          .filter(Boolean)
          .join('');

        $grid.append(`
          <div class="pontifex-oi-card-item">
            <div class="pontifex-oi-card-header">${title}</div>
            <div class="pontifex-oi-card-body">${content || t('no_data', 'Geen gegevens beschikbaar')}</div>
          </div>
        `);
      });
    }

    // Events
    $('#poi_sub_apply').on('click', e => {
      e.preventDefault();
      fetchSubmissions(1);
    });

    $perPage.on('change', () => fetchSubmissions(1));

    $search.on('input', debounce(() => fetchSubmissions(1), 400));

    $search.on('keypress', e => {
      if (e.which === 13) {
        e.preventDefault();
        fetchSubmissions(1);
      }
    });

    $pagination.find('button').on('click', function () {
      const dir = $(this).data('direction');
      const current = parseInt($pageInfo.text().match(/^(\d+)/)?.[1] ?? 1, 10);
      fetchSubmissions(dir === 'next' ? current + 1 : current - 1);
    });

    // Start
    fetchSubmissions(1);
  }

  // ===================== Save Button Protection =====================
  function setupSaveButtonProtection() {
    const $form = $('form[action="options.php"]');
    const $submit = $('.pontifex-admin-submit');

    $form.on('submit', () => {
      $submit.prop('disabled', true).text(t('saving', 'Opslaan...'));
    });
  }

  // ===================== Initialisatie =====================
  function init() {
    setupSuccessNotice();
    setupTestModeToggle();
    setupMollieValidation();
    setupSoapFetch();
    setupSubmissionsAjax();
    setupSaveButtonProtection();

    // Prijzen altijd als laatste renderen
    renderPrices();
  }

  init();
});