<?php
/**
 * Cost Handler Class
 *
 * Handles cost override logic for local pickup locations
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
		add_filter( 'woocommerce_package_rates', array( $this, 'modify_local_pickup_cost' ), 10, 2 );
	}

	/**
	 * Modify local pickup cost based on location-specific settings
	 *
	 * @param array $rates Array of shipping rates.
	 * @param array $package Package data.
	 * @return array Modified rates.
	 */
	public function modify_local_pickup_cost( $rates, $package ) {
		if ( ! is_array( $rates ) || empty( $rates ) ) {
			return $rates;
		}

		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $location_costs ) ) {
			return $rates;
		}

		// Loop through all rates
		foreach ( $rates as $rate_id => $rate ) {
			// Only process local pickup methods
			if ( ! isset( $rate->method_id ) || 'local_pickup' !== $rate->method_id ) {
				continue;
			}

			// Get the instance ID (this is usually in the rate ID like "local_pickup:1")
			$instance_id = $this->get_instance_id_from_rate( $rate_id );

			if ( ! $instance_id ) {
				continue;
			}

			// Check if this location has a custom cost
			if ( isset( $location_costs[ $instance_id ] ) && $location_costs[ $instance_id ] !== '' ) {
				$custom_cost = floatval( $location_costs[ $instance_id ] );

				// Set the new cost
				// Note: Rate cost is typically stored as an array with cost and label
				if ( property_exists( $rate, 'meta_data' ) && is_array( $rate->meta_data ) ) {
					// Handle newer WooCommerce rate structure
					$rate->set_cost( $custom_cost );
				} else {
					// Fallback for older versions
					$rate->cost = $custom_cost;
				}
			}
		}

		return $rates;
	}

	/**
	 * Get instance ID from rate ID
	 *
	 * @param string $rate_id Rate ID (e.g., "local_pickup:1").
	 * @return int|false Instance ID or false if not found.
	 */
	private function get_instance_id_from_rate( $rate_id ) {
		// Extract instance ID from rate ID
		// Format is usually "method_id:instance_id" or similar
		$parts = explode( ':', $rate_id );
		
		if ( count( $parts ) >= 2 ) {
			return intval( $parts[1] );
		}

		// Alternative: check the rate object for instance ID
		// This might vary depending on WooCommerce version
		return false;
	}
}

