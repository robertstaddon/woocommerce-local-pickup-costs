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

		// Get pickup locations to map instance to index
		$pickup_locations = get_option( 'pickup_location_pickup_locations', array() );

		// Loop through all rates
		foreach ( $rates as $rate_id => $rate ) {
			// Only process local pickup methods
			if ( ! isset( $rate->method_id ) || 'local_pickup' !== $rate->method_id ) {
				continue;
			}

			// Check if the rate has selected_pickup_location in its meta_data
			$selected_location_index = null;
			
			if ( property_exists( $rate, 'meta_data' ) && is_array( $rate->meta_data ) ) {
				// Check if the location index is stored in meta_data
				foreach ( $rate->meta_data as $meta ) {
					if ( isset( $meta->key ) && 'selected_pickup_location' === $meta->key ) {
						$selected_location_index = $meta->value;
						break;
					}
				}
			}

			// If no location index in meta, try to get it from the rate ID or instance
			if ( null === $selected_location_index ) {
				// Check if rate has a pickup location index in its settings
				// This would be set when a specific location is selected
				$selected_location_index = $this->get_location_index_from_rate( $rate_id, $rate );
			}

			// If we found a location index, apply the custom cost
			if ( null !== $selected_location_index && isset( $location_costs[ $selected_location_index ] ) && $location_costs[ $selected_location_index ] !== '' ) {
				$custom_cost = floatval( $location_costs[ $selected_location_index ] );

				// Set the new cost
				$rate->set_cost( $custom_cost );
			}
		}

		return $rates;
	}

	/**
	 * Get location index from rate
	 *
	 * @param string $rate_id Rate ID.
	 * @param object $rate Rate object.
	 * @return int|null Location index or null.
	 */
	private function get_location_index_from_rate( $rate_id, $rate ) {
		// This will be implemented based on how WooCommerce stores selected location
		// For now, we'll check the session for the selected location
		if ( WC()->session ) {
			$selected_location = WC()->session->get( 'chosen_shipping_methods', array() );
			
			// The selected pickup location index might be stored elsewhere
			// Check if we can determine it from the rate
			
			// Try to extract from rate ID format if it includes location info
			// Rate format might be "pickup_location:0" or similar
			if ( strpos( $rate_id, 'pickup_location' ) !== false ) {
				$parts = explode( ':', $rate_id );
				if ( isset( $parts[2] ) && is_numeric( $parts[2] ) ) {
					return intval( $parts[2] );
				}
			}
		}

		return null;
	}

}

