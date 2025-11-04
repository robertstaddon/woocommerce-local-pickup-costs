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
        // Per-request guard: prevent duplicate registration even if instantiated multiple times in the same request
        if ( defined( 'WC_LPC_COST_HANDLER_HOOKS_ADDED' ) && WC_LPC_COST_HANDLER_HOOKS_ADDED ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'WC LPC - Cost Handler hooks already registered (per-request guard), skipping' );
            }
            return;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
            $doing_ajax  = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? 'yes' : 'no';
            $in_admin    = is_admin() ? 'yes' : 'no';
            error_log( 'WC LPC - Cost Handler hooks being initialized' );
            error_log( 'WC LPC - Context: REQUEST_URI=' . $request_uri . ', is_admin=' . $in_admin . ', DOING_AJAX=' . $doing_ajax );
        }

        // Hook for WooCommerce Blocks checkout (order finalization)
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'apply_custom_pickup_cost' ), 10, 2 );
        
        // Hook to modify shipping rates before they're displayed in cart/checkout
        // Use woocommerce_package_rates (not woocommerce_shipping_package_rates) for Blocks compatibility
        add_filter( 'woocommerce_package_rates', array( $this, 'modify_pickup_rates_for_blocks' ), 9999, 2 );
        
        // Fallback: also hook woocommerce_shipping_rate_cost to guard against other filters resetting costs
        add_filter( 'woocommerce_shipping_rate_cost', array( $this, 'modify_shipping_rate_cost' ), 9999, 2 );

		// Register Store API update callback to force recalculation on-demand from Checkout Blocks
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_update_callback' ) );

        // Set per-request guard
        if ( ! defined( 'WC_LPC_COST_HANDLER_HOOKS_ADDED' ) ) {
            define( 'WC_LPC_COST_HANDLER_HOOKS_ADDED', true );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'WC LPC - Cost Handler hooks registered: woocommerce_package_rates filter (priority 9999)' );
            error_log( 'WC LPC - Cost Handler hooks registered: woocommerce_shipping_rate_cost filter (priority 9999)' );
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
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
            $doing_ajax  = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? 'yes' : 'no';
            $in_admin    = is_admin() ? 'yes' : 'no';
            error_log( 'WC LPC - apply_custom_pickup_cost() CALLED' );
            error_log( 'WC LPC - apply_custom_pickup_cost Context: REQUEST_URI=' . $request_uri . ', is_admin=' . $in_admin . ', DOING_AJAX=' . $doing_ajax );
        }
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
	 * Register Store API update callback (Blocks cart update on-demand)
	 */
	public function register_store_api_update_callback() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Store API register_update_callback not available' );
			}
			return;
		}

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'wc-lpc',
				'callback'  => array( $this, 'handle_store_api_update' ),
			)
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC - Store API update callback registered (namespace: wc-lpc)' );
		}
	}

	/**
	 * Handle Store API update (triggered by extensionCartUpdate)
	 *
	 * @param array $data Data passed from client.
	 * @return void
	 */
	public function handle_store_api_update( $data ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
			$doing_ajax  = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? 'yes' : 'no';
			$in_admin    = is_admin() ? 'yes' : 'no';
			error_log( 'WC LPC - handle_store_api_update() CALLED' );
			error_log( 'WC LPC - handle_store_api_update Context: REQUEST_URI=' . $request_uri . ', is_admin=' . $in_admin . ', DOING_AJAX=' . $doing_ajax );
			error_log( 'WC LPC - handle_store_api_update Data: ' . wp_json_encode( $data ) );
		}

		// Optionally persist index for troubleshooting/consistency
		$pickup_index = isset( $data['pickup_location_index'] ) ? intval( $data['pickup_location_index'] ) : null;
		if ( null !== $pickup_index && WC()->session ) {
			WC()->session->set( 'wc_lpc_last_pickup_index', $pickup_index );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Stored wc_lpc_last_pickup_index=' . $pickup_index . ' in session' );
			}
		}

		// Recalculate totals to invoke shipping rates filter
		if ( WC()->cart ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WC LPC - Recalculating cart totals via handle_store_api_update' );
			}
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
		// Always log that the filter was called (if WP_DEBUG is on)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC Cart - ============================================' );
			error_log( 'WC LPC Cart - modify_pickup_rates_for_blocks() CALLED' );
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : 'unknown';
            $doing_ajax  = ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ? 'yes' : 'no';
            $in_admin    = is_admin() ? 'yes' : 'no';
            error_log( 'WC LPC Cart - Context: REQUEST_URI=' . $request_uri . ', is_admin=' . $in_admin . ', DOING_AJAX=' . $doing_ajax );
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

	/**
	 * Modify shipping rate cost (fallback filter)
	 *
	 * This is a fallback filter that runs on individual rate costs to ensure
	 * our overrides are applied even if other filters modify rates.
	 *
	 * @param float  $cost  Current shipping rate cost.
	 * @param object $rate  Shipping rate object.
	 * @return float Modified cost.
	 */
	public function modify_shipping_rate_cost( $cost, $rate ) {
		// Only process pickup_location methods
		if ( ! is_object( $rate ) || ! isset( $rate->method_id ) || 'pickup_location' !== $rate->method_id ) {
			return $cost;
		}

		// Get saved location costs
		$location_costs = get_option( 'wc_lpc_location_costs', array() );
		if ( empty( $location_costs ) ) {
			return $cost;
		}

		// Extract location index from rate ID (format: pickup_location:0, pickup_location:1, etc.)
		$rate_id = isset( $rate->id ) ? $rate->id : '';
		$rate_id_parts = explode( ':', $rate_id );
		
		if ( count( $rate_id_parts ) < 2 || ! is_numeric( $rate_id_parts[1] ) ) {
			return $cost;
		}

		$location_index = intval( $rate_id_parts[1] );

		// Check if we have a custom cost for this location
		if ( ! isset( $location_costs[ $location_index ] ) || '' === $location_costs[ $location_index ] ) {
			return $cost;
		}

		// Apply the custom cost
		$custom_cost = floatval( $location_costs[ $location_index ] );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WC LPC Cost - modify_shipping_rate_cost applied: index=' . $location_index . ', cost=' . $custom_cost . ' (was ' . $cost . ')' );
		}

		return $custom_cost;
	}
}
