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
				// Blocks checkout not loaded yet, retry
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
				// Already selected, proceed to location selection
				return true;
			}

			// Click the Pickup option to select it
			pickupOption.trigger('click');
			
			// Also try native click event
			if ( pickupOption[0] ) {
				pickupOption[0].click();
			}

			return true;
		}

		/**
		 * Select specific pickup location
		 *
		 * @param {string} locationId Location ID to select
		 */
		function selectSpecificLocation( locationId ) {
			// Wait for location selection UI to appear
			// Location inputs have values like "pickup_location:0", "pickup_location:1", etc.
			const locationInput = $('input[type="radio"][value="pickup_location:' + locationId + '"]');
			
			if ( locationInput.length > 0 ) {
				// Check the radio button
				locationInput.prop('checked', true).trigger('change').trigger('click');
				
				// Also try native events
				if ( locationInput[0] ) {
					locationInput[0].click();
					locationInput[0].dispatchEvent(new Event('change', { bubbles: true }));
				}
				
				return true;
			}

			// Try alternative selector for dropdowns
			const locationSelect = $('select[name*="pickup_location"], select[name*="local_pickup_instance"]');
			
			if ( locationSelect.length > 0 ) {
				locationSelect.val( locationId ).trigger('change');
				return true;
			}

			return false;
		}

		/**
		 * Main function to select pickup and location with retry logic
		 */
		function selectPickupLocation() {
			// Try to select the Pickup option
			const pickupSelected = selectBlocksPickupOption();
			
			if ( ! pickupSelected ) {
				// Pickup option not found or not clicked yet, will retry
				return;
			}

			// Wait for location selection UI to appear after Pickup is selected
			// Use multiple attempts with increasing delays
			let attempts = 0;
			const maxAttempts = 10;
			
			function trySelectLocation() {
				attempts++;
				
				const locationSelected = selectSpecificLocation( selectedLocation );
				
				if ( ! locationSelected && attempts < maxAttempts ) {
					// Location not found yet, retry after delay
					setTimeout( trySelectLocation, 200 * attempts ); // Exponential backoff
				}
			}

			// Start trying to select location after a short delay
			setTimeout( trySelectLocation, 300 );
		}

		// Initial attempt
		selectPickupLocation();

		// Retry with exponential backoff if initial attempt fails
		let retryCount = 0;
		const maxRetries = 5;
		
		function retrySelection() {
			retryCount++;
			
			// Check if Pickup is already selected
			const pickupOption = $('.wc-block-checkout__shipping-method-option').filter(function() {
				return $(this).find('.wc-block-checkout__shipping-method-option-title').text().trim() === 'Pickup';
			});
			
			if ( pickupOption.length > 0 && 
			     ( pickupOption.attr('aria-checked') === 'true' || 
			       pickupOption.hasClass('wc-block-checkout__shipping-method-option--selected') ) ) {
				// Pickup is selected, try location selection
				const locationSelected = selectSpecificLocation( selectedLocation );
				if ( ! locationSelected && retryCount < maxRetries ) {
					setTimeout( retrySelection, 500 * retryCount );
				}
			} else if ( retryCount < maxRetries ) {
				// Pickup not selected yet, try again
				setTimeout( retrySelection, 500 * retryCount );
			}
		}

		// Start retry logic after initial delay
		setTimeout( retrySelection, 1000 );
	}

	// Run on document ready
	$( document ).ready( function() {
		// Wait a bit for Blocks checkout to initialize
		setTimeout( initCheckoutPreselect, 100 );
	});

	// Also listen for checkout updates (Blocks checkout may update dynamically)
	$( document.body ).on( 'updated_checkout', function() {
		setTimeout( initCheckoutPreselect, 100 );
	});

})( jQuery );
