/* global wc */
(function () {
	function isPickupRateRadio(target) {
		return (
			target &&
			target.tagName === 'INPUT' &&
			target.type === 'radio' &&
			/^pickup_location:\\d+/.test(String(target.value || ''))
		);
	}

	function attachDelegatedListener() {
		if (window.__wc_lpc_blocks_bound) return; // avoid duplicate binding

		function handler(e) {
			var el = e.target;
			if (!isPickupRateRadio(el)) return;
			var parts = String(el.value).split(':');
			var idx = parts.length > 1 ? parseInt(parts[1], 10) : null;
			if (idx === null || isNaN(idx)) return;
			try {
				var api = (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.extensionCartUpdate)
					? window.wc.blocksCheckout
					: null;
				if (!api) {
					return;
				}
				api.extensionCartUpdate({ namespace: 'wc-lpc', data: { pickup_location_index: idx } });
			} catch (err) {
				// Silently fail
			}
		}

		// Use capture phase to avoid being stopped by internal handlers
		document.addEventListener('change', handler, true);
		document.addEventListener('input', handler, true);
		document.addEventListener('click', handler, true);
		window.__wc_lpc_blocks_bound = true;
	}

	function init() {
		attachDelegatedListener();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
