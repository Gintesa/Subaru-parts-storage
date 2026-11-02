/* Subaru Parts Inventory – label print view. */
(function () {
	'use strict';

	function renderQr(el) {
		if (typeof window.qrcode !== 'function') {
			return;
		}
		var qr = window.qrcode(0, 'M');
		qr.addData(el.getAttribute('data-qr'));
		qr.make();
		el.innerHTML = qr.createSvgTag({ cellSize: 1, margin: 0, scalable: true });
	}

	document.querySelectorAll('.sps-qr[data-qr]').forEach(renderQr);

	var cfg = window.SPSLabels || {};
	var marked = false;

	function markPrinted() {
		if (marked || !cfg.ids || !cfg.ids.length) {
			return;
		}
		marked = true;
		var body = new URLSearchParams();
		body.append('action', 'sps_mark_printed');
		body.append('_ajax_nonce', cfg.nonce);
		cfg.ids.forEach(function (id) { body.append('ids[]', id); });
		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				var status = document.getElementById('sps-mark-status');
				if (status && res && res.success) {
					status.textContent = cfg.done;
				}
			})
			.catch(function () { marked = false; });
	}

	var printBtn = document.getElementById('sps-print');
	if (printBtn) {
		printBtn.addEventListener('click', function () {
			window.print();
		});
	}
	var markBtn = document.getElementById('sps-mark');
	if (markBtn) {
		markBtn.addEventListener('click', markPrinted);
	}
	// After the print dialog closes, remember these labels as printed.
	window.addEventListener('afterprint', markPrinted);
})();
