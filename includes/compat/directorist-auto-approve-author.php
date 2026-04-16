<?php
/**
 * Directorist auto-approve author registration.
 *
 * When a user registers and selects the Author type, Directorist sets
 * _user_type = 'become_author' (pending). This compat file catches that
 * meta write and immediately upgrades it to 'author', so no admin
 * approve/deny step is required.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'added_user_meta',   'dlt_compat_auto_approve_author', 10, 4 );
add_action( 'updated_user_meta', 'dlt_compat_auto_approve_author', 10, 4 );

/**
 * @param int    $meta_id    Meta row ID.
 * @param int    $user_id    User ID.
 * @param string $meta_key   Meta key being written.
 * @param mixed  $meta_value Value being saved.
 */
function dlt_compat_auto_approve_author( $meta_id, $user_id, $meta_key, $meta_value ) {
	if ( '_user_type' !== $meta_key || 'become_author' !== $meta_value ) {
		return;
	}

	// Detach temporarily to prevent an infinite loop on the update below.
	remove_action( 'updated_user_meta', 'dlt_compat_auto_approve_author', 10 );

	update_user_meta( (int) $user_id, '_user_type', 'author' );

	add_action( 'updated_user_meta', 'dlt_compat_auto_approve_author', 10, 4 );
}
