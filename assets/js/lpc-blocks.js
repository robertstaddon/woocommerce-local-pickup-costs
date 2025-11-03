/* global wc */
(function () {
	function log() {
		if (typeof console !== 'undefined' && console.debug) {
			console.debug.apply(console, ['[WC LPC]'].concat([].slice.call(arguments)));
		}
	}

	function getPickupIndexFromValue(value) {
		if (typeof value !== 'string') return null;
		var parts = value.split(':');
		if (parts.length < 2) return null;
		var idx = parseInt(parts[1], 10);
		return isNaN(idx) ? null : idx;
	}

	function attachListeners(root) {
		var container = root.querySelector('.wc-block-components-local-pickup-rates-control');
		if (!container) return;
		var radios = container.querySelectorAll('input[type="radio"][value^="pickup_location:"]');
		if (!radios || !radios.length) {
			log('No pickup radios found yet');
			return;
		}
		radios.forEach(function (r) {
			r.addEventListener('change', function (e) {
				var value = e.target && e.target.value;
				var idx = getPickupIndexFromValue(value);
				log('Pickup radio changed', { value: value, idx: idx });
				if (idx === null) return;
				try {
					var api = (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.extensionCartUpdate) ? window.wc.blocksCheckout : null;
					if (!api) {
						log('blocksCheckout.extensionCartUpdate not available');
						return;
					}
					api.extensionCartUpdate({
						namespace: 'wc-lpc',
						data: { pickup_location_index: idx },
					}).then(function () {
						log('extensionCartUpdate resolved');
					}).catch(function (err) {
						log('extensionCartUpdate error', err);
					});
				} catch (err) {
					log('Error calling extensionCartUpdate', err);
				}
			});
		});
		log('Attached pickup change listeners to', radios.length, 'inputs');
	}

	function init() {
		var root = document;
		attachListeners(root);
		// Observe dynamic re-renders in blocks
		var mo = new MutationObserver(function () {
			attachListeners(root);
		});
		mo.observe(document.body, { childList: true, subtree: true });
		log('Initialized WC LPC Blocks script');
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();


