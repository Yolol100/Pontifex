// main.js
jQuery(function($) {
  'use strict';

  console.log('pontifex-oi main.js actief!');

  // 1) Sidebar (mobiel)
  if (window.PontifexOI) {
    if (typeof window.PontifexOI.pontifexAddSidebarHtml === 'function') {
      window.PontifexOI.pontifexAddSidebarHtml();
    }
    if (typeof window.PontifexOI.registerSidebarEvents === 'function') {
      window.PontifexOI.registerSidebarEvents();
    }
  }

  // 2) Filters (init + events) — filters.js regelt de handlers & state
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

  // 4) Dynamische prijzen
  if (window.PontifexOI && typeof window.PontifexOI.updateAllDynamicPrices === 'function') {
    window.PontifexOI.updateAllDynamicPrices();
  }

  // 5) Paginatie events NIET hier — dat gebeurt na elke render in filters.js via pagination.js

  // 6) Form hidden inputs in table rows syncen (optioneel)
  if (window.PontifexOI && typeof window.PontifexOI.updateFormInputsInTableRows === 'function') {
    window.PontifexOI.updateFormInputsInTableRows();
  }

  // 7) (Optioneel) Kandidaten
  // if (window.PontifexOI && typeof window.PontifexOI.initCandidates === 'function') {
  //   window.PontifexOI.initCandidates();
  // }

  // 8) ENIGE initiële AJAX-load — met guard-vlag tegen dubbele calls
  if ($('.pontifex-oi-filters').length && window.PontifexOI && typeof window.PontifexOI.updatePontifexTable === 'function') {
    if (!window.PontifexOI.initialTableLoaded) {
      console.log('Initialiseer planning via AJAX (pagina 1)');
      window.PontifexOI.updatePontifexTable(1);
      window.PontifexOI.initialTableLoaded = true; // FIX: vlag zetten
    } else {
      console.log('Initiale table load al gedaan — skip.');
    }
  }

  console.log('pontifex-oi: alle functies zijn geïnitialiseerd.');
});