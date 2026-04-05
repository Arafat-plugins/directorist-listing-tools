<?php
/**
 * Directorist admin menu / Listings screen visibility fix.
 *
 * Mirrors the must-use plugin pattern: when the listing CPT was registered with
 * show_ui=false because the administrator role lacked edit_others_at_biz_dirs
 * (and related caps), the Listings menu disappears. This repairs caps and forces
 * show_ui for users who should see Directorist in wp-admin.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directorist listing caps required for CPT admin UI and full admin access.
 *
 * @return string[]
 */
function dlt_listing_tools_directorist_caps_for_administrator() {
	return array(
		'edit_at_biz_dir',
		'read_at_biz_dir',
		'delete_at_biz_dir',
		'edit_at_biz_dirs',
		'edit_others_at_biz_dirs',
		'publish_at_biz_dirs',
		'read_private_at_biz_dirs',
		'delete_at_biz_dirs',
		'delete_private_at_biz_dirs',
		'delete_published_at_biz_dirs',
		'delete_others_at_biz_dirs',
		'edit_private_at_biz_dirs',
		'edit_published_at_biz_dirs',
		'manage_atbdp_options',
	);
}

/**
 * If the administrator role is missing Directorist listing caps, add them (idempotent add_cap).
 *
 * @return void
 */
function dlt_listing_tools_repair_administrator_directorist_caps() {
	/**
	 * Allow disabling cap repair (same filter name as the MU plugin for drop-in compatibility).
	 *
	 * @param bool $run Whether to run cap repair.
	 */
	if ( ! is_admin() || ! apply_filters( 'dlt_directorist_repair_admin_caps', true ) ) {
		return;
	}

	$role = get_role( 'administrator' );
	if ( ! $role ) {
		return;
	}

	if ( $role->has_cap( 'edit_others_at_biz_dirs' ) ) {
		return;
	}

	foreach ( dlt_listing_tools_directorist_caps_for_administrator() as $cap ) {
		$role->add_cap( $cap );
	}
}
add_action( 'admin_init', 'dlt_listing_tools_repair_administrator_directorist_caps', 1 );

/**
 * Ensure show_ui is true for users who should see Directorist in wp-admin.
 *
 * @param array  $args      Post type args.
 * @param string $post_type Post type name.
 * @return array
 */
function dlt_listing_tools_fix_directorist_listing_post_type_show_ui( $args, $post_type ) {
	if ( 'at_biz_dir' !== $post_type || ! is_array( $args ) ) {
		return $args;
	}

	if ( ! is_user_logged_in() ) {
		return $args;
	}

	if (
		current_user_can( 'manage_options' )
		|| current_user_can( 'manage_atbdp_options' )
		|| current_user_can( 'edit_others_at_biz_dirs' )
	) {
		$args['show_ui'] = true;
	}

	return $args;
}
add_filter( 'register_post_type_args', 'dlt_listing_tools_fix_directorist_listing_post_type_show_ui', 20, 2 );
