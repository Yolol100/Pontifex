// assets/js/price.js
(function(window) {
    'use strict';

    const PontifexOI = window.PontifexOI = window.PontifexOI || {};

    // Cache to store fetched prices
    const _priceCache = new Map();

    /**
     * Generates a unique cache key for a given combination of exam, language, and material.
     * @param {string} exam
     * @param {string} lang
     * @param {string} mat
     * @returns {string}
     */
    function cacheKey(exam, lang, mat) {
        return `${exam}|${lang}|${mat}`;
    }

    /**
     * Calculates the price locally based on configured product data.
     * This is a fallback or for pages where server-side verification isn't needed.
     * @param {string} exam
     * @param {string} lang
     * @param {string} mat
     * @returns {number}
     */
    function calculateLocalPrice(exam, lang, mat) {
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

    /**
     * Fetches the price for a single item, using local calculation, server-side
     * AJAX, or a REST API call, and caches the result.
     * @param {string} exam
     * @param {string} lang
     * @param {string} mat
     * @param {function} cb - Callback function to handle the price string.
     */
    function fetchPrice(exam, lang, mat, cb) {
        const key = cacheKey(exam, lang, mat);
        if (_priceCache.has(key)) {
            cb(_priceCache.get(key));
            return;
        }

        const localPrice = calculateLocalPrice(exam, lang, mat);
        const localStr = '€' + localPrice.toFixed(2).replace('.', ',');

        const onRegistrationPage = !!document.querySelector('#step-2');
        const cfgAll = PontifexOI.getCfg();

        if (!onRegistrationPage || (!cfgAll.ajaxUrl && !cfgAll.restBase)) {
            _priceCache.set(key, localStr);
            cb(localStr);
            return;
        }

        // Use the centralized apiPost utility function for AJAX or REST API
        PontifexOI.apiPost('price', {
            exam_type: exam,
            language: lang,
            material: mat
        }).done(function(resp) {
            const data = resp?.data || resp;
            const priceStr = data?.price || localStr;
            _priceCache.set(key, priceStr);
            cb(priceStr);
        }).fail(function() {
            // Fallback to local calculation on failure
            _priceCache.set(key, localStr);
            cb(localStr);
        });
    }

    /**
     * Fetches prices for multiple items in a batch.
     * @param {Array<Object>} items - Array of items, each with exam_type, language, and material.
     * @param {function} cb - Callback function to handle the map of results.
     */
    function fetchPricesBatch(items, cb) {
        const cfgAll = PontifexOI.getCfg();
        const url = cfgAll.ajaxUrl || '';
        const onRegistrationPage = !!document.querySelector('#step-2');
        
        const toRequest = [];
        const result = {};

        items.forEach(it => {
            const key = cacheKey(it.exam_type, it.language, it.material);
            if (_priceCache.has(key)) {
                result[key] = _priceCache.get(key);
            } else if (!onRegistrationPage || !url) {
                const local = calculateLocalPrice(it.exam_type, it.language, it.material);
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

        // Use PontifexOI.apiPost for a potential 'batch' endpoint if it were available
        // For now, we will use a direct AJAX call as implemented in the second file
        // as the apiPost function does not seem to handle a batch endpoint.
        // The more complete version used a direct Jquery.ajax call anyway.
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
                    toRequest.forEach(it => {
                        const k = cacheKey(it.exam_type, it.language, it.material);
                        if (!result[k]) {
                            const local = calculateLocalPrice(it.exam_type, it.language, it.material);
                            const localStr = '€' + local.toFixed(2).replace('.', ',');
                            _priceCache.set(k, localStr);
                            result[k] = localStr;
                        }
                    });
                    cb(result);
                } else {
                    toRequest.forEach(it => {
                        const k = cacheKey(it.exam_type, it.language, it.material);
                        const local = calculateLocalPrice(it.exam_type, it.language, it.material);
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
                    const local = calculateLocalPrice(it.exam_type, it.language, it.material);
                    const localStr = '€' + local.toFixed(2).replace('.', ',');
                    _priceCache.set(k, localStr);
                    result[k] = localStr;
                });
                cb(result);
            }
        });
    }

    PontifexOI.calculatePrice = calculateLocalPrice;
    PontifexOI.fetchPrice = fetchPrice;
    PontifexOI.fetchPricesBatch = fetchPricesBatch;

})(window);