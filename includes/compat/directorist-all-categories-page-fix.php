<?php
/**
 * All categories page fix.
 *
 * Fixes the Directorist all-categories shortcode/page so it:
 * - defaults to all categories when no directory type is selected,
 * - respects shortcode directory_type settings across pagination,
 * - keeps category links clean in all-types mode.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'dlt_acpage_register_shortcode_fix', 1000 );
add_action( 'wp_ajax_directorist_taxonomy_pagination', 'dlt_acpage_ajax_taxonomy_pagination', 0 );
add_action( 'wp_ajax_nopriv_directorist_taxonomy_pagination', 'dlt_acpage_ajax_taxonomy_pagination', 0 );

/**
 * Override the Directorist all-categories shortcode with a wrapped renderer.
 *
 * @return void
 */
function dlt_acpage_register_shortcode_fix() {
	add_shortcode( 'directorist_all_categories', 'dlt_acpage_shortcode_callback' );
}

/**
 * Wrapped shortcode callback for Directorist all categories.
 *
 * @param array       $atts    Shortcode attributes.
 * @param string|null $content Shortcode content.
 * @param string      $tag     Shortcode tag.
 * @return string
 */
function dlt_acpage_shortcode_callback( $atts = array(), $content = null, $tag = '' ) {
	unset( $content, $tag );

	return dlt_acpage_render_all_categories( is_array( $atts ) ? $atts : array() );
}

/**
 * Intercept category pagination so page 2+ keeps the same fixed behavior.
 *
 * @return void
 */
function dlt_acpage_ajax_taxonomy_pagination() {
	if ( ! wp_doing_ajax() ) {
		return;
	}

	$atts = isset( $_REQUEST['attrs'] ) && is_array( $_REQUEST['attrs'] )
		? directorist_clean( wp_unslash( $_REQUEST['attrs'] ) )
		: array();

	if ( empty( $atts['type'] ) || 'category' !== $atts['type'] ) {
		return;
	}

	if ( ! directorist_verify_nonce( 'nonce' ) ) {
		wp_send_json(
			array(
				'search_form' => __( 'Something went wrong, please try again.', 'directorist' ),
			)
		);
	}

	$page = isset( $_REQUEST['page'] ) ? absint( $_REQUEST['page'] ) : 1;

	wp_send_json_success(
		array(
			'content' => dlt_acpage_render_all_categories( $atts, $page ),
		)
	);
}

/**
 * Render the all-categories output with corrected directory-type behavior.
 *
 * @param array    $atts         Shortcode attributes.
 * @param int|null $current_page Current pagination page.
 * @return string
 */
function dlt_acpage_render_all_categories( $atts = array(), $current_page = null ) {
	if ( ! class_exists( '\Directorist\Directorist_Listing_Taxonomy' ) ) {
		return '';
	}

	$atts                  = is_array( $atts ) ? $atts : array();
	$atts['shortcode']     = 'directorist_all_categories';
	$request_directory_type = dlt_acpage_get_request_directory_type();
	$attr_directory_type    = dlt_acpage_get_attr_directory_type( $atts );
	$is_all_mode            = dlt_acpage_is_all_mode( $request_directory_type, $attr_directory_type );

	if ( 'all' === $attr_directory_type ) {
		$atts['directory_type'] = '';
	}

	$taxonomy = new \Directorist\Directorist_Listing_Taxonomy( $atts, 'category' );

	if ( $is_all_mode ) {
		$taxonomy->current_listing_type = 'all';

		dlt_acpage_with_temp_request_directory_type(
			'all',
			function () use ( $taxonomy, $current_page ) {
				$taxonomy->set_terms( is_int( $current_page ) ? $current_page : null );
			}
		);

		$html = dlt_acpage_with_temp_request_directory_type(
			null,
			function () use ( $taxonomy, $atts ) {
				return $taxonomy->render_shortcode( $atts );
			}
		);

		return dlt_acpage_replace_data_attrs_directory_type( $html, 'all' );
	}

	if ( is_int( $current_page ) ) {
		$taxonomy->set_terms( $current_page );
	}

	$html = $taxonomy->render_shortcode( $atts );

	if ( '' !== $request_directory_type ) {
		return $html;
	}

	if ( '' !== $attr_directory_type ) {
		return dlt_acpage_replace_data_attrs_directory_type( $html, $attr_directory_type );
	}

	return $html;
}

/**
 * Get the requested directory type from the current request.
 *
 * @return string
 */
function dlt_acpage_get_request_directory_type() {
	if ( empty( $_REQUEST['directory_type'] ) ) {
		return '';
	}

	return sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );
}

/**
 * Normalize the shortcode directory_type attribute.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function dlt_acpage_get_attr_directory_type( $atts ) {
	if ( empty( $atts['directory_type'] ) ) {
		return '';
	}

	$parts = array_map( 'trim', explode( ',', (string) $atts['directory_type'] ) );
	$parts = array_map( 'sanitize_title', $parts );
	$parts = array_values( array_filter( $parts ) );

	return implode( ',', $parts );
}

/**
 * Determine whether the page should run in "all categories" mode.
 *
 * @param string $request_directory_type Directory type from the request.
 * @param string $attr_directory_type    Directory type from shortcode attributes.
 * @return bool
 */
function dlt_acpage_is_all_mode( $request_directory_type, $attr_directory_type ) {
	if ( 'all' === $request_directory_type ) {
		return true;
	}

	if ( '' !== $request_directory_type ) {
		return false;
	}

	return ( '' === $attr_directory_type || 'all' === $attr_directory_type );
}

/**
 * Temporarily set or clear request directory_type while running a callback.
 *
 * @param string|null $directory_type Directory type to use. Null clears the value.
 * @param callable    $callback       Callback to execute.
 * @return mixed
 */
function dlt_acpage_with_temp_request_directory_type( $directory_type, callable $callback ) {
	$has_request = array_key_exists( 'directory_type', $_REQUEST );
	$has_get     = array_key_exists( 'directory_type', $_GET );
	$has_post    = array_key_exists( 'directory_type', $_POST );
	$prev_request = $has_request ? $_REQUEST['directory_type'] : null;
	$prev_get     = $has_get ? $_GET['directory_type'] : null;
	$prev_post    = $has_post ? $_POST['directory_type'] : null;

	if ( null === $directory_type || '' === $directory_type ) {
		unset( $_REQUEST['directory_type'], $_GET['directory_type'], $_POST['directory_type'] );
	} else {
		$_REQUEST['directory_type'] = $directory_type;
		$_GET['directory_type']     = $directory_type;
		$_POST['directory_type']    = $directory_type;
	}

	try {
		return $callback();
	} finally {
		if ( $has_request ) {
			$_REQUEST['directory_type'] = $prev_request;
		} else {
			unset( $_REQUEST['directory_type'] );
		}

		if ( $has_get ) {
			$_GET['directory_type'] = $prev_get;
		} else {
			unset( $_GET['directory_type'] );
		}

		if ( $has_post ) {
			$_POST['directory_type'] = $prev_post;
		} else {
			unset( $_POST['directory_type'] );
		}
	}
}

/**
 * Force the rendered wrapper data-attrs JSON to carry the correct directory type.
 *
 * @param string $html           Rendered HTML.
 * @param string $directory_type Directory type value to inject.
 * @return string
 */
function dlt_acpage_replace_data_attrs_directory_type( $html, $directory_type ) {
	if ( '' === $directory_type || false === strpos( $html, 'directorist-categories' ) || false === strpos( $html, 'data-attrs=' ) ) {
		return $html;
	}

	$pattern = '/(<div[^>]*class="[^"]*directorist-categories[^"]*"[^>]*data-attrs=")([^"]*)(")/i';

	return preg_replace_callback(
		$pattern,
		function ( $matches ) use ( $directory_type ) {
			$charset = get_bloginfo( 'charset' );
			$charset = $charset ? $charset : 'UTF-8';
			$data    = json_decode( html_entity_decode( $matches[2], ENT_QUOTES, $charset ), true );

			if ( ! is_array( $data ) ) {
				return $matches[0];
			}

			$data['directory_type'] = $directory_type;

			return $matches[1] . esc_attr( wp_json_encode( $data ) ) . $matches[3];
		},
		$html,
		1
	);
}
