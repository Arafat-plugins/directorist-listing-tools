<?php
/**
 * Directorist Pricing Plans tag field key fix.
 *
 * Some builder data stores the Tags preset field key as
 * tax_input[at_biz_dir-tags] instead of tax_input[at_biz_dir-tags][].
 * Pricing Plans expects the bracketed key and maps that to plan meta "tag".
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_DIRECTORIST_PRICING_PLANS_TAG_FIELD_KEY_FIX' ) ) {
	return;
}

define( 'DLT_DIRECTORIST_PRICING_PLANS_TAG_FIELD_KEY_FIX', true );

/**
 * Get Directorist listing type taxonomy.
 *
 * @return string
 */
function dlt_pp_tag_field_key_fix_get_listing_type_taxonomy() {
	if ( function_exists( 'dlt_get_listing_types_taxonomy' ) ) {
		return dlt_get_listing_types_taxonomy();
	}

	if ( defined( 'ATBDP_TYPE' ) ) {
		return ATBDP_TYPE;
	}

	return 'atbdp_listing_types';
}

/**
 * Check whether a field key is a Directorist tags field key variant.
 *
 * @param string $field_key Field key.
 * @return bool
 */
function dlt_pp_tag_field_key_fix_is_tag_key( $field_key ) {
	return in_array(
		(string) $field_key,
		array(
			'tax_input[at_biz_dir-tags]',
			'tax_input[at_biz_dir-tags][]',
		),
		true
	);
}

/**
 * Check whether a field definition is the tags preset.
 *
 * @param array $field Field definition.
 * @return bool
 */
function dlt_pp_tag_field_key_fix_is_tag_field( $field ) {
	if ( ! is_array( $field ) ) {
		return false;
	}

	$field_key   = isset( $field['field_key'] ) ? $field['field_key'] : '';
	$widget_name = isset( $field['widget_name'] ) ? $field['widget_name'] : '';
	$type        = isset( $field['type'] ) ? $field['type'] : '';

	return (
		dlt_pp_tag_field_key_fix_is_tag_key( $field_key ) ||
		in_array( $widget_name, array( 'tag', 'tags' ), true ) ||
		in_array( $type, array( 'tag', 'tags' ), true )
	);
}

/**
 * Normalize malformed Tags field key in a submission form definition.
 *
 * @param mixed $form Submission form definition.
 * @return mixed
 */
function dlt_pp_tag_field_key_fix_normalize_submission_form( $form ) {
	if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
		return $form;
	}

	foreach ( $form['fields'] as $index => $field ) {
		if ( ! dlt_pp_tag_field_key_fix_is_tag_field( $field ) ) {
			continue;
		}

		if ( ! isset( $form['fields'][ $index ] ) || ! is_array( $form['fields'][ $index ] ) ) {
			continue;
		}

		$form['fields'][ $index ]['field_key'] = 'tax_input[at_biz_dir-tags][]';
		$form['fields'][ $index ]['widget_name'] = 'tag';

		if ( empty( $form['fields'][ $index ]['widget_key'] ) ) {
			$form['fields'][ $index ]['widget_key'] = 'tag';
		}
	}

	return $form;
}

/**
 * Normalize submission_form_fields term meta before Directorist Pricing Plans reads it.
 *
 * @param mixed  $value     Existing short-circuit value.
 * @param int    $object_id Term ID.
 * @param string $meta_key  Meta key.
 * @param bool   $single    Whether a single value was requested.
 * @param string $meta_type Meta type.
 * @return mixed
 */
function dlt_pp_tag_field_key_fix_filter_submission_form_fields( $value, $object_id, $meta_key, $single, $meta_type ) {
	static $running = false;

	if ( null !== $value || $running || 'term' !== $meta_type || 'submission_form_fields' !== $meta_key ) {
		return $value;
	}

	$term = get_term( (int) $object_id );
	if ( ! $term || is_wp_error( $term ) || dlt_pp_tag_field_key_fix_get_listing_type_taxonomy() !== $term->taxonomy ) {
		return $value;
	}

	$running = true;
	$form    = get_term_meta( (int) $object_id, 'submission_form_fields', true );
	$running = false;

	if ( ! is_array( $form ) ) {
		return $value;
	}

	$normalized = dlt_pp_tag_field_key_fix_normalize_submission_form( $form );

	if ( $normalized === $form ) {
		return $value;
	}

	return $single ? array( $normalized ) : array( $normalized );
}
add_filter( 'get_term_metadata', 'dlt_pp_tag_field_key_fix_filter_submission_form_fields', 20, 5 );

/**
 * Normalize field data that may already have been loaded before term-meta filtering.
 *
 * @param array $field_data Field data.
 * @return array
 */
function dlt_pp_tag_field_key_fix_normalize_field_data( $field_data ) {
	if ( ! dlt_pp_tag_field_key_fix_is_tag_field( $field_data ) ) {
		return $field_data;
	}

	$field_data['field_key']   = 'tax_input[at_biz_dir-tags][]';
	$field_data['widget_name'] = 'tag';

	if ( empty( $field_data['widget_key'] ) ) {
		$field_data['widget_key'] = 'tag';
	}

	return $field_data;
}
add_filter( 'directorist_form_field_data', 'dlt_pp_tag_field_key_fix_normalize_field_data', 5 );
