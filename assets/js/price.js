(function(window) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  function parsePrice(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
  }

  function getCfg() {
    const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
    return {
      EXAM_PRODUCTS: cfg.examProducts || cfg.EXAM_PRODUCTS || {},
      MATERIAL_PRODUCTS: cfg.materialProducts || cfg.MATERIAL_PRODUCTS || {},
      MATERIAL_COMBIS: cfg.materialCombis || cfg.MATERIAL_COMBIS || {},
      EXAM_WEEKEND: cfg.examWeekend || cfg.EXAM_WEEKEND || [],
      ajaxUrl: cfg.ajaxUrl || ''
    };
  }

  function calculatePrice(exam, lang, mat) {
    const cfg = getCfg();

    if (cfg.EXAM_WEEKEND.includes(exam)) return 245;

    let total = 0;

    if (cfg.EXAM_PRODUCTS[exam]) {
      const p = cfg.EXAM_PRODUCTS[exam].prices || {};
      total += (p[lang] ?? p['nl'] ?? 0);
    }

    if (!mat || mat === '1') return total;

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

  function fetchPrice(exam, lang, mat, cb, ajaxUrl = '') {
    const cfg = getCfg();

    if (cfg.EXAM_WEEKEND.includes(exam)) {
      cb('€245,00');
      return;
    }

    const localPrice = calculatePrice(exam, lang, mat);

    const url = ajaxUrl || cfg.ajaxUrl;
    if (!url) {
      cb('€' + localPrice.toFixed(2).replace('.', ','));
      return;
    }

    if (typeof window.jQuery !== 'undefined') {
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
          if (resp?.success && resp?.data?.price) cb(resp.data.price);
          else cb('€' + localPrice.toFixed(2).replace('.', ','));
        },
        error: function() {
          cb('€' + localPrice.toFixed(2).replace('.', ','));
        }
      });
      return;
    }

    fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          action: 'pontifex_oi_get_price',
          exam_type: exam,
          language: lang,
          material: mat
        })
      })
      .then(r => r.json())
      .then(resp => {
        if (resp?.success && resp?.data?.price) cb(resp.data.price);
        else cb('€' + localPrice.toFixed(2).replace('.', ','));
      })
      .catch(() => cb('€' + localPrice.toFixed(2).replace('.', ',')));
  }

  PontifexOI.calculatePrice = calculatePrice;
  PontifexOI.fetchPrice = fetchPrice;
  PontifexOI.parsePrice = parsePrice;

})(window);