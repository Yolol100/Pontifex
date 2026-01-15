/* price.js — 2026 style (behavior-preserving)
 * Generated: 2026-01-15
 * Doel: Dynamische prijsberekening + totalen incl. weekend-logica
 */
(function (window, $) {
  'use strict';

  // Early exit in wp-admin
  if (/\/wp-admin\//.test(location.pathname) || document.documentElement?.classList.contains('wp-admin')) {
    return;
  }

  if (typeof $ !== 'function') return;

  const cfg = window.PontifexOIConfigData ?? window.PontifexOIConfig ?? {};
  const PontifexOI = (window.PontifexOI = window.PontifexOI || {});

  // ============================================================================
  //  Constants & Config
  // ============================================================================
  const WEEKEND_IDS = ['cursus-weekend', 'cursus-weekend-nl', 'cursus-weekend-en', 'weekend_dh'];
  const WEEKEND_PRICE_EXCL = 245; // excl. btw

  const priceCache = Object.create(null);
  if (cfg.debug) PontifexOI.__priceCache = priceCache;

  // ============================================================================
  //  Helper Functions
  // ============================================================================
  function weekendCheckboxSelected() {
    const selector = WEEKEND_IDS.map(id => `.extra-material-checkbox[value="${id}"]:checked, input[value="${id}"]:checked`)
      .join(', ');
    return $(selector).length > 0;
  }

  function isWeekendActiveForRow(rootEl) {
    const $root = $(rootEl || document);
    const exam = $root.find('select[name="exam_type"]').val() || '';
    const material = $root.find('select[name="material"]').val() || '1';
    const extras = PontifexOI.getSelectedExtras?.() ?? [];

    const isBasisOrVol = ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(exam);
    const hasWeekendExtra = extras.some(id => WEEKEND_IDS.includes(id));

    return isBasisOrVol && (material === 'cursus-weekend' || hasWeekendExtra);
  }

  function withCacheBuster(url) {
    try {
      const u = new URL(url, window.location.origin);
      const b = cfg.cache_buster ?? Date.now();
      u.searchParams.set('_cb', b);
      return u.toString();
    } catch {
      const b = cfg.cache_buster ?? Date.now();
      const sep = url.includes('?') ? '&' : '?';
      return `${url}${sep}_cb=${encodeURIComponent(b)}`;
    }
  }

  function formatEuro(amount) {
    if (typeof amount !== 'number' || !Number.isFinite(amount)) return '';
    return `€${amount.toFixed(2).replace('.', ',')}`;
  }

  function getAjaxUrl() {
    return window.PontifexOiAjax?.ajax_url ?? cfg.ajaxUrl ?? '';
  }

  function isWeekendExam(examKey) {
    return examKey === 'vca-basis-weekend' || examKey === 'vca-vol-weekend';
  }

  // ============================================================================
  //  Lokale prijsberekening (fallback / snelle pad)
  // ============================================================================
  function calcLocalPrice(exam, lang, material) {
    const product = cfg.examProducts?.[exam];
    if (!product) return null;

    const isWeekendExamType = isWeekendExam(exam);
    const weekendSelected = weekendCheckboxSelected();
    const isBasisOrVol = ['los-examen-vca-basis', 'los-examen-vca-vol'].includes(exam);
    const weekendActive = isWeekendExamType || (weekendSelected && isBasisOrVol);

    if (weekendActive || material === 'cursus-weekend') {
      return WEEKEND_PRICE_EXCL;
    }

    const base = Number(product.prices?.[lang] ?? 0);
    if (!Number.isFinite(base)) return null;

    let total = base;

    if (material && material !== '1' && material !== 'cursus-weekend') {
      const matPrice = cfg.materialProducts?.[material]?.price ??
                       cfg.materialCombis?.[material]?.price ??
                       null;

      if (matPrice != null && Number.isFinite(Number(matPrice))) {
        total += Number(matPrice);
      } else {
        return null;
      }
    }

    return total;
  }

  // ============================================================================
  //  AJAX prijs ophalen (batch + fallback per item)
  // ============================================================================
  async function fetchMissingPricesBatch(items) {
    const url = withCacheBuster(getAjaxUrl());
    if (!url || !items.length) return {};

    try {
      const res = await $.post(url, {
        action: 'pontifex_oi_get_prices',
        items: items
      });

      if (res?.success && res.data?.prices) {
        return res.data.prices;
      }
    } catch {}

    // Fallback: per item ophalen
    const results = await Promise.allSettled(
      items.map(({ key, exam, lang, material }) =>
        $.post(url, {
          action: 'pontifex_oi_get_price',
          exam_type: exam,
          language: lang,
          material
        }).then(r => {
          if (r?.success && r.data) {
            const v = r.data.raw_price ?? 
                     Number(String(r.data.price ?? '').replace(/[^\d.-]/g, '').replace(',', '.'));
            return Number.isFinite(v) ? { key, value: v } : { key, value: null };
          }
          return { key, value: null };
        })
      )
    );

    return results.reduce((acc, r) => {
      if (r.status === 'fulfilled' && r.value?.value != null) {
        acc[r.value.key] = r.value.value;
      }
      return acc;
    }, {});
  }

  // ============================================================================
  //  Prijs element updaten
  // ============================================================================
  function setPriceEl($el, amount, runId) {
    if (runId != null && Number($el.attr('data-price-run-id')) > runId) return;

    const $loader = $el.find('.pontifex-oi-price-loader');
    const $amountEl = $el.find('.pontifex-oi-price-amount');
    const text = Number.isFinite(amount) ? formatEuro(amount) : '';

    $amountEl.text(text);

    const $row = $el.closest('tr, .pontifex-oi-card, .acc-item');
    const $input = $row.find('input[name="price"], .pontifex-oi-price-input');

    if (text) {
      $el.addClass('is-hydrated').attr('data-hydrated', '1');
      $loader.hide();
      $input.val(amount.toFixed(2));
    } else {
      $el.removeClass('is-hydrated').removeAttr('data-hydrated');
      $loader.show();
      $input.val('');
    }
  }

  function hydratePrice($el, runId) {
    const $row = $el.closest('tr, .pontifex-oi-card, .acc-item');

    if ($row.length && isWeekendActiveForRow($row[0])) {
      setPriceEl($el, WEEKEND_PRICE_EXCL, runId);
      return true;
    }

    const exam = $el.data('exam') ?? '';
    const lang = $el.data('language') ?? 'nl';
    const material = $el.data('material') ?? '1';
    const key = `${exam}|${lang}|${material}`;

    // Weekend exam type → altijd proberen lokaal te berekenen
    if (isWeekendExam(exam)) {
      const local = calcLocalPrice(exam, lang, material);
      if (local != null) {
        priceCache[key] = local;
        setPriceEl($el, local, runId);
        return true;
      }
    }

    // Normale cache check
    if (priceCache[key] != null) {
      setPriceEl($el, priceCache[key], runId);
      return true;
    }

    // Lokale berekening als fallback
    const local = calcLocalPrice(exam, lang, material);
    if (local != null) {
      priceCache[key] = local;
      setPriceEl($el, local, runId);
      return true;
    }

    return false;
  }

  // ============================================================================
  //  Totale prijs herberekenen (incl. BTW)
  // ============================================================================
  PontifexOI.__recalcRunId = PontifexOI.__recalcRunId ?? 0;

  function recalcTotal() {
    const runId = ++PontifexOI.__recalcRunId;

    const $pay = $('#payment_amount');
    const $vat = $('#vat-amount');
    const $total = $('#total-price');

    const isFlow2 = Boolean(
      $('#extra_option_direct').val() ||
      new URLSearchParams(location.search).get('extra_option')
    );

    let baseExclPer = Number($pay.attr('data-base-price') ?? 0);
    let baseVatPer = Number($pay.attr('data-base-vat') ?? 0);

    if (isFlow2) {
      baseExclPer = 0;
      baseVatPer = 0;
    }

    const material = PontifexOI.getSelectedFilter?.('material', '') ?? '';
    const weekendActive =
      WEEKEND_IDS.includes(material) || weekendCheckboxSelected();

    let baseExcl = baseExclPer;
    let baseVat = baseVatPer;

    if (weekendActive && baseExcl === 0) {
      baseExcl = WEEKEND_PRICE_EXCL;
      baseVat = baseExcl * 0.21;
    }

    let extrasExcl = 0;
    $('.extra-material-checkbox:checked').each(function () {
      const id = String(this.value ?? '');
      if (WEEKEND_IDS.includes(id) && weekendActive) return;
      extrasExcl += Number($(this).data('price') ?? 0);
    });

    const count = Math.max(
      1,
      parseInt($('#candidate-count').text(), 10) ||
      $('.pontifex-oi-candidate-row').length ||
      1
    );

    const preciseRound = (n, d = 2) =>
      Math.round(n * 10 ** d) / 10 ** d;

    const scaledBase = preciseRound(baseExcl * count, 2);
    const scaledExtras = preciseRound(extrasExcl * count, 2);
    const exclTotal = preciseRound(scaledBase + scaledExtras, 2);

    const scaledBaseVat = preciseRound(baseVat * count, 2);
    const scaledExtraVat = preciseRound(extrasExcl * count * 0.21, 2);
    const vatTotal = preciseRound(scaledBaseVat + scaledExtraVat, 2);

    const inclTotal = preciseRound(exclTotal + vatTotal, 2);

    // Update DOM alleen bij wijziging
    const vatText = formatEuro(vatTotal);
    if ($vat.text() !== vatText) $vat.text(vatText);

    const totalText = formatEuro(inclTotal);
    if ($total.text() !== totalText) $total.text(totalText);

    if ($pay.val() !== inclTotal.toFixed(2)) {
      $pay.val(inclTotal.toFixed(2));
    }

    $pay.attr('data-base-price', baseExcl.toFixed(2));
    $pay.attr('data-base-vat', baseVat.toFixed(2));
  }

  // ============================================================================
  //  Dynamische prijzen hydrateren (hoofdfunctie)
  // ============================================================================
  PontifexOI.updateAllDynamicPrices = async function () {
    const $nodes = $('.pontifex-oi-dynamic-price');
    if (!$nodes.length) return;

    let weekendActive = weekendCheckboxSelected();
    if (!weekendActive) {
      weekendActive = $nodes.toArray().some(el =>
        isWeekendExam((el.getAttribute('data-exam') ?? '').trim())
      );
    }

    if (weekendActive) {
      Object.keys(priceCache).forEach(k => delete priceCache[k]);
    }

    const runId = ++PontifexOI.__priceRunId;
    $nodes.attr('data-price-run-id', runId);

    const toFetch = [];
    const keyMap = [];

    $nodes.each(function () {
      const $el = $(this);
      const exam = $el.data('exam') ?? '';
      const lang = $el.data('language') ?? 'nl';
      const material = $el.data('material') ?? '1';
      const key = `${exam}|${lang}|${material}`;

      keyMap.push({ $el, key });

      if (hydratePrice($el, runId)) return;

      setPriceEl($el, null, runId);
      toFetch.push({ key, exam, lang, material });
    });

    if (toFetch.length) {
      try {
        const prices = await fetchMissingPricesBatch(toFetch);
        keyMap.forEach(({ $el, key }) => {
          const val = Number(prices?.[key]);
          if (Number.isFinite(val)) {
            priceCache[key] = val;
            setPriceEl($el, val, runId);
          }
        });
      } catch (err) {
        if (cfg.debug) console.error('Batch price fetch failed:', err);
      }
    }
  };

  // ============================================================================
  //  Initialisatie & Events
  // ============================================================================
  jQuery(function ($) {
    // Asynchroon starten om DOM flicker te minimaliseren
    setTimeout(() => {
      PontifexOI.updateAllDynamicPrices();
      PontifexOI.recalcTotal();
    }, 0);

    // Candidate count observer
    const candidateEl = document.getElementById('candidate-count');
    if (candidateEl) {
      new MutationObserver(() => PontifexOI.recalcTotal())
        .observe(candidateEl, { childList: true, characterData: true, subtree: true });
    }

    // Events die totalen beïnvloeden
    $(document).on('change pontifex:recalc-total', 
      '.extra-material-checkbox, select[name="exam_type"], select[name="material"], select[name="language"]', 
      () => PontifexOI.recalcTotal()
    );

    $(document).on('click', '.pontifex-oi-add-candidate, .pontifex-oi-remove-candidate', () => {
      setTimeout(() => PontifexOI.recalcTotal(), 0);
    });

    // Mobile touch verbetering
    if (/Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
      $(document).on('touchend', '.extra-material-checkbox', () => {
        setTimeout(() => PontifexOI.recalcTotal(), 50);
      });
    }

    // Legacy single registration prijs fetch
    const $regForm = $('.pontifex-oi-registration');
    if ($regForm.length) {
      const fetchLegacyPrice = async () => {
        const exam = $('select[name="exam_type"]').val() ?? '';
        const lang = $('select[name="language"]').val() ?? 'nl';
        const mat = $('select[name="material"]').val() ?? '1';
        const cnt = parseInt($('select[name="candidate_count"], input[name="candidate_count"]').val() ?? '1', 10) || 1;

        const url = getAjaxUrl();
        if (!url) return;

        try {
          const res = await $.post(url, {
            action: 'pontifex_oi_get_price',
            exam_type: exam,
            language: lang,
            material: mat,
            candidate_count: cnt
          });

          if (res?.success && res.data) {
            const d = res.data;
            $('#vat-amount').text(formatEuro(d.vat_total ?? 0));
            $('.js-total-price, .pontifex-total-price').text(d.price ?? '');
            $('#payment_amount').val(Number(d.total_incl ?? 0).toFixed(2));
          }
        } catch {}
      };

      fetchLegacyPrice();
      $regForm.on('change', 'select[name="exam_type"], select[name="language"], select[name="material"], input[name="candidate_count"]', fetchLegacyPrice);
    }
  });

  // Expose voor extern gebruik
  Object.assign(PontifexOI, {
    recalcTotal,
    updateAllDynamicPrices,
    calcLocalPrice,
    isWeekendActiveForRow,
    setPriceEl
  });

})(window, jQuery);