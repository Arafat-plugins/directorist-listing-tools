<?php
/**
 * Enqueue Font Awesome on frontend (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_FONT_AWESOME' ) ) {
	return;
}
define( 'DLT_AF_LOADED_FONT_AWESOME', true );

/**
 * Enqueue Font Awesome CSS on the frontend.
 */
function dlt_af_enqueue_font_awesome_frontend() {
	if ( is_admin() ) {
		return;
	}

	if ( wp_style_is( 'directorist-font-awesome', 'enqueued' ) ) {
		return;
	}

	// Prefer Directorist's own pre-registered handle: correct bundled URL/version,
	// and avoids the generic 'font-awesome' handle that themes/Elementor also claim
	// (registering under that shared handle would silently lose to whichever
	// plugin registered it first, leaving .fas rules/icons unstyled).
	if ( wp_style_is( 'directorist-font-awesome', 'registered' ) ) {
		wp_enqueue_style( 'directorist-font-awesome' );
		return;
	}

	$src = plugins_url( 'directorist/assets/icons/font-awesome/css/all.min.css' );

	wp_enqueue_style(
		'dlt-font-awesome',
		$src,
		array(),
		null,
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'dlt_af_enqueue_font_awesome_frontend', 20 );
