<?php
/**
 * Fix builder title toggle save for ddoctors theme.
 *
 * The ddoctors theme adds custom toggles to the listing header builder with
 * hardcoded defaults (value => true). On reload, Vue.js merges these defaults
 * over the saved values. This fix patches the localized builder data right
 * before it reaches Vue, ensuring saved toggle states are correct.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'directorist_builder_localize_data', function ( $data ) {
	$term_id = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;
	if ( ! $term_id ) {
		return $data;
	}

	$saved = get_term_meta( $term_id, 'single_listing_header', true );
	if ( empty( $saved ) || ! is_array( $saved ) ) {
		return $data;
	}

	$saved_opts = isset( $saved['options']['content_settings']['listing_title'] )
		? $saved['options']['content_settings']['listing_title']
		: null;

	if ( ! is_array( $saved_opts ) ) {
		return $data;
	}

	$toggle_keys = array(
		'enable_title', 'enable_tagline', 'enable_category', 'enable_location',
		'enable_address', 'enable_gender', 'enable_age', 'enable_reviews',
		'enable_claim_listing', 'enable_accept_new_patient',
	);

	// Patch widget defaults.
	if ( isset( $data['fields']['single_listing_header']['widgets']['title']['options']['fields'] ) ) {
		foreach ( $toggle_keys as $key ) {
			if ( array_key_exists( $key, $saved_opts )
				&& isset( $data['fields']['single_listing_header']['widgets']['title']['options']['fields'][ $key ] )
			) {
				$v = $saved_opts[ $key ];
				$data['fields']['single_listing_header']['widgets']['title']['options']['fields'][ $key ]['value'] =
					( true === $v || 'true' === $v || 1 === $v || '1' === $v );
			}
		}
	}

	// Patch card-options defaults.
	if ( isset( $data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'] ) ) {
		foreach ( $toggle_keys as $key ) {
			if ( array_key_exists( $key, $saved_opts )
				&& isset( $data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'][ $key ] )
			) {
				$v = $saved_opts[ $key ];
				$data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'][ $key ]['value'] =
					( true === $v || 'true' === $v || 1 === $v || '1' === $v );
			}
		}
	}

	// Patch the stored value blob that Vue reads directly.
	if ( isset( $data['fields']['single_listing_header']['value']['options']['content_settings']['listing_title'] ) ) {
		foreach ( $toggle_keys as $key ) {
			if ( array_key_exists( $key, $saved_opts ) ) {
				$v = $saved_opts[ $key ];
				$data['fields']['single_listing_header']['value']['options']['content_settings']['listing_title'][ $key ] =
					( true === $v || 'true' === $v || 1 === $v || '1' === $v );
			}
		}
	}

	return $data;
}, 999 );
