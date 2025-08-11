jQuery(function($) {
    'use strict';

    // =================== REQUIRED/VALIDATIE PATCH ===================
    // Dynamisch alle velden bundelen in één order-object en amount correct meezenden.

    $(document).on('submit', '.pontifex-oi-candidate-form', function(e) {
        e.preventDefault(); // altijd stoppen, AJAX doet de rest

        const $form = $(this);
        $form.find('.invalid-field').removeClass('invalid-field');
        let isValid = true;
        let $firstInvalid = null;

        // eenvoudige required-validatie
        $form.find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('invalid-field');
                isValid = false;
                if (!$firstInvalid) $firstInvalid = $(this);
            }
        });
        if (!isValid) {
            setTimeout(() => {
                if ($firstInvalid) {
                    $('html, body').animate({
                        scrollTop: $firstInvalid.offset().top - 80
                    }, 400, () => $firstInvalid.focus());
                }
            }, 60);
            return false;
        }

        // =================== AJAX-VERWERKING ===================
        // Bundel ALLE form-velden in één object onder order[...]
        const serialized = $form.serializeArray();
        const orderData = {};
        serialized.forEach(item => {
            // veldnamen met [] maken we om naar echte arrays
            if (item.name.endsWith('[]')) {
                const key = item.name.replace(/\[\]$/, '');
                orderData[key] = orderData[key] || [];
                orderData[key].push(item.value);
            } else {
                orderData[item.name] = item.value;
            }
        });

        // Zet alles in formData
        const formData = [];
        Object.entries(orderData).forEach(([key, val]) => {
            if (Array.isArray(val)) {
                val.forEach(v => formData.push({ name: `order[${key}][]`, value: v }));
            } else {
                formData.push({ name: `order[${key}]`,     value: val });
            }
        });

        // Haal bedrag uit hidden input #payment_amount
        let amountRaw = $('#payment_amount').val() || $('#payment_amount').data('base-price');
        amountRaw = amountRaw.toString().replace(',', '.');
        formData.push({ name: 'amount',   value: amountRaw });
        // **Belangrijk:** actie moet exact overeenkomen met PHP-hooks
        formData.push({ name: 'action',   value: 'pontifex_oi_process_payment' });
        formData.push({ name: 'nonce',    value: window.pontifexOiVars.nonce || '' });

        // Disable submit-knop
        $form.find('button[type=submit], input[type=submit]').prop('disabled', true);

        // Debug: show URL en payload in console
        console.log('Posting to:', window.pontifexOiVars.ajaxurl, formData);

        // AJAX-call
        $.post(window.pontifexOiVars.ajaxurl, formData,
            function(response) {
                if (response.success && response.data.payment_url) {
                    window.location.href = response.data.payment_url;
                } else {
                    alert(response.data && response.data.message
                          ? response.data.message
                          : 'Er ging iets mis bij het starten van de betaling.');
                    $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
                }
            }
        ).fail(function() {
            alert('Er ging iets mis met de verbinding. Probeer het opnieuw.');
            $form.find('button[type=submit], input[type=submit]').prop('disabled', false);
        });

        return false; // altijd stoppen, alles via AJAX
    });

    // Live verwijderen van rood zodra er getypt wordt
    $(document).on('input change', '.invalid-field', function() {
        $(this).removeClass('invalid-field');
    });
});