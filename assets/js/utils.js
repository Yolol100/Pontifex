(function(window) {
    'use strict';
    const PontifexOI = window.PontifexOI = window.PontifexOI || {};

    PontifexOI.getCfg = function () {
        const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
        return {
            EXAM_PRODUCTS: cfg.examProducts || cfg.EXAM_PRODUCTS || {},
            MATERIAL_PRODUCTS: cfg.materialProducts || cfg.MATERIAL_PRODUCTS || {},
            MATERIAL_COMBIS: cfg.materialCombis || cfg.MATERIAL_COMBIS || {},
            WEEKEND_ALLOWED_BY_EXAM: cfg.weekendAllowedByExam || {},
            ajaxUrl: cfg.ajaxUrl || '',
            restBase: (cfg.restBase || '').replace(/\/+$/, '')
        };
    };

    /**
     * UNIFIED POST call with REST -> AJAX fallback.
     * Tries to use the REST API first. If it fails, it falls back to a standard
     * WordPress AJAX call if an AJAX URL is configured.
     * @param {string} endpointOrAction - REST API endpoint or base name for AJAX action.
     * @param {object} payload - The data to send with the request.
     * @returns {jQuery.Promise} A promise that resolves or rejects based on the call.
     */
    PontifexOI.apiPost = function(endpointOrAction, payload) {
        const cfg = PontifexOI.getCfg();

        // Helper function to map REST endpoints to AJAX actions
        const actionForAjax = (ep) => {
            switch (ep) {
                case 'planning':
                    return 'pontifex_oi_get_planning';
                case 'price':
                    return 'pontifex_oi_get_price';
                case 'prices_batch':
                    return 'pontifex_oi_get_prices_batch';
                default:
                    // Default action format for other endpoints
                    return 'pontifex_oi_' + String(ep).replace(/^\/+/, '');
            }
        };

        // Attempt REST API call first
        if (cfg.restBase) {
            const url = cfg.restBase + '/' + String(endpointOrAction).replace(/^\/+/, '');
            const jq = jQuery.ajax({
                url,
                method: 'POST',
                dataType: 'json',
                data: payload || {}
            });

            // Fallback to AJAX when REST fails but an AJAX URL is present
            if (cfg.ajaxUrl) {
                const d = jQuery.Deferred();
                jq.done(function(res) {
                        d.resolve(res);
                    })
                    .fail(function() {
                        jQuery.post(cfg.ajaxUrl, Object.assign({
                                action: actionForAjax(endpointOrAction)
                            }, payload || {}))
                            .done(function(res) {
                                d.resolve(res);
                            })
                            .fail(function(err) {
                                d.reject(err);
                            });
                    });
                return d.promise();
            }
            return jq;
        }

        // Only use AJAX if REST Base isn't configured
        if (cfg.ajaxUrl) {
            return jQuery.post(cfg.ajaxUrl, Object.assign({
                action: actionForAjax(endpointOrAction)
            }, payload || {}));
        }

        // If neither endpoint is configured, return a rejected promise
        return jQuery.Deferred().reject('No endpoint');
    };

    PontifexOI.isWeekendAllowed = function(exam, lang) {
        const cfg = PontifexOI.getCfg();
        const allowed = cfg.WEEKEND_ALLOWED_BY_EXAM[exam] || [];
        if (allowed.includes(lang)) return true;
        return /weekend/i.test(exam);
    };

    PontifexOI.normalizeExam = function(v) {
        if (!v) return v;
        switch (v) {
            case 'vca-basis':
                return 'los-examen-vca-basis';
            case 'vca-vol':
                return 'los-examen-vca-vol';
            case 'los-examen-vil-vcu':
                return 'los-examen-vca-vil';
            default:
                return v;
        }
    };

    PontifexOI.debounce = function(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this,
                args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
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

    /**
     * @param {string|number} str - De te parseren prijsstring (kan ',' of '.' als decimaalscheiding hebben).
     * @returns {number} De numerieke prijs, of 0.
     * ✅ Fix: Altijd naar string casten om .replace te garanderen.
     */
    PontifexOI.parsePrice = function(str) {
        // Cast naar string en voer vervangingen uit om robuust te zijn tegen verschillende inputs
        str = (str + '').replace(',', '.').replace('€', '').trim();
        
        // Gebruik vervolgens parseFloat, wat nu betrouwbaar is met '.' als decimaalscheiding
        return parseFloat(str) || 0;
    };

})(window);