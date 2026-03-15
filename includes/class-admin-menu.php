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

		// Display Settings.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Listing Settings', 'directorist-listing-tools' ),
			esc_html__( 'Listing Settings', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-display-settings',
			array( $this, 'render_display_settings_page' )
		);

		// Plan Price Manager.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'Plan Price Manager', 'directorist-listing-tools' ),
			esc_html__( 'Plan Price Manager', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-plan-manager',
			array( $this, 'render_plan_manager_page' )
		);

		// File Managing.
		add_submenu_page(
			$parent_slug,
			esc_html__( 'File Managing', 'directorist-listing-tools' ),
			esc_html__( 'File Managing', 'directorist-listing-tools' ),
			'manage_options',
			'directorist-listing-tools-file-manager',
			array( $this, 'render_file_manager_page' )
		);

		if ( dlt_is_social_login_active() ) {
			add_submenu_page(
				$parent_slug,
				esc_html__( 'Social Login', 'directorist-listing-tools' ),
				esc_html__( 'Social Login', 'directorist-listing-tools' ),
				'manage_options',
				'directorist-listing-tools-social-login',
				array( $this, 'render_social_login_page' )
			);
		}

		// Hide the individual tools from the Directorist menu so only "Listing Settings"
		// appears; users can navigate between tools using the tab bar rendered on each page.
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-bulk-delete' );
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-pending' );
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-type-manager' );
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-location-manager' );
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-plan-manager' );
		remove_submenu_page( $parent_slug, 'directorist-listing-tools-file-manager' );
		if ( dlt_is_social_login_active() ) {
			remove_submenu_page( $parent_slug, 'directorist-listing-tools-social-login' );
		}
	}

	/**
	 * Render Social Login diagnostics page.
	 */
	public function render_social_login_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		Directorist_Listing_Tools_Social_Login_Diagnostics::get_instance()->render_page();
	}

	/**
	 * Render file manager page.
	 */
	public function render_file_manager_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		$file_manager = Directorist_Listing_Tools_File_Manager::get_instance();
		$file_manager->render_page();
	}

	/**
	 * Render bulk delete page.
	 */
	public function render_bulk_delete_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

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

		dlt_render_main_settings_tabs();

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

		dlt_render_main_settings_tabs();

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

		dlt_render_main_settings_tabs();

		$location_manager = Directorist_Listing_Tools_Location_Manager::get_instance();
		$location_manager->render_page();
	}

	/**
	 * Render plan price manager page.
	 */
	public function render_plan_manager_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		$plan_manager = Directorist_Listing_Tools_Plan_Manager::get_instance();
		$plan_manager->render_page();
	}

	/**
	 * Render display settings page.
	 */
	public function render_display_settings_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		$display_settings = Directorist_Listing_Tools_Display_Settings::get_instance();
		$display_settings->render_page();
	}
}

