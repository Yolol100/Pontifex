function debounce(func, wait, immediate) {
    var timeout;
    return function () {
        var context = this, args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(function () {
            timeout = null;
            if (!immediate) func.apply(context, args);
        }, wait);
        if (immediate && !timeout) func.apply(context, args);
    };
}

jQuery(function ($) {
    'use strict';

    console.log("pontifex-oi.js actief!");

    // ===================== CONFIG & GLOBALS =====================
    const ajaxUrl = (typeof PontifexOiAjax !== 'undefined') ? PontifexOiAjax.ajax_url : '';
    const registrationPageUrl = '/cursus-inschrijven/';
    const planningPageUrl = '/cursus-zoeken/';
    let gekozenPlanning = {};
    const examWeekend = ['vca-basis-weekend', 'vca-vol-weekend'];

    const EXAM_PRODUCTS = {
        'los-examen-vca-basis': { label: 'VCA Basis', prices: { 'nl': 129, 'en': 129 } },
        'los-examen-vca-vol': { label: 'VCA Vol', prices: { 'nl': 139, 'en': 139 } },
        'vca-basis-weekend': { label: 'VCA Basis Cursus Weekend', prices: { 'nl': 245 } },
        'vca-vol-weekend': { label: 'VCA Vol Cursus Weekend', prices: { 'nl': 245 } },
    };

    const MATERIAL_PRODUCTS = {
        'e-learning-vca-basis-nl': { label: 'E-learning VCA Basis (NL)', price: 29 },
        'vca-basis-proefexamens-nl': { label: 'VCA Basis Proefexamens (NL)', price: 25 },
        'boek-vca-basis-nl': { label: 'Boek VCA Basis (NL)', price: 36 },
        'boek-vca-combi-nl': { label: 'Boek VCA Combi (NL)', price: 49 },
        'e-learning-vca-vol-nl': { label: 'E-learning VCA Vol (NL)', price: 39 },
        'vca-vol-proefexamens-nl': { label: 'VCA Vol Proefexamens (NL)', price: 25 },
        'boek-vca-vol-nl': { label: 'Boek VCA Vol (NL)', price: 42 },
        'boek-vca-combi-vol-nl': { label: 'Boek VCA Combi (NL)', price: 49 },
        'e-learning-vca-basis-en': { label: 'E-learning VCA Basis (EN)', price: 49 },
        'boek-vca-basis-en': { label: 'Boek VCA Basis (EN)', price: 56 },
        'boek-vca-combi-en': { label: 'Boek VCA Combi (EN)', price: 69 },
        'e-learning-vca-vol-en': { label: 'E-learning VCA Vol (EN)', price: 59 },
        'boek-vca-vol-en': { label: 'Boek VCA Vol (EN)', price: 62 },
        'boek-vca-combi-vol-en': { label: 'Boek VCA Combi (EN)', price: 69 },
    };

    const MATERIAL_COMBIS = {
        '1': [],
        '2_basis': ['boek-vca-basis-nl'],
        '2_vol': ['boek-vca-vol-nl'],
        '4_basis': ['e-learning-vca-basis-nl'],
        '4_vol': ['e-learning-vca-vol-nl'],
        '5_basis': ['vca-basis-proefexamens-nl'],
        '5_vol': ['vca-vol-proefexamens-nl'],
        '6_basis': ['boek-vca-basis-nl', 'vca-basis-proefexamens-nl'],
        '6_vol': ['boek-vca-vol-nl', 'vca-vol-proefexamens-nl'],
        '7_basis': ['e-learning-vca-basis-nl', 'vca-basis-proefexamens-nl'],
        '7_vol': ['e-learning-vca-vol-nl', 'vca-vol-proefexamens-nl'],
    };

    const extraMaterialCheckboxes = {
        'los-examen-vca-basis_nl': [
            { id: 'e-learning-vca-basis-nl', label: 'E-learning VCA Basis (NL)', price: 29 },
            { id: 'vca-basis-proefexamens-nl', label: 'VCA Basis Proefexamens (NL)', price: 25 },
            { id: 'boek-vca-basis-nl', label: 'Boek VCA Basis (NL)', price: 36 },
            { id: 'boek-vca-combi-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        ],
        'los-examen-vca-vol_nl': [
            { id: 'e-learning-vca-vol-nl', label: 'E-learning VCA Vol (NL)', price: 39 },
            { id: 'vca-vol-proefexamens-nl', label: 'VCA Vol Proefexamens (NL)', price: 25 },
            { id: 'boek-vca-vol-nl', label: 'Boek VCA Vol (NL)', price: 42 },
            { id: 'boek-vca-combi-vol-nl', label: 'Boek VCA Combi (NL)', price: 49 },
        ],
        'los-examen-vca-basis_en': [
            { id: 'e-learning-vca-basis-en', label: 'E-learning VCA Basis (EN)', price: 49 },
            { id: 'boek-vca-basis-en', label: 'Boek VCA Basis (EN)', price: 56 },
            { id: 'boek-vca-combi-en', label: 'Boek VCA Combi (EN)', price: 69 },
        ],
        'los-examen-vca-vol_en': [
            { id: 'e-learning-vca-vol-en', label: 'E-learning VCA Vol (EN)', price: 59 },
            { id: 'boek-vca-vol-en', label: 'Boek VCA Vol (EN)', price: 62 },
            { id: 'boek-vca-combi-vol-en', label: 'Boek VCA Combi (EN)', price: 69 },
        ],
    };

    const hasStep1 = $('#step-1').length > 0;
    const hasStep2 = $('#step-2').length > 0;
    const bothStepsSamePage = hasStep1 && hasStep2;
    const isPlanningPage = $('.pontifex-oi-filters').length > 0;
    const isRegistrationPage = hasStep2 && !hasStep1;

    if (typeof window._pontifex_first_load === 'undefined') {
        window._pontifex_first_load = true;
    }

    // ===================== UTILITY FUNCTIONS =====================
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            exam_type: params.get('exam_type') || '',
            language: params.get('language') || '',
            material: params.get('material') || '',
            date: params.get('date') || '',
            time: params.get('time') || '',
            location: params.get('location') || '',
            province: params.get('province') || '',
            spots: params.get('spots') || '',
            price: params.get('price') || ''
        };
    }

    function saveFilterState() {
        localStorage.setItem('exam_type', $('select[name="exam_type"]').val());
        localStorage.setItem('language', $('select[name="language"]').val());
        localStorage.setItem('material', $('select[name="material"]').val());
    }

    function loadFilterState() {
        const urlParams = getUrlParams();
    
        // Examensoort (eerst URL, dan localStorage)
        if (urlParams.exam_type && $('select[name="exam_type"] option[value="' + urlParams.exam_type + '"]').length) {
            $('select[name="exam_type"]').val(urlParams.exam_type);
        } else {
            const examStored = localStorage.getItem('exam_type');
            if (examStored && $('select[name="exam_type"] option[value="' + examStored + '"]').length) {
                $('select[name="exam_type"]').val(examStored);
            }
        }
    
        // Taal (eerst URL, dan localStorage)
        if (urlParams.language && $('select[name="language"] option[value="' + urlParams.language + '"]').length) {
            $('select[name="language"]').val(urlParams.language);
        } else {
            const langStored = localStorage.getItem('language');
            if (langStored && $('select[name="language"] option[value="' + langStored + '"]').length) {
                $('select[name="language"]').val(langStored);
            }
        }
    
        // Materiaal (eerst URL, dan localStorage)
        if (urlParams.material && $('select[name="material"] option[value="' + urlParams.material + '"]').length) {
            $('select[name="material"]').val(urlParams.material);
        } else {
            const matStored = localStorage.getItem('material');
            if (matStored && $('select[name="material"] option[value="' + matStored + '"]').length) {
                $('select[name="material"]').val(matStored);
            }
        }
    }
    
    function setDefaultFiltersIfNeeded() {
        const urlParams = getUrlParams();
    
        // Alleen fallback naar hardcoded default als géén URL en géén localStorage waarde
        if (!urlParams.exam_type) {
            const examStored = localStorage.getItem('exam_type');
            if (!examStored || !$('select[name="exam_type"] option[value="' + examStored + '"]').length) {
                $('select[name="exam_type"]').val('los-examen-vca-basis');
            }
        }
        if (!urlParams.language) {
            const langStored = localStorage.getItem('language');
            if (!langStored || !$('select[name="language"] option[value="' + langStored + '"]').length) {
                $('select[name="language"]').val('nl');
            }
        }
        if (!urlParams.material) {
            const matStored = localStorage.getItem('material');
            if (!matStored || !$('select[name="material"] option[value="' + matStored + '"]').length) {
                $('select[name="material"]').val('1');
            }
        }
    }

    function updateFiltersState() {
        const $exam = $('select[name="exam_type"]');
        const $lang = $('select[name="language"]');
        const $mat = $('select[name="material"]');
        const examVal = $exam.val();
        const langVal = $lang.val();
        const currentMat = $mat.val();
        const $materialGroup = $('#material-select-block');

        if (examWeekend.includes(examVal) || $materialGroup.length === 0) {
            $materialGroup.hide();
            if ($mat.length) $mat.prop('disabled', true).val('');
        } else {
            $materialGroup.show();
            if ($mat.length) $mat.prop('disabled', false);
        }

        if (window._pontifex_is_updating) return;
        window._pontifex_is_updating = true;

        if (!examVal) {
            $lang.val('').prop('disabled', true).find('option').show();
            if ($mat.length) {
                $mat.val('').prop('disabled', true).find('option').show();
            }
            updateFilterCombinationPrice();
            updateExtraMaterialCheckboxes();
            window._pontifex_is_updating = false;
            saveFilterState();
            return;
        }

        $lang.prop('disabled', false);
        if (examWeekend.includes(examVal)) {
            $lang.find('option').each(function () {
                const v = $(this).val();
                $(this).toggle(v === '' || v === 'nl');
            });
            if (langVal !== 'nl') $lang.val('nl').trigger('change');
        } else {
            $lang.find('option').show();
            if (!langVal) $lang.val('');
        }

        if ($mat.length) {
            $mat.prop('disabled', false);
            let allowed = ['1', '2', '4', '5', '6', '7'];
            if (examWeekend.includes(examVal)) allowed = ['1', '4'];

            $mat.empty();
            if (typeof materialOptions !== 'undefined' && materialOptions['']) {
                $mat.append($('<option>', { value: '', text: materialOptions[''] }));
            }
            if (typeof materialOptions !== 'undefined') {
                $.each(materialOptions, function (val, txt) {
                    if (val && allowed.includes(val)) {
                        $mat.append($('<option>', { value: val, text: txt }));
                    }
                });
            }

            if (examWeekend.includes(examVal)) {
                $mat.val(allowed.find(v => v) || '');
            } else {
                if (allowed.includes(currentMat)) {
                    $mat.val(currentMat);
                } else if (window._pontifex_first_load) {
                    $mat.val(allowed.find(v => v) || '');
                } else {
                    $mat.val('');
                }
            }
            $mat.trigger('change');
        }

        updateExtraMaterialCheckboxes();
        window._pontifex_first_load = false;
        updateFilterCombinationPrice();
        window._pontifex_is_updating = false;
        saveFilterState();
    }

    function updateExtraMaterialCheckboxes() {
        const $container = $('#extra-material-checkboxes');
        if (!$container.length) {
            console.log('Container #extra-material-checkboxes niet gevonden');
            return;
        }
    
        // Voor registratiepagina: gebruik de hidden inputs
        if (isRegistrationPage && !bothStepsSamePage) {
            $container.off('change.material').on('change.material', '.extra-material-checkbox', updateTotalPriceWithCheckboxes);
            updateTotalPriceWithCheckboxes();
            return;
        }
    
        if (!$('#step-2').length) return;
    
        // Haal exam en language waarden op - verschillende bronnen proberen
        let examVal = '';
        let langVal = '';
    
        // Probeer eerst de select elementen (stap 1)
        if ($('select[name="exam_type"]').length) {
            examVal = $('select[name="exam_type"]').val() || '';
        }
        if ($('select[name="language"]').length) {
            langVal = $('select[name="language"]').val() || '';
        }
    
        // Als geen waarden van selects, probeer hidden inputs (stap 2)
        if (!examVal && $('#exam_type').length) {
            examVal = $('#exam_type').val() || '';
        }
        if (!langVal && $('#language').length) {
            langVal = $('#language').val() || '';
        }
    
        // Als nog steeds geen waarden, probeer URL parameters
        if (!examVal || !langVal) {
            const urlParams = getUrlParams();
            if (!examVal) examVal = urlParams.exam_type || '';
            if (!langVal) langVal = urlParams.language || '';
        }
    
        console.log('updateExtraMaterialCheckboxes - examVal:', examVal, 'langVal:', langVal);
    
        // Verberg voor weekendcursussen
        if (examWeekend.includes(examVal)) {
            $container.hide().empty();
            updateTotalPriceWithCheckboxes();
            return;
        }
    
        // Normaliseer exam_type voor materiaal lookup
        let normalizedExam = examVal;
        if (examVal === 'vca-basis') {
            normalizedExam = 'los-examen-vca-basis';
        } else if (examVal === 'vca-vol') {
            normalizedExam = 'los-examen-vca-vol';
        }
    
        // Maak de key voor de materiaal opties
        const key = normalizedExam + '_' + langVal;
        console.log('Zoek materiaal opties voor key:', key);
        
        const options = extraMaterialCheckboxes[key] || [];
        console.log('Gevonden opties:', options);
    
        let html = '';
        if (options.length > 0) {
            html = '<strong>Extra lesmateriaal nodig?</strong><div>';
            options.forEach(opt => {
                html += `<label style="display:block; margin:0.3em 0;">
                    <input type="checkbox" class="extra-material-checkbox" name="extra_material[]" value="${opt.id}" data-price="${opt.price}">
                    ${opt.label} (€${opt.price.toFixed(2).replace('.', ',')})
                </label>`;
            });
            html += '</div>';
        } else {
            html = '<strong>Extra lesmateriaal nodig?</strong><div>Geen extra lesmateriaal beschikbaar.</div>';
        }
    
        console.log('HTML voor checkboxes:', html);
        $container.html(html).show();
        $container.off('change.material').on('change.material', '.extra-material-checkbox', updateTotalPriceWithCheckboxes);
        updateTotalPriceWithCheckboxes();
    }

    function parsePrice(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
    }

    function updateTotalPriceWithCheckboxes() {
        const totalPriceEl = $('#total-price');
        const paymentAmountInput = $('#payment_amount');
        let basePrice = parsePrice(paymentAmountInput.data('base-price') || paymentAmountInput.val() || getUrlParams().price);
        if (isNaN(basePrice)) basePrice = 0;

        let extraTotal = 0;
        $('.extra-material-checkbox:checked').each(function () {
            extraTotal += parseFloat($(this).data('price')) || 0;
        });

        const candidateCount = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row').length || 1;
        const newTotal = (basePrice + extraTotal) * candidateCount;

        totalPriceEl.text('€' + newTotal.toFixed(2).replace('.', ','));
        paymentAmountInput.val(newTotal.toFixed(2));
    }

    function calculatePrice(exam, lang, mat) {
        if (examWeekend.includes(exam)) {
            return 245;
        }
        let total = 0;
        if (EXAM_PRODUCTS[exam]) {
            total += EXAM_PRODUCTS[exam].prices[lang] ?? EXAM_PRODUCTS[exam].prices['nl'] ?? 0;
        }
        if (!mat || mat === '1') {
            return total;
        }
        let combiKey = mat;
        if (['2', '4', '5', '6', '7'].includes(mat)) {
            const suffix = exam.includes('vca-vol') ? 'vol' : 'basis';
            combiKey = `${mat}_${suffix}`;
        }
        if (MATERIAL_COMBIS[combiKey]) {
            MATERIAL_COMBIS[combiKey].forEach(id => {
                total += MATERIAL_PRODUCTS[id]?.price ?? 0;
            });
        }
        return total;
    }

    function fetchPrice(exam, lang, mat, cb) {
        console.log('fetchPrice aangeroepen:', { exam, lang, mat });
        
        if (examWeekend.includes(exam)) {
            cb('€245,00');
            return;
        }
        
        const localPrice = calculatePrice(exam, lang, mat);
        
        if (!ajaxUrl) {
            console.warn('AJAX URL niet beschikbaar, gebruik lokale berekening');
            cb('€' + localPrice.toFixed(2).replace('.', ','));
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: { 
                action: 'pontifex_oi_get_price', 
                exam_type: exam, 
                language: lang, 
                material: mat 
            },
            success: function (resp) {
                console.log('AJAX prijs response:', resp);
                if (resp.success && resp.data && resp.data.price && resp.data.price !== '') {
                    cb(resp.data.price);
                } else {
                    cb('€' + localPrice.toFixed(2).replace('.', ','));
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX prijs fout:', { status, error });
                cb('€' + localPrice.toFixed(2).replace('.', ','));
            }
        });
    }
    
    // Voeg deze debug code toe aan je JavaScript om te controleren wat er gebeurt:

    $(document).ready(function() {
        // Check of extraMaterialCheckboxes correct gedefinieerd is
        console.log('extraMaterialCheckboxes object:', extraMaterialCheckboxes);
        
        // Check welke waarden er zijn bij page load
        setTimeout(function() {
            console.log('=== DEBUG CHECKBOX INFO ===');
            console.log('exam_type select:', $('select[name="exam_type"]').val());
            console.log('language select:', $('select[name="language"]').val());
            console.log('exam_type hidden:', $('#exam_type').val());
            console.log('language hidden:', $('#language').val());
            console.log('URL params:', getUrlParams());
            console.log('Container exists:', $('#extra-material-checkboxes').length > 0);
            console.log('Is step 2:', $('#step-2').length > 0);
            console.log('Weekend exam check:', examWeekend);
            console.log('===========================');
            
            // Force update
            updateExtraMaterialCheckboxes();
        }, 1000);
    });
    
    // Ook deze functie toevoegen om de checkboxes handmatig te forceren:
    function forceUpdateCheckboxes() {
        console.log('=== FORCE UPDATE CHECKBOXES ===');
        const examVal = $('#exam_type').val() || $('select[name="exam_type"]').val() || getUrlParams().exam_type || 'los-examen-vca-basis';
        const langVal = $('#language').val() || $('select[name="language"]').val() || getUrlParams().language || 'nl';
        
        console.log('Force update met:', examVal, langVal);
        
        const key = examVal + '_' + langVal;
        console.log('Key:', key);
        console.log('Available options:', extraMaterialCheckboxes[key]);
        
        const $container = $('#extra-material-checkboxes');
        if (!examWeekend.includes(examVal) && extraMaterialCheckboxes[key]) {
            const options = extraMaterialCheckboxes[key];
            let html = '<strong>Extra lesmateriaal nodig?</strong><div>';
            options.forEach(opt => {
                html += `<label style="display:block; margin:0.3em 0;">
                    <input type="checkbox" class="extra-material-checkbox" name="extra_material[]" value="${opt.id}" data-price="${opt.price}">
                    ${opt.label} (€${opt.price.toFixed(2).replace('.', ',')})
                </label>`;
            });
            html += '</div>';
            $container.html(html).show();
            console.log('Checkboxes toegevoegd!');
        } else {
            console.log('Geen checkboxes - weekend exam of geen opties');
        }
    }
    
    // Je kunt deze functie aanroepen in de browser console om te testen:
    // forceUpdateCheckboxes();

    function updateFilterCombinationPrice() {
        const exam = $('select[name="exam_type"]').val();
        const language = $('select[name="language"]').val();
        const material = $('select[name="material"]').val();
        const $span = $('.pontifex-oi-dynamic-price-filters');

        if (!$span.length) return;

        if (examWeekend.includes(exam)) {
            $span.html('€245,00');
            return;
        }

        $span.html('<span class="pontifex-oi-price-loader" style="min-width:40px;">...</span>');
        fetchPrice(exam, language, material, function (price) {
            $span.html(price && price !== '0' && price !== '0,00' && price !== '€0,00' ? price : 'Niet beschikbaar');
        });
    }

    function updateFormInputsInTableRows() {
        const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
        const selectedLanguage = $('select[name="language"]').val() || 'nl';
        const selectedMaterial = $('select[name="material"]').val() || '1';

        $('.pontifex-oi-table tbody tr').each(function () {
            const $row = $(this);
            const $form = $row.find('.pontifex-oi-aanmeld-form');

            if ($form.length) {
                $form.find('input[name="exam_type"]').val(selectedExamType);
                $form.find('input[name="language"]').val(selectedLanguage);
                $form.find('input[name="material"]').val(selectedMaterial);
            }
        });
    }

    function updateAllDynamicPrices() {
        console.log('updateAllDynamicPrices gestart');
    
        // Pak de filters boven de tabel
        const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
        const selectedLanguage = $('select[name="language"]').val() || 'nl';
        const selectedMaterial = $('select[name="material"]').val() || '1';
    
        console.log('Prijs voor alle rijen:', { selectedExamType, selectedLanguage, selectedMaterial });
    
        $('.pontifex-oi-dynamic-price').each(function () {
            const $span = $(this);
            const $row = $span.closest('tr');
    
            // Altijd de gekozen filters gebruiken
            let finalExam = selectedExamType;
            let finalLang = selectedLanguage;
            let finalMat = selectedMaterial;
    
            $span.html('<span class="pontifex-oi-price-loader" style="min-width:40px;">...</span>');
    
            fetchPrice(finalExam, finalLang, finalMat, function(price) {
                console.log('Prijs voor deze rij:', price);
                const display = price && price !== '0' && price !== '0,00' && price !== '€0,00' ? price : '-';
                $span.html(display);
    
                // Eventueel ook de hidden input invullen
                if ($row.length) {
                    const cleanPrice = display.replace(/[^\d,\.]/g, '');
                    $row.find('.pontifex-oi-price-input').val(cleanPrice);
                }
            });
        });
    }
    
// PAGINATIE FUNCTIE: werkt 1-op-1 met jouw HTML-structuur
function updatePagination(curPage, totPages) {
    var html = '';

    // Vorige pijl
    if (curPage > 1) {
        html += '<a href="#" data-page="' + (curPage - 1) + '" aria-label="Vorige pagina">«</a>';
    } else {
        html += '<span class="pontifex-oi-page-disabled" aria-hidden="true">«</span>';
    }

    // Toon max 7 pagina’s rondom huidige pagina
    var maxLinks = 7;
    var start = Math.max(1, curPage - Math.floor(maxLinks / 2));
    var end = Math.min(totPages, start + maxLinks - 1);
    if (end - start < maxLinks - 1) start = Math.max(1, end - maxLinks + 1);

    for (var i = start; i <= end; i++) {
        if (i === curPage) {
            html += '<a href="#" data-page="' + i + '" class="pontifex-oi-page-active">' + i + '</a>';
        } else {
            html += '<a href="#" data-page="' + i + '">' + i + '</a>';
        }
    }

    // Volgende pijl
    if (curPage < totPages) {
        html += '<a href="#" data-page="' + (curPage + 1) + '" aria-label="Volgende pagina">»</a>';
    } else {
        html += '<span class="pontifex-oi-page-disabled" aria-hidden="true">»</span>';
    }

    $('.pontifex-oi-pagination-wrapper nav').html(html);
}

    // TABEL RIJEN MAKEN + PAGINATIE UPDATEN
    function renderTableRows(planning, curPage, totPages) {
        console.log('renderTableRows gestart met', planning.length, 'rijen');
        
        const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
        const selectedLanguage = $('select[name="language"]').val() || 'nl';
        const selectedMaterial = $('select[name="material"]').val() || '1';
    
        let html = '';
        planning.forEach(function(row, index) {
            const rowExam = row.exam || selectedExamType;
            const rowLanguage = row.language || selectedLanguage;
            const rowMaterial = row.material || selectedMaterial;
    
            console.log(`Rij ${index}:`, { rowExam, rowLanguage, rowMaterial });
    
            const prijsLoader = `<span class="pontifex-oi-dynamic-price"
                                     data-date="${row.date || ''}"
                                     data-time="${row.time || ''}"
                                     data-location="${row.location || ''}"
                                     data-exam="${rowExam}"
                                     data-language="${rowLanguage}"
                                     data-material="${rowMaterial}">
                                   <span class="pontifex-oi-price-loader" style="min-width:40px;">...</span>
                                 </span>`;
    
            const actionBtn = (row.spots && row.spots.toLowerCase() !== 'vol')
                ? `<form method="get" style="display:inline;" action="${registrationPageUrl}" class="pontifex-oi-aanmeld-form">
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
                   </form>`
                : '<span class="pontifex-oi-vol">VOL</span>';
    
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
    
        $('.pontifex-oi-table tbody, .pontifex-oi-table-custom tbody').html(html);
        $('.pontifex-oi-aria-live').text(`${planning.length} resultaten geladen.`);
        updatePagination(curPage, totPages);
        
        setTimeout(function() {
            updateAllDynamicPrices();
        }, 100);
    }

    function renderCards(planning) {
        const selectedExamType = $('select[name="exam_type"]').val() || 'los-examen-vca-basis';
        const selectedLanguage = $('select[name="language"]').val() || 'nl';
        const selectedMaterial = $('select[name="material"]').val() || '1';

        const html = planning.map(row => {
            const rowExam = row.exam || selectedExamType;
            const rowLanguage = row.language || selectedLanguage;
            const rowMaterial = row.material || selectedMaterial;

            const actionBtn = (row.spots && row.spots.toLowerCase() !== 'vol')
                ? `<form method="get" action="${registrationPageUrl}" class="card-cta">
                       <input type="hidden" name="aanmelden" value="1">
                       <input type="hidden" name="exam_type" value="${rowExam}">
                       <input type="hidden" name="language" value="${rowLanguage}">
                       <input type="hidden" name="material" value="${rowMaterial}">
                       <input type="hidden" name="date" value="${row.date || ''}">
                       <input type="hidden" name="time" value="${row.time || ''}">
                       <input type="hidden" name="location" value="${row.location || ''}">
                       <input type="hidden" name="province" value="${row.province || ''}">
                       <input type="hidden" name="spots" value="${row.spots || ''}">
                       <button type="submit" class="pontifex-oi-aanmelden">Kandidaat aanmelden</button>
                   </form>`
                : `<div class="card-full">VOL</div>`;

            return `
              <div class="pontifex-oi-card">
                <header class="card-header">${row.date || '-'}</header>
                <dl class="card-body">
                  <dt>Tijd</dt><dd>${row.time || '-'}</dd>
                  <dt>Locatie</dt><dd>${row.location || '-'}</dd>
                  <dt>Provincie</dt><dd>${row.province || '-'}</dd>
                  <dt>Plaatsen</dt><dd>${row.spots || '-'}</dd>
                  <dt>Prijs</dt>
                  <dd>
                    <span class="pontifex-oi-dynamic-price"
                          data-exam="${rowExam}" 
                          data-language="${rowLanguage}"
                          data-material="${rowMaterial}">
                      …
                    </span>
                  </dd>
                </dl>
                <footer class="card-footer">${actionBtn}</footer>
              </div>`;
        }).join('');

        $('#pontifex-oi-cards-container').html(html);
        // GEEN .show() of .hide()
        updateAllDynamicPrices();
    }

    function updatePontifexTable(page = 1) {
        console.log('updatePontifexTable gestart, pagina:', page);
        
        const filters = {};
        $('.pontifex-oi-filter').each(function () {
            const v = $(this).val();
            if (v) filters[$(this).attr('name')] = v;
        });
        filters.per_page = $('#pontifex-oi-results-per-page').val() || 10;
        filters.page = page;

        console.log('Huidige filter status:', filters);
        
        $('.pontifex-oi-aria-live').text('Laden, even geduld aub…');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'pontifex_oi_get_planning', 
                filters: filters 
            },
            success: function(resp) {
                console.log('AJAX planning response:', resp);
                
                if (resp.success && resp.data && resp.data.planning && resp.data.planning.length > 0) {
                    renderTableRows(resp.data.planning, resp.data.current_page || 1, resp.data.total_pages || 1);
                    renderCards(resp.data.planning); // Altijd beide vullen!
                    $('.pontifex-oi-aria-live').text(`${resp.data.planning.length} resultaten geladen.`);
                } else {
                    // Vul beide containers met "geen resultaten"
                    $('.pontifex-oi-table tbody, .pontifex-oi-table-custom tbody').html('<tr><td colspan="7">Geen resultaten gevonden voor deze filters.</td></tr>');
                    $('#pontifex-oi-cards-container').html('<p class="pontifex-oi-no-results">Geen resultaten gevonden voor deze filters.</p>');
                    updatePagination(1, 1);
                    $('.pontifex-oi-aria-live').text('Geen resultaten gevonden.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX planning fout:', { xhr, status, error });
                
                const errorMsg = 'Er ging iets mis bij het laden van de planning. Probeer het opnieuw.';
                $('.pontifex-oi-table tbody, .pontifex-oi-table-custom tbody').html(`<tr><td colspan="7">${errorMsg}</td></tr>`);
                $('#pontifex-oi-cards-container').html(`<p class="pontifex-oi-no-results">${errorMsg}</p>`);
                $('.pontifex-oi-aria-live').text('Fout bij laden van resultaten.');
            }
        });
    }

    // ===================== EVENT HANDLERS =====================
    $(document).on('submit', '.pontifex-oi-aanmeld-form', function (e) {
        var $form = $(this);
        var $row = $form.closest('tr');
        var prijsTekst = $row.find('.pontifex-oi-dynamic-price').text().trim();
        var prijsClean = prijsTekst.replace(/[^\d,\.]/g, '').trim();
        $form.find('input.pontifex-oi-price-input').val(prijsClean);
    });

    $(document).on('click', '.pontifex-oi-submit-order', function (e) {
        e.preventDefault();
        updateTotalPriceWithCheckboxes();
        var $btn = $(this), $form = $btn.closest('form');
        $form.find('input,select,textarea').removeClass('invalid-field');
        var isValid = true, firstInvalid = null;
        $form.find('[required]').each(function () {
            var $f = $(this), v = $f.val();
            if (typeof v === 'string' && v.trim() === '') {
                isValid = false; $f.addClass('invalid-field');
                if (!firstInvalid) firstInvalid = $f;
            }
        });
        if (!isValid) {
            alert('Vul alle verplichte velden correct in.');
            if (firstInvalid) firstInvalid.focus();
            return;
        }
        var amtRaw = $form.find('#payment_amount').val() || '';
        var amount = typeof amtRaw === 'string' ? parseFloat(amtRaw.replace(',', '.').replace('€', '').replace(/[^0-9.]/g, '')) : parseFloat(amtRaw);
        if (!amount || isNaN(amount) || amount < 1) {
            alert('Geen geldig bedrag gevonden om te betalen.');
            return;
        }
        var pc = $form.find('#order_postcode').val().replace(/\s+/g, '');
        if (!/^[0-9]{4}[A-Za-z]{2}$/.test(pc)) {
            alert('Ongeldige postcode. Gebruik 1234AB (4 cijfers + 2 letters).');
            $form.find('#order_postcode').addClass('invalid-field').focus();
            return;
        }
        var ph = $form.find('#order_phone').val().replace(/[\s\-]/g, '');
        if (!/^(\+31|0)[1-9][0-9]{8}$/.test(ph)) {
            alert('Ongeldig telefoonnummer. Vul een Nederlands vast of mobiel nummer in.');
            $form.find('#order_phone').addClass('invalid-field').focus();
            return;
        }
        var em = $form.find('#order_email').val(), re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(em)) {
            alert('Ongeldig e-mailadres. Vul een geldig e-mail in (bijv. naam@domein.nl).');
            $form.find('#order_email').addClass('invalid-field').focus();
            return;
        }
        $btn.prop('disabled', true).text('Even geduld…');

        var orderData = $form.serializeArray().reduce(function(obj, item) {
            if (obj[item.name]) {
                if (!Array.isArray(obj[item.name])) obj[item.name] = [obj[item.name]];
                obj[item.name].push(item.value);
            } else {
                obj[item.name] = item.value;
            }
            return obj;
        }, {});
        if (orderData['extra_material[]']) {
            orderData.extra_material = orderData['extra_material[]'];
            delete orderData['extra_material[]'];
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'pontifex_oi_process_payment',
                order: orderData,
                amount: amount
            },
            success: function(resp) {
                if (resp.success && resp.data && resp.data.payment_url) {
                    window.location.href = resp.data.payment_url;
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : 'Er ging iets mis bij het starten van de betaling.');
                    $btn.prop('disabled', false).text('Bestelling plaatsen');
                }
            },
            error: function() {
                alert('Betaling starten mislukt (AJAX-fout).');
                $btn.prop('disabled', false).text('Bestelling plaatsen');
            }
        });
    });

    $(document).on('change', '.pontifex-oi-filters-sidebar select', function () {
        var mapping = [
            ['exam_type', 'sidebar-exam_type'],
            ['language', 'sidebar-language'],
            ['material', 'sidebar-material'],
            ['month', 'sidebar-month'],
            ['province', 'sidebar-province'],
            ['location', 'sidebar-location'],
            ['timeslot', 'sidebar-timeslot']
        ];
        mapping.forEach(function(pair) {
            var mainSel = 'select[name="' + pair[0] + '"]';
            var sideSel = '#' + pair[1];
            if ($(mainSel).length && $(sideSel).length) {
                $(mainSel).val($(sideSel).val());
            }
        });
        updatePontifexTable(1);
    });

    $(document).on('click', '.pontifex-oi-filters-sidebar .pontifex-oi-save-btn', function (e) {
        e.preventDefault();
        var mapping = [
            ['exam_type', 'sidebar-exam_type'],
            ['language', 'sidebar-language'],
            ['material', 'sidebar-material'],
            ['month', 'sidebar-month'],
            ['province', 'sidebar-province'],
            ['location', 'sidebar-location'],
            ['timeslot', 'sidebar-timeslot']
        ];
        mapping.forEach(function(pair) {
            var mainSel = 'select[name="' + pair[0] + '"]';
            var sideSel = '#' + pair[1];
            if ($(mainSel).length && $(sideSel).length) {
                $(mainSel).val($(sideSel).val());
            }
        });
        updatePontifexTable(1);
        // HIER AANPASSEN:
        $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
    });

    $(document).on('click', '.pontifex-oi-pagination-wrapper nav a', function (e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            console.log('Pagina gewijzigd naar:', page);
            updatePontifexTable(page);
        }
    });

    $(document).on('change', '#pontifex-oi-results-per-page', function() {
        console.log('Results per page gewijzigd naar:', $(this).val());
        updatePontifexTable(1);
    });

    const maxCandidates = 4;

    function updateCandidateCountAndPrice() {
        const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
        const count = $rows.length;
        $('#candidate-count').text(count);

        let baseRaw = $('#payment_amount').data('base-price') || getUrlParams().price;
        if (!baseRaw) {
            baseRaw = $('#payment_amount').val();
            $('#payment_amount').data('base-price', baseRaw);
        }
        let basePrice = parsePrice(baseRaw);
        if (isNaN(basePrice)) basePrice = 0;
        const total = basePrice * count;

        if ($('#total-price').length) {
            $('#total-price').text('€' + total.toFixed(2).replace('.', ','));
        }
        $('#payment_amount').val(total.toFixed(2).replace('.', ','));
        updateTotalPriceWithCheckboxes();
    }

    $(document).on('click', '.pontifex-oi-add-candidate', function () {
        const $list = $('.pontifex-oi-candidates-list');
        const count = $list.find('.pontifex-oi-candidate-row').length;
        if (count >= maxCandidates) return;
        const $firstRow = $list.find('.pontifex-oi-candidate-row').first();
        const $newRow = $firstRow.clone();
        $newRow.find('input').val('');
        $newRow.find('label').each(function () {
            const oldFor = $(this).attr('for');
            if (oldFor) $(this).attr('for', oldFor.replace(/\d+$/, count + 1));
        });
        $newRow.find('input').each(function () {
            const oldId = $(this).attr('id');
            if (oldId) $(this).attr('id', oldId.replace(/\d+$/, count + 1));
        });
        $list.append($newRow);
        updateCandidateCountAndPrice();
    });

    $(document).on('click', '.pontifex-oi-remove-candidate', function () {
        const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
        if ($rows.length > 1) {
            $(this).closest('.pontifex-oi-candidate-row').remove();
        }
        updateCandidateCountAndPrice();
    });

  function pontifexAddSidebarHtml() {
    if ($(window).width() > 480) {
        $('.pontifex-oi-filters-toggle-btn, .pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').remove();
        return;
    }
    if ($('.pontifex-oi-filters-toggle-btn').length) return;

    $('<button type="button" class="pontifex-oi-filters-toggle-btn">Filters</button>').insertBefore('.pontifex-oi-filters');
    $('body').append(
        '<div class="pontifex-oi-filters-sidebar-overlay"></div>' +
        '<aside class="pontifex-oi-filters-sidebar" aria-modal="true" role="dialog" tabindex="0">' +
        '<button class="pontifex-oi-filters-sidebar-close" aria-label="Sluit filters">&times;</button>' +
        '<form class="pontifex-oi-filters-sidebar-form" autocomplete="off">' +
            '<h2>Selecteer een examen</h2>' +
            // Examensoort + Taal naast elkaar
            '<div class="sidebar-exam-row" style="display:flex;flex-direction:row;gap:1rem;">' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-exam_type">Examensoort</label>' +
                    '<select id="sidebar-exam_type" name="exam_type"></select>' +
                '</div>' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-language">Taal</label>' +
                    '<select id="sidebar-language" name="language"></select>' +
                '</div>' +
            '</div>' +
            '<div style="margin-top:0.5rem;">' +
                '<button type="button" class="pontifex-oi-reset-filter pontifex-oi-sidebar-btn">Reset filter</button>' +
            '</div>' +
            '<h2 style="margin-top:1.6rem;">Kies een datum en locatie</h2>' +
            // Maand + Provincie naast elkaar
            '<div class="sidebar-date-row" style="display:flex;flex-direction:row;gap:1rem;">' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-month">Maand</label>' +
                    '<select id="sidebar-month" name="month"></select>' +
                '</div>' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-province">Provincie</label>' +
                    '<select id="sidebar-province" name="province"></select>' +
                '</div>' +
            '</div>' +
            // Locatie + Dagsoort naast elkaar
            '<div class="sidebar-location-row" style="display:flex;flex-direction:row;gap:1rem;">' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-location">Locatie</label>' +
                    '<select id="sidebar-location" name="location"></select>' +
                '</div>' +
                '<div style="flex:1 1 0;min-width:0;">' +
                    '<label for="sidebar-timeslot">Dagsoort</label>' +
                    '<select id="sidebar-timeslot" name="timeslot"></select>' +
                '</div>' +
            '</div>' +
            '<div style="margin-top:1em;">' +
                '<button type="button" class="pontifex-oi-save-btn pontifex-oi-sidebar-btn">Bewaar filters</button>' +
            '</div>' +
        '</form></aside>'
    );

    const mapping = [
        ['exam_type', 'sidebar-exam_type'],
        ['language', 'sidebar-language'],
        ['material', 'sidebar-material'],
        ['month', 'sidebar-month'],
        ['province', 'sidebar-province'],
        ['location', 'sidebar-location'],
        ['timeslot', 'sidebar-timeslot']
    ];
    mapping.forEach(([name, id]) => {
        const $main = $(`select[name="${name}"]`);
        const $sidebar = $(`#${id}`);
        if ($main.length && $sidebar.length) {
            $sidebar.html($main.html());
            $sidebar.val($main.val());
        }
    });

    function updateSidebarMaterialVisibility() {
        const examVal = $('#sidebar-exam_type').val();
        const isWeekend = examWeekend.includes(examVal);
        $('.sidebar-material-block').toggle(!isWeekend);
    }
    $('#sidebar-exam_type').on('change', updateSidebarMaterialVisibility);
    updateSidebarMaterialVisibility();
}

pontifexAddSidebarHtml();
$(window).on('resize', function () {
    if ($(window).width() < 1000) pontifexAddSidebarHtml();
});

// Sidebar openen via Filters-knop
$(document).on('click', '.pontifex-oi-filters-toggle-btn', function (e) {
    e.preventDefault();
    ['exam_type', 'language', 'material', 'month', 'province', 'location', 'timeslot'].forEach(n => {
        $(`#sidebar-${n}`).val($(`select[name="${n}"]`).val());
    });
    $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').addClass('open');
    $('.pontifex-oi-filters-sidebar')[0].focus();
});

// Sidebar sluiten via overlay of X-knop
$(document).on('click', '.pontifex-oi-filters-sidebar-overlay, .pontifex-oi-filters-sidebar-close', function (e) {
    e.preventDefault();
    $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
});

// Sidebar sluiten via ESC
$(document).on('keydown', function (e) {
    if (e.key === 'Escape') {
        $('.pontifex-oi-filters-sidebar, .pontifex-oi-filters-sidebar-overlay').removeClass('open');
    }
});

// Sidebar filters resetten
$(document).on('click', '.pontifex-oi-filters-sidebar .pontifex-oi-reset-filter', function (e) {
    e.preventDefault();
    $('#sidebar-month, #sidebar-province, #sidebar-location, #sidebar-timeslot').val('');
});

$('#back-to-planning').on('click keypress', function (e) {
    if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $('#step-2').hide(); $('#step-1').show();
        $('.pontifex-oi-stepper').text('Stap 1: Examen & planning');
        history.replaceState(null, '', window.location.pathname);
    }
});

    $(document).on('change', '.pontifex-oi-filter', function () {
        console.log('Filter gewijzigd:', $(this).attr('name'), '=', $(this).val());
        updateFiltersState();
        updateAllDynamicPrices();
        updateFormInputsInTableRows();
        updatePontifexTable(1);
    });

    $(document).on('change', 'select[name="exam_type"], select[name="language"], select[name="material"]', function () {
        const changedField = $(this).attr('name');
        const newValue = $(this).val();
        console.log(`${changedField} gewijzigd naar:`, newValue);
        updateFilterCombinationPrice();
        setTimeout(function() {
            updateAllDynamicPrices();
        }, 50);
    });

    $(window).on('resize', function() {
        if ($('.pontifex-oi-table-custom').length && typeof updatePontifexTable === 'function') {
            updatePontifexTable();
        }
    });

    // ===================== INITIALIZATION =====================
    $(document).ready(function() {
        console.log('Document ready - initialiseer prijsupdate systeem');
        
        loadFilterState();
        setDefaultFiltersIfNeeded();
        updateFiltersState();
        updateTotalPriceWithCheckboxes();
        
        setTimeout(function() {
            updateAllDynamicPrices();
        }, 500);
        
        if (!ajaxUrl || ajaxUrl === '') {
            console.error('AJAX URL niet ingesteld! Prijzen kunnen niet worden opgehaald.');
        } else {
            console.log('AJAX URL ingesteld:', ajaxUrl);
        }
    });
});