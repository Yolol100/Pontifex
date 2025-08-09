// pagination.js
(function(window, $) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  /**
   * Render paginatie HTML in de opgegeven container.
   * @param {number} curPage - De huidige pagina (1-based)
   * @param {number} totPages - Totaal aantal pagina's
   * @param {string|HTMLElement|jQuery} container - Selector of element waar de nav in moet komen (default: '.pontifex-oi-pagination-wrapper nav')
   */
  function renderPagination(curPage, totPages, container = '.pontifex-oi-pagination-wrapper nav') {
    if (totPages <= 1) {
      if (typeof $ !== "undefined" && typeof container === "string") {
        $(container).empty().hide();
      } else if (container instanceof HTMLElement) {
        container.innerHTML = '';
        container.style.display = 'none';
      } else if (typeof container === "object" && typeof container.empty === "function") {
        container.empty().hide();
      }
      return;
    }

    let html = '';

    if (curPage > 1) {
      html += '<a href="#" data-page="' + (curPage - 1) + '" aria-label="Vorige pagina">«</a>';
    } else {
      html += '<span class="pontifex-oi-page-disabled" aria-hidden="true">«</span>';
    }

    const maxLinks = 7;
    let start = Math.max(1, curPage - Math.floor(maxLinks / 2));
    let end = Math.min(totPages, start + maxLinks - 1);
    if (end - start < maxLinks - 1) start = Math.max(1, end - maxLinks + 1);

    for (let i = start; i <= end; i++) {
      if (i === curPage) {
        html += '<a href="#" data-page="' + i + '" class="pontifex-oi-page-active">' + i + '</a>';
      } else {
        html += '<a href="#" data-page="' + i + '">' + i + '</a>';
      }
    }

    if (curPage < totPages) {
      html += '<a href="#" data-page="' + (curPage + 1) + '" aria-label="Volgende pagina">»</a>';
    } else {
      html += '<span class="pontifex-oi-page-disabled" aria-hidden="true">»</span>';
    }

    if (typeof $ !== "undefined" && typeof container === "string") {
      $(container).html(html).show();
    } else if (container instanceof HTMLElement) {
      container.innerHTML = html;
      container.style.display = '';
    }
  }

  /**
   * Event handler voor klik op paginatie-links
   * @param {function} onPageChange - Callback met nieuwe pagina nummer
   * @param {string} container - Selector container waar event op wordt geregistreerd
   */
  function initPaginationEvents(onPageChange, container = '.pontifex-oi-pagination-wrapper nav') {
    if (typeof $ === "undefined") return;

    // FIX: voorkom dubbele handlers
    $(document).off('click', `${container} a[data-page]`);

    $(document).on('click', `${container} a[data-page]`, function(e) {
      e.preventDefault();
      const page = parseInt($(this).data('page'), 10);
      if (page && typeof onPageChange === "function") {
        onPageChange(page);
      }
    });
  }

  // Expose functies
  PontifexOI.renderPagination = renderPagination;
  PontifexOI.initPaginationEvents = initPaginationEvents;

})(window, window.jQuery);