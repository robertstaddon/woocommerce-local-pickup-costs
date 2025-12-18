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
	 * Cached location costs
	 *
	 * @var array|null
	 */
	private static $cached_location_costs = null;

	/**
	 * Cached pickup locations
	 *
	 * @var array|null
	 */
	private static $cached_pickup_locations = null;

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
		// Per-request guard: prevent duplicate registration even if instantiated multiple times in the same request
		if ( defined( 'WC_LPC_COST_HANDLER_HOOKS_ADDED' ) && WC_LPC_COST_HANDLER_HOOKS_ADDED ) {
			return;
		}

		// Hook for WooCommerce Blocks checkout (order finalization)
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_custom_pickup_cost' ), 10, 2 );
		
		// Hook to modify shipping rates before they're displayed in cart/checkout
		// Use woocommerce_package_rates (not woocommerce_shipping_package_rates) for Blocks compatibility
		add_filter( 'woocommerce_package_rates', array( $this, 'modify_pickup_rates_for_blocks' ), 9999, 2 );

		// Register Store API update callback to force recalculation on-demand from Checkout Blocks
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_update_callback' ) );

		// Set per-request guard
		if ( ! defined( 'WC_LPC_COST_HANDLER_HOOKS_ADDED' ) ) {
			define( 'WC_LPC_COST_HANDLER_HOOKS_ADDED', true );
		}
	}

	/**
	 * Get cached location costs
	 *
	 * @return array
	 */
	private static function get_location_costs() {
		if ( null === self::$cached_location_costs ) {
			self::$cached_location_costs = get_option( 'wc_lpc_location_costs', array() );
		}
		return self::$cached_location_costs;
	}

	/**
	 * Get cached pickup locations
	 *
	 * @return array
	 */
	private static function get_pickup_locations() {
		if ( null === self::$cached_pickup_locations ) {
			self::$cached_pickup_locations = get_option( 'pickup_location_pickup_locations', array() );
		}
		return self::$cached_pickup_locations;
	}

	/**
	 * Build location name lookup array
	 *
	 * @param array $pickup_locations Pickup locations array.
	 * @return array Lookup array with location name as key and index as value.
	 */
	private static function build_location_name_lookup( $pickup_locations ) {
		$lookup = array();
		foreach ( $pickup_locations as $index => $location ) {
			if ( isset( $location['name'] ) ) {
				$lookup[ $location['name'] ] = $index;
			}
		}
		return $lookup;
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
		$location_costs = self::get_location_costs();

		if ( empty( $location_costs ) ) {
			return;
		}

		// Get all pickup locations to match names
		$pickup_locations = self::get_pickup_locations();

		if ( empty( $pickup_locations ) ) {
			return;
		}

		// Build lookup array for O(1) name matching instead of O(n) loop
		$location_name_lookup = self::build_location_name_lookup( $pickup_locations );

		// Get shipping items from the order
		$shipping_items = $order->get_items( 'shipping' );

		// Loop through shipping items
		foreach ( $shipping_items as $item_id => $item ) {
			// Only process pickup_location methods
			if ( 'pickup_location' !== $item->get_method_id() ) {
				continue;
			}

			// Get the pickup location name from item meta
			$pickup_location_name = $item->get_meta( 'pickup_location' );

			if ( empty( $pickup_location_name ) ) {
				continue;
			}

			// Get current cost before modification
			$current_total = $item->get_total();

			// Find the location index using lookup array (O(1) instead of O(n))
			$location_index = isset( $location_name_lookup[ $pickup_location_name ] ) 
				? $location_name_lookup[ $pickup_location_name ] 
				: null;

			if ( null === $location_index ) {
				continue;
			}

			// Get location data
			$location_data = $pickup_locations[ $location_index ];

			// Calculate cost using shared method (applies filter hook)
			$custom_cost = $this->calculate_pickup_location_cost( $location_index, $current_total, $location_data, $item );

			// Only apply if we got a valid numeric value (not false)
			if ( false !== $custom_cost ) {
				// Update the item total
				$item->set_total( $custom_cost );

				// Update the order totals
				$order->calculate_totals();

				// Save location index as order meta for reference
				$order->update_meta_data( 'wc_lpc_pickup_location_index', $location_index );
				$order->update_meta_data( 'wc_lpc_original_pickup_cost', $current_total );
			}
		}
	}

	/**
	 * Register Store API update callback (Blocks cart update on-demand)
	 */
	public function register_store_api_update_callback() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'wc-lpc',
				'callback'  => array( $this, 'handle_store_api_update' ),
			)
		);
	}

	/**
	 * Handle Store API update (triggered by extensionCartUpdate)
	 *
	 * @param array $data Data passed from client.
	 * @return void
	 */
	public function handle_store_api_update( $data ) {
		// Optionally persist index for troubleshooting/consistency
		$pickup_index = isset( $data['pickup_location_index'] ) ? intval( $data['pickup_location_index'] ) : null;
		if ( null !== $pickup_index && WC()->session ) {
			WC()->session->set( 'wc_lpc_last_pickup_index', $pickup_index );
		}

		// Recalculate totals to invoke shipping rates filter
		if ( WC()->cart ) {
			WC()->cart->calculate_totals();
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
		if ( ! is_array( $rates ) || empty( $rates ) ) {
			return $rates;
		}

		// Get saved location costs (cached)
		$location_costs = self::get_location_costs();

		if ( empty( $location_costs ) ) {
			return $rates;
		}

		// Get all pickup locations (cached)
		$pickup_locations = self::get_pickup_locations();

		if ( empty( $pickup_locations ) ) {
			return $rates;
		}

		// Loop through all shipping rates
		foreach ( $rates as $rate_id => $rate ) {
			// Check if this is a pickup_location method
			if ( ! is_object( $rate ) || ! isset( $rate->method_id ) ) {
				continue;
			}

			// Only process pickup_location methods
			if ( 'pickup_location' !== $rate->method_id ) {
				continue;
			}

			// Extract location index directly from rate ID
			// Rate IDs are in format: pickup_location:0, pickup_location:1, etc.
			$rate_id_parts = explode( ':', $rate_id );
			
			if ( count( $rate_id_parts ) < 2 ) {
				continue;
			}

			$location_index = $rate_id_parts[1];

			if ( ! is_numeric( $location_index ) ) {
				continue;
			}

			// Convert to integer for array access
			$location_index = intval( $location_index );

			// Verify the location index exists in our locations array
			if ( ! isset( $pickup_locations[ $location_index ] ) ) {
				continue;
			}

			// Get location data
			$location_data = $pickup_locations[ $location_index ];

			// Get original cost from rate
			$original_cost = $rate->get_cost();

			// Calculate cost using shared method (applies filter hook)
			$custom_cost = $this->calculate_pickup_location_cost( $location_index, $original_cost, $location_data, $rate );

			// Only apply if we got a valid numeric value (not false)
			if ( false !== $custom_cost ) {
				// Update the rate cost
				$rate->set_cost( $custom_cost );
			}
		}

		return $rates;
	}

	/**
	 * Calculate pickup location cost with filter hook support
	 *
	 * This method consolidates cost calculation logic and applies a filter hook
	 * to allow developers to adjust pickup costs dynamically.
	 *
	 * @param int    $location_index Location array index.
	 * @param float  $original_cost  Original WooCommerce cost before override.
	 * @param array  $location_data  Full location data array.
	 * @param object $context        Context object (WC_Shipping_Rate for display or WC_Order_Item_Shipping for finalization).
	 * @return float|false Modified cost, or false if override should be skipped.
	 */
	private function calculate_pickup_location_cost( $location_index, $original_cost, $location_data, $context ) {
		// Get saved location costs (cached)
		$location_costs = self::get_location_costs();

		// Check if we have a custom cost for this location
		if ( ! isset( $location_costs[ $location_index ] ) || '' === $location_costs[ $location_index ] ) {
			return false;
		}

		// Get the custom cost from settings
		$custom_cost = floatval( $location_costs[ $location_index ] );

		/**
		 * Filter pickup location cost before applying override
		 *
		 * @param float  $custom_cost    The custom cost from plugin settings.
		 * @param int    $location_index The location array index.
		 * @param array  $location_data  Full location data (name, address, enabled status, etc.).
		 * @param float  $original_cost  Original WooCommerce cost before override.
		 * @param object $context        Context object (WC_Shipping_Rate for display or WC_Order_Item_Shipping for finalization).
		 */
		$modified_cost = apply_filters( 'wc_lpc_pickup_location_cost', $custom_cost, $location_index, $location_data, $original_cost, $context );

		// If filter returns false or null, skip override
		if ( false === $modified_cost || null === $modified_cost ) {
			return false;
		}

		// Ensure we return a valid numeric value
		return floatval( $modified_cost );
	}
}
