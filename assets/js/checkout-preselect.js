/**
 * WooCommerce Local Pickup Costs - Checkout Pre-selection
 * Works exclusively with WooCommerce Blocks checkout
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

		/**
		 * Select the "Pickup" option in Blocks checkout
		 */
		function selectBlocksPickupOption() {
			// Check if Blocks checkout is available
			const shippingMethodContainer = $('.wc-block-checkout__shipping-method-container');
			
			if ( shippingMethodContainer.length === 0 ) {
				return false;
			}

			// Find all shipping method options
			const shippingOptions = $('.wc-block-checkout__shipping-method-option');
			
			if ( shippingOptions.length === 0 ) {
				return false;
			}

			// Find the "Pickup" option by checking the title text
			let pickupOption = null;
			shippingOptions.each( function() {
				const title = $( this ).find('.wc-block-checkout__shipping-method-option-title').text().trim();
				if ( title === 'Pickup' ) {
					pickupOption = $( this );
					return false; // Break loop
				}
			});

			if ( ! pickupOption || pickupOption.length === 0 ) {
				return false;
			}

			// Check if already selected
			if ( pickupOption.attr('aria-checked') === 'true' || 
			     pickupOption.hasClass('wc-block-checkout__shipping-method-option--selected') ) {
				return true;
			}

			// Click the Pickup option to select it
			if ( pickupOption[0] ) {
				pickupOption[0].click();
			}

			return true;
		}

		/**
		 * Wait for location selection UI to appear after Pickup is selected
		 *
		 * @param {Function} callback Function to call when location UI is ready
		 */
		function waitForLocationUI( callback ) {
			const locationValue = 'pickup_location:' + String( selectedLocation );
			const locationInput = $('input[type="radio"][value="' + locationValue + '"]');
			
			if ( locationInput.length > 0 ) {
				// Location UI is already available
				callback();
				return;
			}

			// Wait for location UI to appear (max 2 attempts)
			let attempts = 0;
			const maxAttempts = 2;
			
			function checkForLocationUI() {
				attempts++;
				const checkInput = $('input[type="radio"][value="' + locationValue + '"]');
				
				if ( checkInput.length > 0 ) {
					callback();
				} else if ( attempts < maxAttempts ) {
					setTimeout( checkForLocationUI, 500 );
				}
			}

			// Start checking after a short delay to allow UI to render
			setTimeout( checkForLocationUI, 300 );
		}

		/**
		 * Select specific pickup location
		 *
		 * @param {string} locationId Location ID to select
		 */
		function selectSpecificLocation( locationId ) {
			// Location inputs have values like "pickup_location:0", "pickup_location:1", etc.
			const locationValue = 'pickup_location:' + String( locationId );
			const locationInput = $('input[type="radio"][value="' + locationValue + '"]');
			
			if ( locationInput.length === 0 ) {
				return false;
			}

			// Find the label wrapper that contains this input
			const locationLabel = locationInput.closest('label.wc-block-components-radio-control__option');
			
			if ( locationLabel.length > 0 && locationLabel[0] ) {
				// Click on the label wrapper to trigger all the proper class updates
				locationLabel[0].click();
				return true;
			}

			return false;
		}

		/**
		 * Main function to select pickup and location
		 */
		function selectPickupLocation() {
			// Try to select the Pickup option
			const pickupSelected = selectBlocksPickupOption();
			
			if ( ! pickupSelected ) {
				return;
			}

			// Wait for location selection UI to appear, then select location
			waitForLocationUI( function() {
				selectSpecificLocation( selectedLocation );
			});
		}

		// Initial attempt
		selectPickupLocation();
	}

	// Run on document ready
	$( document ).ready( function() {
		setTimeout( initCheckoutPreselect, 100 );
	});

	// Also listen for checkout updates
	$( document.body ).on( 'updated_checkout', function() {
		setTimeout( initCheckoutPreselect, 100 );
	});

})( jQuery );
