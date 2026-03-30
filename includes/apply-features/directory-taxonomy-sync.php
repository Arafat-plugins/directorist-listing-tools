<?php
/**
 * Directory column: keep atbdp_listing_types taxonomy aligned with _directory_type meta.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap hooks (call once).
 */
function dlt_af_bootstrap_directory_taxonomy_sync() {
	if ( defined( 'DLT_AF_DIRECTORY_TAXONOMY_SYNC_BOOTSTRAPPED' ) ) {
		return;
	}
	define( 'DLT_AF_DIRECTORY_TAXONOMY_SYNC_BOOTSTRAPPED', true );

	add_action( 'load-edit.php', 'dlt_af_directory_register_list_table_hooks' );
	add_action( 'save_post_at_biz_dir', 'dlt_af_directory_queue_sync', 999, 1 );
}

/**
 * @return string
 */
function dlt_af_directory_listing_post_type() {
	return defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
}

/**
 * @param int $post_id Post ID.
 */
function dlt_af_directory_render_column_from_meta( $post_id ) {
	$post_id = (int) $post_id;

	$directory_id = (int) get_post_meta( $post_id, '_directory_type', true );

	if ( $directory_id && function_exists( 'directorist_is_directory' ) && directorist_is_directory( $directory_id ) ) {
		$term = get_term( $directory_id );
		if ( $term && ! is_wp_error( $term ) ) {
			printf( '<span>%s</span>', esc_html( $term->name ) );
			return;
		}
	}

	if ( function_exists( 'directorist_get_object_terms' ) ) {
		$taxonomy = defined( 'ATBDP_TYPE' ) ? ATBDP_TYPE : 'atbdp_listing_types';
		$terms    = directorist_get_object_terms( $post_id, $taxonomy );
		if ( is_array( $terms ) && count( $terms ) > 0 ) {
			$term = current( $terms );
			printf( '<span>%s</span>', esc_html( $term->name ) );
		}
	}
}

/**
 * @param string $column_name Column key.
 * @param int    $post_id     Post ID.
 */
function dlt_af_directory_column_ob_start( $column_name, $post_id ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	if ( 'directory_type' !== $column_name ) {
		return;
	}
	ob_start();
}

/**
 * @param string $column_name Column key.
 * @param int    $post_id     Post ID.
 */
function dlt_af_directory_column_replace( $column_name, $post_id ) {
	if ( 'directory_type' !== $column_name ) {
		return;
	}
	ob_end_clean();
	dlt_af_directory_render_column_from_meta( $post_id );
}

/**
 * Only on All Listings screen.
 */
function dlt_af_directory_register_list_table_hooks() {
	if ( empty( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$pt = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $pt !== dlt_af_directory_listing_post_type() ) {
		return;
	}

	$hook = 'manage_' . dlt_af_directory_listing_post_type() . '_posts_custom_column';
	add_action( $hook, 'dlt_af_directory_column_ob_start', 1, 2 );
	add_action( $hook, 'dlt_af_directory_column_replace', 15, 2 );
}

/**
 * @param int $post_id Post ID.
 */
function dlt_af_directory_sync_taxonomy( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( get_post_type( $post_id ) !== dlt_af_directory_listing_post_type() ) {
		return;
	}

	if ( ! function_exists( 'directorist_set_listing_directory' )
		|| ! function_exists( 'directorist_is_directory' )
		|| ! function_exists( 'directorist_is_listing_post_type' ) ) {
		return;
	}

	if ( ! directorist_is_listing_post_type( $post_id ) ) {
		return;
	}

	$directory_id = (int) get_post_meta( $post_id, '_directory_type', true );
	if ( ! $directory_id || ! directorist_is_directory( $directory_id ) ) {
		return;
	}

	$taxonomy = defined( 'ATBDP_DIRECTORY_TYPE' ) ? ATBDP_DIRECTORY_TYPE : 'atbdp_listing_types';

	$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $term_ids ) ) {
		return;
	}

	$term_ids = array_map( 'intval', (array) $term_ids );
	sort( $term_ids );

	$expected = array( $directory_id );
	sort( $expected );

	if ( $term_ids === $expected ) {
		return;
	}

	directorist_set_listing_directory( $post_id, $directory_id );
}

/**
 * @param int $post_id Post ID.
 */
function dlt_af_directory_queue_sync( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	global $dlt_af_directory_sync_ids;
	if ( ! is_array( $dlt_af_directory_sync_ids ) ) {
		$dlt_af_directory_sync_ids = array();
		add_action( 'shutdown', 'dlt_af_directory_run_queued_sync', 999 );
	}
	$dlt_af_directory_sync_ids[] = (int) $post_id;
}

/**
 * Run queued directory sync once per request.
 */
function dlt_af_directory_run_queued_sync() {
	global $dlt_af_directory_sync_ids;
	if ( empty( $dlt_af_directory_sync_ids ) || ! is_array( $dlt_af_directory_sync_ids ) ) {
		return;
	}
	$ids = array_unique( array_filter( array_map( 'intval', $dlt_af_directory_sync_ids ) ) );
	foreach ( $ids as $post_id ) {
		dlt_af_directory_sync_taxonomy( $post_id );
	}
}
