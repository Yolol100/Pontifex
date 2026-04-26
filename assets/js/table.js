(function(window) {
    'use strict';
    const PontifexOI = window.PontifexOI = window.PontifexOI || {};
    const $ = window.jQuery;
    PontifexOI.getSelectedExtras = function() {
      const extras = [];
      $('.extra-material-checkbox:checked').each(function() {
        const val = $(this).val();
        // filter: alleen weekend meenemen bij echte weekendexamens
        const exam = $('select[name="exam_type"]').val() || '';
        const isWeekendExam = exam.includes('weekend');
        if ((val === 'cursus-weekend' || val === 'weekend_dh') && !isWeekendExam) {
          return;
        }
        extras.push(val);
      });
      return extras;
    };
    PontifexOI.renderTableRows = function(planning, curPage, totPages, registrationPageUrl = '/cursus-inschrijven/') {
        if (!$) return;
        const selectedExamType = PontifexOI.normalizeExam(PontifexOI.getSelectedFilter('exam_type', 'los-examen-vca-basis'));
        const selectedLanguage = PontifexOI.getSelectedFilter('language', 'nl');
        const selectedMaterial = PontifexOI.getSelectedFilter('material', '1');
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
                    <span class="pontifex-oi-price-amount"></span>
                    <span class="pontifex-oi-price-loader" style="min-width:40px;">…</span>
                </span>`;
      
                const actionBtn = (row.spots && row.spots.toLowerCase() !== 'vol') ?
                    `<form method="get" style="display:inline;" action="${registrationPageUrl}" class="pontifex-oi-aanmeld-form">
                    <input type="hidden" name="go" value="1">
                    <input type="hidden" name="exam_type" value="${rowExam}">
                    <input type="hidden" name="language" value="${rowLanguage}">
                    <input type="hidden" name="material" value="${rowMaterial}">
                    <input type="hidden" name="date" value="${row.date || ''}">
                    <input type="hidden" name="time" value="${row.time || ''}">
                    <input type="hidden" name="location" value="${(row.venue || row.location || '')}">
                    <input type="hidden" name="province" value="${row.province || ''}">
                    <input type="hidden" name="spots" value="${row.spots || ''}">
                    <input type="hidden" name="price" value="" class="pontifex-oi-price-input">
                    ${(window.PontifexOI && PontifexOI.getSelectedExtras)
                      ? PontifexOI.getSelectedExtras().map(id =>
                          `<input type="hidden" name="extra_options[]" value="${id}">`
                        ).join('')
                      : ''}
                    <button type="submit" class="pontifex-oi-aanmelden pontifex-register-link">Kandidaat aanmelden</button>
                </form>` :
                    '<span class="pontifex-oi-vol">VOL</span>';
                html += `<tr class="pontifex-oi-table-row">
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
    };
    // Add this function to table.js to handle weekend price detection
    PontifexOI.getEffectivePriceForRow = function(examType, language, material) {
        // Check if weekend is currently selected
        const $weekendCheckbox = $('.extra-material-checkbox[value="cursus-weekend"]:checked');
        const weekendSelected = $weekendCheckbox.length > 0;
        const isBasisOrVol = ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(examType);
   
        if (weekendSelected && isBasisOrVol) {
            return 245; // Fixed weekend price
        }
   
        return null; // Use normal dynamic pricing
    };
    // Update the updateFormInputsInTableRows function
    PontifexOI.updateFormInputsInTableRows = function() {
        if (!$) return;
        const selectedExamType = PontifexOI.normalizeExam(PontifexOI.getSelectedFilter('exam_type', 'los-examen-vca-basis'));
        const selectedLanguage = PontifexOI.getSelectedFilter('language', 'nl');
        const selectedMaterial = PontifexOI.getSelectedFilter('material', '1');
   
        // Check weekend status
        const weekendPrice = PontifexOI.getEffectivePriceForRow(selectedExamType, selectedLanguage, selectedMaterial);
        $('.pontifex-oi-table tbody tr').each(function() {
            const $row = $(this);
            const $form = $row.find('.pontifex-oi-aanmeld-form');
            const $priceInput = $form.find('input[name="price"]');
            if ($form.length) {
                $form.find('input[name="exam_type"]').val(selectedExamType);
                $form.find('input[name="language"]').val(selectedLanguage);
                $form.find('input[name="material"]').val(selectedMaterial);
                // ✅ Weekend fix: force provincie + locatie
                if (['vca-basis-weekend', 'vca-vol-weekend'].includes(selectedExamType)) {
                    $form.find('input[name="province"]').val('Zuid-Holland');
                    $form.find('input[name="location"]').val('Den Haag');
                }
           
                // In stap 1 geen vaste prijs in hidden inputs bewaren; stap 2 rekent server-side
                if ($priceInput.length) {
                    $priceInput.val('');
                }
            }
        });
        // Also update card forms
        $('#pontifex-oi-cards-container .pontifex-oi-aanmeld-form').each(function() {
            const $form = $(this);
            const $priceInput = $form.find('input[name="price"]');
       
            $form.find('input[name="exam_type"]').val(selectedExamType);
            $form.find('input[name="language"]').val(selectedLanguage);
            $form.find('input[name="material"]').val(selectedMaterial);
            // ✅ Weekend fix: force provincie + locatie
            if (['vca-basis-weekend', 'vca-vol-weekend'].includes(selectedExamType)) {
                $form.find('input[name="province"]').val('Zuid-Holland');
                $form.find('input[name="location"]').val('Den Haag');
            }
       
            // idem: hidden prijs leeg laten; stap 2 is bron van waarheid
            if ($priceInput.length) {
                $priceInput.val('');
            }
        });
    };
    window.PontifexOI.renderTableRows = PontifexOI.renderTableRows;
    window.PontifexOI.updateFormInputsInTableRows = PontifexOI.updateFormInputsInTableRows;
})(window);
(function(window, $) {
    'use strict';
    const PontifexOI = window.PontifexOI = window.PontifexOI || {};
    function escHtml(s) { return String(s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])); }
    function escAttr(s) { return escHtml(s).replace(/"/g, '&quot;'); }
    PontifexOI.renderCards = function(planning, registrationPageUrl = '/cursus-inschrijven/') {
        const $wrap = $('#pontifex-oi-cards-container');
        if (!$wrap.length) return;
        if (!Array.isArray(planning) || planning.length === 0) {
            $wrap.html(`
                <div class="pontifex-oi-card-noresults">
                    <div class="pontifex-oi-card-noresults-header">Geen resultaten</div>
                    <div class="pontifex-oi-card-noresults-body">
                        Geen examens gevonden.<br>Pas de filters aan voor een andere combinatie.
                    </div>
                </div>
            `);
            return;
        }
        const grouped = {};
        planning.forEach(r => {
            const key = r.date || '';
            grouped[key] = grouped[key] || [];
            grouped[key].push(r);
        });
        const selectedExamType = PontifexOI.normalizeExam(PontifexOI.getSelectedFilter('exam_type', 'los-examen-vca-basis'));
        const selectedLanguage = PontifexOI.getSelectedFilter('language', 'nl');
        const selectedMaterial = PontifexOI.getSelectedFilter('material', '1');
        let html = '<div class="pontifex-planning-accordion">';
        let idx = 0;
        Object.keys(grouped).forEach(dateKey => {
            const safeId = `acc-${idx++}-${dateKey.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`;
            html += `
                <section class="acc-day pontifex-oi-card" data-date="${escAttr(dateKey)}">
                    <button class="acc-toggle card-header" type="button" aria-expanded="false" aria-controls="${safeId}">
                        <span class="acc-title">${escHtml(dateKey)}</span>
                        <span class="acc-arrow" aria-hidden="true">▼</span>
                    </button>
                    <div id="${safeId}" class="acc-panel card-body" hidden>
                        <ul class="acc-list">`;
            grouped[dateKey].forEach(item => {
                const spots = item.spots || '-';
                const isVol = String(spots).toUpperCase() === 'VOL';
                const ex = item.exam || selectedExamType;
                const lang = item.language || selectedLanguage;
                const material = selectedMaterial;
                html += `
                    <li class="acc-item">
                        <div class="acc-row">
                            <div class="acc-col">
                                <div class="acc-kv"><strong>Tijd</strong><span>${escHtml(item.time || '-')}</span></div>
                                <div class="acc-kv"><strong>Locatie</strong><span>${escHtml(item.location || item.venue || '-')}</span></div>
                            </div>
                            <div class="acc-col">
                                <div class="acc-kv"><strong>Provincie</strong><span>${escHtml(item.province || '-')}</span></div>
                                <div class="acc-kv"><strong>Plekken</strong><span>${escHtml(spots)}</span></div>
                            </div>
                        </div>
                        <div class="acc-kv acc-price">
                            <strong>Prijs</strong>
                            <span class="pontifex-oi-dynamic-price"
                                data-date="${escAttr(item.date || '')}"
                                data-time="${escAttr(item.time || '')}"
                                data-location="${escAttr(item.location || item.venue || '')}"
                                data-exam="${escAttr(ex)}"
                                data-language="${escAttr(lang)}"
                                data-material="${escAttr(material)}">
                                <span class="pontifex-oi-price-amount"></span>
                                <span class="pontifex-oi-price-loader">…</span>
                            </span>
                        </div>
                        <div class="acc-actions">
                            ${isVol ? `
                                <span class="pontifex-oi-vol">VOL</span>`
                                :
                                `<form method="get" class="pontifex-oi-aanmeld-form" action="${registrationPageUrl}">
                                    <input type="hidden" name="go" value="1">
                                    <input type="hidden" name="exam_type" value="${escAttr(ex)}">
                                    <input type="hidden" name="language" value="${escAttr(lang)}">
                                    <input type="hidden" name="material" value="${escAttr(material)}">
                                    <input type="hidden" name="date" value="${escAttr(item.date || '')}">
                                    <input type="hidden" name="time" value="${escAttr(item.time || '')}">
                                    <input type="hidden" name="location" value="${escAttr(item.location || item.venue || '')}">
                                    <input type="hidden" name="province" value="${escAttr(item.province || '')}">
                                    <input type="hidden" name="spots" value="${escAttr(spots)}">
                                    <input type="hidden" name="price" value="" class="pontifex-oi-price-input">
                                    ${(window.PontifexOI && PontifexOI.getSelectedExtras)
                                      ? PontifexOI.getSelectedExtras().map(id =>
                                          `<input type="hidden" name="extra_options[]" value="${id}">`
                                        ).join('')
                                      : ''}
                                    <button type="submit" class="pontifex-oi-aanmelden pontifex-register-link">Kandidaat aanmelden</button>
                                </form>`
                            }
                        </div>
                    </li>`;
            });
            html += `</ul></div></section>`;
        });
        html += '</div>';
        $wrap.html(html);
    };
    window.PontifexOI.renderCards = PontifexOI.renderCards;
})(window, jQuery);