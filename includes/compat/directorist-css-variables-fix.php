<?php
/**
 * Directorist CSS variables in wp_head (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_CSS_VARIABLES_FIX' ) ) {
	return;
}
define( 'DLT_AF_LOADED_CSS_VARIABLES_FIX', true );

add_action( 'wp_head', 'directorist_css_variables_fix_output', 5 );

/**
 * Output Directorist :root CSS variables early in wp_head.
 */
function directorist_css_variables_fix_output() {
	if ( is_admin() ) {
		return;
	}
	if ( ! class_exists( 'Directorist\Asset_Loader\Helper' ) ) {
		return;
	}

	$style = \Directorist\Asset_Loader\Helper::dynamic_style();
	if ( empty( $style ) ) {
		return;
	}

	if ( preg_match( '/:root\s*\{[^}]*\}/s', $style, $matches ) ) {
		echo '<style id="directorist-css-variables-fix">' . $matches[0] . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
