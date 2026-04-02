<?php
/**
 * WP Rocket pagination conflict compatibility.
 *
 * This file exists for the Directorist Listing Tools "Apply Functions" toggle.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared exclusion patterns for Directorist instant-search runtime.
 *
 * @return array
 */
function dlt_wpr_directorist_js_patterns() {
	return array(
		'/directorist/assets/js/all-listings(.min)?.js',
		'/directorist/assets/js/search-form(.min)?.js',
		'/directorist/assets/js/public-main(.min)?.js',
		'/directorist/assets/js/global-main(.min)?.js',
		'/directorist/assets/js/listing-slider(.min)?.js',
		'directorist-instant-search-pagination-fix',
	);
}

/**
 * Exclude Directorist scripts from Delay JS.
 *
 * @param array $excluded Current delayed JS exclusions.
 * @return array
 */
function dlt_wpr_exclude_delay_js( $excluded ) {
	if ( ! is_array( $excluded ) ) {
		$excluded = array();
	}

	$patterns = dlt_wpr_directorist_js_patterns();

	return array_values( array_unique( array_merge( $excluded, $patterns ) ) );
}
add_filter( 'rocket_delay_js_exclusions', 'dlt_wpr_exclude_delay_js' );

/**
 * Exclude Directorist scripts from Defer JS.
 *
 * @param array $excluded Current defer exclusions.
 * @return array
 */
function dlt_wpr_exclude_defer_js( $excluded ) {
	if ( ! is_array( $excluded ) ) {
		$excluded = array();
	}

	$patterns = dlt_wpr_directorist_js_patterns();

	return array_values( array_unique( array_merge( $excluded, $patterns ) ) );
}
add_filter( 'rocket_exclude_defer_js', 'dlt_wpr_exclude_defer_js' );

/**
 * Exclude Directorist scripts from JS minify/combine handling.
 *
 * @param array $excluded Current JS exclusions.
 * @return array
 */
function dlt_wpr_exclude_minify_js( $excluded ) {
	if ( ! is_array( $excluded ) ) {
		$excluded = array();
	}

	$patterns = dlt_wpr_directorist_js_patterns();

	return array_values( array_unique( array_merge( $excluded, $patterns ) ) );
}
add_filter( 'rocket_exclude_js', 'dlt_wpr_exclude_minify_js' );

/**
 * Exclude critical inline Directorist snippets from JS combine transforms.
 *
 * @param array $excluded_inline Current inline JS content exclusions.
 * @return array
 */
function dlt_wpr_exclude_inline_js_content( $excluded_inline ) {
	if ( ! is_array( $excluded_inline ) ) {
		$excluded_inline = array();
	}

	$needles = array(
		'directorist.ajaxurl',
		'directorist.ajax_nonce',
		'directorist_instant_search',
		'directorist-instant-search',
		'dlt-instant-search-context-fix',
	);

	return array_values( array_unique( array_merge( $excluded_inline, $needles ) ) );
}
add_filter( 'rocket_excluded_inline_js_content', 'dlt_wpr_exclude_inline_js_content' );

