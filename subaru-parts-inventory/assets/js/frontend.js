/* Subaru Parts Inventory – live filtering for the public catalogue + single part gallery. */
(function () {
	'use strict';

	var cfg = window.SPSFront || {};
	var urlKeys = { search: 'sps_q', category: 'sps_cat', condition: 'sps_cond', orderby: 'sps_sort', page: 'sps_page' };

	function initCatalog(root) {
		var form = root.querySelector('[data-sps-filters]');
		var results = root.querySelector('[data-sps-results]');
		var pager = root.querySelector('[data-sps-pagination]');
		var summary = root.querySelector('[data-sps-summary]');
		var fixed = {};
		try { fixed = JSON.parse(root.getAttribute('data-fixed') || '{}'); } catch (e) { fixed = {}; }
		var controller = null;
		var timer = null;

		root.classList.add('is-enhanced');

		function state(page) {
			var s = { page: page || 1 };
			if (form) {
				new FormData(form).forEach(function (v, k) { s[k] = v; });
			}
			return s;
		}

		function load(page, push) {
			var s = state(page);
			var params = new URLSearchParams();
			params.set('format', 'html');
			params.set('view', fixed.view || 'grid');
			params.set('per_page', fixed.per_page || 24);
			Object.keys(s).forEach(function (k) { if (s[k] !== '' && s[k] != null) { params.set(k, s[k]); } });

			if (controller) { controller.abort(); }
			controller = 'AbortController' in window ? new AbortController() : null;
			root.classList.add('is-loading');
			fetch(cfg.rest + (cfg.rest.indexOf('?') === -1 ? '?' : '&') + params.toString(), {
				credentials: 'same-origin',
				signal: controller ? controller.signal : undefined
			})
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
				.then(function (data) {
					results.innerHTML = data.html;
					if (pager) { pager.innerHTML = data.pagination || ''; }
					if (summary) { summary.textContent = data.summary || ''; }
					root.classList.remove('is-loading');
					syncUrl(s, push);
					if (push) { root.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
				})
				.catch(function (err) {
					if (err && err.name === 'AbortError') { return; }
					root.classList.remove('is-loading');
					results.innerHTML = '<p class="sps-empty">' + (cfg.i18n ? cfg.i18n.error : 'Error') + '</p>';
				});
		}

		function syncUrl(s, push) {
			if (!window.history || !history.replaceState) { return; }
			var url = new URL(window.location.href);
			Object.keys(urlKeys).forEach(function (k) {
				var v = s[k];
				if (v && !(k === 'page' && String(v) === '1') && !(k === 'orderby' && v === 'newest')) {
					url.searchParams.set(urlKeys[k], v);
				} else {
					url.searchParams.delete(urlKeys[k]);
				}
			});
			history[push ? 'pushState' : 'replaceState']({}, '', url.toString());
		}

		if (form) {
			form.addEventListener('submit', function (e) { e.preventDefault(); load(1); });
			form.addEventListener('change', function (e) {
				if (e.target.name !== 'search') { load(1); }
			});
			var search = form.querySelector('input[name=search]');
			if (search) {
				search.addEventListener('input', function () {
					clearTimeout(timer);
					timer = setTimeout(function () { load(1); }, 300);
				});
			}
		}
		if (pager) {
			pager.addEventListener('click', function (e) {
				var a = e.target.closest('a[data-page]');
				if (!a) { return; }
				e.preventDefault();
				load(parseInt(a.getAttribute('data-page'), 10) || 1, true);
			});
		}
	}

	function initGallery(root) {
		var main = root.querySelector('.sps-gallery-view__main img');
		root.addEventListener('click', function (e) {
			var btn = e.target.closest('button[data-full]');
			if (!btn || !main) { return; }
			main.src = btn.getAttribute('data-full');
			root.querySelectorAll('button[data-full]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
		});
	}

	function boot() {
		document.querySelectorAll('[data-sps-catalog]').forEach(initCatalog);
		document.querySelectorAll('[data-sps-gallery]').forEach(initGallery);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
