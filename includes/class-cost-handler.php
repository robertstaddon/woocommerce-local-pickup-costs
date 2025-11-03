<?php
/**
 * Cost Handler Class
 *
 * Handles cost override logic for local pickup locations in WooCommerce Blocks
 *
 * @package WC_Local_Pickup_Costs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_LPC_Cost_Handler class
 */
class WC_LPC_Cost_Handler {

	/**
	 * Instance
	 *
	 * @var WC_LPC_Cost_Handler
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return WC_LPC_Cost_Handler
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Cost Handler hooks being initialized' );
		}

		// Hook for WooCommerce Blocks checkout (order finalization)
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_custom_pickup_cost' ), 10, 2 );
		
		// Hook to modify shipping rates before they're displayed in cart/checkout
		add_filter( 'woocommerce_shipping_package_rates', array( $this, 'modify_pickup_rates_for_blocks' ), 100, 2 );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Cost Handler hooks registered: woocommerce_shipping_package_rates filter' );
			error_log( 'WC LPC - Cost Handler hooks registered: woocommerce_store_api_checkout_update_order_from_request action' );
		}
	}

	/**
	 * Apply custom pickup cost for Checkout Blocks
	 *
	 * @param object $order Order object.
	 * @param object $request Request object from Store API.
	 * @return void
	 */
	public function apply_custom_pickup_cost( $order, $request ) {
		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $location_costs ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - No location costs saved' );
			}
			return;
		}

		// Get all pickup locations to match names
		$pickup_locations = get_option( 'pickup_location_pickup_locations', array() );

		if ( empty( $pickup_locations ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - No pickup locations found in database' );
			}
			return;
		}

		// Get shipping items from the order
		$shipping_items = $order->get_items( 'shipping' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Processing ' . count( $shipping_items ) . ' shipping item(s)' );
		}

		// Loop through shipping items
		foreach ( $shipping_items as $item_id => $item ) {
			// Check if this is a pickup_location method
			$method_id = $item->get_method_id();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Processing item ID ' . $item_id . ' with method ID: ' . $method_id );
			}

			// Only process pickup_location methods
			if ( 'pickup_location' !== $method_id ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC - Method ID is not pickup_location, skipping' );
				}
				continue;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Found pickup_location method' );
			}

			// Get the pickup location name from item meta
			$pickup_location_name = $item->get_meta( 'pickup_location' );

			if ( empty( $pickup_location_name ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC - No pickup_location meta found in item' );
				}
				continue;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Pickup location name from meta: ' . $pickup_location_name );
			}

			// Get current cost before modification
			$current_total = $item->get_total();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Current shipping total: ' . $current_total );
			}

			// Find the location index by matching the name
			$location_index = null;
			foreach ( $pickup_locations as $index => $location ) {
				if ( isset( $location['name'] ) && $location['name'] === $pickup_location_name ) {
					$location_index = $index;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'WC LPC - Found matching location at index: ' . $location_index );
					}
					break;
				}
			}

			if ( null === $location_index ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC - No matching location found in pickup_locations array' );
				}
				continue;
			}

			// Check if we have a custom cost for this location
			if ( ! isset( $location_costs[ $location_index ] ) || '' === $location_costs[ $location_index ] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC - No custom cost set for location index ' . $location_index );
				}
				continue;
			}

			// Apply the custom cost
			$custom_cost = floatval( $location_costs[ $location_index ] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Applying custom cost: ' . $custom_cost . ' (was ' . $current_total . ')' );
			}

			// Update the item total
			$item->set_total( $custom_cost );

			// Update the order totals
			$order->calculate_totals();

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Updated shipping total to: ' . $item->get_total() );
				error_log( 'WC LPC - Order shipping total: ' . $order->get_shipping_total() );
			}

			// Save location index as order meta for reference
			$order->update_meta_data( 'wc_lpc_pickup_location_index', $location_index );
			$order->update_meta_data( 'wc_lpc_original_pickup_cost', $current_total );
		}
	}

	/**
	 * Modify pickup rates for Blocks (cart/checkout display)
	 *
	 * This hook runs BEFORE rates are displayed, allowing us to modify costs
	 * so customers see the correct override prices in cart and checkout.
	 *
	 * @param array $rates Array of shipping rates.
	 * @param array $package Package data.
	 * @return array Modified rates array.
	 */
	public function modify_pickup_rates_for_blocks( $rates, $package ) {
		// Always log that the filter was called (if WP_DEBUG is on)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC Cart - ============================================' );
			error_log( 'WC LPC Cart - modify_pickup_rates_for_blocks() CALLED' );
			error_log( 'WC LPC Cart - Rates is array? ' . ( is_array( $rates ) ? 'yes' : 'no' ) );
			error_log( 'WC LPC Cart - Rates count: ' . ( is_array( $rates ) ? count( $rates ) : 'N/A' ) );
			error_log( 'WC LPC Cart - Package keys: ' . print_r( is_array( $package ) ? array_keys( $package ) : 'not array', true ) );
		}

		if ( ! is_array( $rates ) || empty( $rates ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Early return: rates is not array or empty' );
			}
			return $rates;
		}

		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $location_costs ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Early return: No location costs saved' );
				error_log( 'WC LPC Cart - Location costs option value: ' . print_r( $location_costs, true ) );
			}
			return $rates;
		}

		// Get all pickup locations to match names
		$pickup_locations = get_option( 'pickup_location_pickup_locations', array() );

		if ( empty( $pickup_locations ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Early return: No pickup locations found in database' );
				error_log( 'WC LPC Cart - Pickup locations option value: ' . print_r( $pickup_locations, true ) );
			}
			return $rates;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC Cart - Processing ' . count( $rates ) . ' shipping rate(s)' );
			error_log( 'WC LPC Cart - Location costs array: ' . print_r( $location_costs, true ) );
			error_log( 'WC LPC Cart - Pickup locations array keys: ' . print_r( array_keys( $pickup_locations ), true ) );
		}

		// Loop through all shipping rates
		foreach ( $rates as $rate_id => $rate ) {
			// Check if this is a pickup_location method
			if ( ! is_object( $rate ) || ! isset( $rate->method_id ) ) {
				continue;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - ========================================' );
				error_log( 'WC LPC Cart - Rate ID: ' . $rate_id );
				error_log( 'WC LPC Cart - Rate ID type: ' . gettype( $rate_id ) );
				error_log( 'WC LPC Cart - Rate ID parts: ' . print_r( explode( ':', $rate_id ), true ) );
				error_log( 'WC LPC Cart - Method ID: ' . $rate->method_id );
			}

			// Only process pickup_location methods
			if ( 'pickup_location' !== $rate->method_id ) {
				continue;
			}

			// Extract location index directly from rate ID
			// Rate IDs are in format: pickup_location:0, pickup_location:1, etc.
			$rate_id_parts = explode( ':', $rate_id );
			
			if ( count( $rate_id_parts ) < 2 ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC Cart - Rate ID does not contain index separator: ' . $rate_id );
				}
				continue;
			}

			$location_index = $rate_id_parts[1];

			if ( ! is_numeric( $location_index ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC Cart - Rate ID index is not numeric: ' . $location_index );
				}
				continue;
			}

			// Convert to integer for array access
			$location_index = intval( $location_index );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Extracted location index from rate ID: ' . $location_index );
				error_log( 'WC LPC Cart - Rate object class: ' . get_class( $rate ) );
				error_log( 'WC LPC Cart - Rate label: ' . ( isset( $rate->label ) ? $rate->label : 'not set' ) );
			}

			// Get current cost before modification
			$current_cost = isset( $rate->cost ) ? floatval( $rate->cost ) : 0;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Current rate cost: ' . $current_cost );
			}

			// Verify the location index exists in our locations array
			if ( ! isset( $pickup_locations[ $location_index ] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC Cart - Location index ' . $location_index . ' not found in pickup_locations array' );
				}
				continue;
			}

			// Check if we have a custom cost for this location
			if ( ! isset( $location_costs[ $location_index ] ) || '' === $location_costs[ $location_index ] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'WC LPC Cart - No custom cost set for location index ' . $location_index );
				}
				continue;
			}

			// Apply the custom cost
			$custom_cost = floatval( $location_costs[ $location_index ] );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Applying custom cost: ' . $custom_cost . ' (was ' . $current_cost . ')' );
			}

			// Update the rate cost
			$rate->set_cost( $custom_cost );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC Cart - Updated rate cost to: ' . $rate->cost );
			}
		}

		return $rates;
	}
}
