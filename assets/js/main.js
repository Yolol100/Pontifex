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