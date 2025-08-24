jQuery(function($){
  'use strict';

  const $app   = $('#pontifex-prices-app');
  const $input = $('#pontifex_oi_prices');

  const I18N = (window.POI_PRICES_DATA && POI_PRICES_DATA.i18n) || {};
  const t = (k, d)=> (I18N[k] || d || k);

  let state = (function(){
    try { return JSON.parse($app.data('initial')); } catch(e){ return {courses:[]}; }
  })();

  function ensureState(){
    if (!state || typeof state !== 'object') state = { courses: [] };
    if (!Array.isArray(state.courses)) state.courses = [];
    if (state.courses.length === 0) state.courses.push({ name:'', languages:{ nl:'' }, _currentLang:'nl' });
  }

  function saveHidden(){ $input.val(JSON.stringify(state)); }

  function addCourse(afterIndex){
    const c = { name:'', languages:{ nl:'' }, _currentLang:'nl' };
    if (typeof afterIndex === 'number') state.courses.splice(afterIndex+1, 0, c);
    else state.courses.push(c);
  }

  function render(){
    ensureState();
    $app.empty();

    state.courses.forEach((course, idx)=>{
      if (!course.languages || typeof course.languages !== 'object') course.languages = { nl:'' };
      const langKeys = Object.keys(course.languages);
      if (!course._currentLang || !course.languages[course._currentLang]) course._currentLang = langKeys[0];

      const $row = $('<div class="pontifex-row" />');

      // NAAM
      const $name = $('<input type="text" class="pontifex-admin-input" placeholder="'+t('course','Cursus')+'"/>')
        .val(course.name || '')
        .on('input', function(){ course.name = $(this).val(); saveHidden(); });

      // TAAL
      const $select = $('<select class="pontifex-admin-input" />').on('change', function(){
        course._currentLang = $(this).val();
        $price.val(course.languages[course._currentLang] || '');
      });
      Object.keys(course.languages).forEach(code=>{
        $select.append($('<option/>').attr('value', code).text(code.toUpperCase()));
      });
      $select.val(course._currentLang);

      // TAAL +/-
      const $addLang = $('<button type="button" class="pontifex-icon-btn" title="'+t('add','Toevoegen')+'">+</button>')
        .on('click', function(){
          const code = prompt(t('add_lang_prompt','Voer taalcode in (bijv. nl, en, fr):'));
          if (!code) return;
          const key = code.trim().toLowerCase().replace(/[^a-z0-9_-]/g,'');
          if (!key) return;
          if (course.languages[key] !== undefined) { alert(I18N.lang_exists || 'Taal bestaat al.'); return; }
          course.languages[key] = '';
          course._currentLang = key;
          render(); saveHidden();
        });

      const $remLang = $('<button type="button" class="pontifex-icon-btn" title="'+t('remove','Verwijderen')+'">−</button>')
        .on('click', function(){
          const keys = Object.keys(course.languages);
          if (keys.length <= 1) { alert(I18N.need_one_lang || 'Minimaal één taal nodig.'); return; }
          delete course.languages[course._currentLang];
          course._currentLang = Object.keys(course.languages)[0];
          render(); saveHidden();
        });

      const $langWrap = $('<div class="pontifex-lang-actions" />').append($select, $addLang, $remLang);

      // PRIJS
      const $price = $('<input type="number" step="0.01" min="0" class="pontifex-admin-input" placeholder="'+t('price','Prijs')+'"/>')
        .val(course.languages[course._currentLang] || '')
        .on('input', function(){ course.languages[course._currentLang] = $(this).val(); saveHidden(); })
        .on('blur', function(){
          const v = $(this).val();
          if (v !== '') {
            let n = parseFloat(String(v).replace(',', '.'));
            if (!isNaN(n) && n >= 0) {
              $(this).val(n.toFixed(2));
              course.languages[course._currentLang] = $(this).val();
              saveHidden();
            }
          }
        });

      // RIJ +/-
      const $addCourse = $('<button type="button" class="pontifex-icon-btn" title="'+t('add','Toevoegen')+'">+</button>')
        .on('click', function(){ addCourse(idx); render(); saveHidden(); });
      const $remCourse = $('<button type="button" class="pontifex-icon-btn" title="'+t('remove','Verwijderen')+'">−</button>')
        .on('click', function(){
          if (state.courses.length <= 1) { alert(I18N.need_one_course || 'Minimaal één cursus nodig.'); return; }
          state.courses.splice(idx, 1);
          render(); saveHidden();
        });

      // GRID: Naam | Taal | Prijs | Acties
      $row.append($('<div/>').append($name));
      $row.append($('<div/>').append($langWrap));
      $row.append($('<div/>').append($price));
      $row.append($('<div class="pontifex-prices-actions-col"/>').append($addCourse, ' ', $remCourse));

      $app.append($row);
    });

    $('.pontifex-add-course').off('click').on('click', function(){ addCourse(); render(); saveHidden(); });

    saveHidden();
  }

  render();
});