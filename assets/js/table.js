// table.js
(function(window) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};
  const $ = window.jQuery;

  PontifexOI.renderTableRows = function(planning, curPage, totPages, registrationPageUrl = '/cursus-inschrijven/') {
    if (!$) return;

    const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
    const selectedLanguage = $('select[name="language"]').val() || 'nl';
    const selectedMaterial = $('select[name="material"]').val() || '1';

    let html = '';
    if (planning.length === 0) {
      html = `<tr>
        <td colspan="7" class="pontifex-oi-no-results-row">
          <div class="pontifex-oi-no-results-table-block">
            <div class="pontifex-oi-card-noresults-header">Geen resultaten</div>
            <div class="pontifex-oi-card-noresults-body">
              Geen examens gevonden.<br>
              Pas de filters aan voor een andere combinatie.
            </div>
          </div>
        </td>
      </tr>`;
    } else {
      planning.forEach(function(row) {
        const rowExam = row.exam || selectedExamType;
        const rowLanguage = row.language || selectedLanguage;
        const rowMaterial = row.material || selectedMaterial;

        const prijsLoader = `<span class="pontifex-oi-dynamic-price"
          data-date="${row.date || ''}"
          data-time="${row.time || ''}"
          data-location="${row.location || ''}"
          data-exam="${rowExam}"
          data-language="${rowLanguage}"
          data-material="${rowMaterial}">
            <span class="pontifex-oi-price-loader" style="min-width:40px;">…</span>
        </span>`;

        const actionBtn = (row.spots && row.spots.toLowerCase() !== 'vol') ?
          `<form method="get" style="display:inline;" action="${registrationPageUrl}" class="pontifex-oi-aanmeld-form">
              <input type="hidden" name="aanmelden" value="1">
              <input type="hidden" name="exam_type" value="${rowExam}">
              <input type="hidden" name="language" value="${rowLanguage}">
              <input type="hidden" name="material" value="${rowMaterial}">
              <input type="hidden" name="date" value="${row.date || ''}">
              <input type="hidden" name="time" value="${row.time || ''}">
              <input type="hidden" name="location" value="${row.location || ''}">
              <input type="hidden" name="province" value="${row.province || ''}">
              <input type="hidden" name="spots" value="${row.spots || ''}">
              <input type="hidden" name="price" value="" class="pontifex-oi-price-input">
              <button type="submit" class="pontifex-oi-aanmelden">Kandidaat aanmelden</button>
          </form>` :
          '<span class="pontifex-oi-vol">VOL</span>';

        html += `
          <tr class="pontifex-oi-table-row">
            <td>${row.date || '-'}</td>
            <td>${row.time || '-'}</td>
            <td>${row.location || '-'}</td>
            <td>${row.province || '-'}</td>
            <td>${row.spots || '-'}</td>
            <td>${prijsLoader}</td>
            <td>${actionBtn}</td>
          </tr>`;
      });
    }

    $('.pontifex-oi-table tbody, .pontifex-oi-table-custom tbody').html(html);
    $('.pontifex-oi-aria-live').text(`${planning.length} resultaten geladen.`);

    if (typeof PontifexOI.updatePagination === 'function') {
      PontifexOI.updatePagination(curPage, totPages);
    }
    setTimeout(function() {
      if (typeof PontifexOI.updateAllDynamicPrices === 'function') PontifexOI.updateAllDynamicPrices();
    }, 50);
  };

  PontifexOI.renderCards = function(planning, registrationPageUrl = '/cursus-inschrijven/') {
    if (!$) return;

    const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
    const selectedLanguage = $('select[name="language"]').val() || 'nl';
    const selectedMaterial = $('select[name="material"]').val() || '1';

    let html = '';

    if (planning.length === 0) {
      html = `
        <div class="pontifex-oi-card-noresults">
          <div class="pontifex-oi-card-noresults-header">Geen resultaten</div>
          <div class="pontifex-oi-card-noresults-body">
            Geen examens gevonden.<br>
            Pas de filters aan voor een andere combinatie.
          </div>
        </div>
      `;
    } else {
      html = planning.map(row => {
        const rowExam = row.exam || selectedExamType;
        const rowLanguage = row.language || selectedLanguage;
        const rowMaterial = row.material || selectedMaterial;

        const prijsLoader = `<span class="pontifex-oi-dynamic-price"
          data-date="${row.date || ''}"
          data-time="${row.time || ''}"
          data-location="${row.location || ''}"
          data-exam="${rowExam}"
          data-language="${rowLanguage}"
          data-material="${rowMaterial}">
            <span class="pontifex-oi-price-loader">…</span>
        </span>`;

        const actionBtn = (row.spots && row.spots.toLowerCase() !== 'vol') ?
          `<form method="get" action="${registrationPageUrl}" class="card-cta pontifex-oi-aanmeld-form">
              <input type="hidden" name="aanmelden" value="1">
              <input type="hidden" name="exam_type" value="${rowExam}">
              <input type="hidden" name="language" value="${rowLanguage}">
              <input type="hidden" name="material" value="${rowMaterial}">
              <input type="hidden" name="date" value="${row.date || ''}">
              <input type="hidden" name="time" value="${row.time || ''}">
              <input type="hidden" name="location" value="${row.location || ''}">
              <input type="hidden" name="province" value="${row.province || ''}">
              <input type="hidden" name="spots" value="${row.spots || ''}">
              <input type="hidden" name="price" value="" class="pontifex-oi-price-input">
              <button type="submit" class="pontifex-oi-aanmelden">Kandidaat aanmelden</button>
          </form>` :
          `<div class="card-full">VOL</div>`;

        return `
          <div class="pontifex-oi-card">
            <header class="card-header">${row.date || '-'}</header>
            <dl class="card-body">
              <dt>Tijd</dt><dd>${row.time || '-'}</dd>
              <dt>Locatie</dt><dd>${row.location || '-'}</dd>
              <dt>Provincie</dt><dd>${row.province || '-'}</dd>
              <dt>Plaatsen</dt><dd>${row.spots || '-'}</dd>
              <dt>Prijs</dt><dd>${prijsLoader}</dd>
            </dl>
            <footer class="card-footer">${actionBtn}</footer>
          </div>`;
      }).join('');
    }

    $('#pontifex-oi-cards-container').html(html);

    if (typeof PontifexOI.updateAllDynamicPrices === 'function') PontifexOI.updateAllDynamicPrices();
  };

  PontifexOI.updateFormInputsInTableRows = function() {
    if (!$) return;

    const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
    const selectedLanguage = $('select[name="language"]').val() || 'nl';
    const selectedMaterial = $('select[name="material"]').val() || '1';

    $('.pontifex-oi-table tbody tr').each(function() {
      const $row = $(this);
      const $form = $row.find('.pontifex-oi-aanmeld-form');

      if ($form.length) {
        $form.find('input[name="exam_type"]').val(selectedExamType);
        $form.find('input[name="language"]').val(selectedLanguage);
        $form.find('input[name="material"]').val(selectedMaterial);
      }
    });
  };

  PontifexOI.updateAllDynamicPrices = function() {
    if (!$) return;

    const cfg = PontifexOI.getCfg();
    const ajaxUrl = cfg.ajaxUrl || '';

    $('.pontifex-oi-dynamic-price').each(function() {
      const $el = $(this);
      const exam = $el.data('exam') || $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
      const language = $el.data('language') || $('select[name="language"]').val() || 'nl';
      const material = $el.data('material') || '1';
      const $loader = $el.find('.pontifex-oi-price-loader');

      $loader.text('…');

      if (PontifexOI.fetchPrice) {
        PontifexOI.fetchPrice(exam, language, material, function(priceStr) {
          $loader.text(priceStr || '€0,00');

          const $form = $el.closest('tr, .pontifex-oi-card').find('.pontifex-oi-aanmeld-form');
          if ($form.length) {
            $form.find('.pontifex-oi-price-input, input[name="price"]').val(priceStr || '€0,00');
          }
        }, ajaxUrl);
      } else {
        $loader.text('€0,00');
      }
    });
  };

  if ($) {
    $(function() {
      if (PontifexOI.updateAllDynamicPrices) {
        PontifexOI.updateAllDynamicPrices();
      }
    });
  }

  window.PontifexOI.renderTableRows = PontifexOI.renderTableRows;
  window.PontifexOI.renderCards = PontifexOI.renderCards;
  window.PontifexOI.updateFormInputsInTableRows = PontifexOI.updateFormInputsInTableRows;
  window.PontifexOI.updateAllDynamicPrices = PontifexOI.updateAllDynamicPrices;

})(window);