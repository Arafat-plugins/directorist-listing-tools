<?php
/**
 * WooCommerce Pricing Plans: checkout bridge for atbdp_listing_id + empty cart / empty Directorist checkout form.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap hooks (call once).
 */
function dlt_af_bootstrap_wc_plan_checkout_bridge() {
	if ( defined( 'DLT_AF_WC_CHECKOUT_BRIDGE_BOOTSTRAPPED' ) ) {
		return;
	}
	define( 'DLT_AF_WC_CHECKOUT_BRIDGE_BOOTSTRAPPED', true );

	add_action( 'wp', 'dlt_af_wcpp_map_get_listing_to_query_var', 0 );
	add_action( 'template_redirect', 'dlt_af_wcpp_maybe_fill_cart_from_listing', 20 );
	add_filter( 'atbdp_checkout_form_data', 'dlt_af_wcpp_checkout_form_data', 20, 2 );
	add_filter( 'directorist_checkout_guard', 'dlt_af_wcpp_relax_checkout_guard', 20 );
}

/**
 * @return bool
 */
function dlt_af_wcpp_fee_manager_on() {
	return function_exists( 'is_fee_manager_active' ) && is_fee_manager_active();
}

/**
 * @return string
 */
function dlt_af_wcpp_listing_type() {
	return defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
}

/**
 * @param int $plan_id Plan ID.
 * @return int
 */
function dlt_af_wcpp_resolve_product_id( $plan_id ) {
	$plan_id = (int) $plan_id;
	if ( $plan_id <= 0 ) {
		return 0;
	}
	if ( function_exists( 'directoirst_wc_plan_auto_renewal' ) ) {
		$renewal = directoirst_wc_plan_auto_renewal( $plan_id );
		if ( ! empty( $renewal ) ) {
			return (int) $renewal;
		}
	}
	return $plan_id;
}

/**
 * @param int $listing_id Listing ID.
 * @return bool
 */
function dlt_af_wcpp_user_may_pay_for_listing( $listing_id ) {
	$listing_id = (int) $listing_id;
	if ( $listing_id <= 0 ) {
		return false;
	}
	$post = get_post( $listing_id );
	if ( ! $post || $post->post_type !== dlt_af_wcpp_listing_type() ) {
		return false;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( current_user_can( 'edit_others_posts' ) || current_user_can( 'manage_options' ) ) {
		return true;
	}
	return (int) $post->post_author === get_current_user_id();
}

/**
 * @return int
 */
function dlt_af_wcpp_get_listing_id_from_request() {
	if ( isset( $_GET['atbdp_listing_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return absint( wp_unslash( $_GET['atbdp_listing_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	$qv = get_query_var( 'atbdp_listing_id' );
	return $qv ? absint( $qv ) : 0;
}

/**
 * Fill WooCommerce cart from listing plan when needed.
 */
function dlt_af_wcpp_maybe_fill_cart_from_listing() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	if ( ! dlt_af_wcpp_fee_manager_on() ) {
		return;
	}
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	$listing_id = dlt_af_wcpp_get_listing_id_from_request();
	if ( ! $listing_id || ! dlt_af_wcpp_user_may_pay_for_listing( $listing_id ) ) {
		return;
	}

	$plan_id = (int) get_post_meta( $listing_id, '_fm_plans', true );
	if ( isset( $_GET['plan'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$from_get = absint( wp_unslash( $_GET['plan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $from_get > 0 ) {
			$plan_id = $from_get;
		}
	}

	$product_id = dlt_af_wcpp_resolve_product_id( $plan_id );
	if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
		return;
	}

	$cart_has_plan = false;
	foreach ( WC()->cart->get_cart() as $item ) {
		$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		$vid = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
		if ( $pid === $product_id || $vid === $product_id || $pid === (int) $plan_id ) {
			$cart_has_plan = true;
			break;
		}
	}

	if ( $cart_has_plan ) {
		return;
	}

	WC()->cart->empty_cart();
	WC()->session->set( 'cart', array() );
	WC()->cart->add_to_cart( $product_id );
}

/**
 * Map GET listing id to main query var for Directorist checkout.
 */
function dlt_af_wcpp_map_get_listing_to_query_var() {
	if ( ! isset( $_GET['atbdp_listing_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$id = absint( wp_unslash( $_GET['atbdp_listing_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $id && function_exists( 'set_query_var' ) ) {
		set_query_var( 'atbdp_listing_id', $id );
	}
}

/**
 * @param array $form_data Form rows.
 * @param int   $listing_id Listing ID.
 * @return array
 */
function dlt_af_wcpp_checkout_form_data( $form_data, $listing_id ) {
	if ( ! empty( $form_data ) ) {
		return $form_data;
	}
	if ( ! dlt_af_wcpp_fee_manager_on() ) {
		return $form_data;
	}
	$listing_id = (int) $listing_id;
	if ( $listing_id <= 0 ) {
		return $form_data;
	}
	if ( get_post_type( $listing_id ) !== dlt_af_wcpp_listing_type() ) {
		return $form_data;
	}

	$plan_id = (int) get_post_meta( $listing_id, '_fm_plans', true );
	if ( $plan_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
		return $form_data;
	}

	$product = wc_get_product( dlt_af_wcpp_resolve_product_id( $plan_id ) );
	if ( ! $product ) {
		$product = wc_get_product( $plan_id );
	}
	if ( ! $product ) {
		return $form_data;
	}

	$title = $product->get_name();
	$price = $product->get_price();
	$desc  = __( 'Pricing plan for your listing.', 'directorist-listing-tools' );

	$form_data[] = array(
		'type'     => 'header',
		'title'    => $title,
		'name'     => 'dwpp_plan',
		'value'    => 1,
		'selected' => 1,
		'desc'     => $desc,
		'price'    => $price,
	);
	$form_data[] = array(
		'type'     => 'checkbox',
		'name'     => 'dwpp_plan',
		'value'    => $price,
		'selected' => 1,
		'title'    => $title,
		'desc'     => '',
		'price'    => $price,
	);

	return $form_data;
}

/**
 * @param bool $guard Guard state.
 * @return bool
 */
function dlt_af_wcpp_relax_checkout_guard( $guard ) {
	if ( ! $guard ) {
		return $guard;
	}
	$from_get = isset( $_GET['atbdp_listing_id'] ) ? absint( wp_unslash( $_GET['atbdp_listing_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $from_get ) {
		return $guard;
	}
	if ( get_post_type( $from_get ) !== dlt_af_wcpp_listing_type() ) {
		return $guard;
	}
	if ( ! dlt_af_wcpp_user_may_pay_for_listing( $from_get ) ) {
		return $guard;
	}
	return false;
}
