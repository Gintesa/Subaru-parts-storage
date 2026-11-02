/* Subaru Parts Inventory – front-end app (catalogue, scanner, add/edit, boxes, labels, activity). No build step. */
(function () {
	'use strict';

	var root = document.querySelector('[data-sps-app]');
	if (!root) { return; }
	var cfgEl = root.querySelector('[data-sps-config]');
	var C = {};
	try { C = JSON.parse(cfgEl.textContent); } catch (e) { return; }
	var T = C.i18n || {};
	var M = !!C.manager;

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */
	function $(sel, ctx) { return (ctx || root).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function debounce(fn, ms) { var t; return function () { var a = arguments, s = this; clearTimeout(t); t = setTimeout(function () { fn.apply(s, a); }, ms); }; }
	function money(v) {
		if (v === '' || v == null) { return ''; }
		var n = Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
		return (C.currency === '$' || C.currency === '£') ? C.currency + n : n + ' ' + C.currency;
	}
	function icon(name) {
		var p = {
			img: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="M21 16l-5-5-8 8"/>',
			box: '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>'
		}[name] || '';
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
	}

	var toastEl = $('[data-toast]'), toastT;
	function toast(msg, err) {
		toastEl.textContent = msg;
		toastEl.classList.toggle('err', !!err);
		toastEl.hidden = false;
		clearTimeout(toastT);
		toastT = setTimeout(function () { toastEl.hidden = true; }, 2600);
	}

	function url(path, params) {
		var u = new URL(C.rest + path, window.location.href);
		Object.keys(params || {}).forEach(function (k) {
			if (params[k] !== '' && params[k] != null) { u.searchParams.set(k, params[k]); }
		});
		return u.toString();
	}

	function refreshNonce() {
		return fetch(C.ajax + '?action=rest-nonce', { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.text() : ''; })
			.then(function (n) { if (n && n !== '0') { C.nonce = n.trim(); } return C.nonce; });
	}

	function api(path, opts, retried) {
		opts = opts || {};
		var headers = {};
		if (C.nonce) { headers['X-WP-Nonce'] = C.nonce; }
		var body = opts.body;
		if (body && !(body instanceof FormData)) {
			headers['Content-Type'] = 'application/json';
			body = JSON.stringify(body);
		}
		return fetch(url(path, opts.params), { method: opts.method || 'GET', credentials: 'same-origin', headers: headers, body: body })
			.then(function (r) {
				return r.json().catch(function () { return {}; }).then(function (data) {
					if (!r.ok) {
						if (!retried && data && data.code === 'rest_cookie_invalid_nonce') {
							return refreshNonce().then(function () { return api(path, opts, true); });
						}
						var err = new Error((data && data.message) || T.error);
						err.data = data;
						throw err;
					}
					return data;
				});
			});
	}

	function setStatus(state) {
		var dot = $('[data-status-dot]'), txt = $('[data-status]');
		dot.className = state === 'off' ? 'off' : 'on';
		txt.textContent = state === 'off' ? T.offline : (M ? T.live : T.readonly);
	}

	function condBadge(key) {
		var c = C.conditions[key];
		if (!c) { return ''; }
		return '<span class="cond cond-' + esc(key) + '" title="' + esc(c.desc) + '">' + esc(c.label) + '</span>';
	}
	function statusLabel(key) { return C.statuses[key] ? C.statuses[key].label : key; }

	/* ------------------------------------------------------------------ */
	/* Sections (accordion + nav)                                          */
	/* ------------------------------------------------------------------ */
	var ACC_KEY = 'sps-app-acc-' + (M ? 'm' : 'v');
	var accState = {};
	try { accState = JSON.parse(localStorage.getItem(ACC_KEY) || '{}'); } catch (e) { accState = {}; }
	var loaders = {};
	var loaded = {};

	function section(key) { return $('[data-acc="' + key + '"]'); }
	function setOpen(key, open, persist) {
		var s = section(key);
		if (!s) { return; }
		s.classList.toggle('collapsed', !open);
		s.querySelector('.acc-head').setAttribute('aria-expanded', open ? 'true' : 'false');
		if (persist !== false) {
			accState[key] = open;
			try { localStorage.setItem(ACC_KEY, JSON.stringify(accState)); } catch (e) { /* ignore */ }
		}
		if (open && loaders[key] && !loaded[key]) { loaded[key] = true; loaders[key](); }
	}
	function goTo(key) {
		setOpen(key, true);
		var s = section(key);
		if (!s) { return; }
		s.scrollIntoView({ behavior: 'smooth', block: 'start' });
		s.classList.remove('flash');
		void s.offsetWidth;
		s.classList.add('flash');
	}
	$$('.acc').forEach(function (s) {
		var key = s.getAttribute('data-acc');
		if (Object.prototype.hasOwnProperty.call(accState, key)) { setOpen(key, accState[key], false); }
		s.querySelector('.acc-head').addEventListener('click', function () { setOpen(key, s.classList.contains('collapsed')); });
	});
	$$('[data-nav]').forEach(function (b) {
		b.addEventListener('click', function () { goTo(b.getAttribute('data-nav')); });
	});
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					var key = en.target.getAttribute('data-acc');
					$$('[data-nav]').forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-nav') === key); });
				}
			});
		}, { rootMargin: '-30% 0px -60% 0px' });
		$$('.acc').forEach(function (s) { io.observe(s); });
	}
	function count(key, text) { var el = $('[data-count="' + key + '"]'); if (el) { el.textContent = text; } }

	/* ------------------------------------------------------------------ */
	/* Stats strip                                                         */
	/* ------------------------------------------------------------------ */
	var stats = null;
	function stat(k, v, s, nav, sCls) {
		return '<button type="button" class="stat" data-nav-to="' + esc(nav || '') + '"><div class="k">' + esc(k) + '</div><div class="v">' + v + '</div>' +
			(s ? '<div class="s ' + (sCls || '') + '">' + s + '</div>' : '') + '</button>';
	}
	function loadStats() {
		return api('stats').then(function (s) {
			stats = s;
			var html;
			if (s.manager) {
				html = stat(T.parts, esc(s.parts), esc(s.items) + ' ' + esc(T.pieces.toLowerCase()), 'inv') +
					stat(T.notBoxed, esc(s.not_boxed || 0), esc(s.unlabelled) + ' ' + esc(T.unlabelled.toLowerCase()), 'labels', (s.not_boxed || s.unlabelled) ? 'warn' : 'ok') +
					stat(T.newUsed, esc(s['new']) + '<small>/</small>' + esc(s.used), '', 'inv') +
					stat(T.value, esc(s.value_fmt || '—'), esc(T.cost) + ' ' + esc(s.cost_fmt || '—'), 'tools');
			} else {
				html = stat(T.parts, esc(s.parts), '', 'inv') + stat(T.newLbl, esc(s['new']), '', 'inv') + stat(T.usedLbl, esc(s.used), '', 'inv') +
					stat(T.boxes, String(boxes ? boxes.length : '—'), '', 'boxes');
			}
			$('[data-stats]').innerHTML = html;
			setStatus('on');
			renderToolFigs();
		}).catch(function () { setStatus('off'); });
	}
	$('[data-stats]').addEventListener('click', function (e) {
		var b = e.target.closest('[data-nav-to]');
		if (b && b.getAttribute('data-nav-to')) { goTo(b.getAttribute('data-nav-to')); }
	});

	/* ------------------------------------------------------------------ */
	/* Inventory                                                           */
	/* ------------------------------------------------------------------ */
	var inv = { search: '', category: C.start.category || '', condition: C.start.condition || '', status: 'instock', orderby: 'newest', page: 1, pages: 1, items: {} };
	var invList = $('[data-inv-list]');
	var moreBtn = $('[data-action="more"]');

	function catOptions(sel, firstLabel, valueBy) {
		var html = '<option value="">' + esc(firstLabel) + '</option>';
		C.categories.forEach(function (c) {
			var v = valueBy === 'slug' ? c.slug : c.id;
			html += '<option value="' + esc(v) + '" data-box="' + esc(c.box) + '" data-loc="' + esc(c.location) + '"' + (String(sel) === String(v) ? ' selected' : '') + '>' +
				' '.repeat(c.depth * 2) + (c.depth ? '— ' : '') + esc(c.name) + (c.depth === 0 && c.box ? '  [' + esc(c.box) + ']' : '') + '</option>';
		});
		return html;
	}
	var catFilter = $('[data-filter="category"]');
	catFilter.innerHTML = catOptions(inv.category, T.allCats, 'slug');
	var statusFilter = $('[data-filter="status"]');
	if (statusFilter) {
		var sh = '<option value="instock">' + esc(T.inStock) + '</option><option value="all">' + esc(T.allStatuses) + '</option>';
		Object.keys(C.statuses).forEach(function (k) { sh += '<option value="' + esc(k) + '">' + esc(C.statuses[k].label) + '</option>'; });
		statusFilter.innerHTML = sh;
	}
	$$('[data-cond]').forEach(function (chip) {
		chip.classList.toggle('on', chip.getAttribute('data-cond') === inv.condition);
		chip.addEventListener('click', function () {
			inv.condition = chip.getAttribute('data-cond');
			$$('[data-cond]').forEach(function (c) { c.classList.toggle('on', c === chip); });
			loadInv(true);
		});
	});
	$$('[data-filter]').forEach(function (s) {
		s.addEventListener('change', function () { inv[s.getAttribute('data-filter')] = s.value; loadInv(true); });
	});
	var gsearch = $('[data-global-search]');
	gsearch.addEventListener('input', debounce(function () {
		inv.search = gsearch.value.trim();
		setOpen('inv', true);
		loadInv(true);
	}, 280));
	gsearch.addEventListener('keydown', function (e) {
		if (e.key === 'Enter') { e.preventDefault(); goTo('inv'); }
	});

	function invRow(d) {
		var img = d.images && d.images[0] ? '<img src="' + esc(d.images[0].thumb) + '" alt="" loading="lazy">' : icon('img');
		var box = d.box && d.box.code ? '<span class="tag neon">' + esc(d.box.code) + '</span>' : '<span class="tag">—</span>';
		var meta = [];
		if (d.category) { meta.push(d.category.name); }
		if (M && d.sku) { meta.push(d.sku); }
		if (M && d.status && d.status !== 'in_stock') { meta.push(statusLabel(d.status)); }
		if (M && d['public'] === 0) { meta.push(T.hidden); }
		if (!M && C.showPrices && d.sale_price !== '' && d.sale_price != null) { meta.push(money(d.sale_price)); }
		var qty = d.quantity != null ? '<span class="qty' + (d.quantity ? '' : ' zero') + '">×' + esc(d.quantity) + '</span>' : '<span></span>';
		return '<button type="button" class="inv-row" data-open="' + d.id + '" aria-expanded="false"><span class="thumb">' + img + '</span>' +
			'<span style="min-width:0"><span class="pn">' + esc(d.part_number || '—') + '</span><span class="pname">' + esc(d.name) + '</span><span class="pmeta">' + esc(meta.join(' · ')) + '</span></span>' +
			'<span class="c-cond">' + condBadge(d.condition) + '</span><span class="c-box">' + box + '</span>' + qty + '</button><div class="inv-detail" hidden></div>';
	}

	function loadInv(reset) {
		if (reset) { inv.page = 1; }
		var params = {
			search: inv.search, category: inv.category, condition: inv.condition, orderby: inv.orderby,
			page: inv.page, per_page: C.perPage
		};
		if (M) { params.context = 'manage'; params.status = inv.status; }
		if (reset) { invList.innerHTML = '<p class="empty-note"><span class="spinner"></span> ' + esc(T.loading) + '</p>'; }
		return api('parts', { params: params }).then(function (res) {
			if (reset) { invList.innerHTML = ''; inv.items = {}; }
			inv.pages = res.pages;
			if (!res.items.length && reset) {
				invList.innerHTML = '<p class="empty-note">' + esc(T.none) + '</p>';
			}
			var frag = '';
			res.items.forEach(function (d) {
				inv.items[d.id] = d;
				frag += '<div class="inv-item" data-item="' + d.id + '">' + invRow(d) + '</div>';
			});
			invList.insertAdjacentHTML('beforeend', frag);
			moreBtn.hidden = inv.page >= inv.pages;
			count('inv', res.total + ' ' + T.parts.toLowerCase());
			setStatus('on');
		}).catch(function (e) {
			invList.innerHTML = '<p class="empty-note">' + esc(e.message) + '</p>';
			setStatus('off');
		});
	}
	moreBtn.addEventListener('click', function () { inv.page++; loadInv(false); });

	invList.addEventListener('click', function (e) {
		var row = e.target.closest('[data-open]');
		if (!row || !invList.contains(row)) { return; }
		var item = row.parentNode;
		var det = item.querySelector('.inv-detail');
		var open = det.hidden;
		det.hidden = !open;
		item.classList.toggle('open', open);
		row.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (!open) { return; }
		var id = row.getAttribute('data-open');
		var cached = inv.items[id];
		if (!M) { det.innerHTML = partCard(cached); return; }
		det.innerHTML = '<p class="empty-note"><span class="spinner"></span></p>';
		api('parts/' + id).then(function (d) { inv.items[id] = d; det.innerHTML = partCard(d); })
			.catch(function (er) { det.innerHTML = '<p class="empty-note">' + esc(er.message) + '</p>'; });
	});

	function updateEverywhere(d) {
		inv.items[d.id] = d;
		$$('[data-item="' + d.id + '"]').forEach(function (item) {
			var wasOpen = item.classList.contains('open');
			item.innerHTML = invRow(d);
			if (wasOpen) {
				item.querySelector('.inv-row').setAttribute('aria-expanded', 'true');
				var det = item.querySelector('.inv-detail');
				det.hidden = false;
				det.innerHTML = partCard(d);
			}
		});
		$$('[data-card="' + d.id + '"]').forEach(function (card) { card.outerHTML = partCard(d); });
	}

	/* Part detail card (inventory + scanner). */
	function partCard(d) {
		if (!d) { return ''; }
		var img = d.images && d.images[0] ? '<img src="' + esc(d.images[0].thumb) + '" alt="">' : icon('img');
		var h = '<div class="card" data-card="' + d.id + '"><div class="card-head"><span class="thumb">' + img + '</span><div style="min-width:0">' +
			'<div class="pn">' + esc(d.part_number || '—') + '</div><div class="pname">' + esc(d.name) + '</div><div class="meta">' + condBadge(d.condition);
		if (d.sku) { h += '<span class="tag">' + esc(d.sku) + '</span>'; }
		if (d.box && d.box.code) { h += '<span class="tag neon">▣ ' + esc(d.box.code) + (d.box.bin ? ' · ' + esc(d.box.bin) : '') + '</span>'; }
		if (M) {
			h += '<span class="tag ' + (d.status === 'not_boxed' ? 'notboxed' : (d.status === 'in_stock' || d.status === 'listed' ? 'ok' : 'amber')) + '">' + esc(statusLabel(d.status)) + '</span>';
			h += d.label_printed ? '<span class="tag ok">✔ ' + esc(T.labelPrinted) + '</span>' : '<span class="tag amber">' + esc(T.labelMissing) + '</span>';
			if (d['public'] === 0) { h += '<span class="tag">' + esc(T.hidden) + '</span>'; }
		}
		h += '</div></div></div>';
		if (d.images && d.images.length > 1) {
			h += '<div class="gallery">' + d.images.map(function (im) { return '<a href="' + esc(im.full) + '" target="_blank" rel="noopener"><img src="' + esc(im.thumb) + '" alt="" loading="lazy"></a>'; }).join('') + '</div>';
		}
		var kv = [];
		if (d.category) { kv.push([T.category, esc(d.category.path)]); }
		if (d.fitment && d.fitment.length) { kv.push([T.fits, esc(d.fitment.join(', '))]); }
		if (d.alt_numbers) { kv.push([T.altNumbers, '<span class="mono">' + esc(d.alt_numbers) + '</span>']); }
		if (d.condition_flags && d.condition_flags.length) { kv.push([T.checklist, d.condition_flags.map(function (f) { return '<span class="tag">' + esc(f) + '</span>'; }).join(' ')]); }
		if (d.condition_notes) { kv.push([d.condition_label, esc(d.condition_notes)]); }
		if (d.mileage !== '' && d.mileage != null && d.condition_group !== 'new') { kv.push([T.mileage, esc(Number(d.mileage).toLocaleString()) + ' ' + esc(C.unit)]); }
		if (M && d.removed_from) { kv.push([T.removedFrom, esc(d.removed_from)]); }
		if (d.sale_price !== '' && d.sale_price != null) { kv.push([T.price, esc(money(d.sale_price))]); }
		if (d.box && d.box.location) { kv.push([T.box, esc(d.box.location)]); }
		if (kv.length) {
			h += '<dl class="kv">' + kv.map(function (r) { return '<dt>' + esc(r[0]) + '</dt><dd>' + r[1] + '</dd>'; }).join('') + '</dl>';
		}
		if (M) {
			var opts = Object.keys(C.statuses).map(function (k) { return '<option value="' + esc(k) + '"' + (k === d.status ? ' selected' : '') + '>' + esc(C.statuses[k].label) + '</option>'; }).join('');
			h += '<div class="quick" data-quick="' + d.id + '">' +
				'<span class="qty-ctl"><button type="button" data-q="dec" aria-label="-1">−</button><span>' + esc(d.quantity) + '</span><button type="button" data-q="inc" aria-label="+1">+</button></span>' +
				'<select data-q="status" aria-label="' + esc(T.statusNow) + '">' + opts + '</select>' +
				'<div class="note-row"><input type="text" data-q="note" placeholder="' + esc(T.notePh) + '"><button type="button" class="btn ghost mini" data-q="addnote">' + esc(T.addNote) + '</button></div></div>';
			h += '<div class="card-actions">' +
				(d.status === 'not_boxed' && d.box && d.box.code ? '<button type="button" class="btn mini" data-q="boxed" data-box-code="' + esc(d.box.code) + '">✔ ' + esc(T.markBoxed) + ' ' + esc(d.box.code) + '</button>' : '') +
				'<button type="button" class="btn ' + (d.status === 'not_boxed' ? 'ghost ' : '') + 'mini" data-q="edit">' + esc(T.edit) + '</button>' +
				'<a class="btn ghost mini" href="' + esc(C.printUrl + '&ids=' + d.id) + '" target="_blank" rel="noopener">' + esc(T.printLabel) + '</a>' +
				(d.edit_url ? '<a class="btn faint mini" href="' + esc(d.edit_url) + '">' + esc(T.editAdmin) + '</a>' : '') +
				(d['public'] && C.statuses[d.status] && C.statuses[d.status]['public'] && d.post_status === 'publish' ? '<a class="btn faint mini" href="' + esc(d.url) + '">' + esc(T.publicPage) + '</a>' : '') +
				'<button type="button" class="btn danger mini" data-q="trash">' + esc(T.trash) + '</button></div>';
			if (d.inspections && d.inspections.length) {
				h += '<ul class="mini-log">' + d.inspections.slice(0, 8).map(function (en) {
					return '<li><span class="date">' + esc(en.date) + '</span><span>' + (en.condition ? condBadge(en.condition) + ' ' : '') + esc(en.note) + (en.user ? ' <span class="pmeta" style="display:inline">· ' + esc(en.user) + '</span>' : '') + '</span></li>';
				}).join('') + '</ul>';
			}
		} else if (d.url) {
			h += '<div class="card-actions"><a class="btn ghost mini" href="' + esc(d.url) + '">' + esc(T.publicPage) + ' →</a></div>';
		}
		return h + '</div>';
	}

	/* Quick actions on any card. */
	root.addEventListener('click', function (e) {
		var b = e.target.closest('[data-q]');
		if (!b || b.tagName === 'SELECT' || b.tagName === 'INPUT') { return; }
		var card = b.closest('[data-card]');
		if (!card) { return; }
		var id = card.getAttribute('data-card');
		var q = b.getAttribute('data-q');
		if (q === 'edit') { editPart(id); return; }
		if (q === 'trash') {
			if (!window.confirm(T.confirmTrash)) { return; }
			api('parts/' + id, { method: 'DELETE' }).then(function () {
				$$('[data-item="' + id + '"]').forEach(function (n) { n.remove(); });
				$$('[data-card="' + id + '"]').forEach(function (n) { n.remove(); });
				toast(T.trashed);
				loadStats();
			}).catch(function (er) { toast(er.message, true); });
			return;
		}
		var body = {};
		if (q === 'boxed') { body.put_away = b.getAttribute('data-box-code'); }
		if (q === 'inc') { body.qty_delta = 1; }
		if (q === 'dec') { body.qty_delta = -1; }
		if (q === 'addnote') {
			var inp = card.querySelector('[data-q="note"]');
			if (!inp.value.trim()) { inp.focus(); return; }
			body.note = inp.value.trim();
		}
		quick(id, body, q === 'addnote' ? T.noteAdded : (q === 'boxed' ? T.paBoxed + ' ' + body.put_away : null));
	});
	root.addEventListener('change', function (e) {
		var s = e.target.closest('select[data-q="status"]');
		if (!s) { return; }
		var card = s.closest('[data-card]');
		quick(card.getAttribute('data-card'), { status: s.value, note: T.statusNow + ': ' + statusLabel(s.value) });
	});
	root.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && e.target.matches('[data-q="note"]')) {
			e.preventDefault();
			e.target.parentNode.querySelector('[data-q="addnote"]').click();
		}
	});
	function quick(id, body, msg) {
		return api('parts/' + id + '/quick', { method: 'POST', body: body }).then(function (d) {
			updateEverywhere(d);
			toast(msg || (body.status ? T.statusNow + ': ' + statusLabel(d.status) : T.qtyNow + ': ' + d.quantity));
			loadStats();
			if (loaded.labels) { loadLabels(); }
		}).catch(function (er) { toast(er.message, true); });
	}

	/* ------------------------------------------------------------------ */
	/* Boxes                                                               */
	/* ------------------------------------------------------------------ */
	var boxes = null;
	function loadBoxes() {
		var grid = $('[data-boxes]');
		return api('boxes').then(function (list) {
			boxes = list;
			count('boxes', list.length + ' ' + T.boxes.toLowerCase());
			grid.innerHTML = list.map(function (b) {
				return '<div class="box-card' + (b.count ? '' : ' empty') + '" data-box="' + b.id + '" role="button" tabindex="0">' +
					'<div class="bc">' + esc(b.code) + '<span>' + esc(b.count) + ' ' + esc(T.inBox) + '</span></div>' +
					'<div class="bn">' + esc(b.path) + '</div>' + (b.location ? '<div class="bl">⌖ ' + esc(b.location) + '</div>' : '') +
					(b.children.length ? '<div class="bk">' + esc(b.children.join(' · ')) + '</div>' : '') +
					(M ? '<div class="bx">' + (b.label_url ? '<a class="btn faint mini" href="' + esc(b.label_url) + '" target="_blank" rel="noopener">' + esc(T.boxLabel) + '</a>' : '') +
						(C.canBoxes ? '<button type="button" class="btn faint mini" data-box-edit="' + b.id + '">' + esc(T.editBox) + '</button>' : '') + '</div>' : '') +
					'</div>';
			}).join('');
			if (stats && !stats.manager) { loadStats(); }
		}).catch(function (e) { grid.innerHTML = '<p class="empty-note">' + esc(e.message) + '</p>'; });
	}
	loaders.boxes = loadBoxes;
	function boxById(id) { return (boxes || []).filter(function (b) { return String(b.id) === String(id); })[0]; }
	$('[data-boxes]').addEventListener('click', function (e) {
		var ed = e.target.closest('[data-box-edit]');
		if (ed) {
			e.stopPropagation();
			var b = boxById(ed.getAttribute('data-box-edit'));
			var card = ed.closest('.box-card');
			if (card.querySelector('.box-edit')) { return; }
			card.insertAdjacentHTML('beforeend', '<form class="box-edit"><input type="text" name="code" class="mono" value="' + esc(b.code) + '" aria-label="' + esc(T.boxCode) + '">' +
				'<input type="text" name="location" value="' + esc(b.location) + '" placeholder="' + esc(T.boxLocation) + '"><div class="bx"><button class="btn mini">' + esc(T.save) + '</button>' +
				'<button type="button" class="btn faint mini" data-box-cancel>' + esc(T.cancel) + '</button></div></form>');
			return;
		}
		if (e.target.closest('[data-box-cancel]')) { e.stopPropagation(); e.target.closest('.box-edit').remove(); return; }
		if (e.target.closest('.box-edit') || e.target.closest('a')) { return; }
		var c = e.target.closest('[data-box]');
		if (c) { showBox(boxById(c.getAttribute('data-box'))); }
	});
	$('[data-boxes]').addEventListener('keydown', function (e) {
		if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-box]')) { e.preventDefault(); showBox(boxById(e.target.getAttribute('data-box'))); }
	});
	$('[data-boxes]').addEventListener('submit', function (e) {
		e.preventDefault();
		var f = e.target;
		var id = f.closest('[data-box]').getAttribute('data-box');
		api('boxes/' + id, { method: 'POST', body: { code: f.code.value, location: f.location.value } }).then(function () {
			toast(T.saved);
			loadBoxes();
		}).catch(function (er) { toast(er.message, true); });
	});
	function showBox(b) {
		if (!b) { return; }
		inv.category = b.slug;
		catFilter.value = b.slug;
		loadInv(true);
		goTo('inv');
	}

	/* ------------------------------------------------------------------ */
	/* Labels queue                                                        */
	/* ------------------------------------------------------------------ */
	function loadLabels() {
		var list = $('[data-label-list]');
		if (!list) { return; }
		var sel = $('[data-label-template]');
		if (!sel.options.length) {
			sel.innerHTML = Object.keys(C.templates).map(function (k) { return '<option value="' + esc(k) + '"' + (k === C.template ? ' selected' : '') + '>' + esc(C.templates[k]) + '</option>'; }).join('');
		}
		return api('parts', { params: { context: 'manage', label: 'no', status: 'instock', per_page: 100, orderby: 'oldest' } }).then(function (res) {
			count('labels', String(res.total));
			if (!res.items.length) { list.innerHTML = '<p class="empty-note">' + esc(T.noLabels) + '</p>'; return; }
			list.innerHTML = res.items.map(function (d) {
				return '<label class="label-row"><input type="checkbox" value="' + d.id + '" checked><span class="pn">' + esc(d.part_number || '—') + '</span>' +
					'<span class="pname">' + esc(d.name) + '</span>' + condBadge(d.condition) + '<span class="tag">' + esc(d.sku) + '</span></label>';
			}).join('');
		}).catch(function (e) { list.innerHTML = '<p class="empty-note">' + esc(e.message) + '</p>'; });
	}
	if (M) {
		loaders.labels = loadLabels;
		$('[data-action="select-all"]').addEventListener('click', function () {
			var boxesEls = $$('[data-label-list] input');
			var all = boxesEls.every(function (i) { return i.checked; });
			boxesEls.forEach(function (i) { i.checked = !all; });
		});
		$('[data-action="print-selected"]').addEventListener('click', function () {
			var ids = $$('[data-label-list] input:checked').map(function (i) { return i.value; });
			if (!ids.length) { toast(T.selectSome, true); return; }
			window.open(C.printUrl + '&ids=' + ids.join(',') + '&template=' + encodeURIComponent($('[data-label-template]').value), '_blank');
			toast(T.printed);
		});
		// The print page marks labels as printed; refresh when coming back to this tab.
		window.addEventListener('focus', debounce(function () {
			if (loaded.labels) { loadLabels(); loadStats(); }
		}, 300));
	}

	/* ------------------------------------------------------------------ */
	/* Activity / tools                                                    */
	/* ------------------------------------------------------------------ */
	function loadActivity() {
		var el = $('[data-activity]');
		return api('activity').then(function (list) {
			if (!list.length) { el.innerHTML = '<p class="empty-note">' + esc(T.noActivity) + '</p>'; return; }
			el.innerHTML = list.map(function (a) {
				return '<div class="log-entry"><span class="date">' + esc(a.date) + '</span><div><button type="button" data-find="' + esc(a.sku) + '">' + esc(a.part_number || a.sku) + '</button> ' +
					'<span class="pmeta" style="display:inline">' + esc(a.name) + '</span><div class="text">' + (a.condition ? condBadge(a.condition) + ' ' : '') + esc(a.note) + '</div></div>' +
					'<span class="who">' + esc(a.user) + '</span></div>';
			}).join('');
		}).catch(function (e) { el.innerHTML = '<p class="empty-note">' + esc(e.message) + '</p>'; });
	}
	if (M) {
		loaders.log = loadActivity;
		$('[data-activity]').addEventListener('click', function (e) {
			var b = e.target.closest('[data-find]');
			if (!b) { return; }
			lookup(b.getAttribute('data-find'));
			goTo('scan');
		});
	}
	function renderToolFigs() {
		var el = $('[data-tool-figs]');
		if (!el || !stats || !stats.by_status) { return; }
		el.innerHTML = Object.keys(stats.by_status).map(function (k) {
			return '<div class="fig"><div class="k">' + esc(statusLabel(k)) + '</div><div class="v">' + esc(stats.by_status[k]) + '</div></div>';
		}).join('');
	}

	/* ------------------------------------------------------------------ */
	/* Scanner                                                             */
	/* ------------------------------------------------------------------ */
	var scan = { stream: null, running: false, detector: null, mode: 'lookup', canvas: null, last: 0 };
	var view = $('[data-scan-view]');
	var video = view ? view.querySelector('video') : null;
	var result = $('[data-scan-result]');

	function loadJsQR() {
		if (window.jsQR) { return Promise.resolve(); }
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = C.jsqr;
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	}

	function startCamera(mode) {
		scan.mode = mode || 'lookup';
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { toast(T.cameraNo, true); return; }
		goTo('scan');
		if (scan.mode === 'pn') { result.innerHTML = '<p class="hint">' + esc(T.scanPnHint) + '</p>'; }
		scan.lastCode = ''; scan.lastAt = 0;
		var prep = Promise.resolve();
		if ('BarcodeDetector' in window) {
			prep = window.BarcodeDetector.getSupportedFormats().then(function (formats) {
				var want = ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'data_matrix'].filter(function (f) { return formats.indexOf(f) !== -1; });
				scan.detector = want.length ? new window.BarcodeDetector({ formats: want }) : null;
				if (!scan.detector) { return loadJsQR(); }
			}).catch(function () { scan.detector = null; return loadJsQR(); });
		} else {
			prep = loadJsQR();
		}
		prep.then(function () {
			return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }, audio: false });
		}).then(function (stream) {
			scan.stream = stream;
			video.srcObject = stream;
			video.setAttribute('playsinline', '');
			return video.play();
		}).then(function () {
			view.hidden = false;
			view.classList.remove('hit');
			scan.running = true;
			requestAnimationFrame(tick);
		}).catch(function () {
			stopCamera();
			toast(T.cameraNo, true);
		});
	}

	function stopCamera() {
		scan.running = false;
		if (scan.stream) { scan.stream.getTracks().forEach(function (t) { t.stop(); }); scan.stream = null; }
		if (view) { view.hidden = true; }
	}

	function tick(ts) {
		if (!scan.running) { return; }
		if (ts - scan.last < 160 || video.readyState < 2) { requestAnimationFrame(tick); return; }
		scan.last = ts;
		if (scan.detector) {
			scan.detector.detect(video).then(function (codes) {
				if (codes && codes.length) { found(codes[0].rawValue); } else { requestAnimationFrame(tick); }
			}).catch(function () { requestAnimationFrame(tick); });
			return;
		}
		if (window.jsQR) {
			var w = video.videoWidth, h = video.videoHeight, scale = Math.min(1, 800 / Math.max(w, h));
			if (!scan.canvas) { scan.canvas = document.createElement('canvas'); }
			scan.canvas.width = Math.round(w * scale);
			scan.canvas.height = Math.round(h * scale);
			var ctx = scan.canvas.getContext('2d', { willReadFrequently: true });
			ctx.drawImage(video, 0, 0, scan.canvas.width, scan.canvas.height);
			var img = ctx.getImageData(0, 0, scan.canvas.width, scan.canvas.height);
			var code = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
			if (code && code.data) { found(code.data); return; }
		}
		requestAnimationFrame(tick);
	}

	function found(text) {
		if (navigator.vibrate) { navigator.vibrate(60); }
		if (scan.mode === 'putaway') {
			// Keep the camera running; ignore the same code for a few seconds.
			var now = Date.now();
			if (text === scan.lastCode && now - scan.lastAt < 4000) { requestAnimationFrame(tick); return; }
			scan.lastCode = text; scan.lastAt = now;
			view.classList.add('hit');
			setTimeout(function () { view.classList.remove('hit'); }, 600);
			putAwayScan(text);
			setTimeout(function () { if (scan.running) { requestAnimationFrame(tick); } }, 1200);
			return;
		}
		view.classList.add('hit');
		stopCamera();
		if (scan.mode === 'pn') {
			var code = extractCode(text);
			var pn = $('[name="part_number"]', form);
			pn.value = /^(BOX:)/i.test(code) ? '' : code.toUpperCase();
			result.innerHTML = '';
			goTo('add');
			pn.dispatchEvent(new Event('input', { bubbles: true }));
			pn.focus();
			return;
		}
		lookup(text);
	}

	function extractCode(text) {
		text = String(text || '').trim();
		var m = text.match(/[?&]sps_scan=([^&#]+)/);
		if (m) { try { return decodeURIComponent(m[1].replace(/\+/g, ' ')); } catch (e) { return m[1]; } }
		return text;
	}

	function lookup(raw) {
		var code = extractCode(raw);
		if (!code) { return; }
		var hit = '<div class="scan-hit">' + esc(T.scanned) + ': <b>' + esc(code) + '</b></div>';
		result.innerHTML = hit + '<p class="empty-note"><span class="spinner"></span></p>';
		if (/^BOX:/i.test(code)) {
			var bc = code.replace(/^BOX:/i, '').toUpperCase();
			var p = boxes ? Promise.resolve(boxes) : loadBoxes().then(function () { return boxes; });
			p.then(function (list) {
				var b = (list || []).filter(function (x) { return x.code.toUpperCase() === bc || x.slug === bc.toLowerCase(); })[0];
				if (!b) { result.innerHTML = hit + notFound(code); return; }
				return api('parts', { params: { context: 'manage', category: b.slug, per_page: 100, orderby: 'part_number' } }).then(function (res) {
					var items = res.items.filter(function (d) { return d.box && d.box.code === b.code; });
					result.innerHTML = hit + '<div class="card"><div class="pn">▣ ' + esc(b.code) + '</div><div class="pname">' + esc(b.path) + '</div>' +
						(b.location ? '<div class="pmeta">⌖ ' + esc(b.location) + '</div>' : '') +
						'<div class="scan-hit" style="margin-top:12px">' + esc(T.boxContents) + ' · ' + items.length + '</div><div class="inv-list">' +
						items.map(function (d) { inv.items[d.id] = d; return '<div class="inv-item" data-item="' + d.id + '">' + invRow(d) + '</div>'; }).join('') + '</div></div>';
				});
			}).catch(function (e) { result.innerHTML = hit + '<p class="empty-note">' + esc(e.message) + '</p>'; });
			return;
		}
		api('lookup', { params: { code: code } }).then(function (list) {
			if (!list.length) { result.innerHTML = hit + notFound(code); return; }
			list.forEach(function (d) { inv.items[d.id] = d; });
			result.innerHTML = hit + list.map(partCard).join('');
		}).catch(function (e) { result.innerHTML = hit + '<p class="empty-note">' + esc(e.message) + '</p>'; });
	}
	function notFound(code) {
		return '<div class="card"><p class="empty-note" style="padding-top:0">' + esc(T.notFound) + ' <b class="mono">' + esc(code) + '</b></p>' +
			(/^BOX:/i.test(code) ? '' : '<button type="button" class="btn mini" data-add-pn="' + esc(code) + '">' + esc(T.addAsNew) + '</button>') + '</div>';
	}
	// Results in the scanner reuse the inventory row behaviour.
	if (result) {
		result.addEventListener('click', function (e) {
			var add = e.target.closest('[data-add-pn]');
			if (add) { resetForm(); $('[name="part_number"]', form).value = add.getAttribute('data-add-pn').toUpperCase(); goTo('add'); checkDup(); identify(); return; }
			var row = e.target.closest('[data-open]');
			if (!row) { return; }
			var id = row.getAttribute('data-open');
			var item = row.parentNode, det = item.querySelector('.inv-detail');
			det.hidden = !det.hidden;
			item.classList.toggle('open', !det.hidden);
			if (!det.hidden) { api('parts/' + id).then(function (d) { inv.items[id] = d; det.innerHTML = partCard(d); }); }
		});
	}
	/* Put away: scan a box label, then every part dropped into it. */
	var pa = { box: null, log: [] };
	var paEl = $('[data-putaway]');
	function renderPutAway() {
		if (!paEl) { return; }
		paEl.hidden = false;
		var ok = pa.log.filter(function (l) { return l.ok; }).length;
		var head = pa.box
			? '<div class="pa-head"><span>' + esc(T.paStep2) + '</span><span class="pa-box">' + esc(pa.box.code) + '</span><span class="pa-n">' + ok + ' ' + esc(T.paDone) + '</span>' +
				'<button type="button" class="btn faint mini" data-pa="change">' + esc(T.paChangeBox) + '</button><button type="button" class="btn mini" data-pa="finish">' + esc(T.paFinish) + '</button></div>' +
				'<div class="pmeta">' + esc(pa.box.path) + (pa.box.location ? ' · ⌖ ' + esc(pa.box.location) : '') + '</div>'
			: '<div class="pa-head"><span>' + esc(T.paStep1) + '</span><button type="button" class="btn faint mini" data-pa="finish">' + esc(T.cancel) + '</button></div>';
		paEl.innerHTML = head + '<ul class="pa-log">' + pa.log.map(function (l, i) {
			return '<li class="' + (l.ok ? 'ok' : 'err') + '"><span class="pn">' + esc(l.pn) + '</span> <span>' + esc(l.text) + '</span>' +
				(l.force ? '<button type="button" class="btn danger mini" data-pa-force="' + i + '">' + esc(T.paForce) + '</button>' : '') + '</li>';
		}).join('') + '</ul>';
	}
	function startPutAway() {
		pa.box = null; pa.log = [];
		result.innerHTML = '';
		renderPutAway();
		startCamera('putaway');
	}
	function finishPutAway() {
		pa.box = null; pa.log = [];
		if (paEl) { paEl.hidden = true; paEl.innerHTML = ''; }
		stopCamera();
		scan.mode = 'lookup';
		loadInv(true); loadStats();
		if (loaded.labels) { loadLabels(); }
		if (loaded.boxes) { loadBoxes(); }
	}
	function findBox(code) {
		var bc = code.replace(/^BOX:/i, '').trim().toUpperCase();
		var p = boxes ? Promise.resolve(boxes) : loadBoxes().then(function () { return boxes; });
		return p.then(function (list) {
			return (list || []).filter(function (x) { return x.code.toUpperCase() === bc || x.slug === bc.toLowerCase(); })[0] || null;
		});
	}
	function putAwayOne(d, force) {
		return api('parts/' + d.id + '/quick', { method: 'POST', body: { put_away: pa.box.code, force: !!force } }).then(function (nd) {
			updateEverywhere(nd);
			pa.log.unshift({ ok: true, pn: nd.part_number || nd.sku, text: nd.name + ' → ' + pa.box.code });
			renderPutAway();
		}).catch(function (er) {
			var exp = er.data && er.data.data && er.data.data.expected;
			pa.log.unshift({ ok: false, pn: d.part_number || d.sku, text: T.paWrong + ' ' + er.message, force: exp ? d : null });
			renderPutAway();
			if (navigator.vibrate) { navigator.vibrate([80, 60, 80]); }
		});
	}
	function putAwayScan(raw) {
		var code = extractCode(raw);
		if (!code) { return; }
		var looksBox = /^BOX[:\-]/i.test(code);
		if (!pa.box || looksBox) {
			findBox(code).then(function (b) {
				if (b) { pa.box = b; pa.log = []; renderPutAway(); toast(T.paStep2 + ' ' + b.code); return; }
				if (!pa.box) { toast(looksBox ? T.paNoBox + ': ' + code : T.paNeedBox, true); return; }
				putAwayPart(code);
			});
			return;
		}
		putAwayPart(code);
	}
	function putAwayPart(code) {
		api('lookup', { params: { code: code } }).then(function (list) {
			if (!list.length) { pa.log.unshift({ ok: false, pn: code, text: T.notFound + ' ' + code }); renderPutAway(); return; }
			if (list.length === 1) { return putAwayOne(list[0]); }
			result.innerHTML = '<p class="hint">' + esc(T.paMany) + '</p>' + list.map(function (d) {
				return '<div class="card"><span class="pn">' + esc(d.part_number) + '</span> ' + esc(d.name) + ' ' + condBadge(d.condition) + ' <span class="tag">' + esc(d.sku) + '</span> ' +
					'<button type="button" class="btn mini" data-pa-pick="' + d.id + '">' + esc(T.putAway) + '</button></div>';
			}).join('');
			pa.choices = list;
		}).catch(function (e) { toast(e.message, true); });
	}
	if (paEl) {
		paEl.addEventListener('click', function (e) {
			var b = e.target.closest('[data-pa]');
			if (b && b.getAttribute('data-pa') === 'finish') { finishPutAway(); return; }
			if (b && b.getAttribute('data-pa') === 'change') { pa.box = null; renderPutAway(); if (!scan.running) { startCamera('putaway'); } return; }
			var f = e.target.closest('[data-pa-force]');
			if (f) {
				var entry = pa.log[Number(f.getAttribute('data-pa-force'))];
				pa.log.splice(Number(f.getAttribute('data-pa-force')), 1);
				putAwayOne(entry.force, true);
			}
		});
		result.addEventListener('click', function (e) {
			var pick = e.target.closest('[data-pa-pick]');
			if (!pick || !pa.choices) { return; }
			var d = pa.choices.filter(function (x) { return String(x.id) === pick.getAttribute('data-pa-pick'); })[0];
			result.innerHTML = '';
			if (d) { putAwayOne(d); }
		});
	}

	if (M) {
		$('[data-action="putaway-start"]').addEventListener('click', startPutAway);
		$('[data-action="scan-start"]').addEventListener('click', function () { startCamera('lookup'); });
		$('[data-action="scan-stop"]').addEventListener('click', stopCamera);
		$('[data-action="open-scan"]').addEventListener('click', function () { startCamera('lookup'); });
		$('[data-scan-form]').addEventListener('submit', function (e) {
			e.preventDefault();
			var inp = e.target.code;
			if (paEl && !paEl.hidden) { putAwayScan(inp.value); } else { lookup(inp.value); }
			inp.select();
		});
		document.addEventListener('visibilitychange', function () { if (document.hidden) { stopCamera(); } });
	}

	/* ------------------------------------------------------------------ */
	/* Add / edit form                                                     */
	/* ------------------------------------------------------------------ */
	var form = $('[data-part-form]');
	var pendingPhotos = [];
	var editing = null;

	function buildForm() {
		$('[data-cat-select]', form).innerHTML = catOptions('', T.choose, 'id');
		$('[data-status-select]', form).innerHTML = Object.keys(C.statuses).map(function (k) { return '<option value="' + esc(k) + '">' + esc(C.statuses[k].label) + '</option>'; }).join('');
		$('[data-cond-pick]', form).innerHTML = Object.keys(C.conditions).map(function (k) {
			var c = C.conditions[k];
			var col = { 'new': '#7DD87C', used: '#C5CBD5', bad: '#FFB84D', unusable: '#FF6673' }[k] || '#C5CBD5';
			return '<label style="--c:' + col + '" title="' + esc(c.desc) + '">' +
				'<input type="radio" name="condition" value="' + esc(k) + '">' + esc(c.label) + '</label>';
		}).join('');
		$('[data-flag-pick]', form).innerHTML = Object.keys(C.flags).map(function (k) {
			return '<label><input type="checkbox" name="condition_flags" value="' + esc(k) + '">' + esc(C.flags[k]) + '</label>';
		}).join('');
		$('[data-fitments]', form).innerHTML = (C.fitments || []).map(function (f) { return '<option value="' + esc(f) + '"></option>'; }).join('');
	}

	function syncPicks() {
		$$('.svc-pick label', form).forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); });
		var c = form.querySelector('[name="condition"]:checked');
		var grp = c && C.conditions[c.value] ? C.conditions[c.value].group : 'used';
		form.classList.toggle('is-new', grp === 'new');
		var cd = $('[data-cond-desc]', form);
		cd.innerHTML = c && C.conditions[c.value] ? '<b>' + esc(C.conditions[c.value].label) + '</b> – ' + esc(C.conditions[c.value].desc) : '';
	}
	function syncBox() {
		var sel = $('[data-cat-select]', form);
		var o = sel.options[sel.selectedIndex];
		var override = form.box_override.value.trim().toUpperCase();
		$('[data-box-preview]', form).textContent = override || (o && o.getAttribute('data-box')) || '—';
		$('[data-box-loc]', form).textContent = override ? '' : ((o && o.getAttribute('data-loc')) || '');
	}

	function setField(name, value) {
		var els = form.querySelectorAll('[name="' + name + '"]');
		if (!els.length) { return; }
		if (els[0].type === 'radio' || els[0].type === 'checkbox') {
			var vals = Array.isArray(value) ? value.map(String) : [String(value)];
			Array.prototype.forEach.call(els, function (el) {
				el.checked = els.length === 1 && el.type === 'checkbox' ? !!Number(value) || value === true : vals.indexOf(el.value) !== -1;
			});
			return;
		}
		els[0].value = value == null ? '' : value;
	}

	function resetForm(keep) {
		var keepCat = keep ? form.category_id.value : '';
		var keepCond = keep ? (form.querySelector('[name="condition"]:checked') || {}).value : '';
		form.reset();
		editing = null;
		pendingPhotos = [];
		form.part_id.value = '';
		form.classList.remove('editing');
		$('[data-form-title]', form).textContent = T.newPart;
		$('[data-submit]', form).textContent = T.savePart;
		$('[data-photos]', form).innerHTML = '';
		$('[data-dup]', form).hidden = true;
		form.fitment.value = C.fitment || '';
		form.removed_from.value = C.removedFrom || '';
		form.status.value = 'not_boxed';
		form.name.dataset.auto = '';
		form.dataset.catManual = '';
		var ih = $('[data-ident]', form); ih.hidden = true; ih.textContent = '';
		setField('condition', keepCond || 'used');
		if (keepCat) { form.category_id.value = keepCat; }
		syncPicks();
		syncBox();
	}

	function fillForm(d) {
		resetForm();
		editing = d;
		form.part_id.value = d.id;
		form.classList.add('editing');
		$('[data-form-title]', form).textContent = T.editing + ': ' + (d.part_number || d.name) + ' · ' + d.sku;
		$('[data-submit]', form).textContent = T.saveChanges;
		['part_number', 'alt_numbers', 'epc_ref', 'condition_notes', 'mileage', 'removed_from', 'quantity', 'status', 'box_override', 'bin',
			'purchase_price', 'purchase_date', 'source', 'value', 'sale_price', 'private_notes'].forEach(function (k) { setField(k, d[k]); });
		setField('name', d.name);
		setField('condition', d.condition);
		setField('public', d['public']);
		setField('fitment', (d.fitment || []).join(', '));
		form.description.value = d.description ? d.description.replace(/<\/p>\s*<p>/g, '\n\n').replace(/<br\s*\/?>/g, '\n').replace(/<[^>]+>/g, '').trim() : '';
		form.dataset.catManual = '1';
		var flagLabels = d.condition_flags || [];
		$$('[name="condition_flags"]', form).forEach(function (el) { el.checked = flagLabels.indexOf(C.flags[el.value]) !== -1; });
		form.category_id.value = d.category ? d.category.id : '';
		if (d.weight != null) { setField('weight', d.weight); }
		renderPhotos();
		syncPicks();
		syncBox();
		if (d.alt_numbers || d.box_override || d.bin || d.purchase_price || d.value || d.sale_price || d.private_notes) { form.querySelector('details').open = true; }
	}

	function editPart(id) {
		api('parts/' + id).then(function (d) {
			fillForm(d);
			goTo('add');
		}).catch(function (er) { toast(er.message, true); });
	}

	function renderPhotos() {
		var el = $('[data-photos]', form);
		var html = '';
		if (editing && editing.images) {
			html += editing.images.map(function (im) {
				return '<div class="photo"><img src="' + esc(im.thumb) + '" alt=""><button type="button" data-rm-photo="' + im.id + '" aria-label="' + esc(T.removePhoto) + '">✕</button></div>';
			}).join('');
		}
		html += pendingPhotos.map(function (p, i) {
			return '<div class="photo pending"><img src="' + esc(p.preview) + '" alt=""><button type="button" data-rm-pending="' + i + '" aria-label="' + esc(T.removePhoto) + '">✕</button></div>';
		}).join('');
		el.innerHTML = html;
	}

	function shrink(file) {
		// Downscale big phone photos before upload (max 2000 px, JPEG).
		return new Promise(function (resolve) {
			if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
			var img = new Image();
			var u = URL.createObjectURL(file);
			img.onload = function () {
				var max = 2000, w = img.naturalWidth, h = img.naturalHeight, s = Math.min(1, max / Math.max(w, h));
				if (s === 1 && file.size < 1.5e6) { URL.revokeObjectURL(u); resolve(file); return; }
				var c = document.createElement('canvas');
				c.width = Math.round(w * s); c.height = Math.round(h * s);
				c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
				c.toBlob(function (b) {
					URL.revokeObjectURL(u);
					resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
				}, 'image/jpeg', 0.86);
			};
			img.onerror = function () { URL.revokeObjectURL(u); resolve(file); };
			img.src = u;
		});
	}

	var identTimer, identSeq = 0;
	function identify() {
		clearTimeout(identTimer);
		identTimer = setTimeout(function () {
			var v = form.part_number.value.trim();
			var hint = $('[data-ident]', form);
			if (form.part_id.value || v.replace(/[^a-z0-9]/gi, '').length < 5) { hint.hidden = true; return; }
			var seq = ++identSeq;
			api('identify', { params: { number: v } }).then(function (r) {
				if (seq !== identSeq) { return; }
				if (!r.found) { hint.hidden = true; return; }
				var nameEl = form.name;
				if (r.name && (!nameEl.value.trim() || nameEl.dataset.auto === '1')) {
					nameEl.value = r.name;
					nameEl.dataset.auto = '1';
				}
				if (r.category_id && form.dataset.catManual !== '1') {
					form.category_id.value = r.category_id;
					syncBox();
				}
				var from = (T.identFrom && T.identFrom[r.source]) || '';
				hint.className = 'ident' + (r.source === 'rule' || r.source === 'similar' ? ' guess' : '');
				hint.innerHTML = '✓ ' + (r.name ? '<b>' + esc(r.name) + '</b> ' : '') + (r.box ? esc(T.goesIn) + ' <b class="boxcode">' + esc(r.box) + '</b> ' : '') + '<span>(' + esc(from) + ')</span>';
				hint.hidden = false;
			}).catch(function () { hint.hidden = true; });
		}, 300);
	}

	var dupTimer;
	function checkDup() {
		clearTimeout(dupTimer);
		dupTimer = setTimeout(function () {
			var v = form.part_number.value.trim();
			var box = $('[data-dup]', form);
			if (v.replace(/[^a-z0-9]/gi, '').length < 4) { box.hidden = true; return; }
			api('check-number', { params: { number: v, exclude: form.part_id.value || 0 } }).then(function (rows) {
				if (!rows.length) { box.hidden = true; return; }
				box.innerHTML = esc(T.dup) + ' ' + rows.map(function (r) {
					var c = C.conditions[r.condition];
					return '<a href="#" data-edit-dup="' + r.id + '">' + esc(r.sku) + '</a> ' + esc(c ? c.short : '') + ' ×' + esc(r.quantity);
				}).join(', ');
				box.hidden = false;
			}).catch(function () { box.hidden = true; });
		}, 350);
	}

	if (M && form) {
		buildForm();
		resetForm();
		form.addEventListener('change', function (e) {
			if (e.target.name === 'condition' || e.target.name === 'condition_flags') { syncPicks(); }
			if (e.target.name === 'category_id') { syncBox(); }
		});
		form.box_override.addEventListener('input', syncBox);
		form.part_number.addEventListener('input', checkDup);
		form.part_number.addEventListener('input', identify);
		form.name.addEventListener('input', function () { form.name.dataset.auto = ''; });
		form.category_id.addEventListener('change', function () { form.dataset.catManual = '1'; });
		form.addEventListener('click', function (e) {
			var d = e.target.closest('[data-edit-dup]');
			if (d) { e.preventDefault(); editPart(d.getAttribute('data-edit-dup')); return; }
			var rp = e.target.closest('[data-rm-pending]');
			if (rp) { pendingPhotos.splice(Number(rp.getAttribute('data-rm-pending')), 1); renderPhotos(); return; }
			var rm = e.target.closest('[data-rm-photo]');
			if (rm && editing) {
				api('parts/' + editing.id + '/photos/' + rm.getAttribute('data-rm-photo'), { method: 'DELETE' }).then(function (d) {
					editing = d; updateEverywhere(d); renderPhotos();
				}).catch(function (er) { toast(er.message, true); });
			}
		});
		$('[data-photo-input]', form).addEventListener('change', function (e) {
			Array.prototype.forEach.call(e.target.files, function (f) {
				pendingPhotos.push({ file: f, preview: URL.createObjectURL(f) });
			});
			e.target.value = '';
			renderPhotos();
		});
		$('[data-action="form-reset"]', form).addEventListener('click', function () { resetForm(); $('[data-form-done]', form).hidden = true; });
		$('[data-action="scan-into-pn"]', form).addEventListener('click', function () { startCamera('pn'); });
		$('[data-action="open-add"]').addEventListener('click', function () {
			if (editing) { resetForm(); }
			goTo('add');
			setTimeout(function () { form.part_number.focus(); }, 350);
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(form);
			var body = {};
			['name', 'part_number', 'alt_numbers', 'epc_ref', 'category_id', 'condition_notes', 'mileage', 'removed_from', 'quantity', 'status', 'fitment',
				'box_override', 'bin', 'purchase_price', 'purchase_date', 'source', 'value', 'sale_price', 'weight', 'description', 'private_notes', 'note'].forEach(function (k) {
				body[k] = fd.get(k) == null ? '' : String(fd.get(k)).trim();
			});
			body.condition = fd.get('condition') || 'used';
			if (body.condition === 'new' && !form.part_id.value) { body.removed_from = ''; body.mileage = ''; }
			body.condition_flags = fd.getAll('condition_flags');
			if (!body.condition_flags.length) { body.condition_flags = ''; }
			body['public'] = form['public'].checked ? 1 : 0;
			if (!body.note) { delete body.note; }
			if (!body.name && !body.part_number) { toast(T.needPnOrName, true); form.part_number.focus(); return; }

			var btn = $('[data-submit]', form);
			btn.disabled = true;
			btn.textContent = T.saving;
			var id = form.part_id.value;
			api(id ? 'parts/' + id : 'parts', { method: 'POST', body: body }).then(function (d) {
				if (!pendingPhotos.length) { return d; }
				btn.textContent = T.uploading;
				return Promise.all(pendingPhotos.map(function (p) { return shrink(p.file); })).then(function (files) {
					var up = new FormData();
					files.forEach(function (f) { up.append('photos[]', f, f.name); });
					return api('parts/' + d.id + '/photos', { method: 'POST', body: up }).then(function (d2) {
						if (d2.errors && d2.errors.length) { toast(d2.errors.join(' '), true); }
						d2.print_url = d.print_url; d2.created = d.created;
						return d2;
					});
				});
			}).then(function (d) {
				var wasNew = !id;
				var done = $('[data-form-done]', form);
				done.innerHTML = '<span>✔ ' + esc(T.saved) + ' <b>' + esc(d.sku) + '</b> · ' + esc(d.part_number || d.name) + ' → <b>' + esc(d.box && d.box.code ? d.box.code : '—') + '</b></span>' +
					'<a class="btn mini" href="' + esc(d.print_url || (C.printUrl + '&ids=' + d.id)) + '" target="_blank" rel="noopener">' + esc(T.printLabel) + '</a>' +
					'<button type="button" class="btn ghost mini" data-done-open="' + d.id + '">' + esc(T.edit) + '</button>';
				done.hidden = false;
				done.querySelector('[data-done-open]').addEventListener('click', function () { fillForm(d); done.hidden = true; });
				toast(T.saved + ' ' + d.sku);
				if (wasNew) {
					resetForm(true);
					form.part_number.focus();
				} else {
					fillForm(d);
				}
				updateEverywhere(d);
				loadInv(true);
				loadStats();
				if (loaded.labels) { loadLabels(); }
				if (loaded.log) { loadActivity(); }
			}).catch(function (er) {
				toast(er.message, true);
			}).then(function () {
				btn.disabled = false;
				btn.textContent = form.part_id.value ? T.saveChanges : T.savePart;
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* Boot                                                                */
	/* ------------------------------------------------------------------ */
	loadStats();
	loadInv(true);
	Object.keys(loaders).forEach(function (k) {
		var s = section(k);
		if (s && !s.classList.contains('collapsed')) { loaded[k] = true; loaders[k](); }
	});
	if (!M) { loaded.boxes = true; loadBoxes(); }

	// Deep links: #sps-add, #sps-scan, ?sps_q=…
	var qs = new URLSearchParams(window.location.search);
	if (qs.get('sps_q')) { gsearch.value = qs.get('sps_q'); inv.search = gsearch.value; loadInv(true); }
	if (M && /^#sps-(add|scan)$/.test(window.location.hash)) { goTo(window.location.hash.slice(5)); }
})();
