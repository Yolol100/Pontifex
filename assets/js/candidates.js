(function(window, $) {
    'use strict';
    // Global configuration (assuming cfg is available or defined elsewhere)
    const cfg = window.PontifexOIConfig || {};
    // Only run if jQuery is available.
    if (!$) return;
    if (!window.PontifexOI) window.PontifexOI = {};

    // ⚡️ 2️⃣ DOM-query caching
    const DOM = {
        $doc: $(document),
        $candidateList: $('.pontifex-oi-candidates-list'),
        // Dynamic access to candidate rows
        $candidateRows: () => DOM.$candidateList.find('.pontifex-oi-candidate-row'),
        $payment: $('#payment_amount'),
        $count: $('#candidate-count'),
        $totalPrice: $('#total-price'),
        $extras: $('.extra-material-checkbox'),
        $addCandidateBtn: $('.pontifex-oi-add-candidate'),
        // Added from main/recalc logic
        $directOption: $('#extra_option_direct'),
    };

    // Maximum number of candidates that can be added.
    const maxCandidates = 12;

    // 🧠 3️⃣ Debounce herberekening
    const debounce = (fn, delay = 100) => {
        let t;
        return function(...args) {
            const context = this;
            clearTimeout(t);
            t = setTimeout(() => fn.apply(context, args), delay);
        };
    };

    function callGlobalRecalc() {
        if (window.PontifexOI && typeof window.PontifexOI.recalcTotal === 'function') {
            window.PontifexOI.recalcTotal();
        } else {
            // fallback event
            $(document).trigger('pontifex:recalc-total');
        }
    }
    const debouncedGlobalRecalc = debounce(callGlobalRecalc, 100);

    // Failsafe: als data-base-price ontbreekt, haal uit value en schrijf beide weg
    (function() {
        const $amt = DOM.$payment;
        if ($amt.length) {
            let baseRaw = $amt.data('base-price');
            if (!baseRaw) {
                baseRaw = $amt.val(); // puntnotatie uit PHP
                if (baseRaw) {
                    $amt.attr('data-base-price', baseRaw);
                    $amt.data('base-price', baseRaw);
                }
            }
        }
    })();

    // Helper die material uit de filters haalt (valt terug op '1')
    if (!window.PontifexOI.getSelectedMaterial) {
        window.PontifexOI.getSelectedMaterial = function() {
            return ($('select[name="material"]').val() || '1');
        };
    }

    /**
     * Updates the number of candidates in the UI and recalculates the total price.
     */
    function updateCandidateCountAndPrice() {
        const count = DOM.$candidateRows().length;
        DOM.$count.text(count);

        // ✅ Laat de prijsberekening volledig aan recalcTotal() over
        // Gebruik queueMicrotask om recalcTotal altijd na DOM-mutaties te laten draaien.
        if ('queueMicrotask' in window) {
            queueMicrotask(callGlobalRecalc);
        } else {
            callGlobalRecalc();
        }
    }

    /**
     * Updates the titles of each candidate row (e.g., 'Kandidaat 1').
     * It also shows/hides the 'remove' button for the first candidate and the 'add' button
     * when the maximum limit is reached.
     */
    function updateCandidateTitles() {
        const $rows = DOM.$candidateRows();

        // Remove existing titles in one batch.
        DOM.$candidateList.find('h3.kandidaat-titel').remove();

        $rows.each(function(index) {
            const $row = $(this);
            const $title = $('<h3>').addClass('kandidaat-titel').text('Kandidaat ' + (index + 1));

            $row.before($title);

            // Show/hide remove button
            if (index === 0) {
                $row.find('.pontifex-oi-remove-candidate').hide();
            } else {
                $row.find('.pontifex-oi-remove-candidate').show();
            }
        });

        // Probleem 2: Fix voor inconsistente class-namen
        DOM.$addCandidateBtn.toggle($rows.length < maxCandidates);
    }

    /**
     * Initializes all event handlers for adding and removing candidate rows.
     */
    function initCandidatesEvents() {
        // Event handler for the 'add candidate' button.
        DOM.$doc.on('click', '.pontifex-oi-add-candidate', function() {
            const count = DOM.$candidateRows().length;
            if (count >= maxCandidates) return;

            // Clone the first row.
            const $firstRow = DOM.$candidateRows().first();
            // Use clone(true) to also copy events if needed, but here we just need the structure.
            const $newRow = $firstRow.clone();

            // Probleem 3: Klonen met unieke name/id + reset checkboxes
            const newIndex = count + 1;

            $newRow.find('input, select, textarea').each(function() {
                const $el = $(this);

                // leegmaken + uncheck/reset
                if ($el.is(':checkbox,:radio')) {
                    $el.prop('checked', false).removeClass('invalid-field error');
                } else if ($el.is('select')) {
                    $el.val($el.find('option:first').val()).removeClass('invalid-field error'); // reset select to first option
                } else {
                    $el.val('').removeClass('invalid-field error');
                }

                // id/for/name bijwerken
                const updateAttribute = (attr) => {
                    const oldValue = $el.attr(attr);
                    if (oldValue) {
                        // Check if it ends with a digit (e.g., id-1)
                        if (/\d+$/.test(oldValue)) {
                            return oldValue.replace(/\d+$/, newIndex);
                        }
                        // If it ends with -digit (e.g., id-1)
                        if (/-(\d+)$/.test(oldValue)) {
                            return oldValue.replace(/-(\d+)$/, '-' + newIndex);
                        }
                        // If no digit, append with a hyphen (e.g., id-1)
                        return oldValue + '-' + newIndex;
                    }
                    return null;
                };

                // Update ID
                const newId = updateAttribute('id');
                if (newId) $el.attr('id', newId);

                // Update Name
                const oldName = $el.attr('name');
                if (oldName) {
                    // ✅ FIX: Zorg dat arrays correct blijven (candidate_fullname[] blijft candidate_fullname[])
                    // We updaten ALLEEN de id/for attributes, niet de name voor array fields
                    if (oldName.endsWith('[]')) {
                        // Laat array notation intact: candidate_fullname[] blijft candidate_fullname[]
                        // Geen wijziging nodig
                    } else if (/\[\d+\]$/.test(oldName)) {
                        // kandidaat[1] -> kandidaat[2]
                        $el.attr('name', oldName.replace(/\[\d+\]$/, '[' + newIndex + ']'));
                    } else if (/_(\d+)$/.test(oldName)) {
                        // kandidaat_1 -> kandidaat_2
                        $el.attr('name', oldName.replace(/_(\d+)$/, '_' + newIndex));
                    } else if (/\d+$/.test(oldName)) {
                        // kandidaat1 -> kandidaat2
                        $el.attr('name', oldName.replace(/\d+$/, newIndex));
                    }
                    // Oorspronkelijke fallback (die array-notatie toevoegde als er geen index was)
                    // Deze fallback is belangrijk om *niet* te doen als de veldnaam al een `[]` heeft.
                    // Als de veldnaam op rij 1 GEEN index heeft (bv 'email'), dan geven we 'email[2]' op rij 2.
                    else if (!oldName.endsWith('[]')) {
                        $el.attr('name', oldName + '[' + newIndex + ']');
                    }
                    // Anders: behoud de originele naam (inclusief [] voor arrays)
                }
            });

            // Update associated labels
            $newRow.find('label[for]').each(function() {
                const $lb = $(this);
                const oldFor = $lb.attr('for');
                if (oldFor) {
                    // Pas de 'for' attribuut op dezelfde manier aan als 'id'
                    let newFor = oldFor;
                    if (/\d+$/.test(oldFor)) {
                        newFor = oldFor.replace(/\d+$/, newIndex);
                    } else if (/-(\d+)$/.test(oldFor)) {
                        newFor = oldFor.replace(/-(\d+)$/, '-' + newIndex);
                    } else {
                        newFor = oldFor + '-' + newIndex;
                    }
                    $lb.attr('for', newFor);
                }
            });

            DOM.$candidateList.append($newRow);

            // Update the count, price, and titles after adding a row.
            updateCandidateCountAndPrice();
            updateCandidateTitles();
        });

        // Event handler for the 'remove candidate' button.
        DOM.$doc.on('click', '.pontifex-oi-remove-candidate', function() {
            const $rows = DOM.$candidateRows();
            if ($rows.length > 1) {
                $(this).closest('.pontifex-oi-candidate-row').remove();
            }
            // Update the count, price, and titles after removing a row.
            updateCandidateCountAndPrice();
            updateCandidateTitles();
        });
    }

    /**
     * Helper function to auto-select prechecked extras.
     */
    function autoSelectExtras() {
        // Auto-select prechecked options on page load
        DOM.$extras.filter('[data-auto-select="1"]').each(function() {
            const $cb = $(this);
            if (!$cb.is(':checked')) {
                $cb.prop('checked', true);
            }
            // Trigger change event to ensure price is updated immediately
            // Using requestAnimationFrame to ensure DOM is stable before triggering change
            window.requestAnimationFrame(() => $cb.trigger('change'));
        });
    }

    // Make the functions available in the global 'PontifexOI' namespace.
    Object.assign(window.PontifexOI, {
        updateCandidateCountAndPrice,
        initCandidatesEvents,
        updateCandidateTitles,
        // Expose helper to global scope for main.js/etc
        getSelectedMaterial: window.PontifexOI.getSelectedMaterial,
    });

    // --- Second Module Logic (main.js/table interaction) ---
    // 2) Wordt door main.js aangeroepen na render/verversen
    window.PontifexOI.updateFormInputsInTableRows = function() {
        const mat = (window.PontifexOI.getSelectedMaterial ? window.PontifexOI.getSelectedMaterial() : ($('select[name="material"]').val() || '1'));

        // 1) Hidden inputs in inline formulieren: material zetten, price laten staan
        $('.pontifex-oi-aanmeld-form').each(function() {
            const $form = $(this);

            // material
            let $mat = $form.find('input[name="material"]');
            if (!$mat.length) $mat = $('<input>', {
                type: 'hidden',
                name: 'material'
            }).appendTo($form);
            $mat.val(mat);
        });

        // 2) Links uniform updaten met material (prijs nooit meesturen)
        $('a.pontifex-register-link').each(function() {
            try {
                const url = new URL(this.href, window.location.origin);
                url.searchParams.set('material', mat);
                // verwijder 'price' uit alle links/URL's zodat server bron blijft
                url.searchParams.delete('price');
                this.href = url.toString();
            } catch (e) { /* noop */ }
        });
    };

    // 3) Failsafe: op klik alsnog material toevoegen als het ontbreekt
    DOM.$doc.on('click', 'a.pontifex-register-link', function() {
        try {
            const mat = window.PontifexOI.getSelectedMaterial();
            const url = new URL(this.href, window.location.origin);
            if (!url.searchParams.get('material')) {
                url.searchParams.set('material', mat);
                // verwijder 'price' uit alle links/URL's zodat server bron blijft
                url.searchParams.delete('price');
                this.href = url.toString();
            }
        } catch (e) { /* noop */ }
    });

    // --- Price Recalculation Handlers ---
    // Custom event handler for the candidates.js trigger
    DOM.$doc.on('pontifex:recalc-total', debouncedGlobalRecalc);

    // Handlers
    DOM.$doc.on('change', '.extra-material-checkbox', debouncedGlobalRecalc);

    // Initialisatie: Zorg dat door PHP (data-prechecked="1") ingestelde opties geactiveerd worden.
    let prechecked = DOM.$extras.filter('[data-prechecked="1"]');
    if (prechecked.length > 0) {
        prechecked.each(function() {
            // Vink aan. Price calculation is handled by autoSelectExtras() and initial recalc.
            $(this).prop('checked', true);
        });
    }

    // Init-optimalisatie: uitstellen tot idle-tijd
    const initAll = () => {
        initCandidatesEvents();
        updateCandidateTitles();
        autoSelectExtras();

        // ✅ CORRECTIE: ALTIJD callGlobalRecalc()
        // We laten de prijsberekening nu volledig aan recalcTotal() over,
        // ongeacht of er al een basisprijs is geleverd.
        callGlobalRecalc();
    };

    if ('requestIdleCallback' in window) {
        requestIdleCallback(initAll, {timeout: 500});
    } else {
        setTimeout(initAll, 250);
    }

    // ✅ Fallback: querystring uitlezen for Flow 2 (direct link)
    try {
        const p = new URLSearchParams(window.location.search);
        const opt = (p.get('extra_option') || '').trim();
        if (opt) {
            const $cb = DOM.$extras.filter('[value="'+opt+'"]');
            if ($cb.length) {
                let shouldRecalc = false;

                // Als niet aangevinkt (door PHP), dan client-side afhandelen (incl. data-attributen)
                if (!$cb.is(':checked')) {
                    $cb.prop('checked', true)
                        .attr('data-prechecked','1')
                        .attr('data-auto-select','1');
                    shouldRecalc = true;
                }

                if (shouldRecalc) {
                    // Use microtask for immediate recalc after the sync block
                    window.queueMicrotask(callGlobalRecalc);
                }
            }
        }
    } catch(e) {
        if (cfg.debug) {
            console.error("Pontifex fallback error:", e);
        }
    }

    // -----------------------------------------------------------------------
    // ✅ FIX: VERWIJDER DE DUPLICATE SUBMIT-HANDLER
    // -----------------------------------------------------------------------
    // Dit zorgt ervoor dat de prijsberekening in candidates.js niet in conflict komt
    // met de validatie- en verzendlogica van form-validation.js.
    DOM.$doc.off('submit', '.pontifex-oi-aanmeld-form');

})(window, window.jQuery);