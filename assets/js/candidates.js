(function(window, $) {
    'use strict';

    // Voert de code alleen uit als jQuery ($) beschikbaar is.
    if (!$) return;

    // Maximale aantal kandidaten dat kan worden toegevoegd.
    const maxCandidates = 4;

    /**
     * Parsed een prijsstring door niet-numerieke tekens te verwijderen en deze
     * te converteren naar een float. Dit maakt het mogelijk om met de prijs
     * te rekenen, ongeacht de valutasymbolen en komma's.
     *
     * @param {string} str - De prijsstring die moet worden geparsed.
     * @returns {number} De geparste prijs als een float, of 0 als de string ongeldig is.
     */
    function parsePrice(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/[^\d,.-]/g, '').replace(',', '.')) || 0;
    }

    /**
     * Haalt specifieke URL-parameters op van de huidige vensterlocatie.
     * Dit is nuttig voor het initialiseren van de prijs op basis van de URL.
     *
     * @returns {object} Een object met de 'price' parameter uit de URL.
     */
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            price: params.get('price') || ''
        };
    }

    /**
     * Werkt het aantal kandidaten in de UI bij en herrekent de totale prijs.
     * De totale prijs is de basisprijs vermenigvuldigd met het aantal kandidaatrijen.
     */
    function updateCandidateCountAndPrice() {
        // Telt het aantal kandidaatrijen.
        const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
        const count = $rows.length;
        $('#candidate-count').text(count);

        // Bepaalt de basisprijs. Eerst uit data-attribuut, dan uit de URL, anders uit de input.
        let baseRaw = $('#payment_amount').data('base-price') || getUrlParams().price;
        if (!baseRaw) {
            baseRaw = $('#payment_amount').val();
            $('#payment_amount').data('base-price', baseRaw);
        }
        
        let basePrice = parsePrice(baseRaw);
        if (isNaN(basePrice)) basePrice = 0;
        const total = basePrice * count;

        // Werkt de totale prijs in de UI bij.
        if ($('#total-price').length) {
            $('#total-price').text('€' + total.toFixed(2).replace('.', ','));
        }
        // Werkt de verborgen input `payment_amount` bij.
        $('#payment_amount').val(total.toFixed(2).replace('.', ','));
        
        // Roept een externe functie aan als die bestaat, voor verdere prijsaanpassingen.
        if (typeof window.updateTotalPriceWithCheckboxes === 'function') {
            window.updateTotalPriceWithCheckboxes();
        }
    }

    /**
     * Werkt de titels van elke kandidaatrij bij (bijv. 'Kandidaat 1', 'Kandidaat 2').
     * Dit zorgt voor een duidelijke, geordende weergave voor de gebruiker.
     *
     * Inclusief een controle om de 'Kandidaat toevoegen'-knop te tonen/verbergen
     * wanneer het maximum aantal kandidaten is bereikt.
     */
    function updateCandidateTitles() {
        const $list = $('.pontifex-oi-candidates-list');
        // Verwijdert eerst alle bestaande titels om duplicatie te voorkomen.
        $list.find('h3.kandidaat-titel').remove();
        
        // Loop over elke kandidaatrij en voeg een nieuwe titel toe.
        $list.find('.pontifex-oi-candidate-row').each(function(index) {
            const $title = $('<h3>')
                .addClass('kandidaat-titel')
                .text('Kandidaat ' + (index + 1));
            $(this).before($title);
    
            // Verberg de verwijderknop voor de eerste kandidaat
            if (index === 0) {
                $(this).find('.pontifex-oi-remove-candidate').hide(); // Verberg de X voor de eerste kandidaat
            } else {
                $(this).find('.pontifex-oi-remove-candidate').show(); // Toon de X voor de rest
            }
        });
    
        // Toont of verbergt de knop om een nieuwe kandidaat toe te voegen,
        // gebaseerd op het totale aantal kandidaten.
        if ($list.find('.pontifex-oi-candidate-row').length >= maxCandidates) {
            $('.pontifex-oi-candidate-addrow').hide();
        } else {
            $('.pontifex-oi-candidate-addrow').show();
        }
    }

    /**
     * Initialiseert alle event handlers voor het toevoegen en verwijderen van
     * kandidaatrijen. Dit is het hart van de interactie op de pagina.
     */
    function initCandidatesEvents() {
        // Event handler voor de knop 'Kandidaat toevoegen'.
        $(document).on('click', '.pontifex-oi-add-candidate', function() {
            const $list = $('.pontifex-oi-candidates-list');
            const count = $list.find('.pontifex-oi-candidate-row').length;
            
            // Voorkomt dat er meer kandidaten worden toegevoegd dan het maximum.
            if (count >= maxCandidates) return;
            
            // Kloont de eerste rij, leegt de velden en past de ID's en 'for' attributen aan.
            const $firstRow = $list.find('.pontifex-oi-candidate-row').first();
            const $newRow = $firstRow.clone();
            $newRow.find('input').val('');
            
            $newRow.find('label').each(function() {
                const oldFor = $(this).attr('for');
                if (oldFor) $(this).attr('for', oldFor.replace(/\d+$/, count + 1));
            });
            
            $newRow.find('input').each(function() {
                const oldId = $(this).attr('id');
                if (oldId) $(this).attr('id', oldId.replace(/\d+$/, count + 1));
            });
            
            $list.append($newRow);
            
            // Werk de telling, prijs en titels bij na het toevoegen van een rij.
            updateCandidateCountAndPrice();
            updateCandidateTitles();
        });

        // Event handler voor de knop 'Kandidaat verwijderen'.
        $(document).on('click', '.pontifex-oi-remove-candidate', function() {
            const $rows = $('.pontifex-oi-candidates-list .pontifex-oi-candidate-row');
            // Verwijder alleen de rij als er meer dan één kandidaat is.
            if ($rows.length > 1) {
                $(this).closest('.pontifex-oi-candidate-row').remove();
            }
            
            // Werk de telling, prijs en titels bij na het verwijderen van een rij.
            updateCandidateCountAndPrice();
            updateCandidateTitles();
        });
    }

    // Maakt de functies beschikbaar in de globale namespace 'PontifexOI'.
    window.PontifexOI = window.PontifexOI || {};
    window.PontifexOI.updateCandidateCountAndPrice = updateCandidateCountAndPrice;
    window.PontifexOI.initCandidatesEvents = initCandidatesEvents;
    window.PontifexOI.updateCandidateTitles = updateCandidateTitles;

    // Initialiseer de events en titels automatisch wanneer de pagina volledig is geladen.
    $(function() {
        initCandidatesEvents();
        updateCandidateTitles();
    });

})(window, window.jQuery);