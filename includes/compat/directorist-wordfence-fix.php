<?php
/**
 * Directorist Wordfence login compatibility (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_WORDFENCE_FIX' ) ) {
	return;
}
define( 'DLT_AF_LOADED_WORDFENCE_FIX', true );

$GLOBALS['directorist_wf_fix_login_actions'] = array(
	'ajaxlogin',
	'atbdp_ajax_quick_login',
	'directorist_ajax_quick_login',
);

add_filter( 'wp_redirect', 'directorist_wf_fix_intercept_redirect', 99, 2 );
add_filter( 'authenticate', 'directorist_wf_fix_allow_ajax_login', 9999, 3 );

/**
 * @param string $location URL.
 * @param int    $status   Status code.
 * @return string
 */
function directorist_wf_fix_intercept_redirect( $location, $status ) {
	if ( ! wp_doing_ajax() ) {
		return $location;
	}
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	if ( ! in_array( $action, $GLOBALS['directorist_wf_fix_login_actions'], true ) ) {
		return $location;
	}
	wp_send_json(
		array(
			'loggedin' => false,
			'message'  => esc_html__( 'Wrong username or password.', 'directorist' ),
		)
	);
}

/**
 * @param WP_User|WP_Error|null $user     User or error.
 * @param string                $username Username.
 * @param string                $password Password.
 * @return WP_User|WP_Error|null
 */
function directorist_wf_fix_allow_ajax_login( $user, $username, $password ) {
	if ( ! wp_doing_ajax() || ! is_wp_error( $user ) ) {
		return $user;
	}
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	if ( ! in_array( $action, $GLOBALS['directorist_wf_fix_login_actions'], true ) ) {
		return $user;
	}
	$login = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
	$pass  = isset( $_POST['password'] ) ? $_POST['password'] : '';
	if ( $login === '' || $pass === '' ) {
		return $user;
	}
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$u = get_user_by( 'email', $login );
	}
	if ( ! $u || ! wp_check_password( $pass, $u->user_pass, $u->ID ) ) {
		return $user;
	}
	return $u;
}
