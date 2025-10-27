<?php
/**
 * Main plugin class
 *
 * @package WC_Local_Pickup_Costs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Local_Pickup_Costs class
 */
class WC_Local_Pickup_Costs {

	/**
	 * Plugin instance
	 *
	 * @var WC_Local_Pickup_Costs
	 */
	private static $instance = null;

	/**
	 * Admin instance
	 *
	 * @var WC_LPC_Admin_Settings
	 */
	public $admin;

	/**
	 * Cost handler instance
	 *
	 * @var WC_LPC_Cost_Handler
	 */
	public $cost_handler;

	/**
	 * Checkout handler instance
	 *
	 * @var WC_LPC_Checkout_Handler
	 */
	public $checkout_handler;

	/**
	 * Get instance
	 *
	 * @return WC_Local_Pickup_Costs
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
		$this->includes();
		$this->init();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Include required files
	 */
	private function includes() {
		// Admin
		if ( is_admin() ) {
			require_once WC_LPC_PLUGIN_PATH . 'includes/admin/class-admin-settings.php';
			$this->admin = WC_LPC_Admin_Settings::instance();
		}

		// Cost handler (frontend)
		require_once WC_LPC_PLUGIN_PATH . 'includes/class-cost-handler.php';
		$this->cost_handler = WC_LPC_Cost_Handler::instance();

		// Checkout handler (frontend)
		require_once WC_LPC_PLUGIN_PATH . 'includes/frontend/class-checkout-handler.php';
		$this->checkout_handler = WC_LPC_Checkout_Handler::instance();
	}

	/**
	 * Initialize
	 */
	private function init() {
		// Plugin initialization code goes here
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woocommerce-local-pickup-costs', false, dirname( WC_LPC_PLUGIN_BASENAME ) . '/languages' );
	}

}

