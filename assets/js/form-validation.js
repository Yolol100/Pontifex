jQuery(function($) {
    'use strict';

    // =================== UNIFIED SUBMIT & VALIDATION ===================
    // Target beide mogelijke formulier-ID's/classes met één handler
    $(document).on('submit', '.pontifex-oi-candidate-form, #pontifex-oi-registration-form', function(e) {
        e.preventDefault(); // Altijd stoppen, AJAX doet de rest

        const $form = $(this);
        // Verwijder alle foutklassen van tevoren
        $form.find('.invalid-field, .error').removeClass('invalid-field error');

        let isValid = true;
        let $firstInvalid = null;
        let streetError = false; // Vlag om specifieke foutmelding te triggeren

        $form.find('[required]').each(function() {
            const $field = $(this);
            const value = $field.val().trim();

            // 1. Required check
            if (!value) {
                $field.addClass('invalid-field error');
                isValid = false;
                if (!$firstInvalid) $firstInvalid = $field;
                return true; // Ga naar de volgende loop-iteratie
            }

            // 2. Straatnaam mag geen cijfers bevatten (waarschuwing tegen huisnummer)
            if ($field.attr('id') === 'order_street' && /\d/.test(value)) {
                $field.addClass('invalid-field error');
                isValid = false;
                streetError = true;
                if (!$firstInvalid) $firstInvalid = $field;
            }
        });

        if (!isValid) {
            // Geef de specifieke of de generieke foutmelding
            alert(streetError ? 'Voer alleen een straatnaam in, zonder huisnummer.'
                              : 'Vul alle verplichte velden in.');
            if ($firstInvalid) {
                // Scroll naar en focus op het eerste foutieve veld
                setTimeout(() => {
                    $('html, body').animate({
                        scrollTop: $firstInvalid.offset().top - 80
                    }, 400, () => $firstInvalid.focus());
                }, 60);
            }
            return false;
        }

        // =================== AJAX-VERWERKING ===================
        // ✅ FIX: Handmatig kandidaten verzamelen (correcte array-structuur)
        const candidateData = {
            candidate_fullname: [],
            candidate_infix: [],
            candidate_lastname: [],
            candidate_birthdate: []
        };
        // Loop door alle kandidaatrijen en verzamel data
        $('.pontifex-oi-candidate-row').each(function() {
            const $row = $(this);
            const fullname = $row.find('input[name*="candidate_fullname"]').val().trim(); // Gebruik *= om indexen te vangen

            // Skip lege rijen (geen voornaam = geen kandidaat)
            if (!fullname) return;

            // Zoek velden op basis van de array-naam-suffix (bv. name="candidate_fullname[1]" of name="candidate_fullname[]")
            candidateData.candidate_fullname.push(fullname);
            candidateData.candidate_infix.push($row.find('input[name*="candidate_infix"]').val().trim());
            candidateData.candidate_lastname.push($row.find('input[name*="candidate_lastname"]').val().trim());
            candidateData.candidate_birthdate.push($row.find('input[name*="candidate_birthdate"]').val().trim());
        });
        
        const candidateCount = candidateData.candidate_fullname.length;

        // Validatie: minimaal 1 kandidaat
        if (candidateCount === 0) {
            alert('Vul minimaal 1 kandidaat in.');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
            return false;
        }

        // Verzamel ANDERE velden (niet-kandidaat velden)
        const orderData = {};
        // Target alle inputs/selects/textareas die NIET "candidate_" in hun naam hebben
        $form.find('input:not([name*="candidate_"]), select:not([name*="candidate_"]), textarea:not([name*="candidate_"])').each(function() {
            const $el = $(this);
            const name = $el.attr('name');

            if (!name || name === 'nonce') return; // Skip nonce (wordt apart toegevoegd)

            // Checkboxes (extra_options)
            if ($el.attr('type') === 'checkbox') {
                if ($el.is(':checked')) {
                    // Handhaaf array-notatie (bv. extra_options[] -> name: extra_options, value: [v1, v2])
                    if (name.endsWith('[]')) {
                        const key = name.replace(/\[\]$/, '');
                        if (!orderData[key]) orderData[key] = [];
                        orderData[key].push($el.val());
                    } else {
                        // Normale checkbox zonder array-notatie
                        orderData[name] = $el.val();
                    }
                }
            }
            // Radio buttons
            else if ($el.attr('type') === 'radio') {
                if ($el.is(':checked')) {
                    orderData[name] = $el.val();
                }
            }
            // Gewone velden
            else {
                orderData[name] = $el.val();
            }
        });

        // Voeg kandidaatdata toe aan orderData
        Object.assign(orderData, candidateData);
        orderData.candidate_count = candidateCount;

        // Haal bedrag op
        let amountRaw = $('#payment_amount').val() || $('#payment_amount').data('base-price') || '0';
        amountRaw = amountRaw.toString().replace(',', '.');

        // Bouw finale AJAX data
        const ajaxData = {
            order: orderData,
            amount: amountRaw,
            action: 'pontifex_oi_process_payment',
            nonce: window.pontifexOiVars.nonce || ''
        };

        // Debug logging
        console.log('Form submission data (for AJAX):', ajaxData);
        console.log('Candidate count:', candidateCount);
        console.log('Payment amount:', amountRaw);

        // Disable submit-knop
        $form.find('button[type=submit], input[type=submit]').prop('disabled', true);

        // AJAX-call
        $.post(window.pontifexOiVars.ajaxurl, ajaxData,
            function(response) {
                if (response.success && response.data.payment_url) {
                    window.location.href = response.data.payment_url;
                } else {
                    alert(response.data && response.data.message ?
                        response.data.message :
                        'Er ging iets mis bij het starten van de betaling.');
                    $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
                }
            }
        ).fail(function(xhr, status, error) {
            console.error('Form submission error:', { status, error, response: xhr.responseText });
            alert('Er ging iets mis met de verbinding. Probeer het opnieuw.');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
        });

        return false; // altijd stoppen, alles via AJAX
    });

    // =================== LIVE ERROR REMOVAL ===================
    $(document).on('input change', '.invalid-field, .error', function() {
        const $field = $(this);
        const value = $field.val().trim();
        const isStreetField = $field.attr('id') === 'order_street';

        // Verwijder de classes als:
        // 1. Het veld is niet leeg (standaard required check)
        // EN
        // 2. OF het is NIET het straatveld OF het straatveld bevat GEEN cijfers meer
        if (value !== '' && (!isStreetField || !/\d/.test(value))) {
            $field.removeClass('invalid-field error');
        }
    });
});