/**
 * WooCommerce Local Pickup Costs - Checkout Pre-selection
 */

(function( $ ) {
	'use strict';

	/**
	 * Initialize on document ready and after checkout updates
	 */
	function initCheckoutPreselect() {
		if ( typeof wc_lpc_data === 'undefined' || ! wc_lpc_data.selected_location ) {
			return;
		}

		const selectedLocation = wc_lpc_data.selected_location;

		// Function to select local pickup and specific location
		function selectPickupLocation() {
			// Wait for shipping methods to be loaded
			if ( $( '.woocommerce-shipping-totals' ).length === 0 ) {
				return;
			}

			// Find all local pickup shipping method radio buttons or inputs
			const localPickupInputs = $('input[value*="local_pickup"]');

			if ( localPickupInputs.length === 0 ) {
				return;
			}

			// Select the first local pickup method found
			const selectedMethod = localPickupInputs.first();

			if ( selectedMethod.length > 0 ) {
				// Check the radio button
				selectedMethod.prop('checked', true).trigger('change');
			}

			// Wait a bit for the location selection UI to appear
			setTimeout(function() {
				selectSpecificLocation( selectedLocation );
			}, 500 );
		}

		/**
		 * Select specific pickup location
		 *
		 * @param {string} locationId Location ID to select
		 */
		function selectSpecificLocation( locationId ) {
			// Try to find and select the location by ID
			// The location selector might be a radio button or dropdown
			const locationInput = $('input[value="' + locationId + '"]');
			const locationSelect = $('select[name*="pickup_location"], select[name*="local_pickup_instance"]');

			// If it's a radio button
			if ( locationInput.length > 0 ) {
				locationInput.prop('checked', true).trigger('change');
				return;
			}

			// If it's a dropdown
			if ( locationSelect.length > 0 ) {
				locationSelect.val( locationId ).trigger('change');
			}
		}

		// Trigger the selection
		selectPickupLocation();

		// Re-trigger after checkout updates
		$( document.body ).on('updated_checkout', function() {
			setTimeout( selectPickupLocation, 100 );
		});
	}

	// Run on document ready
	$( document ).ready( initCheckoutPreselect );

	// Also run after checkout has been loaded
	$( document.body ).on( 'updated_checkout', initCheckoutPreselect );

})( jQuery );

