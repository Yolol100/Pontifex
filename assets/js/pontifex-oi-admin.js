jQuery(function ($) {
    'use strict';

    // ===================== Instellingen zijn opgeslagen notificatie automatisch smooth laten verdwijnen =====================
    setTimeout(function() {
        var $msg = $('#message.updated.notice-success');
        if ($msg.length) {
            $msg.addClass('hide')
                .one('transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', function() {
                    $(this).remove();
                });
        }
    }, 3500);

    // ===================== TOGGLE SWITCH (alleen Mollie pagina) =====================
    var $testToggle = $('#pontifex_oi_mollie_test_mode');
    var $toggleSwitch = $testToggle.parent('.pontifex-toggle-switch');
    var $toggleLabel = $('.pontifex-toggle-label');

    if ($testToggle.length && $toggleSwitch.length) {
        $toggleSwitch.toggleClass('checked', $testToggle.prop('checked'));
        $toggleLabel.text($testToggle.prop('checked') ? 'Testmodus ingeschakeld' : 'Testmodus uitgeschakeld');

        $testToggle.on('change', function () {
            $toggleSwitch.toggleClass('checked', this.checked);
            $toggleLabel.text(this.checked ? 'Testmodus ingeschakeld' : 'Testmodus uitgeschakeld');
        });
    }

    // ===================== COPY API KEY (voor alle inputs) =====================
    $('.pontifex-admin-input').on('focus', function () {
        $(this).select();
    });

    // ===================== SIMPLE VALIDATION (alleen voor Mollie) =====================
    $('form').on('submit', function (e) {
        var $live = $('#pontifex_oi_mollie_live_api_key');
        var $test = $('#pontifex_oi_mollie_test_api_key');
        if ($live.length && $live.val() && !$live.val().startsWith('live_')) {
            alert('Let op: De live key hoort meestal te beginnen met "live_".');
            $live.focus();
            e.preventDefault();
            return false;
        }
        if ($test.length && $test.val() && !$test.val().startsWith('test_')) {
            alert('Let op: De test key hoort meestal te beginnen met "test_".');
            $test.focus();
            e.preventDefault();
            return false;
        }
        return true;
    });

    // ===================== SOAP: Haal gegevens op knop (smooth animatie) =====================
    var $fetchBtn = $('#pontifex-soap-fetch-btn');
    var $feedback = $('#pontifex-soap-fetch-feedback');
    var feedbackTimeout;

    function showFeedback(html) {
        clearTimeout(feedbackTimeout);
        // Remove active to trigger fade-out if needed
        $feedback.removeClass('active');
        // Force browser to register the class removal before adding again (for consecutive calls)
        setTimeout(function() {
            $feedback.html(html).show(0).addClass('active');
        }, 20);
        // Auto-hide after 4 sec
        feedbackTimeout = setTimeout(hideFeedback, 4000);
    }

    function hideFeedback() {
        $feedback.removeClass('active');
        // After transition, clean up
        setTimeout(function() {
            $feedback.html('').hide(0);
        }, 350); // Timing must match CSS transition
    }

    if ($fetchBtn.length) {
        $feedback.removeClass('active').html('').hide();

        $fetchBtn.on('click', function (e) {
            e.preventDefault();
            $fetchBtn.prop('disabled', true).text('Bezig...');
            clearTimeout(feedbackTimeout);

            // Bij opnieuw klikken: smooth wegfaden als er feedback staat
            if ($feedback.hasClass('active')) {
                hideFeedback();
            } else {
                $feedback.html('').hide();
            }

            var soap_url    = $('#pontifex_oi_soap_url').val();
            var user_id     = $('#pontifex_oi_soap_user_id').val();
            var company_id  = $('#pontifex_oi_soap_company_id').val();
            var hash        = $('#pontifex_oi_soap_hash').val();

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'pontifex_oi_fetch_soap_data',
                    soap_url: soap_url,
                    user_id: user_id,
                    company_id: company_id,
                    hash: hash
                },
                success: function (response) {
                    $fetchBtn.prop('disabled', false).text('Haal gegevens op');
                    var html = '';
                    if (response && response.success) {
                        html =
                            '<div class="pontifex-soap-feedback success">' +
                            '<span class="pontifex-feedback-icon">&#10003;</span> ' +
                            '<span>Gegevens succesvol opgehaald en opgeslagen.</span>' +
                            '</div>';
                    } else {
                        html =
                            '<div class="pontifex-soap-feedback error">' +
                            '<span class="pontifex-feedback-icon">&#10060;</span> ' +
                            '<span>' + (response.data && response.data.message ? response.data.message : 'Ophalen mislukt. Probeer opnieuw.') + '</span>' +
                            '</div>';
                    }
                    showFeedback(html);
                },
                error: function () {
                    $fetchBtn.prop('disabled', false).text('Haal gegevens op');
                    var html =
                        '<div class="pontifex-soap-feedback error">' +
                        '<span class="pontifex-feedback-icon">&#10060;</span> ' +
                        '<span>Ophalen mislukt. Probeer opnieuw.</span>' +
                        '</div>';
                    showFeedback(html);
                }
            });
        });
    }
});