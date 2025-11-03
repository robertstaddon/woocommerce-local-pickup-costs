/* global wc */
(function () {
	function log() {
		if (typeof console !== 'undefined' && console.log) {
			console.log.apply(console, ['[WC LPC]'].concat([].slice.call(arguments)));
		}
	}

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
			log('Pickup radio changed (capture)', { value: el.value, idx: idx });
			if (idx === null || isNaN(idx)) return;
			try {
				var api = (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.extensionCartUpdate)
					? window.wc.blocksCheckout
					: null;
				if (!api) {
					log('blocksCheckout.extensionCartUpdate not available');
					return;
				}
				api
					.extensionCartUpdate({ namespace: 'wc-lpc', data: { pickup_location_index: idx } })
					.then(function () {
						log('extensionCartUpdate resolved');
					})
					.catch(function (err) {
						log('extensionCartUpdate error', err);
					});
			} catch (err) {
				log('Error calling extensionCartUpdate', err);
			}
		}

		// Use capture phase to avoid being stopped by internal handlers
		document.addEventListener('change', handler, true);
		document.addEventListener('input', handler, true);
		document.addEventListener('click', handler, true);
		window.__wc_lpc_blocks_bound = true;
		log('Delegated listener attached (capture)');
	}

	function init() {
		attachDelegatedListener();
		// Observe dynamic re-renders (listener is delegated, this is mainly for debugging/consistency)
		var mo = new MutationObserver(function () {});
		mo.observe(document.body, { childList: true, subtree: true });
		log('Initialized WC LPC Blocks script');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();


