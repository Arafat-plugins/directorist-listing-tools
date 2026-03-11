<?php
/**
 * Helper Functions
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if current user has required capability.
 *
 * @return bool
 */
function dlt_current_user_can() {
	// Core capability for full admin control.
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	// Optional, plugin-specific capability that can be granted to non-admin roles
	// via the "User Roles" tab in the Listing Tools settings.
	return current_user_can( 'dlt_manage_listing_tools' );
}

/**
 * Get the capabilities that Listing Tools can manage per role.
 *
 * The keys map to actual WP capabilities that will be added to roles.
 *
 * @return array
 */
function dlt_get_role_capabilities_schema() {
	return array(
		'upload_files'            => array(
			'label'       => __( 'Can upload media for listings', 'directorist-listing-tools' ),
			'description' => __( 'Allow this role to upload images and files when creating or editing listings on the front end.', 'directorist-listing-tools' ),
		),
		'dlt_manage_listing_tools' => array(
			'label'       => __( 'Can access Listing Tools admin pages', 'directorist-listing-tools' ),
			'description' => __( 'Allow this role to access the Directorist Listing Tools pages in the WordPress admin (bulk tools, display settings, etc.).', 'directorist-listing-tools' ),
		),
	);
}

/**
 * Get saved role capability settings.
 *
 * Stored as option:
 *  dlt_role_settings = [ role_slug => [ cap_key => bool ] ].
 *
 * @return array
 */
function dlt_get_role_settings() {
	$settings = get_option( 'dlt_role_settings', array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return $settings;
}

/**
 * Apply saved role settings to WordPress roles.
 *
 * We only ever ADD capabilities; we never remove existing caps to avoid
 * interfering with other plugins or core defaults.
 *
 * This runs on every request (hooked to init) so changes applied via AJAX
 * take effect immediately.
 */
function dlt_apply_role_settings() {
	$schema   = dlt_get_role_capabilities_schema();
	$settings = dlt_get_role_settings();

	if ( empty( $schema ) ) {
		return;
	}

	if ( ! function_exists( 'get_editable_roles' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	$roles = get_editable_roles();

	foreach ( $roles as $role_slug => $role_data ) {
		$role_obj = get_role( $role_slug );

		if ( ! $role_obj ) {
			continue;
		}

		// Administrators should always have all capabilities; no UI override.
		$is_admin_role = ( 'administrator' === $role_slug );

		foreach ( $schema as $cap_key => $meta ) {
			// If the role already has the capability, leave it alone.
			if ( $role_obj->has_cap( $cap_key ) ) {
				continue;
			}

			$should_add = false;

			if ( $is_admin_role ) {
				// Admins always get full control.
				$should_add = true;
			} elseif ( ! empty( $settings[ $role_slug ][ $cap_key ] ) ) {
				// For non-admin roles, only add when explicitly enabled in settings.
				$should_add = true;
			}

			if ( $should_add ) {
				$role_obj->add_cap( $cap_key );
			}
		}
	}
}

// Apply role settings on init so that capabilities are ready for both admin and frontend.
add_action( 'init', 'dlt_apply_role_settings', 20 );

/**
 * Sanitize listing IDs from comma-separated string.
 *
 * @param string $ids_string Comma-separated IDs.
 * @return array Array of sanitized integer IDs.
 */
function dlt_sanitize_listing_ids( $ids_string ) {
	if ( empty( $ids_string ) ) {
		return array();
	}

	$ids = explode( ',', $ids_string );
	$ids = array_map( 'trim', $ids );
	$ids = array_map( 'absint', $ids );
	$ids = array_filter( $ids );

	return array_unique( $ids );
}

/**
 * Verify nonce.
 *
 * @param string $nonce Nonce value.
 * @param string $action Nonce action.
 * @return bool
 */
function dlt_verify_nonce( $nonce, $action ) {
	return wp_verify_nonce( $nonce, $action );
}

/**
 * Get Directorist post type.
 *
 * @return string
 */
function dlt_get_post_type() {
	return defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
}

/**
 * Get listing types taxonomy.
 *
 * @return string
 */
function dlt_get_listing_types_taxonomy() {
	return defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';
}

/**
 * Render top-level Listing Tools settings tabs (Bulk, Pending, Types, Locations, Display, Plans).
 *
 * This appears above each tools page so they feel like a single tabbed interface.
 */
function dlt_render_main_settings_tabs() {
	$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	$parent_url = admin_url( 'edit.php?post_type=' . dlt_get_post_type() );

	$tabs = array(
		'directorist-listing-tools-display-settings' => __( 'Display Settings', 'directorist-listing-tools' ),
		'directorist-listing-tools-bulk-delete'      => __( 'Bulk Delete', 'directorist-listing-tools' ),
		'directorist-listing-tools-pending'          => __( 'Pending Manager', 'directorist-listing-tools' ),
		'directorist-listing-tools-type-manager'     => __( 'Type Manager', 'directorist-listing-tools' ),
		'directorist-listing-tools-location-manager' => __( 'Location Manager', 'directorist-listing-tools' ),
		'directorist-listing-tools-plan-manager'     => __( 'Plan Prices', 'directorist-listing-tools' ),
	);

	?>
	<div class="wrap dlt-main-settings-wrap">
		<h1 class="wp-heading-inline">
			<span class="dashicons dashicons-admin-generic" style="font-size:24px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
			<?php esc_html_e( 'Listing Settings', 'directorist-listing-tools' ); ?>
		</h1>
		<h2 class="nav-tab-wrapper dlt-main-tab-nav" style="margin-top:16px;">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<?php
				$url      = add_query_arg( array( 'page' => $slug ), $parent_url );
				$is_active = ( $slug === $current_page );
				?>
				<a href="<?php echo esc_url( $url ); ?>"
				   class="nav-tab <?php echo $is_active ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</h2>
	</div>
	<?php
}

/**
 * Check if Directorist is active.
 *
 * @return bool
 */
function dlt_is_directorist_active() {
	// Check if plugin file is active.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	if ( is_plugin_active( 'directorist/directorist-base.php' ) ) {
		return true;
	}
	
	// Fallback: check for class or constant.
	if ( class_exists( 'Directorist_Base' ) || defined( 'ATBDP_VERSION' ) ) {
		return true;
	}
	
	return false;
}

/**
 * Format admin notice HTML.
 *
 * @param string $message Message text.
 * @param string $type Notice type (success, error, warning, info).
 * @return string
 */
function dlt_format_notice( $message, $type = 'info' ) {
	$class = 'notice notice-' . esc_attr( $type );
	return '<div class="' . esc_attr( $class ) . '"><p>' . wp_kses_post( $message ) . '</p></div>';
}

