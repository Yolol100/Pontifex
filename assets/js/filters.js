(function(window, $) {
	'use strict';
	const cfg = window.PontifexOIConfigData || window.PontifexOIConfig || {};
	if (!window.PontifexOI) window.PontifexOI = {};
	const PontifexOI = window.PontifexOI;
	const weekendAllowedByExam = cfg.weekendAllowedByExam || {};
	const examProducts = cfg.examProducts || cfg.EXAM_PRODUCTS || {};
	const ALL_LANGS = cfg.availableLanguages || [
		'nl', 'en', 'de', 'fr', 'ar', 'bg', 'lt', 'pl', 'pt', 'ro',
		'ru', 'tr', 'el', 'hu', 'it', 'hr', 'uk', 'sk', 'es', 'vi'
	];
	let cityToProvince = {};
	let __pfAutoSyncProvince = false;
	const debounce = (fn, wait = 200) => {
		let timeout;
		return function(...args) {
			const context = this;
			clearTimeout(timeout);
			timeout = setTimeout(() => fn.apply(context, args), wait);
		};
	};
	function getSavedPerPage() {
		try { return parseInt(sessionStorage.getItem('pontifex_per_page') || '', 10) || null; } catch(e) { return null; }
	}
	function savePerPage(val) {
		try { sessionStorage.setItem('pontifex_per_page', String(val)); } catch(e) {}
	}
	function resolvePerPageDropdown() {
		const sel = document.querySelector('.pontifex-oi-rows-select');
		const v = sel ? parseInt(sel.value, 10) : NaN;
		const saved = getSavedPerPage();
		if (Number.isFinite(v) && v > 0) return v;
		if (Number.isFinite(saved) && saved > 0) return saved;
		return (typeof PontifexOI.getDeviceLimit === 'function') ? PontifexOI.getDeviceLimit() : 25;
	}
	function isVisible(el) {
		if (!el) return false;
		const st = window.getComputedStyle(el);
		if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) === 0) return false;
		if (el.offsetParent !== null) return true;
		const r = el.getBoundingClientRect();
		return (st.position === 'fixed' || st.position === 'absolute') && r.width > 0 && r.height > 0;
	}
	PontifexOI.getSelectedFilter = function(name, fallback = '') {
		const order = [
			document.querySelector(`#sidebar-${name}`),
			document.querySelector(`#${name}-select`),
			document.querySelector(`select[name="${name}"]`)
		];
		const el = order.find(e => e && (typeof isVisible === 'function' ? isVisible(e) : true)) || order.find(Boolean);
		return (el && el.value != null) ? el.value : fallback;
	};
	function getDevice() {
		const w = window.innerWidth || document.documentElement.clientWidth || 1025;
		if (w >= 1025) return 'desktop';
		if (w >= 768) return 'tablet';
		return 'mobile';
	}
	function withCacheBuster(url) {
		const b = (window.PontifexOIConfig && window.PontifexOIConfig.cache_buster) || Date.now();
		const u = new URL(url, window.location.origin);
		u.searchParams.set('_cb', b);
		return u.toString();
	}
	function getLanguagesForExam(examType) {
		const normalizedExam = PontifexOI.normalizeExam(examType);
		if (normalizedExam === 'los-examen-vca-basis') return [...ALL_LANGS];
		const prices = (examProducts[normalizedExam] && examProducts[normalizedExam].prices) || {};
		const languagesFromPrices = Object.keys(prices);
		return languagesFromPrices.length ? languagesFromPrices : [...ALL_LANGS];
	}
	function ensureDesktopLanguageOptions() {
		const $lang = $('#language-select, select[name="language"]');
		if (!$lang.length) return;
		const labels = cfg.availableLanguageLabels || {};
		const allLanguages = cfg.availableLanguages || ALL_LANGS;
		const examVal = PontifexOI.normalizeExam(
			PontifexOI.getSelectedFilter('exam_type', cfg.defaultExamType || 'los-examen-vca-basis')
		);
		const allowedLangs = (typeof getLanguagesForExam === 'function') ?
			getLanguagesForExam(examVal) :
			allLanguages;
		let current = PontifexOI.getSelectedFilter('language', cfg.defaultLanguage || 'nl');
		let html = '<option value="">Kies taal</option>';
		(allowedLangs.length ? allowedLangs : allLanguages).forEach(code => {
			const label = labels[code] || code.toUpperCase();
			html += `<option value="${code}">${label}</option>`;
		});
		$lang.html(html);
		if (allowedLangs.length && !allowedLangs.includes(current)) {
			current = allowedLangs.includes('nl') ? 'nl' : (allowedLangs[0] || '');
		}
		$lang.val(current);
		$lang.attr('data-pfLangInit', '1');
		window.PontifexOI = window.PontifexOI || {};
		window.PontifexOI.__initChangeFired = true;
		$lang.trigger('change');
	}
	function toggleWeekendCheckbox(show) {
		const $options = $('.extra-material input[value="cursus-weekend"], #extra-material-checkboxes input[value="cursus-weekend"]')
			.closest('.extra-option, label');
		if (show) {
			$options.show();
		} else {
			$options.hide().find('input').prop('checked', false);
		}
	}
	function updateFiltersState() {
		const $langSelect = $('select[name="language"], #language-select');
		const examVal = PontifexOI.normalizeExam(PontifexOI.getSelectedFilter('exam_type', ''));
		const prevLang = PontifexOI.getSelectedFilter('language', '');
		$langSelect.prop('disabled', false).find('option').show();
		if (!examVal) {
			toggleWeekendCheckbox(false);
			return;
		}
		const allowedLangs = getLanguagesForExam(examVal);
		const shouldFilter = allowedLangs.length > 0 && allowedLangs.length !== ALL_LANGS.length;
		if (shouldFilter) {
			$langSelect.find('option').each(function() {
				const value = $(this).val();
				if (value) $(this).toggle(allowedLangs.includes(value));
			});
			if (prevLang && !allowedLangs.includes(prevLang)) {
				if (allowedLangs.includes('nl')) {
					$langSelect.val('nl');
				} else if (allowedLangs.length) {
					$langSelect.val(allowedLangs[0]);
				} else {
					$langSelect.val('');
				}
			}
		}
		const currentLang = PontifexOI.getSelectedFilter('language', '');
		const allowedForWeekend = weekendAllowedByExam[examVal] || [];
		toggleWeekendCheckbox(allowedForWeekend.includes(currentLang));
	}
	PontifexOI.__fetchInFlight = PontifexOI.__fetchInFlight || false;
	PontifexOI.__pendingPage = PontifexOI.__pendingPage || null;
	PontifexOI.__paginationBound = PontifexOI.__paginationBound || false;
	PontifexOI.updatePontifexTable = function(page = 1, perPageOverride = null) {
		if (PontifexOI.__fetchInFlight) {
			PontifexOI.__pendingPage = page;
			return;
		}
		PontifexOI.__fetchInFlight = true;
		const perPage = perPageOverride !== null ? perPageOverride : resolvePerPageDropdown();
		const safeVal = v => (v === undefined || v === null ? '' : v);
		const filters = {
			exam_type: safeVal(PontifexOI.normalizeExam(PontifexOI.getSelectedFilter('exam_type', cfg.defaultExamType || 'los-examen-vca-basis'))),
			language: safeVal(PontifexOI.getSelectedFilter('language', 'nl')),
			material: safeVal(PontifexOI.getSelectedFilter('material', '1')),
			month: safeVal(PontifexOI.getSelectedFilter('month', '')),
			province: safeVal(PontifexOI.getSelectedFilter('province', '')),
			location: safeVal(PontifexOI.getSelectedFilter('location', '')),
			timeslot: safeVal(PontifexOI.getSelectedFilter('timeslot', '')),
			page,
			per_page: perPage
		};
		$.ajax({
			url: withCacheBuster(PontifexOiAjax.ajax_url),
			type: 'POST',
			cache: false,
			data: { action: 'pontifex_oi_get_planning', filters }
		})
		.done(res => {
			const data = (res && res.data) ? res.data : res;
			if (!data || !Array.isArray(data.planning)) return;
			const { planning, current_page, total_pages, total_results } = data;
			const registrationUrl = cfg.registrationPageUrl || '/cursus-inschrijven/';
			if (PontifexOI.renderTableRows) PontifexOI.renderTableRows(planning, current_page, total_pages, registrationUrl);
			if (PontifexOI.renderCards) PontifexOI.renderCards(planning, registrationUrl);
			if (PontifexOI.updateAllDynamicPrices) PontifexOI.updateAllDynamicPrices();
			if (PontifexOI.updateFormInputsInTableRows) PontifexOI.updateFormInputsInTableRows();
			if (PontifexOI.renderPagination) PontifexOI.renderPagination(current_page, total_pages, total_results);
			const total = Number(total_results || 0);
			const $wrap = $('.pontifex-oi-pagination-wrapper');
			const $nav = $wrap.find('nav, .pontifex-oi-pagination-nav');
			$wrap.attr('data-total-results', total);
			$wrap.show().css('display', 'flex');
			const deviceLimit = (typeof PontifexOI.getDeviceLimit === 'function')
				? PontifexOI.getDeviceLimit() : 25;
			$wrap.find('.-left').toggleClass('is-hidden', total <= deviceLimit);
			$nav.toggle(total_pages > 1);
			$wrap.addClass('show-arrows');
			if (!PontifexOI.__paginationBound && PontifexOI.initPaginationEvents) {
				PontifexOI.initPaginationEvents(
					nextPage => PontifexOI.updatePontifexTable(nextPage),
					newPerPage => {
						savePerPage(newPerPage);
						PontifexOI.updatePontifexTable(1);
					}
				);
				PontifexOI.__paginationBound = true;
			}
		})
		.always(() => {
			PontifexOI.__fetchInFlight = false;
			if (PontifexOI.__pendingPage != null) {
				const next = PontifexOI.__pendingPage;
				PontifexOI.__pendingPage = null;
				PontifexOI.updatePontifexTable(next);
			}
		});
	};
	async function fetchFilterOptions(params = {}) {
		const form = new URLSearchParams();
		form.append('action', 'pontifex_oi_get_filter_options');
		form.append('filter', 'location');
		if (params.province) form.append('filters[province]', params.province);
		const res = await fetch(PontifexOiAjax.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: form.toString()
		});
		const json = await res.json();
		if (!json.success) throw new Error(json.data?.message || 'Error');
		return json.data;
	}
	function updateCityMap(locations) {
		cityToProvince = {};
		locations.forEach(opt => {
			const city = opt?.id || opt?.city || '';
			const province = opt?.province || '';
			if (city) cityToProvince[city] = province;
		});
	}
	function fillCitySelect(locations) {
		const $city = $('#location-select');
		$city.empty();
		$city.append($('<option>', { value: '', text: 'Kies locatie' }));
		locations.forEach(opt => {
			if (opt && opt.id) {
				$city.append($('<option>', { value: opt.id, text: opt.name || opt.id }));
			}
		});
		updateCityMap(locations);
		$(document).trigger('pontifex:locations-updated', [locations]);
	}
	PontifexOI.areAllFiltersDefault = function() {
		const els = document.querySelectorAll('.pontifex-oi-filter');
		return Array.from(els).every(el => {
			const val = (el.value || '').trim();
			return val === '' || val === '0';
		});
	};
	PontifexOI.getDeviceLimit = function() {
		const device = getDevice();
		switch (device) {
			case 'desktop': return 25;
			case 'tablet': return 12;
			default: return 7;
		}
	};
	function bindFilterHandlers() {
		const debouncedUpdateTable = debounce(() => {
			const allDefault = PontifexOI.areAllFiltersDefault();
			let perPageOverride = null;
			if (allDefault) {
				perPageOverride = PontifexOI.getDeviceLimit();
				savePerPage(perPageOverride);
				const $sel = $('.pontifex-oi-rows-select');
				$sel.val(perPageOverride);
			}
			PontifexOI.updatePontifexTable(1, perPageOverride);
		}, 200);
		$(document).on('change', '#province-select', async function() {
			const province = this.value;
			const $locationSelect = $('#location-select');
			try {
				const data = await fetchFilterOptions({ province });
				const hadCitySelected = !!PontifexOI.getSelectedFilter('location', '');
				fillCitySelect(data.options || []);
				const isWeekend = PontifexOI.getSelectedFilter('material', '') === 'cursus-weekend';
				if (isWeekend) {
					const locOpt = $locationSelect.find('option').filter((i, opt) =>
						(opt.textContent || '').toLowerCase().includes('den haag')
					).first();
					if (locOpt.length) {
						$locationSelect.val(locOpt.val()).trigger('change');
					}
				} else {
					const keep = hadCitySelected ? $locationSelect.val() : '';
					if (keep && !$locationSelect.find(`option[value="${keep}"]`).length) {
						$locationSelect.val('');
					}
				}
			} catch (e) {
				$locationSelect.empty().append($('<option>', { value: '', text: 'Kies locatie' }));
			} finally {
				__pfAutoSyncProvince = false;
			}
		});
		$(document).on('change', '#location-select', function() {
			const city = this.value;
			const provinceForCity = cityToProvince[city] || '';
			const $provinceSelect = $('#province-select');
			if (provinceForCity && $provinceSelect.val() !== provinceForCity) {
				__pfAutoSyncProvince = true;
				$provinceSelect.val(provinceForCity);
				$provinceSelect.trigger('change');
			}
		});
		$(document).on(
			'change',
			'.pontifex-oi-filter, select[name="exam_type"], select[name="language"], #month-select, #location-select, #timeslot-select',
			debouncedUpdateTable
		);
		$(document).on('submit', '.pontifex-oi-filters', function(e) {
			e.preventDefault();
			updateFiltersState();
			PontifexOI.updatePontifexTable(1);
		});
	}
	Object.assign(window.PontifexOI, {
		updatePontifexTable: PontifexOI.updatePontifexTable,
		updateFiltersState: updateFiltersState,
		bindFilterHandlers: bindFilterHandlers,
		getLanguagesForExam: getLanguagesForExam,
		resolvePerPageDropdown,
		areAllFiltersDefault: PontifexOI.areAllFiltersDefault,
		getDeviceLimit: PontifexOI.getDeviceLimit
	});
	$(document).ready(() => {
		ensureDesktopLanguageOptions();
		bindFilterHandlers();
		updateFiltersState();
		PontifexOI.updatePontifexTable(1);
	});
	$(document).on('change', '.pontifex-oi-rows-select', function() {
		const v = parseInt(this.value, 10);
		if (Number.isFinite(v) && v > 0) savePerPage(v);
		PontifexOI.updatePontifexTable(1);
	});
})(window, jQuery);