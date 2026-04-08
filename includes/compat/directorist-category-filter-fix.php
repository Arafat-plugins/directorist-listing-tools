<?php
/**
 * Category filter fix.
 *
 * Two-part fix:
 * 1. Frontend: category links include ?directory_type= so the type is preserved on click.
 * 2. Backend:  if directory_type is missing from URL, detect it from the category's term meta.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ─── PART 1: Frontend — add directory_type to category links ───────────────

/**
 * When Directorist renders category links inside a directory type context,
 * append ?directory_type= so clicking a category preserves the active type.
 *
 * Filter: atbdp_single_category (applied in atbdp_get_category_page).
 */
function dlt_catfix_add_directory_type_to_category_link( $link, $page_id, $term, $directory_type ) {
	// Already has directory_type in URL — skip.
	if ( ! empty( $directory_type ) ) {
		return $link;
	}

	// Try to get current directory type from the request or the category's own meta.
	$current_type = '';

	// From request (user is on a directory-type-specific page).
	if ( ! empty( $_REQUEST['directory_type'] ) ) {
		$current_type = sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );
	}

	// From AJAX: Directorist search home sends listing_type (directory type slug) via POST.
	if ( empty( $current_type ) && ! empty( $_POST['listing_type'] ) ) {
		$current_type = sanitize_text_field( wp_unslash( $_POST['listing_type'] ) );
	}

	// Fallback: get from category term meta.
	if ( empty( $current_type ) && $term ) {
		$term_id   = is_object( $term ) ? $term->term_id : absint( $term );
		$dir_types = get_term_meta( $term_id, '_directory_type', true );
		if ( ! empty( $dir_types ) && is_array( $dir_types ) ) {
			$first_type_id = absint( reset( $dir_types ) );
			$type_term     = get_term( $first_type_id );
			if ( $type_term && ! is_wp_error( $type_term ) ) {
				$current_type = $type_term->slug;
			}
		}
	}

	if ( ! empty( $current_type ) ) {
		$link = add_query_arg( 'directory_type', $current_type, $link );
	}

	return $link;
}
add_filter( 'atbdp_single_category', 'dlt_catfix_add_directory_type_to_category_link', 10, 4 );

// ─── PART 2: Backend — fix meta query on category pages ────────────────────

/**
 * If we're on a category page without explicit directory_type in URL,
 * set the meta query to the category's actual directory type(s).
 *
 * Filter: atbdp_listing_search_query_argument (final WP_Query args).
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
		unset( $args['meta_query']['directory_type'] );
	}

	return $args;
}
add_filter( 'atbdp_listing_search_query_argument', 'dlt_fix_category_filter_query_args', 20 );

// ─── Helpers ───────────────────────────────────────────────────────────────

/**
 * Detect category slug/ID from every possible source.
 */
function dlt_catfix_detect_category( $args ) {
	$taxonomy = defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';

	// 1. tax_query in args.
	if ( ! empty( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
		$slug = dlt_catfix_find_in_tax_query( $args['tax_query'], $taxonomy );
		if ( $slug ) {
			return $slug;
		}
	}

	// 2. Taxonomy archive.
	if ( function_exists( 'is_tax' ) && is_tax( $taxonomy ) ) {
		$obj = get_queried_object();
		if ( $obj && ! empty( $obj->slug ) ) {
			return $obj->slug;
		}
	}

	// 3. GET ?category=.
	if ( ! empty( $_GET['category'] ) ) {
		return sanitize_text_field( wp_unslash( $_GET['category'] ) );
	}

	// 4. Query var.
	$qv = get_query_var( 'atbdp_category' );
	if ( ! empty( $qv ) ) {
		return sanitize_text_field( $qv );
	}

	// 5. URL path /single-category/{slug}/.
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
 */
function dlt_catfix_find_in_tax_query( $tax_query, $taxonomy ) {
	foreach ( $tax_query as $clause ) {
		if ( ! is_array( $clause ) ) {
			continue;
		}
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
