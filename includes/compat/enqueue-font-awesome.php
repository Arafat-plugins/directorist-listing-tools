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

	$handle = 'font-awesome';
	$src    = plugins_url( 'directorist/assets/icons/font-awesome/css/all.min.css' );

	wp_enqueue_style(
		$handle,
		$src,
		array(),
		null,
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'dlt_af_enqueue_font_awesome_frontend', 20 );
