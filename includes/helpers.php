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
	return current_user_can( 'manage_options' );
}

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

