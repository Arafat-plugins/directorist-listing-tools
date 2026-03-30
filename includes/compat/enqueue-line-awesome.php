<?php
/**
 * Enqueue Line Awesome on frontend (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_LINE_AWESOME' ) ) {
	return;
}
define( 'DLT_AF_LOADED_LINE_AWESOME', true );

/**
 * Enqueue Line Awesome CSS on the frontend.
 */
function dlt_af_enqueue_line_awesome_frontend() {
	if ( is_admin() ) {
		return;
	}

	if ( wp_style_is( 'directorist-line-awesome', 'enqueued' ) ) {
		return;
	}

	$handle = 'line-awesome';
	$src    = plugins_url( 'directorist/assets/icons/line-awesome/css/line-awesome.min.css' );

	wp_enqueue_style(
		$handle,
		$src,
		array(),
		null,
		'all'
	);
}
add_action( 'wp_enqueue_scripts', 'dlt_af_enqueue_line_awesome_frontend', 20 );
