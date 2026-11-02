/* Subaru Parts Inventory – admin behaviour. */
(function ($) {
	'use strict';

	var cfg = window.SPSAdmin || {};

	/* QR previews (label meta box). */
	function renderQrs() {
		if (typeof window.qrcode !== 'function') {
			return;
		}
		document.querySelectorAll('.sps-qr[data-qr]').forEach(function (el) {
			var qr = window.qrcode(0, 'M');
			qr.addData(el.getAttribute('data-qr'));
			qr.make();
			el.innerHTML = qr.createSvgTag({ cellSize: 1, margin: 0, scalable: true });
		});
	}

	/* Hide used-only fields for new parts. */
	function toggleNew() {
		var checked = document.querySelector('input[name="sps[condition]"]:checked');
		var group = checked && cfg.conditions[checked.value] ? cfg.conditions[checked.value].group : 'used';
		document.body.classList.toggle('sps-is-new', group === 'new');
	}

	/* Category → show which box the part goes into. */
	function updateBox() {
		var sel = document.getElementById('sps_category_id');
		var display = document.querySelector('.sps-box-display');
		var override = document.getElementById('sps_box_override');
		if (!sel || !display) {
			return;
		}
		var opt = sel.options[sel.selectedIndex];
		var code = override && override.value.trim() ? override.value.trim().toUpperCase() : (opt ? opt.getAttribute('data-box') : '');
		var loc = override && override.value.trim() ? '' : (opt ? opt.getAttribute('data-box-location') : '');
		display.querySelector('.sps-box-display__code').textContent = code || '—';
		display.querySelector('.sps-box-display__loc').textContent = loc || '';
	}

	/* Warn when the same part number already exists. */
	var dupTimer;
	function checkDuplicate() {
		var input = document.getElementById('sps_part_number');
		var box = document.getElementById('sps-duplicate-warning');
		if (!input || !box || !window.wp || !wp.apiFetch) {
			return;
		}
		clearTimeout(dupTimer);
		dupTimer = setTimeout(function () {
			var value = input.value.trim();
			if (value.replace(/[^a-z0-9]/gi, '').length < 4) {
				box.hidden = true;
				return;
			}
			wp.apiFetch({ path: cfg.restPath + 'check-number?number=' + encodeURIComponent(value) + '&exclude=' + (cfg.postId || 0) })
				.then(function (rows) {
					if (!rows || !rows.length) {
						box.hidden = true;
						return;
					}
					var html = '<p><strong>' + escapeHtml(cfg.i18n.duplicate) + '</strong></p><ul>';
					rows.forEach(function (r) {
						var c = cfg.conditions[r.condition];
						html += '<li><a href="' + escapeHtml(r.edit_url) + '">' + escapeHtml(r.sku) + '</a> – ' + escapeHtml(r.name) +
							(c ? ' (' + escapeHtml(c.short) + ')' : '') + ' × ' + r.quantity + '</li>';
					});
					box.innerHTML = html + '</ul>';
					box.hidden = false;
				})
				.catch(function () { box.hidden = true; });
		}, 400);
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/* Gallery. */
	function syncGallery() {
		var ids = $('.sps-gallery li').map(function () { return $(this).data('id'); }).get();
		$('#sps_gallery').val(ids.join(','));
	}

	var frame;
	$(document).on('click', '.sps-gallery-add', function (e) {
		e.preventDefault();
		if (!window.wp || !wp.media) {
			return;
		}
		if (!frame) {
			frame = wp.media({
				title: cfg.i18n.galleryTitle,
				button: { text: cfg.i18n.galleryButton },
				library: { type: 'image' },
				multiple: 'add'
			});
			frame.on('select', function () {
				var list = $('.sps-gallery');
				frame.state().get('selection').each(function (att) {
					var a = att.toJSON();
					if (list.find('li[data-id="' + a.id + '"]').length) {
						return;
					}
					var src = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
					list.append('<li data-id="' + a.id + '"><img src="' + escapeHtml(src) + '" alt="" /><button type="button" class="sps-gallery-remove" aria-label="' + escapeHtml(cfg.i18n.remove) + '">✕</button></li>');
				});
				syncGallery();
			});
		}
		frame.open();
	});
	$(document).on('click', '.sps-gallery-remove', function () {
		$(this).closest('li').remove();
		syncGallery();
	});

	/* Inspection log row removal. */
	$(document).on('click', '.sps-log-remove', function () {
		$(this).closest('tr').remove();
	});

	/* Camera scanning on the Scan screen (BarcodeDetector API: Chrome / Android). */
	var stream, scanning = false;
	function stopCamera() {
		scanning = false;
		if (stream) {
			stream.getTracks().forEach(function (t) { t.stop(); });
			stream = null;
		}
		$('.sps-camera').prop('hidden', true);
	}
	$(document).on('click', '.sps-camera-btn', function () {
		if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
			window.alert(cfg.i18n.noCamera);
			return;
		}
		var detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128'] });
		var video = document.querySelector('.sps-camera video');
		navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }).then(function (s) {
			stream = s;
			video.srcObject = s;
			video.play();
			$('.sps-camera').prop('hidden', false);
			scanning = true;
			(function tick() {
				if (!scanning) {
					return;
				}
				detector.detect(video).then(function (codes) {
					if (codes && codes.length) {
						var value = codes[0].rawValue;
						stopCamera();
						var form = document.querySelector('.sps-scan-form');
						form.querySelector('input[name=code]').value = value;
						form.submit();
						return;
					}
					requestAnimationFrame(tick);
				}).catch(function () { requestAnimationFrame(tick); });
			})();
		}).catch(function () {
			window.alert(cfg.i18n.noCamera);
		});
	});
	$(document).on('click', '.sps-camera-stop', stopCamera);

	$(function () {
		renderQrs();
		if (document.querySelector('.sps-condition-picker')) {
			toggleNew();
			$(document).on('change', 'input[name="sps[condition]"]', toggleNew);
		}
		$(document).on('change', '#sps_category_id', updateBox);
		$(document).on('input', '#sps_box_override', updateBox);
		$(document).on('input', '#sps_part_number', checkDuplicate);
		if ($.fn.sortable && $('.sps-gallery').length) {
			$('.sps-gallery').sortable({ update: syncGallery });
		}
		var pn = document.getElementById('sps_part_number');
		if (pn && pn.value) {
			checkDuplicate();
		}
	});
})(jQuery);
