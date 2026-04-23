<?php
/**
 * WooCommerce checkout endpoint conflict fix.
 *
 * Restores order-received / order-pay routes when generic Directorist
 * checkout rewrite rules catch those URLs first.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap hooks once.
 *
 * @return void
 */
function dlt_af_bootstrap_wc_checkout_endpoint_conflict_fix() {
	if ( defined( 'DLT_AF_WC_CHECKOUT_ENDPOINT_CONFLICT_FIX_BOOTSTRAPPED' ) ) {
		return;
	}

	if ( function_exists( 'mu_wc_endpoint_fix_get_context' ) ) {
		return;
	}

	define( 'DLT_AF_WC_CHECKOUT_ENDPOINT_CONFLICT_FIX_BOOTSTRAPPED', true );

	add_filter( 'option_rewrite_rules', 'dlt_af_wcec_prepend_rewrite_rules', 5 );
	add_action( 'parse_request', 'dlt_af_wcec_normalize_checkout_request', 0 );
}

/**
 * Get current checkout context.
 *
 * @return array<string, int|string>
 */
function dlt_af_wcec_get_context() {
	static $context = null;

	if ( null !== $context ) {
		return $context;
	}

	if ( ! function_exists( 'wc_get_page_id' ) ) {
		$context = array();
		return $context;
	}

	$checkout_id = (int) wc_get_page_id( 'checkout' );
	if ( $checkout_id <= 0 ) {
		$context = array();
		return $context;
	}

	$checkout_slug = (string) get_post_field( 'post_name', $checkout_id );
	if ( '' === $checkout_slug ) {
		$context = array();
		return $context;
	}

	$context = array(
		'checkout_id'         => $checkout_id,
		'checkout_slug'       => $checkout_slug,
		'order_received_slug' => (string) get_option( 'woocommerce_checkout_order_received_endpoint', 'order-received' ),
		'order_pay_slug'      => (string) get_option( 'woocommerce_checkout_pay_endpoint', 'order-pay' ),
	);

	return $context;
}

/**
 * Cheap request gate so normal pages do almost no work.
 *
 * @return bool
 */
function dlt_af_wcec_request_might_apply() {
	static $should_apply = null;

	if ( null !== $should_apply ) {
		return $should_apply;
	}

	$context = dlt_af_wcec_get_context();
	if ( empty( $context ) ) {
		$should_apply = false;
		return $should_apply;
	}

	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	if ( '' === $path ) {
		$should_apply = false;
		return $should_apply;
	}

	$path = trim( $path, '/' );
	if ( '' === $path ) {
		$should_apply = false;
		return $should_apply;
	}

	$segments = explode( '/', $path );

	$should_apply = isset( $segments[0], $segments[1], $segments[2] )
		&& $segments[0] === $context['checkout_slug']
		&& in_array( $segments[1], array( $context['order_received_slug'], $context['order_pay_slug'] ), true )
		&& absint( $segments[2] ) > 0;

	return $should_apply;
}

/**
 * Prepend exact WooCommerce endpoint rules ahead of generic checkout rewrites.
 *
 * @param mixed $rules Rewrite rules.
 * @return mixed
 */
function dlt_af_wcec_prepend_rewrite_rules( $rules ) {
	if ( ! is_array( $rules ) || ! dlt_af_wcec_request_might_apply() ) {
		return $rules;
	}

	$context = dlt_af_wcec_get_context();
	if ( empty( $context ) ) {
		return $rules;
	}

	$checkout_slug = preg_quote( $context['checkout_slug'], '#' );
	$custom_rules  = array(
		'^' . $checkout_slug . '/' . preg_quote( $context['order_received_slug'], '#' ) . '/([0-9]{1,})/?$' => 'index.php?page_id=' . $context['checkout_id'] . '&order-received=$matches[1]',
		'^' . $checkout_slug . '/' . preg_quote( $context['order_pay_slug'], '#' ) . '/([0-9]{1,})/?$'      => 'index.php?page_id=' . $context['checkout_id'] . '&order-pay=$matches[1]',
	);

	return $custom_rules + $rules;
}

/**
 * Normalize query vars if Directorist already matched a broad checkout rule.
 *
 * @param WP $wp WordPress environment instance.
 * @return void
 */
function dlt_af_wcec_normalize_checkout_request( $wp ) {
	if ( ! $wp instanceof WP || is_admin() || ! dlt_af_wcec_request_might_apply() ) {
		return;
	}

	$context = dlt_af_wcec_get_context();
	if ( empty( $context ) ) {
		return;
	}

	$endpoint_map = array(
		(string) $context['order_received_slug'] => 'order-received',
		(string) $context['order_pay_slug']      => 'order-pay',
	);

	$endpoint = '';
	$order_id = 0;

	if (
		isset( $wp->query_vars['page_id'], $wp->query_vars['atbdp_action'], $wp->query_vars['atbdp_order_id'] ) &&
		(int) $wp->query_vars['page_id'] === (int) $context['checkout_id']
	) {
		$endpoint = (string) $wp->query_vars['atbdp_action'];
		$order_id = absint( $wp->query_vars['atbdp_order_id'] );
	}

	if ( '' === $endpoint || $order_id <= 0 ) {
		$segments = array_values( array_filter( explode( '/', trim( (string) $wp->request, '/' ) ) ) );

		if ( count( $segments ) < 3 || $segments[0] !== $context['checkout_slug'] ) {
			return;
		}

		$endpoint = (string) $segments[1];
		$order_id = absint( $segments[2] );
	}

	if ( ! isset( $endpoint_map[ $endpoint ] ) || $order_id <= 0 ) {
		return;
	}

	unset( $wp->query_vars['atbdp_action'], $wp->query_vars['atbdp_order_id'] );

	$wp->query_vars['page_id']                     = (string) $context['checkout_id'];
	$wp->query_vars[ $endpoint_map[ $endpoint ] ] = (string) $order_id;
}
