<?php
/**
 * Pricing type tabs open add listing page.
 *
 * Makes Directorist WooCommerce Pricing Plan type tabs go to the configured
 * Add Listing page with the clicked directory type in the URL.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the configured Directorist Add Listing page URL.
 *
 * @return string
 */
function dlt_pricing_type_tabs_get_add_listing_page_url() {
	$page_id = function_exists( 'get_directorist_option' ) ? absint( get_directorist_option( 'add_listing_page' ) ) : 0;
	$url     = $page_id ? get_permalink( $page_id ) : '';

	return $url ? $url : home_url( '/add-listing/' );
}

/**
 * Normalize a URL/path to a comparable request path.
 *
 * @param string $url URL or path.
 * @return string
 */
function dlt_pricing_type_tabs_get_url_path( $url ) {
	$path = wp_parse_url( $url, PHP_URL_PATH );

	return is_string( $path ) ? trim( $path, '/' ) : '';
}

/**
 * Check whether the current request path matches a page permalink.
 *
 * This catches translated/current page IDs that do not equal the page ID saved
 * in Directorist settings, while still keeping the URL filter scoped.
 *
 * @param int $page_id Page ID.
 * @return bool
 */
function dlt_pricing_type_tabs_current_request_matches_page_path( $page_id ) {
	$page_url = $page_id ? get_permalink( $page_id ) : '';

	if ( ! $page_url ) {
		return false;
	}

	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = dlt_pricing_type_tabs_get_url_path( $request_uri );
	$page_path    = dlt_pricing_type_tabs_get_url_path( $page_url );

	return '' !== $page_path && $request_path === $page_path;
}

/**
 * Check whether the current request is a page where pricing type tabs appear.
 *
 * @return bool
 */
function dlt_pricing_type_tabs_is_pricing_page_context() {
	if ( is_admin() || wp_doing_ajax() ) {
		return false;
	}

	$add_listing_page_id = function_exists( 'get_directorist_option' ) ? absint( get_directorist_option( 'add_listing_page' ) ) : 0;
	$pricing_page_id     = function_exists( 'get_directorist_option' ) ? absint( get_directorist_option( 'pricing_plans' ) ) : 0;

	if ( $add_listing_page_id && is_page( $add_listing_page_id ) ) {
		return true;
	}

	if ( dlt_pricing_type_tabs_current_request_matches_page_path( $add_listing_page_id ) ) {
		return true;
	}

	if ( $pricing_page_id && is_page( $pricing_page_id ) ) {
		return true;
	}

	if ( dlt_pricing_type_tabs_current_request_matches_page_path( $pricing_page_id ) ) {
		return true;
	}

	return false;
}

/**
 * Point pricing directory type tab URLs at the configured Add Listing page.
 *
 * Filter: directorist_get_directory_type_nav_url.
 *
 * @param string $url      Generated directory type URL.
 * @param string $type     Directory type slug.
 * @param string $base_url Base URL used by Directorist.
 * @return string
 */
function dlt_pricing_type_tabs_open_add_listing_page_url( $url, $type, $base_url ) {
	unset( $base_url );

	if ( empty( $type ) || 'all' === $type || ! dlt_pricing_type_tabs_is_pricing_page_context() ) {
		return $url;
	}

	return add_query_arg( 'directory_type', sanitize_key( $type ), dlt_pricing_type_tabs_get_add_listing_page_url() );
}
add_filter( 'directorist_get_directory_type_nav_url', 'dlt_pricing_type_tabs_open_add_listing_page_url', 20, 3 );

/**
 * Force pricing type tabs to follow their href before Ajax tab handlers run.
 *
 * Directorist and themes also use .directorist-type-nav__link for Ajax archive
 * tabs, so this script is scoped to pricing plan containers only. It is printed
 * in the head so the capture listener is registered before later frontend
 * scripts attach their own click handlers.
 */
function dlt_pricing_type_tabs_print_click_guard() {
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}
	?>
	<script>
	(function() {
		document.addEventListener('click', function(event) {
			var target = event.target;
			var link = target && target.closest ? target.closest('#fm_plans_container .directorist-type-nav__link, #directorist-pricing-plan-container .directorist-type-nav__link') : null;

			if (!link || !link.href) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			if (event.stopImmediatePropagation) {
				event.stopImmediatePropagation();
			}

			window.location.href = link.href;
		}, true);
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'dlt_pricing_type_tabs_print_click_guard', 1 );
