<?php
/**
 * Plugin Name: WooCommerce Local Pickup Costs
 * Plugin URI: https://github.com/your-username/woocommerce-local-pickup-costs
 * Description: Add customizable costs for individual local pickup locations and enable URL-based pre-selection.
 * Version: 1.2.0
 * Author: Abundant Designs
 * Author URI: https://abundantdesigns.com
 * Text Domain: woocommerce-local-pickup-costs
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants
define( 'WC_LPC_VERSION', '1.2.0' );
define( 'WC_LPC_PLUGIN_FILE', __FILE__ );
define( 'WC_LPC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_LPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_LPC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Check if WooCommerce is active
 */
function wc_lpc_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_lpc_missing_woocommerce_notice' );
		return false;
	}
	return true;
}

/**
 * Display admin notice if WooCommerce is missing
 */
function wc_lpc_missing_woocommerce_notice() {
	?>
	<div class="error">
		<p>
			<strong><?php esc_html_e( 'WooCommerce Local Pickup Costs', 'woocommerce-local-pickup-costs' ); ?></strong>
			<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'woocommerce-local-pickup-costs' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin
 */
function wc_lpc_init() {
	if ( ! wc_lpc_check_woocommerce() ) {
		return;
	}

	// Load the main plugin class
	require_once WC_LPC_PLUGIN_PATH . 'includes/class-wc-local-pickup-costs.php';
	
	// Initialize the plugin
	WC_Local_Pickup_Costs::instance();
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action( 'plugins_loaded', 'wc_lpc_init', 10 );

