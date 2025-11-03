<?php
/**
 * Checkout Handler Class
 *
 * Handles URL parameter-based pre-selection of pickup locations
 *
 * @package WC_Local_Pickup_Costs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_LPC_Checkout_Handler class
 */
class WC_LPC_Checkout_Handler {

	/**
	 * Instance
	 *
	 * @var WC_LPC_Checkout_Handler
	 */
	private static $instance = null;

	/**
	 * Selected location ID
	 *
	 * @var string
	 */
	private $selected_location = '';

	/**
	 * Get instance
	 *
	 * @return WC_LPC_Checkout_Handler
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
		add_action( 'wp', array( $this, 'check_url_parameter' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_blocks_script' ) );
	}

	/**
	 * Check URL parameter for pickup location
	 */
	public function check_url_parameter() {
		// Only process on checkout page
		if ( ! is_checkout() ) {
			return;
		}

		// Check for pickup_location parameter
		if ( isset( $_GET['pickup_location'] ) ) {
			$this->selected_location = sanitize_text_field( wp_unslash( $_GET['pickup_location'] ) );

			// Validate that the location exists
			if ( $this->is_valid_location( $this->selected_location ) ) {
				// Store in session or transient for JavaScript to pick up
				WC()->session->set( 'wc_lpc_selected_location', $this->selected_location );
			}
		}
	}

	/**
	 * Validate that the location ID exists and is enabled
	 *
	 * @param string $location_id Location ID to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_location( $location_id ) {
		$locations = $this->get_local_pickup_locations();

		foreach ( $locations as $location ) {
			if ( (string) $location['index'] === (string) $location_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all local pickup locations
	 *
	 * @return array Array of pickup locations.
	 */
	private function get_local_pickup_locations() {
		$locations = array();

		// Get pickup locations from the WordPress option
		$pickup_locations_data = get_option( 'pickup_location_pickup_locations', array() );

		if ( is_array( $pickup_locations_data ) && ! empty( $pickup_locations_data ) ) {
			foreach ( $pickup_locations_data as $index => $location_data ) {
				// Only include enabled locations
				if ( isset( $location_data['enabled'] ) && $location_data['enabled'] ) {
					$location_name = isset( $location_data['name'] ) ? $location_data['name'] : sprintf( __( 'Location %s', 'woocommerce-local-pickup-costs' ), $index );
					
					$locations[] = array(
						'location_id' => $index,
						'index'       => $index,
						'title'       => $location_name,
					);
				}
			}
		}

		return $locations;
	}

	/**
	 * Enqueue scripts for checkout
	 */
	public function enqueue_scripts() {
		// Only enqueue on checkout page
		if ( ! is_checkout() ) {
			return;
		}

		$selected_location = WC()->session->get( 'wc_lpc_selected_location' );

		if ( $selected_location ) {
			// Enqueue checkout script
			wp_enqueue_script(
				'wc-lpc-checkout',
				WC_LPC_PLUGIN_URL . 'assets/js/checkout-preselect.js',
				array( 'jquery', 'woocommerce', 'wc-checkout' ),
				WC_LPC_VERSION,
				true
			);

			// Localize script with selected location
			wp_localize_script(
				'wc-lpc-checkout',
				'wc_lpc_data',
				array(
					'selected_location' => $selected_location,
				)
			);

			// Clear session after passing to JavaScript
			WC()->session->__unset( 'wc_lpc_selected_location' );
		}
	}

	/**
	 * Enqueue Blocks integration script to trigger Store API cart updates
	 */
	public function enqueue_blocks_script() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'wc-lpc-blocks',
			WC_LPC_PLUGIN_URL . 'assets/js/lpc-blocks.js',
			array(),
			WC_LPC_VERSION,
			true
		);
	}
}

