<?php
/**
 * Directorist Directory Type Guard (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_DIRECTORY_TYPE_GUARD' ) ) {
	return;
}
define( 'DLT_AF_LOADED_DIRECTORY_TYPE_GUARD', true );

/**
 * @param string $message Message to write.
 */
function mu_dlt_dir_guard_log( $message ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[MU Directory Guard] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * @param int $listing_id Listing ID.
 * @return int
 */
function mu_dlt_dir_guard_resolve_directory_id( $listing_id = 0 ) {
	$maybe      = '';
	$listing_id = absint( $listing_id );

	if ( isset( $_POST['directory_type'] ) ) {
		$maybe = sanitize_text_field( wp_unslash( $_POST['directory_type'] ) );
	}

	if ( $maybe === '' && isset( $_REQUEST['directory_type'] ) ) {
		$maybe = sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );
	}

	if ( $maybe === '' && ! empty( $_POST['preview_url'] ) ) {
		$preview_url = esc_url_raw( wp_unslash( $_POST['preview_url'] ) );
		$query       = wp_parse_url( $preview_url, PHP_URL_QUERY );
		if ( $query ) {
			parse_str( $query, $args );
			if ( ! empty( $args['directory_type'] ) ) {
				$maybe = sanitize_text_field( $args['directory_type'] );
			}
		}
	}

	if ( $maybe === '' && ! empty( $_SERVER['REQUEST_URI'] ) ) {
		$uri_path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		if ( is_string( $uri_path ) && $uri_path !== '' ) {
			$segments = array_values( array_filter( explode( '/', trim( $uri_path, '/' ) ) ) );
			$count    = count( $segments );
			for ( $i = 0; $i < $count - 1; $i++ ) {
				if ( $segments[ $i ] === 'directory' && ! empty( $segments[ $i + 1 ] ) ) {
					$maybe = sanitize_title( $segments[ $i + 1 ] );
					break;
				}
			}
		}
	}

	if ( $maybe === '' && $listing_id > 0 && defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
		$terms = get_the_terms( $listing_id, ATBDP_DIRECTORY_TYPE );
		if ( is_array( $terms ) && ! empty( $terms[0]->term_id ) ) {
			return (int) $terms[0]->term_id;
		}
	}

	if ( $maybe !== '' && defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
		$term = get_term_by( is_numeric( $maybe ) ? 'id' : 'slug', $maybe, ATBDP_DIRECTORY_TYPE );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
	}

	if ( function_exists( 'directorist_get_default_directory' ) ) {
		return (int) directorist_get_default_directory();
	}

	return 0;
}

/**
 * @param int $listing_id Listing ID.
 */
function mu_dlt_dir_guard_apply( $listing_id ) {
	$listing_id = absint( $listing_id );
	if ( ! $listing_id ) {
		return;
	}

	$directory_id = mu_dlt_dir_guard_resolve_directory_id( $listing_id );
	if ( ! $directory_id ) {
		mu_dlt_dir_guard_log( 'Skip apply: no resolved directory for listing #' . $listing_id );
		return;
	}

	if ( function_exists( 'directorist_is_directory' ) && ! directorist_is_directory( $directory_id ) ) {
		mu_dlt_dir_guard_log( 'Skip apply: invalid directory #' . $directory_id . ' for listing #' . $listing_id );
		return;
	}

	$applied = false;

	if ( function_exists( 'directorist_set_listing_directory' ) ) {
		$result = directorist_set_listing_directory( $listing_id, $directory_id );
		if ( ! is_wp_error( $result ) ) {
			$applied = true;
		}
	}

	if ( ! $applied ) {
		update_post_meta( $listing_id, '_directory_type', $directory_id );
		if ( defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
			wp_set_object_terms( $listing_id, array( $directory_id ), ATBDP_DIRECTORY_TYPE, false );
		}
	}

	mu_dlt_dir_guard_log(
		sprintf(
			'Applied directory #%1$d to listing #%2$d',
			$directory_id,
			$listing_id
		)
	);
}

/**
 * @return int
 */
function mu_dlt_dir_guard_get_request_listing_id() {
	if ( ! empty( $_GET['listing_id'] ) ) {
		return absint( $_GET['listing_id'] );
	}
	if ( ! empty( $_GET['post_id'] ) ) {
		return absint( $_GET['post_id'] );
	}
	if ( ! empty( $_GET['atbdp_listing_id'] ) ) {
		return absint( $_GET['atbdp_listing_id'] );
	}
	if ( is_singular( 'at_biz_dir' ) ) {
		return get_queried_object_id();
	}
	return 0;
}

/**
 * @param int $listing_id Listing ID.
 * @return bool
 */
function mu_dlt_dir_guard_listing_needs_fix( $listing_id ) {
	$listing_id = absint( $listing_id );
	if ( ! $listing_id ) {
		return false;
	}
	$current = (int) get_post_meta( $listing_id, '_directory_type', true );
	if ( $current < 1 ) {
		return true;
	}
	if ( function_exists( 'directorist_is_directory' ) ) {
		return ! directorist_is_directory( $current );
	}
	return false;
}

add_action(
	'atbdp_after_created_listing',
	static function ( $listing_id ) {
		mu_dlt_dir_guard_apply( $listing_id );
	},
	999
);

add_action(
	'atbdp_listing_inserted',
	static function ( $listing_id ) {
		mu_dlt_dir_guard_apply( $listing_id );
	},
	999
);

add_action(
	'atbdp_listing_updated',
	static function ( $listing_id ) {
		mu_dlt_dir_guard_apply( $listing_id );
	},
	999
);

add_action(
	'template_redirect',
	static function () {
		$listing_id = mu_dlt_dir_guard_get_request_listing_id();
		if ( ! $listing_id ) {
			return;
		}

		$is_listing_page = is_singular( 'at_biz_dir' );
		$is_preview_flow = ! empty( $_GET['preview'] ) || ! empty( $_GET['payment'] ) || ! empty( $_GET['redirect'] );
		if ( ! $is_listing_page && ! $is_preview_flow ) {
			return;
		}

		if ( ! mu_dlt_dir_guard_listing_needs_fix( $listing_id ) ) {
			return;
		}

		mu_dlt_dir_guard_log( 'Template redirect self-heal for listing #' . $listing_id );
		mu_dlt_dir_guard_apply( $listing_id );
	},
	1
);
