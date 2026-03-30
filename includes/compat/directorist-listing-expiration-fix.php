<?php
/**
 * Directorist listing expiration fixes (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_LISTING_EXPIRATION_FIX' ) ) {
	return;
}
define( 'DLT_AF_LOADED_LISTING_EXPIRATION_FIX', true );

add_action( 'atbdp_listing_expired', 'directorist_expiration_fix_set_expired_meta', 20, 1 );

/**
 * @param int $listing_id Listing ID.
 */
function directorist_expiration_fix_set_expired_meta( $listing_id ) {
	if ( ! $listing_id || ! defined( 'ATBDP_POST_TYPE' ) ) {
		return;
	}
	if ( get_post_type( $listing_id ) !== ATBDP_POST_TYPE ) {
		return;
	}
	update_post_meta( $listing_id, '_listing_status', 'expired' );
	update_post_meta( $listing_id, '_featured', 0 );
	update_post_meta( $listing_id, '_renewal_reminder_sent', 0 );
}

add_action( 'atbdp_schedule_task', 'directorist_expiration_fix_nearly_expired_catch_all', 20 );

/**
 * Supplemental nearly-expired catch-all after cron task.
 */
function directorist_expiration_fix_nearly_expired_catch_all() {
	if ( ! defined( 'ATBDP_POST_TYPE' ) || ! function_exists( 'get_directorist_option' ) ) {
		return;
	}

	$renew_email_threshold = get_directorist_option( 'email_to_expire_day' );
	if ( empty( $renew_email_threshold ) || (int) $renew_email_threshold <= 0 ) {
		return;
	}

	$renew_email_threshold = (int) $renew_email_threshold;
	$threshold_date        = date( 'Y-m-d H:i:s', strtotime( '+' . $renew_email_threshold . ' days' ) );

	$args = array(
		'post_type'      => ATBDP_POST_TYPE,
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'cache_results'  => false,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_never_expire',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_expiry_date',
				'value'   => $threshold_date,
				'compare' => '<=',
				'type'    => 'DATETIME',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_listing_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_listing_status',
					'value'   => 'renewal',
					'compare' => '!=',
				),
			),
		),
	);

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return;
	}

	foreach ( $query->posts as $listing_id ) {
		update_post_meta( $listing_id, '_listing_status', 'renewal' );
		do_action( 'atbdp_status_updated_to_renewal', $listing_id );
	}
}
