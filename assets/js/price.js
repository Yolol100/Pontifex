(function(window, $) {
'use strict';

// 1. Exit early if in WordPress admin area
if (/\/wp-admin\//.test(location.pathname) || (document.documentElement && document.documentElement.classList.contains('wp-admin'))) {
    return;
}

const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
if (!window.PontifexOI) window.PontifexOI = {};
const PontifexOI = window.PontifexOI;

// Constants
const WEEKEND_IDS = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
const WEEKEND_PRICE_EXCL = 245;           // excl btw
const VAT_RATE = 0.21;                    // centrale constante → matcht PHP

// --- Helper Functions ---
function weekendCheckboxSelected() {
    const selector = WEEKEND_IDS.map(id => `.extra-material-checkbox[value="${id}"]:checked, input[value="${id}"]:checked`).join(', ');
    return $(selector).length > 0;
}

function isWeekendActiveForRow(rootEl) {
    const $rootEl = $(rootEl || document);
    const exam = $rootEl.find('select[name="exam_type"]').val() || $('select[name="exam_type"]').val() || '';
    const material = $rootEl.find('select[name="material"]').val() || $('select[name="material"]').val() || '1';
    const extras = (window.PontifexOI && PontifexOI.getSelectedExtras) ? PontifexOI.getSelectedExtras() : [];
    const basisOrVol = exam === 'los-examen-vca-basis' || exam === 'los-examen-vca-vol';
    const weekendExtra = extras.some(id => WEEKEND_IDS.includes(id));
    return basisOrVol && (material === 'cursus-weekend' || weekendExtra);
}

function withCacheBuster(url) {
    try {
        const b = (window.PontifexOIConfig && window.PontifexOIConfig.cache_buster) || Date.now();
        const u = new URL(url, window.location.origin);
        u.searchParams.set('_cb', b);
        return u.toString();
    } catch (e) {
        const sep = url.indexOf('?') === -1 ? '?' : '&';
        const b = (window.PontifexOIConfig && window.PontifexOIConfig.cache_buster) || Date.now();
        return url + sep + '_cb=' + encodeURIComponent(b);
    }
}

function formatEuro(amount) {
    if (typeof amount !== 'number' || !isFinite(amount)) return '';
    return '€' + amount.toFixed(2).replace('.', ',');
}

function getAjaxUrl() {
    return (window.PontifexOiAjax && window.PontifexOiAjax.ajax_url) ||
           (window.PontifexOIConfigData && window.PontifexOIConfigData.ajaxUrl) || '';
}

const priceCache = Object.create(null);
if (cfg.debug) PontifexOI.__priceCache = priceCache;

function calcLocalPrice(exam, lang, material) {
    const ex = (cfg.examProducts || {})[exam];
    if (!ex) return null;

    const examType = exam;
    const isWeekendExamType = (examType === 'vca-basis-weekend' || examType === 'vca-vol-weekend');
    const weekendSelected = weekendCheckboxSelected();
    const isBasisOrVol = ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(examType);
    const weekendActive = isWeekendExamType || (weekendSelected && isBasisOrVol);

    if (weekendActive || material === 'cursus-weekend') {
        return WEEKEND_PRICE_EXCL;
    }

    const base = Number((ex.prices || {})[lang]);
    if (!isFinite(base)) return null;

    let total = base;
    if (material && material !== '1' && material !== 'cursus-weekend') {
        const mat = (cfg.materialProducts || {})[material];
        if (mat && isFinite(Number(mat.price))) {
            total += Number(mat.price);
        } else {
            const combi = (cfg.materialCombis || {})[material];
            if (combi && isFinite(Number(combi.price))) {
                total += Number(combi.price);
            } else {
                return null;
            }
        }
    }
    return total;
}

function perItemFallback(items, url) {
    const requests = items.map(({ key, exam, lang, material }) => {
        return $.post(url, {
            action: 'pontifex_oi_get_price',
            exam_type: exam,
            language: lang,
            material: material
        }).then(function(res) {
            if (res && res.success && res.data) {
                if (typeof res.data.raw_price !== 'undefined' && isFinite(Number(res.data.raw_price))) {
                    return { key, value: Number(res.data.raw_price) };
                }
                if (typeof res.data.price === 'string') {
                    const v = Number(String(res.data.price).replace(/[^\d,.-]/g, '').replace(',', '.'));
                    if (isFinite(v)) return { key, value: v };
                }
            }
            return { key, value: null };
        }, function() {
            return { key, value: null };
        });
    });

    return Promise.all(requests).then(list => {
        const out = {};
        list.forEach(({ key, value }) => {
            if (value != null && isFinite(value)) out[key] = value;
        });
        return out;
    });
}

function fetchMissingPricesBatch(items) {
    const url = withCacheBuster(getAjaxUrl());
    if (!url || !items.length) return Promise.resolve({});

    return new Promise((resolve) => {
        $.post(url, {
            action: 'pontifex_oi_get_prices',
            items: items
        }).done(function(res) {
            if (res && res.success) {
                const data = res.data || {};
                const map = (data && data.prices) ? data.prices : data;
                if (map && Object.keys(map).length) {
                    resolve(map);
                } else {
                    perItemFallback(items, url).then(resolve);
                }
            } else {
                perItemFallback(items, url).then(resolve);
            }
        }).fail(function() {
            perItemFallback(items, url).then(resolve);
        });
    });
}

function setPriceEl($el, amountNumber, runId) {
    if (runId != null && Number($el.attr('data-price-run-id')) > runId) return;

    const $loader = $el.find('.pontifex-oi-price-loader');
    const $amt = $el.find('.pontifex-oi-price-amount');
    const txt = (typeof amountNumber === 'number' && isFinite(amountNumber)) ? formatEuro(amountNumber) : '';

    if ($amt.length) $amt.text(txt);

    const $row = $el.closest('tr, .pontifex-oi-card, .acc-item');
    const $priceInput = $row.find('input[name="price"], .pontifex-oi-price-input');

    if (txt) {
        $el.addClass('is-hydrated').attr('data-hydrated', '1');
        $loader.hide();
    } else {
        $el.removeClass('is-hydrated').removeAttr('data-hydrated');
        $loader.show();
        $priceInput.val('');
    }
}

PontifexOI.__priceRunId = PontifexOI.__priceRunId || 0;

function isWeekendExam(examKey) {
    return examKey === 'vca-basis-weekend' || examKey === 'vca-vol-weekend';
}

function hydratePrice($span, runId) {
    const $row = $span.closest('tr, .pontifex-oi-card, .acc-item');

    if ($row.length && isWeekendActiveForRow($row[0])) {
        setPriceEl($span, WEEKEND_PRICE_EXCL, runId);
        return true;
    }

    const exam = $span.data('exam');
    const lang = $span.data('language') || 'nl';
    const material = $span.data('material') || '1';
    const key = `${exam}|${lang}|${material}`;
    const examForEl = (exam || '').trim();
    const weekendForEl = isWeekendExam(examForEl);

    if (weekendForEl) {
        const local = calcLocalPrice(exam, lang, material);
        if (local != null) {
            priceCache[key] = local;
            setPriceEl($span, local, runId);
            return true;
        }
    }

    if (priceCache[key] != null && !weekendForEl) {
        setPriceEl($span, priceCache[key], runId);
        return true;
    }

    const local = calcLocalPrice(exam, lang, material);
    if (local != null) {
        priceCache[key] = local;
        setPriceEl($span, local, runId);
        return true;
    }

    return false;
}

function isFlow2Active() {
    const qsExtra = (() => { try { return new URLSearchParams(location.search).get('extra_option'); } catch(e) { return ''; } })();
    return Boolean((($('#extra_option_direct').val() || qsExtra) || '').trim());
}

PontifexOI.__recalcRunId = PontifexOI.__recalcRunId || 0;

function recalcTotal() {
    const runId = ++PontifexOI.__recalcRunId;

    console.log(`recalcTotal started [Run ${runId}]`, JSON.stringify({
        isFlow2: isFlow2Active(),
        dataBasePrice: $('#payment_amount').attr('data-base-price'),
        dataBaseVat: $('#payment_amount').attr('data-base-vat'),
        candidateCount: $('#candidate-count').text(),
        checkedExtras: $('.extra-material-checkbox:checked').map((i, el) => ({
            id: el.value,
            price: $(el).data('price')
        })).get()
    }, null, 2));

    const $pay = $('#payment_amount');
    const $vatAmt = $('#vat-amount');
    const $totalPrice = $('#total-price');

    const isFlow2 = isFlow2Active();

    let baseExcl = parseFloat($pay.attr('data-base-price')) || 0;
    let baseVat  = parseFloat($pay.attr('data-base-vat'))   || 0;

    if (isFlow2) {
        baseExcl = 0;
        baseVat  = 0;
    }

    const isWeekendActive =
        WEEKEND_IDS.includes(String(PontifexOI.getSelectedFilter && PontifexOI.getSelectedFilter('material', ''))) ||
        weekendCheckboxSelected();

    if (isWeekendActive && baseExcl === 0) {
        baseExcl = WEEKEND_PRICE_EXCL;
        baseVat  = baseExcl * VAT_RATE;
    }

    let extrasExcl = 0;
    let vat21 = 0;

    $('.extra-material-checkbox:checked').each(function() {
        const priceExcl = parseFloat($(this).data('price')) || 0;
        const id = String($(this).val() || '');

        if (WEEKEND_IDS.includes(id) && isWeekendActive) {
            return;
        }

        extrasExcl += priceExcl;
        vat21 += priceExcl * VAT_RATE;
    });

    let count = parseInt($('#candidate-count').text(), 10);
    if (!Number.isFinite(count) || count <= 0) {
        const rows = $('.pontifex-oi-candidate-row').length;
        count = rows > 0 ? rows : 1;
    }

    const preciseRound = (value, decimals = 2) => {
        return Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
    };

    const scaledBaseExcl   = preciseRound(baseExcl   * count, 2);
    const scaledExtrasExcl = preciseRound(extrasExcl * count, 2);
    const exclAll          = preciseRound(scaledBaseExcl + scaledExtrasExcl, 2);

    const finalVat21    = preciseRound(vat21   * count, 2);
    const finalBaseVat  = preciseRound(baseVat * count, 2);

    const vatAll = preciseRound(finalBaseVat + finalVat21, 2);
    const inclAll = preciseRound(exclAll + vatAll, 2);

    console.log(`recalcTotal result [Run ${runId}]`, JSON.stringify({
        baseExcl: baseExcl.toFixed(2),
        scaledBaseExcl: scaledBaseExcl.toFixed(2),
        extrasExcl: extrasExcl.toFixed(2),
        scaledExtrasExcl: scaledExtrasExcl.toFixed(2),
        count,
        baseVat: baseVat.toFixed(2),
        vat21: vat21.toFixed(2),
        exclAll: exclAll.toFixed(2),
        vatAll: vatAll.toFixed(2),
        inclAll: inclAll.toFixed(2)
    }, null, 2));

    const newVatAmount = vatAll;
    const newTotalIncl = inclAll;

    const newVatText   = formatEuro(newVatAmount);
    const newTotalText = formatEuro(newTotalIncl);

    if ($vatAmt.text() !== newVatText) {
        $vatAmt.text(newVatText);
    }

    if ($totalPrice.text() !== newTotalText) {
        $totalPrice.text(newTotalText);
    }

    // Verbeterde robuustheid: || 0 voorkomt NaN bij lege/ongeldige waarde
    const currentPaymentVal = parseFloat($pay.val()) || 0;
    if (newTotalIncl.toFixed(2) !== currentPaymentVal.toFixed(2)) {
        $pay.val(newTotalIncl.toFixed(2));
    }

    $pay.attr('data-base-price', baseExcl.toFixed(2));
    $pay.attr('data-base-vat',   baseVat.toFixed(2));
}

// --- Dynamic Price Update Function ---
PontifexOI.updateAllDynamicPrices = async function() {
    const $nodes = $('.pontifex-oi-dynamic-price');
    if (!$nodes.length) {
        let forced = $('#payment_amount').attr('data-force-price');
        if (forced) {
            $('#payment_amount').val(forced);
        }
        return;
    }

    let weekendActive = weekendCheckboxSelected();
    if (!weekendActive) {
        const hasWeekendRow = $nodes.toArray().some(n => {
            const ex = (n.getAttribute('data-exam') || '').trim();
            return isWeekendExam(ex);
        });
        weekendActive = hasWeekendRow;
    }

    if (weekendActive) {
        Object.keys(priceCache).forEach(key => delete priceCache[key]);
    }

    const runId = ++PontifexOI.__priceRunId;
    $nodes.attr('data-price-run-id', runId);

    const toFetch = [];
    const keyMap = [];

    $nodes.each(function() {
        const $el = $(this);
        const exam = $el.data('exam');
        const lang = $el.data('language') || 'nl';
        const material = $el.data('material') || '1';
        const key = `${exam}|${lang}|${material}`;
        keyMap.push({ $el, key });

        if (hydratePrice($el, runId)) {
            return;
        }

        setPriceEl($el, null, runId);
        toFetch.push({ key, exam, lang, material });
    });

    if (toFetch.length) {
        try {
            const serverMap = await fetchMissingPricesBatch(toFetch);
            keyMap.forEach(({ $el, key }) => {
                if (serverMap && Object.prototype.hasOwnProperty.call(serverMap, key)) {
                    const v = Number(serverMap[key]);
                    if (isFinite(v)) {
                        priceCache[key] = v;
                        setPriceEl($el, v, runId);
                    }
                }
            });
        } catch (error) {
            console.error("Failed to fetch prices in batch:", error);
        }
    }
};

// --- Legacy Registration Price Fetch ---
async function fetchPrice() {
    const $reg = $('.pontifex-oi-registration');
    if (!$reg.length) return;

    const $exam = $('input[name="exam_type"], select[name="exam_type"]');
    const $lang = $('input[name="language"], select[name="language"]');
    const $mat  = $('input[name="material"], select[name="material"]');
    const $count = $('input[name="candidate_count"], select[name="candidate_count"]');

    const $priceEl = $('.js-total-price, .pontifex-total-price');

    const exam_type = $exam.val() || '';
    const language = $lang.val() || 'nl';
    const material = $mat.val() || '1';
    const candidate_count = parseInt(($count.val() || '1'), 10) || 1;

    const ajaxUrl = getAjaxUrl();
    if (!ajaxUrl) {
        if (cfg.debug) console.warn('No AJAX URL available');
        return;
    }

    try {
        const res = await $.ajax({
            url: ajaxUrl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'pontifex_oi_get_price',
                exam_type,
                language,
                material,
                candidate_count
            }
        });

        const data = (res && res.data && res.data.data) ? res.data.data : (res.data || res);

        if (data && typeof data.price !== 'undefined') {
            if (typeof data.vat_total === 'number') {
                $('#vat-amount').text(formatEuro(data.vat_total));
            }
            if ($priceEl.length) $priceEl.text(data.price);

            let $raw = $('input[name="calculated_price_excl"]');
            if (!$raw.length) $raw = $('<input>', {type: 'hidden', name: 'calculated_price_excl'}).appendTo('form.pontifex-oi-registration-form');
            $raw.val(String(data.raw_price || ''));

            let $pay = $('#payment_amount');
            if (!$pay.length) $pay = $('<input>', {type: 'hidden', id: 'payment_amount', name: 'payment_amount'}).appendTo('form.pontifex-oi-registration-form');
            if (typeof data.total_incl === 'number') {
                $pay.val((data.total_incl).toFixed(2));
            }
        }
    } catch (e) {
        if (cfg.debug) console.error('Failed to fetch price:', e);
    }
}

// --- Exposed Functions and Initialization ---
PontifexOI.calcLocalPrice = calcLocalPrice;
PontifexOI.fetchMissingPricesBatch = fetchMissingPricesBatch;
PontifexOI.setPriceEl = setPriceEl;
PontifexOI.recalcTotal = recalcTotal;
PontifexOI.isWeekendActiveForRow = isWeekendActiveForRow;

jQuery(function($) {
    'use strict';

    setTimeout(PontifexOI.updateAllDynamicPrices, 0);
    setTimeout(PontifexOI.recalcTotal, 0);

    // Candidate count observer
    (function() {
        const el = document.getElementById('candidate-count');
        if (!el) return;
        const obs = new MutationObserver(() => recalcTotal());
        obs.observe(el, { childList: true, characterData: true, subtree: true });
    })();

    $(document).on('pontifex:recalc-total change', '.extra-material-checkbox', function() {
        recalcTotal();
    });

    $(document).on('change', '.extra-material-checkbox, select[name="exam_type"], select[name="material"], select[name="language"]', recalcTotal);

    $(document).on('click', '.pontifex-oi-add-candidate, .pontifex-oi-remove-candidate', function() {
        setTimeout(recalcTotal, 0);
    });

    function isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    if (isMobileDevice()) {
        $(document).on('touchend', '.extra-material-checkbox', function() {
            setTimeout(recalcTotal, 50);
        });
    }

    const $reg = $('.pontifex-oi-registration');
    if ($reg.length) {
        fetchPrice();
        $(document).on('change', 'select[name="material"], select[name="language"], select[name="exam_type"], input[name="candidate_count"]', fetchPrice);
    }
});

})(window, jQuery);