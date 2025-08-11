// assets/js/price.js
(function(window) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  function calculatePrice(exam, lang, mat) {
    const cfg = PontifexOI.getCfg();

    let total = 0;

    if (cfg.EXAM_PRODUCTS[exam]) {
      const p = cfg.EXAM_PRODUCTS[exam].prices || {};
      total += (p[lang] ?? p['nl'] ?? 0);
    }

    if (!mat || mat === '1') return total;

    if (mat === 'cursus-weekend') {
      if (PontifexOI.isWeekendAllowed(exam, lang)) {
        return total + (cfg.MATERIAL_PRODUCTS['cursus-weekend']?.price ?? 245);
      }
      return total;
    }

    let combiKey = mat;
    if (['2', '4', '5', '6', '7'].includes(mat)) {
      const suffix = exam.includes('vca-vol') ? 'vol' : 'basis';
      combiKey = `${mat}_${suffix}`;
    }

    const combi = cfg.MATERIAL_COMBIS[combiKey] || [];
    combi.forEach(id => {
      total += cfg.MATERIAL_PRODUCTS[id]?.price ?? 0;
    });

    return total;
  }

  // Cache
  const _priceCache = new Map(); // key -> "€x,xx"

  function cacheKey(exam, lang, mat) {
    return `${exam}|${lang}|${mat}`;
  }

  function fetchPrice(exam, lang, mat, cb, ajaxUrl = '') {
    const key = cacheKey(exam, lang, mat);
    if (_priceCache.has(key)) {
      cb(_priceCache.get(key));
      return;
    }

    const localPrice = calculatePrice(exam, lang, mat);
    const localStr = '€' + localPrice.toFixed(2).replace('.', ',');

    const onRegistrationPage = !!document.querySelector('#step-2');
    if (!onRegistrationPage) {
      _priceCache.set(key, localStr);
      cb(localStr);
      return;
    }

    const cfgAll = PontifexOI.getCfg();
    const url = ajaxUrl || cfgAll.ajaxUrl;
    if (!url || typeof window.jQuery === 'undefined') {
      _priceCache.set(key, localStr);
      cb(localStr);
      return;
    }

    window.jQuery.ajax({
      url: url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'pontifex_oi_get_price',
        exam_type: exam,
        language: lang,
        material: mat
      },
      success: function(resp) {
        const out = (resp?.success && resp?.data?.price) ? resp.data.price : localStr;
        _priceCache.set(key, out);
        cb(out);
      },
      error: function() {
        _priceCache.set(key, localStr);
        cb(localStr);
      }
    });
  }

  // NEW: Batch fetch
  function fetchPricesBatch(items, cb) {
    // items: [{exam_type, language, material}]
    const cfgAll = PontifexOI.getCfg();
    const url = cfgAll.ajaxUrl || '';
    const onRegistrationPage = !!document.querySelector('#step-2');

    // Prepare request, but skip those already in cache; compute local immediately if not on registration page
    const toRequest = [];
    const result = {};

    items.forEach(it => {
      const key = cacheKey(it.exam_type, it.language, it.material);
      if (_priceCache.has(key)) {
        result[key] = _priceCache.get(key);
      } else if (!onRegistrationPage || !url) {
        const local = calculatePrice(it.exam_type, it.language, it.material);
        const localStr = '€' + local.toFixed(2).replace('.', ',');
        _priceCache.set(key, localStr);
        result[key] = localStr;
      } else {
        toRequest.push(it);
      }
    });

    if (toRequest.length === 0) {
      cb(result);
      return;
    }

    if (typeof window.jQuery === 'undefined') {
      // Fallback compute local even on step 2
      toRequest.forEach(it => {
        const k = cacheKey(it.exam_type, it.language, it.material);
        const local = calculatePrice(it.exam_type, it.language, it.material);
        const localStr = '€' + local.toFixed(2).replace('.', ',');
        _priceCache.set(k, localStr);
        result[k] = localStr;
      });
      cb(result);
      return;
    }

    window.jQuery.ajax({
      url: url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'pontifex_oi_get_prices_batch',
        items: JSON.stringify(toRequest)
      },
      success: function(resp) {
        if (resp?.success && resp?.data?.prices) {
          const prices = resp.data.prices;
          Object.keys(prices).forEach(k => {
            _priceCache.set(k, prices[k]);
            result[k] = prices[k];
          });
          // any missing -> fill local
          toRequest.forEach(it => {
            const k = cacheKey(it.exam_type, it.language, it.material);
            if (!result[k]) {
              const local = calculatePrice(it.exam_type, it.language, it.material);
              const localStr = '€' + local.toFixed(2).replace('.', ',');
              _priceCache.set(k, localStr);
              result[k] = localStr;
            }
          });
          cb(result);
        } else {
          // server failed -> local fallback
          toRequest.forEach(it => {
            const k = cacheKey(it.exam_type, it.language, it.material);
            const local = calculatePrice(it.exam_type, it.language, it.material);
            const localStr = '€' + local.toFixed(2).replace('.', ',');
            _priceCache.set(k, localStr);
            result[k] = localStr;
          });
          cb(result);
        }
      },
      error: function() {
        toRequest.forEach(it => {
          const k = cacheKey(it.exam_type, it.language, it.material);
          const local = calculatePrice(it.exam_type, it.language, it.material);
          const localStr = '€' + local.toFixed(2).replace('.', ',');
          _priceCache.set(k, localStr);
          result[k] = localStr;
        });
        cb(result);
      }
    });
  }

  PontifexOI.calculatePrice = calculatePrice;
  PontifexOI.fetchPrice = fetchPrice;
  PontifexOI.fetchPricesBatch = fetchPricesBatch;

})(window);