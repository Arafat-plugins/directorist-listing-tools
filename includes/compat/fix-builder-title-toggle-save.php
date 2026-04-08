<?php
/**
 * Fix builder title toggle save for ddoctors theme.
 *
 * Saved data is a flat layout array. The "title" widget lives inside
 * selectedWidgets with toggle values as flat keys ("1" or "").
 * We extract saved values and patch widget defaults so Vue shows
 * the correct state on reload.
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

	// Find the "title" widget inside the saved layout's selectedWidgets.
	$saved_title_widget = null;
	foreach ( $saved as $placeholder ) {
		if ( empty( $placeholder['selectedWidgets'] ) || ! is_array( $placeholder['selectedWidgets'] ) ) {
			continue;
		}
		foreach ( $placeholder['selectedWidgets'] as $widget ) {
			if ( ! empty( $widget['widget_name'] ) && 'title' === $widget['widget_name'] ) {
				$saved_title_widget = $widget;
				break 2;
			}
		}
	}

	if ( ! $saved_title_widget ) {
		return $data;
	}

	$toggle_keys = array(
		'enable_title', 'enable_tagline', 'enable_category', 'enable_location',
		'enable_address', 'enable_gender', 'enable_age', 'enable_reviews',
		'enable_claim_listing', 'enable_accept_new_patient',
	);

	// Extract saved toggle values from widget root flat keys.
	$saved_vals = array();
	foreach ( $toggle_keys as $key ) {
		if ( array_key_exists( $key, $saved_title_widget ) ) {
			$v = $saved_title_widget[ $key ];
			$saved_vals[ $key ] = ( '1' === $v || 1 === $v || true === $v || 'true' === $v );
		}
	}

	if ( empty( $saved_vals ) ) {
		return $data;
	}

	// Patch 1: widget defaults.
	if ( isset( $data['fields']['single_listing_header']['widgets']['title']['options']['fields'] ) ) {
		foreach ( $saved_vals as $key => $bool ) {
			if ( isset( $data['fields']['single_listing_header']['widgets']['title']['options']['fields'][ $key ] ) ) {
				$data['fields']['single_listing_header']['widgets']['title']['options']['fields'][ $key ]['value'] = $bool;
			}
		}
	}

	// Patch 2: card-options defaults.
	if ( isset( $data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'] ) ) {
		foreach ( $saved_vals as $key => $bool ) {
			if ( isset( $data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'][ $key ] ) ) {
				$data['fields']['single_listing_header']['card-options']['content_settings']['listing_title']['options']['fields'][ $key ]['value'] = $bool;
			}
		}
	}

	// Patch 3: saved value blob (layout with selectedWidgets).
	if ( isset( $data['fields']['single_listing_header']['value'] ) && is_array( $data['fields']['single_listing_header']['value'] ) ) {
		foreach ( $data['fields']['single_listing_header']['value'] as &$ph ) {
			if ( empty( $ph['selectedWidgets'] ) || ! is_array( $ph['selectedWidgets'] ) ) {
				continue;
			}
			foreach ( $ph['selectedWidgets'] as &$w ) {
				if ( ! empty( $w['widget_name'] ) && 'title' === $w['widget_name'] ) {
					foreach ( $saved_vals as $key => $bool ) {
						$w[ $key ] = $bool;
					}
					if ( isset( $w['options']['fields'] ) ) {
						foreach ( $saved_vals as $key => $bool ) {
							if ( isset( $w['options']['fields'][ $key ] ) ) {
								if ( is_array( $w['options']['fields'][ $key ] ) ) {
									$w['options']['fields'][ $key ]['value'] = $bool;
								} else {
									$w['options']['fields'][ $key ] = $bool;
								}
							}
						}
					}
				}
			}
			unset( $w );
		}
		unset( $ph );
	}

	return $data;
}, 999 );
