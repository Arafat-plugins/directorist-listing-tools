<?php
/**
 * Fix builder title toggle save for ddoctors theme.
 *
 * The ddoctors theme adds custom toggles to the listing header builder
 * via directorist_listing_header_layout filter with hardcoded defaults.
 * On reload the defaults overwrite saved values. This fix re-applies
 * saved term_meta values at priority 999 so they win.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', function () {
	if ( empty( $_GET['page'] ) || 'atbdp-directory-types' !== $_GET['page'] ) {
		return;
	}
	$term_id = ! empty( $_GET['listing_type_id'] ) ? absint( $_GET['listing_type_id'] ) : 0;
	if ( ! $term_id ) {
		return;
	}

	add_filter( 'directorist_listing_header_layout', function ( $fields ) use ( $term_id ) {
		$saved = get_term_meta( $term_id, 'single_listing_header', true );
		if ( empty( $saved ) || ! is_array( $saved ) ) {
			return $fields;
		}

		$saved_opts = ! empty( $saved['options']['content_settings']['listing_title'] )
			? $saved['options']['content_settings']['listing_title']
			: array();

		if ( empty( $saved_opts ) || ! is_array( $saved_opts ) ) {
			return $fields;
		}

		$toggle_keys = array(
			'enable_title', 'enable_tagline', 'enable_category', 'enable_location',
			'enable_address', 'enable_gender', 'enable_age', 'enable_reviews',
			'enable_claim_listing', 'enable_accept_new_patient',
		);

		if ( ! empty( $fields['widgets']['title']['options']['fields'] ) ) {
			foreach ( $toggle_keys as $key ) {
				if ( array_key_exists( $key, $saved_opts )
					&& isset( $fields['widgets']['title']['options']['fields'][ $key ] )
				) {
					$v = $saved_opts[ $key ];
					$fields['widgets']['title']['options']['fields'][ $key ]['value'] =
						( true === $v || 'true' === $v || 1 === $v || '1' === $v );
				}
			}
		}

		if ( ! empty( $fields['card-options']['content_settings']['listing_title']['options']['fields'] ) ) {
			foreach ( $toggle_keys as $key ) {
				if ( array_key_exists( $key, $saved_opts )
					&& isset( $fields['card-options']['content_settings']['listing_title']['options']['fields'][ $key ] )
				) {
					$v = $saved_opts[ $key ];
					$fields['card-options']['content_settings']['listing_title']['options']['fields'][ $key ]['value'] =
						( true === $v || 'true' === $v || 1 === $v || '1' === $v );
				}
			}
		}

		return $fields;
	}, 999 );
} );
