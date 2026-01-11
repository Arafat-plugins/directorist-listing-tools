<?php
/**
 * Admin Menu Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu handler class.
 */
class Directorist_Listing_Tools_Admin_Menu {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Admin_Menu
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu items.
	 */
	public function add_admin_menu() {
		// Check if Directorist menu exists, if not create parent.
		$parent_slug = 'edit.php?post_type=' . dlt_get_post_type();

		// Bulk Delete.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Bulk Delete', 'directorist-listing-tools' ),
			esc_html__( 'Listing Tools Bulk Delete', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-bulk-delete',
			array( $this, 'render_bulk_delete_page' )
		);

		// Pending Manager.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Pending Manager', 'directorist-listing-tools' ),
			esc_html__( 'Listing Tools Pending', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-pending',
			array( $this, 'render_pending_manager_page' )
		);

		// Type Manager.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Type Manager', 'directorist-listing-tools' ),
			esc_html__( 'Listing Tools Type Manager', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-type-manager',
			array( $this, 'render_type_manager_page' )
		);

		// Location Manager.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Location Manager', 'directorist-listing-tools' ),
			esc_html__( 'Listing Tools Location Manager', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-location-manager',
			array( $this, 'render_location_manager_page' )
		);
	}

	/**
	 * Render bulk delete page.
	 */
	public function render_bulk_delete_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$bulk_delete = Directorist_Listing_Tools_Bulk_Delete::get_instance();
		$bulk_delete->render_page();
	}

	/**
	 * Render pending manager page.
	 */
	public function render_pending_manager_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$pending_manager = Directorist_Listing_Tools_Pending_Manager::get_instance();
		$pending_manager->render_page();
	}

	/**
	 * Render type manager page.
	 */
	public function render_type_manager_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$type_manager = Directorist_Listing_Tools_Type_Manager::get_instance();
		$type_manager->render_page();
	}

	/**
	 * Render location manager page.
	 */
	public function render_location_manager_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$location_manager = Directorist_Listing_Tools_Location_Manager::get_instance();
		$location_manager->render_page();
	}
}

