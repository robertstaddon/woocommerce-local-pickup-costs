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
		add_action( 'woocommerce_settings_save_shipping', array( $this, 'save_custom_fields' ) );
		add_action( 'woocommerce_admin_field_custom', array( $this, 'output_custom_field' ) );
	}

	/**
	 * Output custom field
	 *
	 * @param array $value Field value.
	 */
	public function output_custom_field( $value ) {
		// Check if this is our locations table field
		if ( 'wc_lpc_locations_table' === $value['id'] ) {
			$this->render_location_costs_table();
		}
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

		// Start with title
		$settings = array(
			array(
				'title' => __( 'Local Pickup Location Costs', 'woocommerce-local-pickup-costs' ),
				'type'  => 'title',
				'desc'  => __( 'Set custom costs for each local pickup location. Leave blank to use the global cost from Local Pickup settings.', 'woocommerce-local-pickup-costs' ),
				'id'    => 'wc_lpc_title',
			),
		);

		if ( empty( $pickup_locations ) ) {
			$settings[] = array(
				'title' => '',
				'desc'  => __( 'No local pickup locations found. Please add a local pickup shipping method first.', 'woocommerce-local-pickup-costs' ),
				'type'  => 'title',
				'id'    => 'wc_lpc_no_locations',
			);
		} else {
			// Single custom field that renders the entire table
			$settings[] = array(
				'title' => '',
				'id'    => 'wc_lpc_locations_table',
				'type'  => 'custom',
			);
		}

		// End section
		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'wc_lpc_end',
		);

		return $settings;
	}

	/**
	 * Render locations costs table
	 */
	private function render_location_costs_table() {
		$pickup_locations = $this->get_local_pickup_locations();
		$location_costs   = get_option( 'wc_lpc_location_costs', array() );

		if ( empty( $pickup_locations ) ) {
			return;
		}

		?>
		<tr valign="top">
			<td class="forminp" colspan="2">
				<table class="wc_shipping widefat wp-list-table" cellspacing="0">
					<thead>
						<tr>
							<th class="sort" width="1%"></th>
							<th class="name"><?php esc_html_e( 'Location Name', 'woocommerce-local-pickup-costs' ); ?></th>
							<th class="address"><?php esc_html_e( 'Address', 'woocommerce-local-pickup-costs' ); ?></th>
							<th class="status"><?php esc_html_e( 'Status', 'woocommerce-local-pickup-costs' ); ?></th>
							<th class="cost"><?php esc_html_e( 'Cost Override', 'woocommerce-local-pickup-costs' ); ?></th>
						</tr>
					</thead>
					<tbody class="ui-sortable">
						<?php
						foreach ( $pickup_locations as $location ) {
							$location_index = $location['index'];
							$location_name   = $location['name'];
							$location_address = $this->format_address( $location['address'] );
							$is_enabled      = isset( $location['enabled'] ) && $location['enabled'];
							$current_cost    = isset( $location_costs[ $location_index ] ) ? $location_costs[ $location_index ] : '';
							?>
							<tr>
								<td class="sort" width="1%"></td>
								<td class="name">
									<strong><?php echo esc_html( $location_name ); ?></strong>
								</td>
								<td class="address">
									<?php echo esc_html( $location_address ); ?>
								</td>
								<td class="status">
									<?php if ( $is_enabled ) : ?>
										<span class="woocommerce-input-toggle woocommerce-input-toggle--enabled" aria-label="<?php esc_attr_e( 'Enabled', 'woocommerce-local-pickup-costs' ); ?>"><?php esc_html_e( 'Enabled', 'woocommerce-local-pickup-costs' ); ?></span>
									<?php else : ?>
										<span class="woocommerce-input-toggle woocommerce-input-toggle--disabled" aria-label="<?php esc_attr_e( 'Disabled', 'woocommerce-local-pickup-costs' ); ?>"><?php esc_html_e( 'Disabled', 'woocommerce-local-pickup-costs' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="cost">
									<input 
										type="text" 
										name="wc_lpc_location_costs[<?php echo esc_attr( $location_index ); ?>]" 
										value="<?php echo esc_attr( $current_cost ); ?>" 
										placeholder="<?php esc_attr_e( 'Use global cost', 'woocommerce-local-pickup-costs' ); ?>"
										class="input-text regular-input wc_input_price"
									/>
									<p class="description"><?php esc_html_e( 'Leave blank to use global cost. Set to 0 for free.', 'woocommerce-local-pickup-costs' ); ?></p>
								</td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php
	}

	/**
	 * Format address from location data array
	 *
	 * @param array $address Address data.
	 * @return string Formatted address string.
	 */
	private function format_address( $address ) {
		if ( ! is_array( $address ) || empty( $address ) ) {
			return __( 'No address', 'woocommerce-local-pickup-costs' );
		}

		$parts = array();

		if ( ! empty( $address['address_1'] ) ) {
			$parts[] = $address['address_1'];
		}

		$city_state = array();
		if ( ! empty( $address['city'] ) ) {
			$city_state[] = $address['city'];
		}
		if ( ! empty( $address['state'] ) ) {
			$city_state[] = $address['state'];
		}
		if ( ! empty( $address['postcode'] ) ) {
			$city_state[] = $address['postcode'];
		}

		if ( ! empty( $city_state ) ) {
			$parts[] = implode( ', ', $city_state );
		}

		if ( ! empty( $address['country'] ) ) {
			$country_name = WC()->countries->countries[ $address['country'] ] ?? $address['country'];
			$parts[] = $country_name;
		}

		return ! empty( $parts ) ? implode( ', ', $parts ) : __( 'No address', 'woocommerce-local-pickup-costs' );
	}

	/**
	 * Get all local pickup locations (including disabled ones)
	 *
	 * @return array Array of pickup locations with full data.
	 */
	private function get_local_pickup_locations() {
		$locations = array();

		// Get pickup locations from the WordPress option
		$pickup_locations_data = get_option( 'pickup_location_pickup_locations', array() );

		if ( is_array( $pickup_locations_data ) && ! empty( $pickup_locations_data ) ) {
			foreach ( $pickup_locations_data as $index => $location_data ) {
				// Include ALL locations (enabled and disabled)
				$location_name = isset( $location_data['name'] ) ? $location_data['name'] : sprintf( __( 'Location %s', 'woocommerce-local-pickup-costs' ), $index );
				
				$locations[] = array(
					'location_id' => $index,
					'index'       => $index,
					'name'        => $location_name,
					'address'     => isset( $location_data['address'] ) ? $location_data['address'] : array(),
					'details'     => isset( $location_data['details'] ) ? $location_data['details'] : '',
					'enabled'     => isset( $location_data['enabled'] ) && $location_data['enabled'],
				);
			}
		}

		return $locations;
	}

	/**
	 * Save settings
	 */
	public function save_settings() {
		// This is kept for backwards compatibility but doesn't do anything
		// The actual saving happens in save_custom_fields
	}

	/**
	 * Save custom fields
	 */
	public function save_custom_fields() {
		// Check capabilities
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Check if this is our section (WooCommerce passes section via GET parameter)
		if ( ! isset( $_GET['section'] ) || 'local_pickup_costs' !== $_GET['section'] ) {
			return;
		}

		// Get the location costs from POST data
		// When form has name="wc_lpc_location_costs[0]", PHP creates $_POST['wc_lpc_location_costs'] as an array
		if ( isset( $_POST['wc_lpc_location_costs'] ) && is_array( $_POST['wc_lpc_location_costs'] ) ) {
			$location_costs = array();

			foreach ( $_POST['wc_lpc_location_costs'] as $index => $cost ) {
				// Sanitize the index
				$index = sanitize_text_field( wp_unslash( $index ) );
				// Sanitize the cost
				$cost = sanitize_text_field( wp_unslash( $cost ) );

				// Allow empty string (to use global cost) or valid numeric value
				if ( $cost === '' ) {
					$location_costs[ $index ] = '';
				} else {
					// Validate it's a valid number
					if ( is_numeric( $cost ) && floatval( $cost ) >= 0 ) {
						$location_costs[ $index ] = $cost;
					}
				}
			}

			// Save the settings
			update_option( 'wc_lpc_location_costs', $location_costs );
		}
	}
}

