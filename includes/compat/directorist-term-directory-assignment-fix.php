<?php
/**
 * Directorist term directory assignment hard fix.
 *
 * Ensures category/location `directory_type[]` multi-select values persist by
 * doing a final canonical write at shutdown.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_TERM_DIRECTORY_ASSIGNMENT_FIX_LOADED' ) ) {
	return;
}
define( 'DLT_TERM_DIRECTORY_ASSIGNMENT_FIX_LOADED', true );

/**
 * Pending writes keyed by term ID.
 *
 * @var array<int,array<int>>
 */
$GLOBALS['dlt_pending_term_directory_writes'] = array();

add_action( 'created_at_biz_dir-category', 'dlt_queue_term_directories', 9999, 1 );
add_action( 'edited_at_biz_dir-category', 'dlt_queue_term_directories', 9999, 1 );
add_action( 'created_at_biz_dir-location', 'dlt_queue_term_directories', 9999, 1 );
add_action( 'edited_at_biz_dir-location', 'dlt_queue_term_directories', 9999, 1 );
add_action( 'shutdown', 'dlt_flush_term_directories', PHP_INT_MAX, 0 );

/**
 * Queue submitted directories for final write.
 *
 * @param int $term_id Term ID.
 */
function dlt_queue_term_directories( $term_id ) {
	if ( ! is_admin() || ! isset( $_POST['directory_type'] ) ) {
		return;
	}

	$term_id = (int) $term_id;
	if ( $term_id <= 0 ) {
		return;
	}

	$raw = wp_unslash( $_POST['directory_type'] );
	$raw = is_array( $raw ) ? $raw : array( $raw );

	$ids = wp_parse_id_list( $raw );
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

	if ( empty( $ids ) ) {
		return;
	}

	$GLOBALS['dlt_pending_term_directory_writes'][ $term_id ] = $ids;
}

/**
 * Perform final canonical write of queued directories.
 */
function dlt_flush_term_directories() {
	if ( empty( $GLOBALS['dlt_pending_term_directory_writes'] ) || ! is_array( $GLOBALS['dlt_pending_term_directory_writes'] ) ) {
		return;
	}

	global $wpdb;

	foreach ( $GLOBALS['dlt_pending_term_directory_writes'] as $term_id => $ids ) {
		$term_id = (int) $term_id;
		$ids     = is_array( $ids ) ? $ids : array();
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( $term_id <= 0 || empty( $ids ) ) {
			continue;
		}

		delete_term_meta( $term_id, '_directory_type' );
		add_term_meta( $term_id, '_directory_type', $ids, true );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key LIKE %s",
				$term_id,
				$wpdb->esc_like( '_directory_type_' ) . '%'
			)
		);

		foreach ( $ids as $directory_id ) {
			update_term_meta( $term_id, '_directory_type_' . $directory_id, true );
		}
	}
}

