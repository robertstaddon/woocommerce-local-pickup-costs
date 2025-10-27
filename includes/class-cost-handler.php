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
		// Hook for WooCommerce Blocks checkout
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_custom_pickup_cost' ), 10, 2 );
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
}
