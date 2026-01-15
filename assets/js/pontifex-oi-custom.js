(function (window, document, $) {
  'use strict';
  if (typeof $ !== 'function') return;

  const recalc = () => {
    const fn = window.PontifexOI?.recalcTotal;
    if (typeof fn === 'function') fn();
  };

  $(function () {
    // Live recalculatie via de GLOBALE functie uit price.js
    $(document).on('change.pontifexOI', '.extra-material-checkbox', recalc);

    // Recalc ook bij touch-events (mobiel fix) — timing behouden
    $(document).on('touchend.pontifexOI', '.extra-material-checkbox', function () {
      window.setTimeout(recalc, 50);
    });

    // MutationObserver: detecteer veranderingen in het aantal kandidaten
    (function observeCandidateCount() {
      const el = document.getElementById('candidate-count');
      if (!el || !('MutationObserver' in window)) return;

      const obs = new MutationObserver(() => recalc());
      obs.observe(el, { childList: true, characterData: true, subtree: true });
    })();

    // Initialisatie: vink automatisch vooraf ingestelde opties aan
    const $prechecked = $('.extra-material-checkbox[data-prechecked="1"]');
    if ($prechecked.length) {
      $prechecked.each(function () {
        $(this).prop('checked', true).trigger('change');
      });
    }

    // Fallback: querystring uitlezen (directe link ?extra_option=...)
    try {
      const p = new URLSearchParams(window.location.search);
      const opt = (p.get('extra_option') || '').trim();
      if (opt) {
        const $cb = $('.extra-material-checkbox[value="' + opt + '"]');
        if ($cb.length) {
          let shouldRecalc = false;

          if (!$cb.is(':checked')) {
            $cb.prop('checked', true)
              .attr('data-prechecked', '1')
              .attr('data-auto-select', '1');
            shouldRecalc = true;
          }

          if (shouldRecalc) recalc();
        }
      }
    } catch (e) {
      console.error('Pontifex fallback error:', e);
    }

    // Altijd een initiële berekening uitvoeren bij laden
    recalc();
  });
})(window, document, window.jQuery);