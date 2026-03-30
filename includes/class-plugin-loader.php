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
		require_once DLT_DIR . 'includes/class-file-manager.php';
		require_once DLT_DIR . 'includes/class-form-inspector.php';
		require_once DLT_DIR . 'includes/class-apply-functions.php';
		if ( dlt_is_social_login_active() ) {
			require_once DLT_DIR . 'includes/class-social-login-diagnostics.php';
		}

		// Instantiate classes early to register hooks.
		Directorist_Listing_Tools_Apply_Functions::get_instance();
		Directorist_Listing_Tools_Bulk_Delete::get_instance();
		Directorist_Listing_Tools_Pending_Manager::get_instance();
		Directorist_Listing_Tools_Type_Manager::get_instance();
		Directorist_Listing_Tools_Location_Manager::get_instance();
		Directorist_Listing_Tools_Display_Settings::get_instance();
		Directorist_Listing_Tools_Plan_Manager::get_instance();
		Directorist_Listing_Tools_Admin_Menu::get_instance();
		Directorist_Listing_Tools_File_Manager::get_instance();
		Directorist_Listing_Tools_Form_Inspector::get_instance();
		if ( dlt_is_social_login_active() ) {
			Directorist_Listing_Tools_Social_Login_Diagnostics::get_instance();
		}
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
		// Match by hook suffix OR by the ?page= query param — covers all live/local scenarios.
		$our_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$on_our_page = (
			strpos( $hook, 'directorist-listing-tools' ) !== false ||
			strpos( $our_page, 'directorist-listing-tools' ) !== false
		);

		if ( ! $on_our_page ) {
			return;
		}

		// Use file-modification-time as version so any file change auto-busts the cache.
		$css_ver = file_exists( DLT_DIR . 'assets/admin.css' ) ? filemtime( DLT_DIR . 'assets/admin.css' ) : DLT_VERSION;
		$js_ver  = file_exists( DLT_DIR . 'assets/admin.js' )  ? filemtime( DLT_DIR . 'assets/admin.js' )  : DLT_VERSION;

		wp_enqueue_style(
			'dlt-admin-style',
			DLT_URL . 'assets/admin.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'dlt-admin-script',
			DLT_URL . 'assets/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'dlt-admin-script',
			'dltAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'dlt_admin_nonce' ),
				'page'    => $our_page,
			)
		);

		// File Manager page: enqueue its own script and styles.
		if ( $our_page === 'directorist-listing-tools-file-manager' ) {
			$fm_js_ver  = file_exists( DLT_DIR . 'assets/file-manager.js' ) ? filemtime( DLT_DIR . 'assets/file-manager.js' ) : DLT_VERSION;
			$fm_css_ver = file_exists( DLT_DIR . 'assets/file-manager.css' ) ? filemtime( DLT_DIR . 'assets/file-manager.css' ) : DLT_VERSION;

			// Load WordPress code editor (CodeMirror) for safe in-admin editing.
			wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
			wp_enqueue_script( 'code-editor' );
			wp_enqueue_style( 'code-editor' );

			wp_enqueue_style(
				'dlt-file-manager-style',
				DLT_URL . 'assets/file-manager.css',
				array(),
				$fm_css_ver
			);
			wp_enqueue_script(
				'dlt-file-manager-script',
				DLT_URL . 'assets/file-manager.js',
				array( 'jquery', 'code-editor' ),
				$fm_js_ver,
				true
			);

			$editor_settings = wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
			if ( $editor_settings ) {
				wp_add_inline_script(
					'dlt-file-manager-script',
					'window.dltFmEditorSettings = ' . wp_json_encode( $editor_settings ) . ';',
					'before'
				);
			}
		}
	}
}

