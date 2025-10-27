<?php
/**
 * Admin Settings Class
 *
 * Handles admin settings page for local pickup costs
 *
 * @package WC_Local_Pickup_Costs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_LPC_Admin_Settings class
 */
class WC_LPC_Admin_Settings {

	/**
	 * Instance
	 *
	 * @var WC_LPC_Admin_Settings
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return WC_LPC_Admin_Settings
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
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_shipping_section' ), 20 );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'get_settings' ), 10, 2 );
		add_action( 'woocommerce_settings_save_shipping', array( $this, 'save_settings' ) );
	}

	/**
	 * Add shipping section tab
	 *
	 * @param array $sections Existing sections.
	 * @return array Modified sections.
	 */
	public function add_shipping_section( $sections ) {
		$sections['local_pickup_costs'] = __( 'Local Pickup Costs', 'woocommerce-local-pickup-costs' );
		return $sections;
	}

	/**
	 * Get settings
	 *
	 * @param array  $settings Existing settings.
	 * @param string $current_section Current section.
	 * @return array Settings array.
	 */
	public function get_settings( $settings, $current_section ) {
		if ( 'local_pickup_costs' === $current_section ) {
			$settings = $this->render_settings_page();
		}
		return $settings;
	}

	/**
	 * Render the settings page
	 *
	 * @return array Settings array.
	 */
	private function render_settings_page() {
		$pickup_locations = $this->get_local_pickup_locations();
		$location_costs   = get_option( 'wc_lpc_location_costs', array() );

		?>
		<div class="wc-lpc-settings">
			<table class="form-table">
				<tr>
					<th colspan="2">
						<h2><?php esc_html_e( 'Local Pickup Location Costs', 'woocommerce-local-pickup-costs' ); ?></h2>
						<p><?php esc_html_e( 'Set custom costs for each local pickup location. Leave blank to use the global cost from Local Pickup settings.', 'woocommerce-local-pickup-costs' ); ?></p>
					</th>
				</tr>
				<?php if ( empty( $pickup_locations ) ) : ?>
					<tr>
						<td colspan="2">
							<p><?php esc_html_e( 'No local pickup locations found. Please add a local pickup shipping method first.', 'woocommerce-local-pickup-costs' ); ?></p>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $pickup_locations as $location ) : ?>
						<?php
						$instance_id = $location['instance_id'];
						$location_id = isset( $location['location_id'] ) ? $location['location_id'] : $instance_id;
						$location_name = $location['title'];
						
						// Use location_id as the key for storing costs
						$cost_key = $location_id;
						$current_cost = isset( $location_costs[ $cost_key ] ) ? $location_costs[ $cost_key ] : '';
						?>
						<tr>
							<th scope="row">
								<label for="wc_lpc_location_cost_<?php echo esc_attr( $cost_key ); ?>">
									<?php echo esc_html( $location_name ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="wc_lpc_location_cost_<?php echo esc_attr( $cost_key ); ?>" 
									name="wc_lpc_location_costs[<?php echo esc_attr( $cost_key ); ?>]" 
									value="<?php echo esc_attr( $current_cost ); ?>" 
									placeholder="<?php esc_attr_e( 'Use global cost', 'woocommerce-local-pickup-costs' ); ?>"
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'Enter a cost (e.g., 5.00) or leave blank to use the global cost. Set to 0 to make it free.', 'woocommerce-local-pickup-costs' ); ?>
								</p>
								<input type="hidden" name="wc_lpc_location_ids[]" value="<?php echo esc_attr( $cost_key ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</table>
			<?php wp_nonce_field( 'wc_lpc_save_settings', 'wc_lpc_settings_nonce' ); ?>
		</div>
		<?php

		// Return empty array since we're outputting HTML
		return array();
	}

	/**
	 * Get all local pickup locations
	 *
	 * @return array Array of pickup locations.
	 */
	private function get_local_pickup_locations() {
		$locations = array();

		// Get all shipping zones
		$zones = WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone_data ) {
			if ( isset( $zone_data['shipping_methods'] ) ) {
				foreach ( $zone_data['shipping_methods'] as $method ) {
					// Check if this is a local pickup method by method_id
					if ( isset( $method->id ) && 'local_pickup' === $method->id ) {
						$instance_id = $method->instance_id;
						$method_title = $method->get_instance_option( 'title' ) ? $method->get_instance_option( 'title' ) : $method->method_title;
						
						// Each instance is a separate location
						$locations[] = array(
							'instance_id' => $instance_id,
							'location_id' => $instance_id,
							'title'       => $method_title,
							'zone_id'     => $zone_data['id'],
						);
					}
				}
			}
		}

		// Also check the "Rest of the World" zone
		$worldwide_zone = WC_Shipping_Zones::get_zone_by( 'zone_id', 0 );

		if ( $worldwide_zone ) {
			$methods = $worldwide_zone->get_shipping_methods();

			foreach ( $methods as $method ) {
				if ( isset( $method->id ) && 'local_pickup' === $method->id ) {
					$instance_id = $method->instance_id;
					$method_title = $method->get_instance_option( 'title' ) ? $method->get_instance_option( 'title' ) : $method->method_title;
					
					// Each instance is a separate location
					$locations[] = array(
						'instance_id' => $instance_id,
						'location_id' => $instance_id,
						'title'       => $method_title,
						'zone_id'     => 0,
					);
				}
			}
		}

		return $locations;
	}

	/**
	 * Save settings
	 */
	public function save_settings() {
		// Check nonce
		if ( ! isset( $_POST['wc_lpc_settings_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wc_lpc_settings_nonce'] ), 'wc_lpc_save_settings' ) ) {
			return;
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Check if this is our section
		if ( isset( $_POST['current_section'] ) && 'local_pickup_costs' !== $_POST['current_section'] ) {
			return;
		}

		// Get and sanitize location costs
		$location_costs = array();

		if ( isset( $_POST['wc_lpc_location_costs'] ) && is_array( $_POST['wc_lpc_location_costs'] ) ) {
			foreach ( $_POST['wc_lpc_location_costs'] as $instance_id => $cost ) {
				$instance_id = sanitize_text_field( wp_unslash( $instance_id ) );
				$cost        = sanitize_text_field( wp_unslash( $cost ) );

				// Allow empty string (to use global cost) or valid numeric value
				if ( $cost === '' ) {
					$location_costs[ $instance_id ] = '';
				} else {
					// Validate it's a valid number
					if ( is_numeric( $cost ) && floatval( $cost ) >= 0 ) {
						$location_costs[ $instance_id ] = $cost;
					}
				}
			}
		}

		// Save the settings
		update_option( 'wc_lpc_location_costs', $location_costs );

		// Add success message
		add_action( 'admin_notices', array( $this, 'settings_saved_notice' ) );
	}

	/**
	 * Display settings saved notice
	 */
	public function settings_saved_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Local pickup costs have been saved.', 'woocommerce-local-pickup-costs' ); ?></p>
		</div>
		<?php
	}
}

