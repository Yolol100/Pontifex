// main.js
jQuery(function($) {
  'use strict';

  console.log('pontifex-oi main.js actief!');

  // 1) Sidebar (mobiel)
  if (window.PontifexOI) {
    if (typeof window.PontifexOI.addAndPopulateSidebar === 'function') {
      window.PontifexOI.addAndPopulateSidebar();
    } else if (typeof window.PontifexOI.pontifexAddSidebarHtml === 'function') {
      // backward compat
      window.PontifexOI.pontifexAddSidebarHtml();
    }
    if (typeof window.PontifexOI.registerSidebarEvents === 'function') {
      window.PontifexOI.registerSidebarEvents();
    }
  }

  // 2) Filters (init + events)
  if (window.PontifexOI) {
    if (typeof window.PontifexOI.loadFilterState === 'function') {
      window.PontifexOI.loadFilterState();
    }
    if (typeof window.PontifexOI.setDefaultFiltersIfNeeded === 'function') {
      window.PontifexOI.setDefaultFiltersIfNeeded();
    }
    if (typeof window.PontifexOI.updateFiltersState === 'function') {
      window.PontifexOI.updateFiltersState();
    }
    if (typeof window.PontifexOI.bindFilterHandlers === 'function') {
      window.PontifexOI.bindFilterHandlers();
    }
  }

  // 3) Extra materiaal checkboxes
  if (window.PontifexOI && typeof window.PontifexOI.updateExtraMaterialCheckboxes === 'function') {
    window.PontifexOI.updateExtraMaterialCheckboxes();
  }

  // 4) GEEN dynamische prijs-call hier (voorkomt dubbele triggers)

  // 5) Form hidden inputs in table rows syncen
  if (window.PontifexOI && typeof window.PontifexOI.updateFormInputsInTableRows === 'function') {
    window.PontifexOI.updateFormInputsInTableRows();
  }

  // 6) Initiale AJAX-load (eenmalig)
  if ($('.pontifex-oi-filters').length && window.PontifexOI && typeof window.PontifexOI.updatePontifexTable === 'function') {
    if (!window.PontifexOI.initialTableLoaded) {
      console.log('Initialiseer planning via AJAX (pagina 1)');
      window.PontifexOI.updatePontifexTable(1);
      window.PontifexOI.initialTableLoaded = true;
    } else {
      console.log('Initiale table load al gedaan — skip.');
    }
  }

  console.log('pontifex-oi: init complete.');
});

// Voeg onderaan bestand toe (of maak bestand aan als het nog niet bestaat)
(function () {
  function qs(name){ try{ return new URLSearchParams(location.search).get(name); }catch(e){ return null; } }

  // Preselecteer examensoort via ?exam_type=... of ?exam=...
  document.addEventListener('DOMContentLoaded', function(){
    var pre = qs('exam_type') || qs('exam');
    var sel = document.querySelector('#exam_type-select, select[name="exam_type"]');
    if (pre && sel) {
      sel.value = pre;
      sel.dispatchEvent(new Event('change'));
    }
  });

  // Linkmaker: <a class="js-exam-link" data-exam-link="los-examen-vca-basis">…</a>
  document.addEventListener('click', function(e){
    var t = e.target.closest('.js-exam-link,[data-exam-link]');
    if (!t) return;
    e.preventDefault();
    var ex = t.getAttribute('data-exam-link');
    if (!ex) return;
    var base = (window.PontifexOIConfigData && PontifexOIConfigData.planningPageUrl) || '/cursus-zoeken/';
    location.href = base + '?exam_type=' + encodeURIComponent(ex);
  });
})();