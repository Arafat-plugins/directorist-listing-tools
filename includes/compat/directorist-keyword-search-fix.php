<?php
/**
 * Directorist keyword search compatibility fix.
 *
 * Improves Directorist keyword search accuracy for AJAX and archive searches by
 * limiting matches to intentional fields only.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect true back-end requests while still allowing admin-ajax search requests.
 *
 * @return bool
 */
function dlt_directorist_keyword_search_is_back_end_request() {
	return is_admin() && ! wp_doing_ajax();
}

/**
 * Get the current keyword from the request.
 *
 * @return string
 */
function dlt_directorist_get_keyword_from_request() {
	if ( ! empty( $_REQUEST['q'] ) ) {
		return sanitize_text_field( wp_unslash( $_REQUEST['q'] ) );
	}

	return '';
}

/**
 * Force Directorist listing queries to carry the typed keyword.
 *
 * @param array $args Listing query arguments.
 * @return array
 */
function dlt_directorist_flag_keyword_search_query( $args ) {
	if ( dlt_directorist_keyword_search_is_back_end_request() || empty( $args['post_type'] ) ) {
		return $args;
	}

	$post_type = $args['post_type'];
	$keyword   = dlt_directorist_get_keyword_from_request();

	if ( is_array( $post_type ) ) {
		$is_directorist_query = in_array( ATBDP_POST_TYPE, $post_type, true );
	} else {
		$is_directorist_query = ( ATBDP_POST_TYPE === $post_type );
	}

	if ( ! $is_directorist_query || '' === $keyword ) {
		return $args;
	}

	$args['s']                                     = $keyword;
	$args['dlt_directorist_keyword_search_fix_kw'] = $keyword;

	return $args;
}
add_filter( 'atbdp_listing_search_query_argument', 'dlt_directorist_flag_keyword_search_query', 20 );

/**
 * Replace broad default search matching with title, key meta, and taxonomies.
 *
 * @param string   $search Existing SQL search fragment.
 * @param WP_Query $query  Current query object.
 * @return string
 */
function dlt_directorist_expand_keyword_search( $search, $query ) {
	global $wpdb;

	if ( dlt_directorist_keyword_search_is_back_end_request() ) {
		return $search;
	}

	$keyword = $query->get( 'dlt_directorist_keyword_search_fix_kw' );

	if ( empty( $keyword ) ) {
		return $search;
	}

	$post_type = $query->get( 'post_type' );
	$is_directorist_query = is_array( $post_type )
		? in_array( ATBDP_POST_TYPE, $post_type, true )
		: ( ATBDP_POST_TYPE === $post_type );

	if ( ! $is_directorist_query ) {
		return $search;
	}

	$terms = preg_split( '/[\s,]+/', $keyword );
	$terms = array_values(
		array_filter(
			array_map( 'trim', (array) $terms )
		)
	);

	if ( empty( $terms ) ) {
		return $search;
	}

	$meta_keys = apply_filters(
		'dlt_directorist_keyword_search_meta_keys',
		array(
			'_address',
			'_zip',
			'_phone',
			'_phone2',
			'_fax',
			'_email',
			'_website',
			'_tagline',
			'_price_range',
		)
	);

	$taxonomy_placeholders = implode( ', ', array_fill( 0, 3, '%s' ) );
	$meta_key_placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$password_sql          = is_user_logged_in() ? '' : " AND ({$wpdb->posts}.post_password = '')";
	$term_clauses          = array();

	foreach ( $terms as $term ) {
		$like = '%' . $wpdb->esc_like( $term ) . '%';

		$term_sql = "
			(
				{$wpdb->posts}.post_title LIKE %s
				OR EXISTS (
					SELECT 1
					FROM {$wpdb->postmeta} pm
					WHERE pm.post_id = {$wpdb->posts}.ID
						AND pm.meta_key IN ($meta_key_placeholders)
						AND pm.meta_value LIKE %s
				)
				OR EXISTS (
					SELECT 1
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt
						ON tt.term_taxonomy_id = tr.term_taxonomy_id
					INNER JOIN {$wpdb->terms} t
						ON t.term_id = tt.term_id
					WHERE tr.object_id = {$wpdb->posts}.ID
						AND tt.taxonomy IN ($taxonomy_placeholders)
						AND (
							t.name LIKE %s
							OR t.slug LIKE %s
						)
				)
			)
		";

		$term_params = array_merge(
			array(
				$like,
			),
			$meta_keys,
			array(
				$like,
				ATBDP_CATEGORY,
				ATBDP_LOCATION,
				ATBDP_TAGS,
				$like,
				$like,
			)
		);

		$term_clauses[] = $wpdb->prepare( $term_sql, $term_params );
	}

	if ( empty( $term_clauses ) ) {
		return $search;
	}

	return $password_sql . ' AND (' . implode( ' AND ', $term_clauses ) . ')';
}
add_filter( 'posts_search', 'dlt_directorist_expand_keyword_search', 20, 2 );
