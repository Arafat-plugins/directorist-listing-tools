<?php
/**
 * Plugin Name: Directorist Listing Tools
 * Plugin URI: https://github.com/arafat-plugins/directorist-listing-tools
 * Description: Listing management tools for Directorist.
 * Version: 2.2.5
 * Author: Arafat
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: directorist-listing-tools
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: directorist
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin version.
 */
define( 'DLT_VERSION', '2.2.5' );

/**
 * Plugin directory path.
 */
define( 'DLT_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'DLT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'DLT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function dlt_activate() {
	// Check if Directorist is active.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	if ( ! is_plugin_active( 'directorist/directorist-base.php' ) && ! class_exists( 'Directorist_Base' ) && ! defined( 'ATBDP_VERSION' ) ) {
		deactivate_plugins( DLT_BASENAME );
		wp_die(
			esc_html__( 'Directorist is required.', 'directorist-listing-tools' ),
			esc_html__( 'Activation Error', 'directorist-listing-tools' ),
			array( 'back_link' => true )
		);
	}
}

/**
 * The code that runs during plugin deactivation.
 */
function dlt_deactivate() {
	// Cleanup if needed.
}

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'dlt_activate' );
register_deactivation_hook( __FILE__, 'dlt_deactivate' );

/**
 * Begins execution of the plugin.
 */
function dlt_run() {
	// Load helpers first to get the check function.
	require_once DLT_DIR . 'includes/helpers.php';
	
	// Check if Directorist is active.
	if ( ! dlt_is_directorist_active() ) {
		add_action( 'admin_notices', 'dlt_directorist_missing_notice' );
		return;
	}

	// Load the plugin.
	require_once DLT_DIR . 'includes/class-plugin-loader.php';
	Directorist_Listing_Tools_Loader::get_instance();
}

/**
 * Display admin notice if Directorist is not active.
 */
function dlt_directorist_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Directorist Listing Tools', 'directorist-listing-tools' ); ?>:</strong>
			<?php esc_html_e( 'Directorist is required.', 'directorist-listing-tools' ); ?>
		</p>
	</div>
	<?php
}

// Run the plugin after plugins are loaded.
add_action( 'plugins_loaded', 'dlt_run', 20 );

