<?php
/**
 * Category filter fix — corrects directory_type meta query on category pages.
 *
 * When clicking a category, Directorist defaults to the first directory type
 * instead of the one the category belongs to. This causes 0 results.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fix directory_type in the final query args.
 *
 * Uses atbdp_listing_search_query_argument which fires AFTER both
 * tax_query and meta_query are fully built — so we can inspect the
 * category tax_query and correct the directory_type meta_query.
 *
 * @param array $args WP_Query args.
 * @return array
 */
function dlt_fix_category_filter_query_args( $args ) {
	if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
		return $args;
	}

	if ( ! isset( $args['meta_query']['directory_type'] ) ) {
		return $args;
	}

	// Detect category from all possible sources.
	$category_slug = dlt_catfix_detect_category( $args );

	// No category context — nothing to fix.
	if ( empty( $category_slug ) ) {
		return $args;
	}

	// If user explicitly picked a directory tab, respect it.
	if ( ! empty( $_GET['directory_type'] ) || ! empty( $_POST['directory_type'] ) ) {
		return $args;
	}

	// Resolve category term.
	$taxonomy = defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';
	$cat_term = null;

	if ( is_numeric( $category_slug ) ) {
		$cat_term = get_term( absint( $category_slug ), $taxonomy );
	} else {
		$cat_term = get_term_by( 'slug', $category_slug, $taxonomy );
	}

	if ( ! $cat_term || is_wp_error( $cat_term ) ) {
		// Can't resolve category — remove constraint to show all.
		unset( $args['meta_query']['directory_type'] );
		return $args;
	}

	// Get directory types assigned to this category.
	$dir_types = get_term_meta( $cat_term->term_id, '_directory_type', true );

	if ( ! empty( $dir_types ) && is_array( $dir_types ) ) {
		$args['meta_query']['directory_type'] = array(
			'key'     => '_directory_type',
			'value'   => array_map( 'absint', $dir_types ),
			'compare' => 'IN',
		);
	} else {
		// No directory types assigned — remove constraint.
		unset( $args['meta_query']['directory_type'] );
	}

	return $args;
}
add_filter( 'atbdp_listing_search_query_argument', 'dlt_fix_category_filter_query_args', 20 );

/**
 * Detect category slug/ID from every possible source.
 *
 * @param array $args WP_Query args being built.
 * @return string Category slug or ID, or empty string.
 */
function dlt_catfix_detect_category( $args ) {
	$taxonomy = defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';

	// 1. Check tax_query already in the args (most reliable).
	if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
		$slug = dlt_catfix_find_in_tax_query( $args['tax_query'], $taxonomy );
		if ( $slug ) {
			return $slug;
		}
	}

	// 2. Taxonomy archive (is_tax).
	if ( function_exists( 'is_tax' ) && is_tax( $taxonomy ) ) {
		$obj = get_queried_object();
		if ( $obj && ! empty( $obj->slug ) ) {
			return $obj->slug;
		}
	}

	// 3. GET ?category= param.
	if ( ! empty( $_GET['category'] ) ) {
		return sanitize_text_field( wp_unslash( $_GET['category'] ) );
	}

	// 4. Query var from rewrite (e.g. /single-category/tv/).
	$qv = get_query_var( 'atbdp_category' );
	if ( ! empty( $qv ) ) {
		return sanitize_text_field( $qv );
	}

	// 5. URL path: /single-category/{slug}/.
	if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$path = wp_unslash( $_SERVER['REQUEST_URI'] );
		if ( preg_match( '#/single-category/([^/?]+)#i', $path, $m ) ) {
			return sanitize_text_field( urldecode( $m[1] ) );
		}
	}

	// 6. in_cat from search form.
	if ( ! empty( $_REQUEST['in_cat'] ) ) {
		$ids = wp_parse_id_list( wp_unslash( $_REQUEST['in_cat'] ) );
		return ! empty( $ids ) ? (string) $ids[0] : '';
	}

	return '';
}

/**
 * Recursively search tax_query for a category taxonomy entry.
 *
 * @param array  $tax_query Tax query array.
 * @param string $taxonomy  Taxonomy to find.
 * @return string|false First term slug/ID found, or false.
 */
function dlt_catfix_find_in_tax_query( $tax_query, $taxonomy ) {
	foreach ( $tax_query as $clause ) {
		if ( ! is_array( $clause ) ) {
			continue;
		}
		// Nested tax_query.
		if ( isset( $clause[0] ) && is_array( $clause[0] ) ) {
			$found = dlt_catfix_find_in_tax_query( $clause, $taxonomy );
			if ( $found ) {
				return $found;
			}
			continue;
		}
		if ( ! empty( $clause['taxonomy'] ) && $clause['taxonomy'] === $taxonomy && ! empty( $clause['terms'] ) ) {
			$terms = (array) $clause['terms'];
			return (string) reset( $terms );
		}
	}
	return false;
}
