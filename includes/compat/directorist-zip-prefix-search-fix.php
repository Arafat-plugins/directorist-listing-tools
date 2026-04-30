<?php
/**
 * Directorist ZIP prefix ordering fix.
 *
 * The Directorist Listings with Map addon searches ZIP codes with a broad
 * `LIKE` comparison. For short ZIP fragments such as "70", that query can
 * return mixed matches like 70173, 57072, and 97070. This compatibility fix
 * keeps those broader results available, but sorts the query so postal codes
 * that start with the entered digits are returned first.
 *
 * Full ZIP searches continue to use Directorist's normal behavior.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a short ZIP prefix from the current request.
 *
 * @return string
 */
function dlt_directorist_get_short_zip_prefix() {
	$zip = '';

	if ( isset( $_POST['zip_code'] ) ) {
		$zip = wp_unslash( $_POST['zip_code'] );
	} elseif ( isset( $_REQUEST['zip_code'] ) ) {
		$zip = wp_unslash( $_REQUEST['zip_code'] );
	} elseif ( isset( $_REQUEST['zip'] ) ) {
		$zip = wp_unslash( $_REQUEST['zip'] );
	}

	$zip = preg_replace( '/\D+/', '', (string) $zip );

	if ( '' === $zip || strlen( $zip ) >= 5 ) {
		return '';
	}

	return $zip;
}

/**
 * Attach a custom marker to listing queries that should prioritize ZIP prefixes.
 *
 * @param array $arguments Listing query arguments.
 *
 * @return array
 */
function dlt_directorist_mark_zip_prefix_order_query( $arguments ) {
	$prefix = dlt_directorist_get_short_zip_prefix();

	if ( '' === $prefix || empty( $arguments['post_type'] ) || ATBDP_POST_TYPE !== $arguments['post_type'] ) {
		return $arguments;
	}

	$arguments['dlt_zip_prefix_order'] = $prefix;

	return $arguments;
}
add_filter( 'atbdp_listing_search_query_argument', 'dlt_directorist_mark_zip_prefix_order_query', 50 );

/**
 * Prioritize ZIPs that start with the requested short prefix.
 *
 * Example for prefix "70":
 * - 70173, 70567, 70734 come first
 * - other broad matches like 57072 or 97070 stay after them
 *
 * @param array    $clauses SQL clauses for WP_Query.
 * @param WP_Query $query   Current query object.
 *
 * @return array
 */
function dlt_directorist_apply_zip_prefix_ordering( $clauses, $query ) {
	$prefix = $query->get( 'dlt_zip_prefix_order' );

	if ( empty( $prefix ) || empty( $query->query_vars['post_type'] ) || ATBDP_POST_TYPE !== $query->query_vars['post_type'] ) {
		return $clauses;
	}

	global $wpdb;

	$alias = 'dlt_zip_prefix_meta';

	if ( false === strpos( $clauses['join'], $alias ) ) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS {$alias} ON ({$wpdb->posts}.ID = {$alias}.post_id AND {$alias}.meta_key = '_zip')";
	}

	$prefix_like      = esc_sql( $wpdb->esc_like( $prefix ) . '%' );
	$existing_orderby = trim( $clauses['orderby'] );
	$priority_orderby = "CASE WHEN {$alias}.meta_value LIKE '{$prefix_like}' THEN 0 ELSE 1 END ASC";
	$zip_orderby      = "CASE WHEN {$alias}.meta_value LIKE '{$prefix_like}' THEN CAST({$alias}.meta_value AS UNSIGNED) END ASC";

	$clauses['orderby'] = $priority_orderby . ', ' . $zip_orderby;

	if ( '' !== $existing_orderby ) {
		$clauses['orderby'] .= ', ' . $existing_orderby;
	}

	return $clauses;
}
add_filter( 'posts_clauses', 'dlt_directorist_apply_zip_prefix_ordering', 20, 2 );
