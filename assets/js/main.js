(function($){
  'use strict';
  // Check alleen frontend: Stopt de uitvoering als we in de WordPress admin zijn.
  if ($('body.wp-admin').length) return;
  $(function(){
    // Init scripts: Vervangt alle expliciete init-calls met een declaratief event
    $('[data-trigger="init"]').each(function(){
      const $el = $(this);
      $el.trigger('change');
    });
    // Sidebar direct open op mobiel
    (function() {
      // Gebruik een vlag om te zorgen dat dit maar één keer gebeurt
      if (sessionStorage.getItem('pontifex-oi-sidebar-opened-once')) {
        return;
      }
      const isMobile = window.matchMedia('(max-width: 767px)').matches;
      if (!isMobile) return;
  
      // Als de sidebar HTML al bestaat, open hem:
      const $overlay = $('.pontifex-oi-filters-sidebar-overlay');
      const $aside = $('.pontifex-oi-filters-sidebar');
      if ($overlay.length && $aside.length) {
        // FIX: Add the .open class to trigger the CSS transition
        $overlay.addClass('open');
        $aside.addClass('open');
    
        $overlay.removeAttr('hidden');
        $aside.removeAttr('hidden');
        $aside.focus();
        sessionStorage.setItem('pontifex-oi-sidebar-opened-once', 'true');
      }
    })();
    // Accordeon-toggle (ook voor tablet) – met event delegation
    $(document).on('click', '.pontifex-planning-accordion .acc-toggle', function() {
      const $btn = $(this);
      const open = $btn.attr('aria-expanded') === 'true';
      const id = $btn.attr('aria-controls');
      const $panel = $('#' + id);
  
      $btn.attr('aria-expanded', !open);
      if ($panel.length) {
        $panel.attr('hidden', open);
      }
    });
    // Init preselecties
    const $exam = $('#exam_type-select, select[name="exam_type"]');
    const $lang = $('#language-select, select[name="language"]');
    if (!$exam.val()) $exam.val('los-examen-vca-basis');
    if (!$lang.val()) $lang.val('nl');
    // Eén enkele trigger is genoeg → luisteraars zorgen voor verdere acties
    $exam.trigger('change');
    $lang.trigger('change');
    // Nieuwe toevoeging: Zorg dat nav + dropdown altijd meteen zichtbaar zijn (AJAX delay-proof)
    setTimeout(() => {
      const navWrap = $('.pontifex-oi-pagination-wrapper');
      if (navWrap.length) {
        navWrap.css('display', 'flex');
        navWrap.find('nav').css('display', 'block');
        // Laat de dropdown-zichtbaarheid aan filters.js/pagination.js
      }
    }, 150);
  });
})(jQuery);
(function () {
  'use strict';
  function qs(name){
    try{
      return new URLSearchParams(location.search).get(name);
    } catch(e){
      return null;
    }
  }
  // Preselecteer examensoort via URL-parameter
  document.addEventListener('DOMContentLoaded', function(){
    var pre = qs('exam_type') || qs('exam');
    var sel = document.querySelector('#exam_type-select, select[name="exam_type"]');
    if (pre && sel) {
      sel.value = pre;
      sel.dispatchEvent(new Event('change'));
    }
  });
  // Linkmaker
  document.addEventListener('click', function(e){
    var t = e.target.closest('.js-exam-link,[data-exam-link]');
    if (!t) return;
    e.preventDefault();
    var ex = t.getAttribute('data-exam-link');
    if (!ex) return;
    var base = (window.PontifexOIConfigData && PontifexOIConfigData.planningPageUrl) || '/cursus-zoeken/';
    location.href = base + '?exam_type=' + encodeURIComponent(ex);
  });
  // Function to add a cache buster to a URL (alleen als config bestaat)
  if (window.PontifexOIConfig) {
    window.withCacheBuster = function(url) {
      const b = window.PontifexOIConfig.cache_buster || Date.now();
      const u = new URL(url, window.location.origin);
      u.searchParams.set('_cb', b);
      return u.toString();
    };
  }
})();