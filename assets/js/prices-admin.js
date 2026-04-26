(function (window, document, $) {
  'use strict';

  // vroegtijdig stoppen als jQuery niet aanwezig is
  if (typeof $ !== 'function') return;

  $(function () {
    const $app = $('#pontifex-prices-app');
    const $input = $('#pontifex_oi_prices');

    // beide elementen moeten bestaan
    if (!$app.length || !$input.length) return;

    const I18N = window.POI_PRICES_DATA?.i18n || {};
    const t = (key, defaultText) => (I18N[key] || defaultText || key);

    // Veiliger JSON parsen
    const safeJsonParse = (value, fallback = { courses: [] }) => {
      try {
        const parsed = JSON.parse(value);
        return parsed && typeof parsed === 'object' ? parsed : fallback;
      } catch {
        return fallback;
      }
    };

    let state = safeJsonParse($app.data('initial'));

    const ensureState = () => {
      if (!state || typeof state !== 'object') state = { courses: [] };
      if (!Array.isArray(state.courses)) state.courses = [];

      if (state.courses.length === 0) {
        state.courses.push({
          name: '',
          languages: { nl: '' },
          _currentLang: 'nl'
        });
      }
    };

    const saveHidden = () => {
      $input.val(JSON.stringify(state));
    };

    const addCourse = (afterIndex) => {
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
    };

    const render = () => {
      ensureState();
      $app.empty();

      state.courses.forEach((course, idx) => {
        // robuuste fallback voor corrupte data
        if (!course || typeof course !== 'object') return;

        course.languages = course.languages && typeof course.languages === 'object'
          ? course.languages
          : { nl: '' };

        const langKeys = Object.keys(course.languages);
        if (!course._currentLang || !langKeys.includes(course._currentLang)) {
          course._currentLang = langKeys[0] || 'nl';
        }

        // ROW
        const $row = $('<div class="pontifex-row" />');

        // 1. Cursusnaam
        const $nameInput = $('<input type="text" class="pontifex-admin-input" />')
          .attr('placeholder', t('course', 'Cursus'))
          .val(course.name || '')
          .on('input', function () {
            course.name = this.value;
            saveHidden();
          });

        // 2. Taal selectie + knoppen
        const $langSelect = $('<select class="pontifex-admin-input" />').on('change', function () {
          course._currentLang = this.value;
          $priceInput.val(course.languages[this.value] || '');
          saveHidden();
        });

        // vul talen
        langKeys.forEach(code => {
          $langSelect.append(
            $('<option>').val(code).text(code.toUpperCase())
          );
        });
        $langSelect.val(course._currentLang);

        const $addLangBtn = $('<button type="button" class="pontifex-icon-btn" title="'+t('add','Toevoegen')+'">+</button>')
          .on('click', function () {
            const codeRaw = prompt(t('add_lang_prompt', 'Voer taalcode in (bijv. nl, en, fr):'));
            if (!codeRaw) return;

            const code = codeRaw.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (!code || course.languages[code] !== undefined) {
              alert(I18N.lang_exists || 'Taalcode ongeldig of bestaat al.');
              return;
            }

            course.languages[code] = '';
            course._currentLang = code;
            render();
            saveHidden();
          });

        const $removeLangBtn = $('<button type="button" class="pontifex-icon-btn" title="'+t('remove','Verwijderen')+'">−</button>')
          .on('click', function () {
            if (Object.keys(course.languages).length <= 1) {
              alert(I18N.need_one_lang || 'Minimaal één taal is verplicht.');
              return;
            }
            delete course.languages[course._currentLang];
            course._currentLang = Object.keys(course.languages)[0];
            render();
            saveHidden();
          });

        const $langActions = $('<div class="pontifex-lang-actions" />')
          .append($langSelect, $addLangBtn, $removeLangBtn);

        // 3. Prijs input
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
              this.value = num.toFixed(2);
              course.languages[course._currentLang] = this.value;
              saveHidden();
            }
          });

        // 4. Acties (+/- cursus)
        const $addCourseBtn = $('<button type="button" class="pontifex-icon-btn" title="'+t('add','Toevoegen')+'">+</button>')
          .on('click', function () {
            addCourse(idx);
            render();
            saveHidden();
          });

        const $removeCourseBtn = $('<button type="button" class="pontifex-icon-btn" title="'+t('remove','Verwijderen')+'">−</button>')
          .on('click', function () {
            if (state.courses.length <= 1) {
              alert(I18N.need_one_course || 'Minimaal één cursus is verplicht.');
              return;
            }
            state.courses.splice(idx, 1);
            render();
            saveHidden();
          });

        const $actionsCol = $('<div class="pontifex-prices-actions-col" />')
          .append($addCourseBtn, $removeCourseBtn);

        // samenstellen
        $row.append(
          $('<div>').append($nameInput),
          $('<div>').append($langActions),
          $('<div>').append($priceInput),
          $actionsCol
        );

        $app.append($row);
      });

      // Globale "nieuwe cursus" knop buiten de loop
      $('.pontifex-add-course')
        .off('click.pontifexOI')
        .on('click.pontifexOI', function () {
          addCourse();
          render();
          saveHidden();
        });

      // altijd up-to-date houden
      saveHidden();
    };

    // start!
    render();
  });

})(window, document, window.jQuery);