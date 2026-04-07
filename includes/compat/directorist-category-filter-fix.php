<?php
/**
 * Category filter fix — corrects directory_type meta query on category pages.
 *
 * When a visitor clicks a category link (e.g. "شاشات"), Directorist's
 * get_current_listing_type() defaults to the first directory type in the
 * system (e.g. "Business") instead of detecting which directory type the
 * category actually belongs to. This causes 0 results.
 *
 * This fix detects category archive pages and either:
 * - Sets the directory_type to the category's actual directory type, or
 * - Removes the directory_type constraint when no explicit tab is selected.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fix directory_type meta query on category archive pages.
 *
 * @param array $meta_queries Current meta query array.
 * @return array
 */
function dlt_fix_category_filter_directory_type( $meta_queries ) {
	if ( ! isset( $meta_queries['directory_type'] ) ) {
		return $meta_queries;
	}

	// If the user explicitly clicked a directory type tab, respect that choice.
	if ( ! empty( $_GET['directory_type'] ) || ! empty( $_REQUEST['directory_type'] ) ) {
		return $meta_queries;
	}

	// Detect category context from multiple sources.
	$category_slug = '';

	// 1. Taxonomy archive page (URL like /category/شاشات/).
	if ( function_exists( 'is_tax' ) && defined( 'ATBDP_CATEGORY' ) && is_tax( ATBDP_CATEGORY ) ) {
		$queried = get_queried_object();
		if ( $queried && ! empty( $queried->slug ) ) {
			$category_slug = $queried->slug;
		}
	}

	// 2. GET parameter (?category=TV).
	if ( empty( $category_slug ) && ! empty( $_GET['category'] ) ) {
		$category_slug = sanitize_text_field( wp_unslash( $_GET['category'] ) );
	}

	// 3. Query var (pretty permalink rewrite).
	if ( empty( $category_slug ) ) {
		$qv = get_query_var( 'atbdp_category' );
		if ( ! empty( $qv ) ) {
			$category_slug = sanitize_text_field( $qv );
		}
	}

	// 4. in_cat request parameter (search form submission).
	if ( empty( $category_slug ) && ! empty( $_REQUEST['in_cat'] ) ) {
		$category_slug = '__by_id__';
	}

	if ( empty( $category_slug ) ) {
		return $meta_queries;
	}

	// Try to find the category's assigned directory type(s).
	$cat_term = null;
	if ( '__by_id__' === $category_slug ) {
		$cat_ids  = wp_parse_id_list( wp_unslash( $_REQUEST['in_cat'] ) );
		$cat_term = ! empty( $cat_ids ) ? get_term( $cat_ids[0] ) : null;
	} else {
		$taxonomy = defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';
		$cat_term = get_term_by( 'slug', $category_slug, $taxonomy );
	}

	if ( $cat_term && ! is_wp_error( $cat_term ) ) {
		// Get directory types assigned to this category.
		$dir_types = get_term_meta( $cat_term->term_id, '_directory_type', true );

		if ( ! empty( $dir_types ) && is_array( $dir_types ) ) {
			// Set meta query to match ANY of the category's directory types.
			$meta_queries['directory_type'] = array(
				'key'     => '_directory_type',
				'value'   => array_map( 'absint', $dir_types ),
				'compare' => 'IN',
			);
			return $meta_queries;
		}
	}

	// Fallback: if we can't determine the directory type, remove the constraint
	// so all listings in this category are shown.
	unset( $meta_queries['directory_type'] );

	return $meta_queries;
}
add_filter( 'atbdp_search_listings_meta_queries', 'dlt_fix_category_filter_directory_type' );
