<?php
/**
 * Category filter fix — removes directory_type meta query on category pages.
 *
 * When a visitor clicks a category link (e.g. "شاشات"), Directorist builds a
 * query that filters by BOTH the category AND the current _directory_type meta.
 * If the listing belongs to a different directory type, the query returns zero
 * results even though the listing exists in that category.
 *
 * This fix drops the directory_type constraint whenever a category filter is
 * active so all matching listings are shown regardless of directory type.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drop the directory_type meta query when browsing by category.
 *
 * @param array $meta_queries Current meta query array.
 * @return array
 */
function dlt_fix_category_filter_directory_type( $meta_queries ) {
	$has_category = ! empty( $_GET['category'] )
		|| ! empty( $_REQUEST['in_cat'] )
		|| get_query_var( 'atbdp_category' );

	if ( $has_category && isset( $meta_queries['directory_type'] ) ) {
		unset( $meta_queries['directory_type'] );
	}

	return $meta_queries;
}
add_filter( 'atbdp_search_listings_meta_queries', 'dlt_fix_category_filter_directory_type' );
