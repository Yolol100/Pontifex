(function(window, document, $) {
  'use strict';

  const PontifexOI = window.PontifexOI = window.PontifexOI || {};

  // --- Device-based pagination helpers ---
  const getViewportWidth = () =>
    window.innerWidth || document.documentElement?.clientWidth || 1025;

  PontifexOI.getDevice = function() {
    const w = getViewportWidth();
    if (w <= 767) return 'mobile';
    if (w <= 1024) return 'tablet';
    return 'desktop';
  };

  PontifexOI.getDeviceLimit = function() {
    const d = PontifexOI.getDevice();
    if (d === 'mobile') return 7;
    if (d === 'tablet') return 12;
    return 25; // desktop
  };

  // build simple ‹ 1 2 3 › HTML
  function buildPagination(curPage, totPages) {
    if (totPages <= 1) return '';

    const device = PontifexOI.getDevice();
    const windowSize = device === 'mobile' ? 4 : (device === 'desktop' ? 7 : 5);

    const start = Math.max(1, curPage - Math.floor((windowSize - 1) / 2));
    const end = Math.min(totPages, start + windowSize - 1);
    const realStart = Math.max(1, end - windowSize + 1);

    let html = `<button class="poi-page -prev" data-page="${Math.max(1, curPage - 1)}">‹</button>`;

    for (let p = realStart; p <= end; p++) {
      const active = p === curPage;
      const activeClass = active ? ' active pontifex-oi-page-active' : '';
      html += `<button class="poi-page${activeClass}" data-page="${p}" aria-current="${active ? 'page' : 'false'}">${p}</button>`;
    }

    html += `<button class="poi-page -next" data-page="${Math.min(totPages, curPage + 1)}">›</button>`;
    return html;
  }

  // public API
  PontifexOI.renderPagination = function(curPage, totPages, totalCount) {
    const nav = document.querySelector('.pontifex-oi-pagination-wrapper nav');
    if (!nav) return;

    nav.innerHTML = buildPagination(curPage, totPages);
    nav.style.display = (totPages > 1) ? 'inline-block' : 'none';

    const dropdownWrap = document.querySelector('.pontifex-oi-pagination-wrapper .-left');
    const limit = PontifexOI.getDeviceLimit();

    if (dropdownWrap) {
      if (typeof totalCount === 'number' && totalCount <= limit) {
        dropdownWrap.classList.add('is-hidden');
      } else {
        dropdownWrap.classList.remove('is-hidden');
      }
    }
  };

  // expose per-page selection
  PontifexOI.getSelectedPerPage = function(totalCount) {
    const limit = PontifexOI.getDeviceLimit();
    const sel = document.querySelector('.pontifex-oi-rows-select');
    let val = sel && sel.value ? parseInt(sel.value, 10) : limit;
    if (!val || isNaN(val)) val = limit;
    return Math.max(1, Math.min(val, limit));
  };

  // events
  PontifexOI.initPaginationEvents = function(onPageChange, onPerPageChange) {
    const nav = document.querySelector('.pontifex-oi-pagination-wrapper nav');
    const sel = document.querySelector('.pontifex-oi-rows-select');

    if (nav) {
      nav.addEventListener('click', function(e) {
        const t = e.target.closest('button[data-page]');
        if (!t) return;
        e.preventDefault();
        onPageChange(parseInt(t.getAttribute('data-page'), 10) || 1);
      });
    }

    if (sel) {
      sel.addEventListener('change', function() {
        onPerPageChange(parseInt(this.value, 10) || PontifexOI.getDeviceLimit());
      });
    }
  };

  // Listener voor rows-per-page (extra fallback/legacy)
  (function() {
    const sel = document.querySelector('.pontifex-oi-rows-select');
    if (!sel) return;
    sel.addEventListener('change', function() {
      if (typeof PontifexOI.onPerPageChange === 'function') {
        PontifexOI.onPerPageChange(parseInt(sel.value, 10));
      }
    });
  })();

})(window, document, window.jQuery);