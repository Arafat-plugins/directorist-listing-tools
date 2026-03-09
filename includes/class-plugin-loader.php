<?php
/**
 * Plugin Loader Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin loader class.
 */
class Directorist_Listing_Tools_Loader {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Loader
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function load_dependencies() {
		require_once DLT_DIR . 'includes/helpers.php';
		require_once DLT_DIR . 'includes/class-bulk-delete.php';
		require_once DLT_DIR . 'includes/class-pending-manager.php';
		require_once DLT_DIR . 'includes/class-type-manager.php';
		require_once DLT_DIR . 'includes/class-location-manager.php';
		require_once DLT_DIR . 'includes/class-display-settings.php';
		require_once DLT_DIR . 'includes/class-plan-manager.php';
		require_once DLT_DIR . 'includes/class-admin-menu.php';

		// Instantiate classes early to register hooks.
		Directorist_Listing_Tools_Bulk_Delete::get_instance();
		Directorist_Listing_Tools_Pending_Manager::get_instance();
		Directorist_Listing_Tools_Type_Manager::get_instance();
		Directorist_Listing_Tools_Location_Manager::get_instance();
		Directorist_Listing_Tools_Display_Settings::get_instance();
		Directorist_Listing_Tools_Plan_Manager::get_instance();
		Directorist_Listing_Tools_Admin_Menu::get_instance();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages.
		if ( strpos( $hook, 'directorist-listing-tools' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'dlt-admin-style',
			DLT_URL . 'assets/admin.css',
			array(),
			DLT_VERSION
		);

		wp_enqueue_script(
			'dlt-admin-script',
			DLT_URL . 'assets/admin.js',
			array( 'jquery' ),
			DLT_VERSION,
			true
		);

		wp_localize_script(
			'dlt-admin-script',
			'dltAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dlt_admin_nonce' ),
			)
		);
	}
}

