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
		
		// Fallback for classic checkout
		add_filter( 'woocommerce_package_rates', array( $this, 'modify_local_pickup_cost_classic' ), 10, 2 );
	}

	/**
	 * Apply custom pickup cost for Checkout Blocks
	 *
	 * @param object $order Order object.
	 * @param object $request Request object from Store API.
	 * @return void
	 */
	public function apply_custom_pickup_cost( $order, $request ) {
		// DEBUG: Log specific data to avoid memory issues
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Log only specific properties to avoid memory exhaustion
			$request_array = $request->get_json_params();
			error_log( 'WC LPC - Shipping method: ' . ( isset( $request_array['shipping_method'] ) ? print_r( $request_array['shipping_method'], true ) : 'not set' ) );
			error_log( 'WC LPC - Extensions: ' . ( isset( $request_array['extensions'] ) ? print_r( $request_array['extensions'], true ) : 'not set' ) );
			error_log( 'WC LPC - Order shipping method: ' . print_r( $order->get_shipping_method(), true ) );
		}

		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $location_costs ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - No location costs saved' );
			}
			return $order;
		}

		// Check if local pickup is selected - check multiple ways
		$chosen_shipping_method = null;
		
		if ( isset( $request->shipping_method ) ) {
			$chosen_shipping_method = $request->shipping_method;
		} elseif ( isset( $request->shipping_rate ) ) {
			$chosen_shipping_method = $request->shipping_rate;
		} elseif ( $order->get_shipping_method() ) {
			$chosen_shipping_method = $order->get_shipping_method();
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Chosen shipping method: ' . print_r( $chosen_shipping_method, true ) );
		}
		
		if ( empty( $chosen_shipping_method ) || ( is_string( $chosen_shipping_method ) && strpos( $chosen_shipping_method, 'local_pickup' ) === false ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Not local pickup or empty method' );
			}
			return $order;
		}

		// Get the selected pickup location from various possible sources
		$selected_pickup_location = null;
		
		// Try different ways to get the pickup location index
		if ( isset( $request->extensions ) && is_array( $request->extensions ) ) {
			if ( isset( $request->extensions['pickup_location'] ) ) {
				$selected_pickup_location = $request->extensions['pickup_location'];
			}
		}
		
		// Also check if it's in the request data directly
		if ( null === $selected_pickup_location && isset( $request->pickup_location ) ) {
			$selected_pickup_location = $request->pickup_location;
		}
		
		// Check order meta
		if ( null === $selected_pickup_location && $order->get_meta( 'pickup_location' ) ) {
			$selected_pickup_location = $order->get_meta( 'pickup_location' );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Selected pickup location: ' . print_r( $selected_pickup_location, true ) );
			error_log( 'WC LPC - Location costs: ' . print_r( $location_costs, true ) );
		}

		// If we found a location and have a custom cost, apply it
		if ( null !== $selected_pickup_location && isset( $location_costs[ $selected_pickup_location ] ) && $location_costs[ $selected_pickup_location ] !== '' ) {
			$custom_cost = floatval( $location_costs[ $selected_pickup_location ] );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Applying custom cost: ' . $custom_cost );
			}
			
			// Update the order shipping total
			$order->set_shipping_total( $custom_cost );
			
			// Save location index as order meta for reference
			$order->update_meta_data( 'wc_lpc_pickup_location_index', $selected_pickup_location );
			$order->update_meta_data( 'wc_lpc_original_pickup_cost', $order->get_shipping_total( 'edit' ) );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Order shipping total updated to: ' . $order->get_shipping_total() );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - No custom cost applied' );
			}
		}
	}

	/**
	 * Modify local pickup cost for classic checkout (fallback)
	 *
	 * @param array $rates Array of shipping rates.
	 * @param array $package Package data.
	 * @return array Modified rates.
	 */
	public function modify_local_pickup_cost_classic( $rates, $package ) {
		if ( ! is_array( $rates ) || empty( $rates ) ) {
			return $rates;
		}

		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $location_costs ) ) {
			return $rates;
		}

		// Get pickup locations data
		$pickup_locations = get_option( 'pickup_location_pickup_locations', array() );

		// Loop through all rates
		foreach ( $rates as $rate_id => $rate ) {
			// Only process local pickup methods
			if ( ! isset( $rate->method_id ) || 'local_pickup' !== $rate->method_id ) {
				continue;
			}

			// Extract location index from rate ID
			// Format: local_pickup:instance_id:location_index
			$selected_location_index = $this->get_location_index_from_rate_id( $rate_id );

			// If we found a location index, apply the custom cost
			if ( null !== $selected_location_index && isset( $location_costs[ $selected_location_index ] ) && $location_costs[ $selected_location_index ] !== '' ) {
				$custom_cost = floatval( $location_costs[ $selected_location_index ] );
				$rate->set_cost( $custom_cost );
			}
		}

		return $rates;
	}

	/**
	 * Get location index from rate ID
	 *
	 * @param string $rate_id Rate ID.
	 * @return int|null Location index or null.
	 */
	private function get_location_index_from_rate_id( $rate_id ) {
		// Rate format is typically something like "local_pickup:instance_id"
		// We need to extract the location index if it's embedded
		$parts = explode( ':', $rate_id );
		
		// The location index might be in the rate ID format
		if ( count( $parts ) >= 3 && is_numeric( $parts[2] ) ) {
			return intval( $parts[2] );
		}

		return null;
	}
}

