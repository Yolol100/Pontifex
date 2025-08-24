jQuery(function ($) {
    'use strict';

    // ===================== Notificaties & UI =====================

    /**
     * Fades out and removes the 'Settings saved' success notice.
     * This enhances the user experience by making the message non-persistent.
     */
    function setupSuccessNotice() {
        const $msg = $('#message.updated.notice-success');
        if ($msg.length) {
            setTimeout(() => {
                $msg.addClass('hide')
                    .one('transitionend webkitTransitionEnd oTransitionEnd MSTransitionEnd', function() {
                        $(this).remove();
                    });
            }, 3500);
        }
    }

    /**
     * Initializes the custom toggle switch for the test mode setting.
     * It handles both the initial state and changes to the checkbox.
     */
    function setupTestModeToggle() {
        const $testToggle = $('#pontifex_oi_mollie_test_mode');
        const $toggleSwitch = $testToggle.parent('.pontifex-toggle-switch');
        const $toggleLabel = $('.pontifex-toggle-label');

        if ($testToggle.length && $toggleSwitch.length) {
            // Set initial state
            $toggleSwitch.toggleClass('checked', $testToggle.prop('checked'));
            $toggleLabel.text($testToggle.prop('checked') ? 'ingeschakeld' : 'uitgeschakeld');

            // Handle state change
            $testToggle.on('change', function () {
                $toggleSwitch.toggleClass('checked', this.checked);
                $toggleLabel.text(this.checked ? 'ingeschakeld' : 'uitgeschakeld');
            });
        }
    }

    /**
     * Binds the focus event to input fields to automatically select their content.
     * This is useful for easy copying of API keys and other values.
     */
    function setupInputSelection() {
        $('.pontifex-admin-input').on('focus', function () {
            $(this).select();
        });
    }


    // ===================== Mollie Validatie =====================

    /**
     * Performs a simple client-side validation on Mollie API keys before form submission.
     * It checks if the keys start with the expected prefixes ('live_' or 'test_').
     */
    function setupMollieValidation() {
        $('form').on('submit', function (e) {
            const $live = $('#pontifex_oi_mollie_live_api_key');
            const $test = $('#pontifex_oi_mollie_test_api_key');

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
    }

    // ===================== SOAP AJAX Logic =====================

    /**
     * Manages the UI and AJAX call for fetching SOAP data.
     * Includes smooth feedback animations and button state management.
     */
    function setupSoapFetch() {
        const $fetchBtn = $('#pontifex-soap-fetch-btn');
        const $feedback = $('#pontifex-soap-fetch-feedback');
        let feedbackTimeout;

        if (!$fetchBtn.length) {
            return;
        }

        // Hide feedback on initial load
        $feedback.removeClass('active').html('').hide();

        /** Shows feedback with a smooth animation and sets a timeout for auto-hide. */
        function showFeedback(html) {
            clearTimeout(feedbackTimeout);
            $feedback.removeClass('active');
            // Timeout to force a reflow and trigger the transition for new content
            setTimeout(() => {
                $feedback.html(html).show(0).addClass('active');
            }, 20);
            feedbackTimeout = setTimeout(hideFeedback, 4000);
        }

        /** Hides feedback with a smooth animation. */
        function hideFeedback() {
            $feedback.removeClass('active');
            // Clean up after the CSS transition ends
            setTimeout(() => {
                $feedback.html('').hide(0);
            }, 350);
        }

        $fetchBtn.on('click', function (e) {
            e.preventDefault();

            // Disable button and update text
            $fetchBtn.prop('disabled', true).text('Bezig...');

            // Hide any existing feedback before the new request
            hideFeedback();

            // Collect data from input fields
            const soap_url = $('#pontifex_oi_soap_url').val();
            const user_id = $('#pontifex_oi_soap_user_id').val();
            const company_id = $('#pontifex_oi_soap_company_id').val();
            const hash = $('#pontifex_oi_soap_hash').val();

            // Perform the AJAX request
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'pontifex_oi_fetch_soap_data',
                    soap_url,
                    user_id,
                    company_id,
                    hash,
                    // Pass the nonce for security
                    nonce: (window.PontifexOIAdmin && PontifexOIAdmin.ajaxNonce) || ''
                },
                success: function (response) {
                    $fetchBtn.prop('disabled', false).text('Haal gegevens op');
                    const html = response && response.success
                        ? `<div class="pontifex-soap-feedback success"><span class="pontifex-feedback-icon">&#10003;</span> <span>Gegevens succesvol opgehaald en opgeslagen.</span></div>`
                        : `<div class="pontifex-soap-feedback error"><span class="pontifex-feedback-icon">&#10060;</span> <span>${(response.data && response.data.message) || 'Ophalen mislukt. Probeer opnieuw.'}</span></div>`;
                    showFeedback(html);
                },
                error: function () {
                    $fetchBtn.prop('disabled', false).text('Haal gegevens op');
                    const html = `<div class="pontifex-soap-feedback error"><span class="pontifex-feedback-icon">&#10060;</span> <span>Ophalen mislukt. Probeer opnieuw.</span></div>`;
                    showFeedback(html);
                }
            });
        });
    }

    // ===================== Initialisatie =====================

    /**
     * Initializes all script components on document ready.
     */
    function init() {
        setupSuccessNotice();
        setupTestModeToggle();
        setupInputSelection();
        setupMollieValidation();
        setupSoapFetch();
    }

    init();

});