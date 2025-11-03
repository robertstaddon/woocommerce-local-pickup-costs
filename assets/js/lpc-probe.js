/*
 * WooCommerce Checkout Blocks Event Probe
 * Logs available events/state changes to aid choosing reliable React hooks
 */
(function () {
	var prefix = '[WC Blocks Probe]';
	function log() {
		if (typeof console !== 'undefined' && console.log) {
			console.log.apply(console, [prefix].concat([].slice.call(arguments)));
		}
	}

	function probeEventRegistration() {
		var er = (window.wc && window.wc.blocksCheckout && window.wc.blocksCheckout.eventRegistration) || null;
		if (!er) {
			log('eventRegistration not available');
			return false;
		}
		var count = 0;
		Object.keys(er).forEach(function (key) {
			var register = er[key];
			if (typeof register === 'function' && /^on/i.test(key)) {
				try {
					register(function () {
						var args = Array.prototype.slice.call(arguments);
						log('event', key, args);
					});
					count++;
				} catch (e) {
					// Some hooks require specific callback shapes; ignore.
				}
			}
		});
		log('eventRegistration listeners attached:', count);
		return true;
	}

	function probeStoreSubscribe() {
		if (!window.wp || !wp.data) {
			log('wp.data not available');
			return false;
		}
		var lastRates = '';
		var lastSelections = '';
		var unsubscribe = wp.data.subscribe(function () {
			try {
				var s = wp.data.select('wc/store');
				if (!s) return;
				var rates = s.getShippingRates ? s.getShippingRates() : null;
				var selections = s.getShippingRateSelections ? s.getShippingRateSelections() : null;
				var rj = rates ? JSON.stringify(rates) : '';
				var sj = selections ? JSON.stringify(selections) : '';
				if (rj !== lastRates) {
					log('shippingRates changed', rates);
					lastRates = rj;
				}
				if (sj !== lastSelections) {
					log('shippingRateSelections changed', selections);
					lastSelections = sj;
				}
			} catch (e) {
				// no-op
			}
		});
		log('wp.data subscription active');
		return !!unsubscribe;
	}

	function probeCartUpdatePatch() {
		var b = (window.wc && window.wc.blocksCheckout) || null;
		if (!b || !b.extensionCartUpdate) {
			log('blocksCheckout.extensionCartUpdate not available');
			return false;
		}
		if (b.__lpcPatched) return true;
		var orig = b.extensionCartUpdate;
		b.extensionCartUpdate = function () {
			var args = Array.prototype.slice.call(arguments);
			log('extensionCartUpdate called', args);
			var p;
			try {
				p = orig.apply(this, args);
			} catch (e) {
				log('extensionCartUpdate threw', e);
				throw e;
			}
			return Promise.resolve(p)
				.then(function (res) {
					log('extensionCartUpdate resolved', res);
					return res;
				})
				.catch(function (err) {
					log('extensionCartUpdate rejected', err);
					throw err;
				});
		};
		b.__lpcPatched = true;
		log('extensionCartUpdate patched');
		return true;
	}

	function probeDomFallback() {
		function isPickupRadio(el) {
			return el && el.tagName === 'INPUT' && el.type === 'radio' && /pickup_location:/.test(String(el.value || ''));
		}
		document.addEventListener('change', function (e) {
			var el = e.target;
			if (isPickupRadio(el)) {
				log('DOM change (radio)', { name: el.name, value: el.value });
			}
		}, true);
		log('DOM fallback listener attached');
		return true;
	}

	function init() {
		var a = probeEventRegistration();
		var b = probeStoreSubscribe();
		var c = probeCartUpdatePatch();
		var d = probeDomFallback();
		log('Probes initialized', { eventRegistration: a, store: b, cartUpdate: c, dom: d });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();


