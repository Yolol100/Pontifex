(function(window) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  PontifexOI.debounce = function(func, wait, immediate) {
    let timeout;
    return function () {
      const context = this, args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        timeout = null;
        if (!immediate) func.apply(context, args);
      }, wait);
      if (immediate && !timeout) func.apply(context, args);
    };
  };

  PontifexOI.getUrlParams = function() {
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
  };

  PontifexOI.saveFilterState = function() {
    const $ = window.jQuery;
    if (!$) return;
    localStorage.setItem('exam_type', $('select[name="exam_type"]').val());
    localStorage.setItem('language', $('select[name="language"]').val());
    localStorage.setItem('material', $('select[name="material"]').val());
  };

  PontifexOI.loadFilterState = function() {
    const $ = window.jQuery;
    if (!$) return;
    const urlParams = PontifexOI.getUrlParams();

    if (urlParams.exam_type && $('select[name="exam_type"] option[value="' + urlParams.exam_type + '"]').length) {
      $('select[name="exam_type"]').val(urlParams.exam_type);
    } else {
      const examStored = localStorage.getItem('exam_type');
      if (examStored && $('select[name="exam_type"] option[value="' + examStored + '"]').length) {
        $('select[name="exam_type"]').val(examStored);
      }
    }

    if (urlParams.language && $('select[name="language"] option[value="' + urlParams.language + '"]').length) {
      $('select[name="language"]').val(urlParams.language);
    } else {
      const langStored = localStorage.getItem('language');
      if (langStored && $('select[name="language"] option[value="' + langStored + '"]').length) {
        $('select[name="language"]').val(langStored);
      }
    }

    if (urlParams.material && $('select[name="material"] option[value="' + urlParams.material + '"]').length) {
      $('select[name="material"]').val(urlParams.material);
    } else {
      const matStored = localStorage.getItem('material');
      if (matStored && $('select[name="material"] option[value="' + matStored + '"]').length) {
        $('select[name="material"]').val(matStored);
      }
    }
  };

  PontifexOI.setDefaultFiltersIfNeeded = function() {
    const $ = window.jQuery;
    if (!$) return;
    const urlParams = PontifexOI.getUrlParams();

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
  };

  PontifexOI.parsePrice = function(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
  };

})(window);