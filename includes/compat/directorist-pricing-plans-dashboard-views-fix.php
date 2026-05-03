<?php
/**
 * Directorist Pricing Plans dashboard views fix.
 *
 * Scopes the heavy plan-change modal and dashboard tabs to the actual
 * Directorist user dashboard so Elementor preview/editor requests do not
 * render them on unrelated pages.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_AF_LOADED_DIRECTORIST_PRICING_PLANS_DASHBOARD_VIEWS_FIX' ) ) {
	return;
}
define( 'DLT_AF_LOADED_DIRECTORIST_PRICING_PLANS_DASHBOARD_VIEWS_FIX', true );

/**
 * Remove class callbacks registered on a hook and return the removed entries.
 *
 * @param string $hook_name Hook name.
 * @param string $class_name Class name.
 * @param string $method_name Method name.
 * @return array<int, array{object:object, priority:int}>
 */
function dlt_pricing_plans_views_fix_remove_class_callbacks( $hook_name, $class_name, $method_name ) {
	global $wp_filter;

	$removed = array();

	if ( empty( $wp_filter[ $hook_name ] ) || ! isset( $wp_filter[ $hook_name ]->callbacks ) ) {
		return $removed;
	}

	foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( empty( $callback['function'] ) || ! is_array( $callback['function'] ) ) {
				continue;
			}

			$object = $callback['function'][0];
			$method = isset( $callback['function'][1] ) ? $callback['function'][1] : null;

			if ( ! is_object( $object ) || ! ( $object instanceof $class_name ) || $method !== $method_name ) {
				continue;
			}

			remove_action( $hook_name, array( $object, $method ), $priority );
			remove_filter( $hook_name, array( $object, $method ), $priority );

			$removed[] = array(
				'object'   => $object,
				'priority' => (int) $priority,
			);
		}
	}

	return $removed;
}

/**
 * Detect Elementor editor/preview frontend requests.
 *
 * @return bool
 */
function dlt_pricing_plans_views_fix_is_elementor_request() {
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		return false;
	}

	$elementor = \Elementor\Plugin::$instance;

	if ( isset( $elementor->editor ) && method_exists( $elementor->editor, 'is_edit_mode' ) && $elementor->editor->is_edit_mode() ) {
		return true;
	}

	if ( isset( $elementor->preview ) && method_exists( $elementor->preview, 'is_preview_mode' ) && $elementor->preview->is_preview_mode() ) {
		return true;
	}

	return false;
}

/**
 * Check whether the current request is the Directorist user dashboard page.
 *
 * @return bool
 */
function dlt_pricing_plans_views_fix_is_dashboard_page() {
	if ( ! function_exists( 'get_directorist_option' ) ) {
		return false;
	}

	$dashboard_page_id = (int) get_directorist_option( 'user_dashboard' );

	return $dashboard_page_id > 0 && is_page( $dashboard_page_id );
}

/**
 * Scope the heavy ATPP_Views callbacks to the real dashboard page only.
 *
 * The pricing plans plugin registers these globally in some versions, which
 * makes Elementor-edited pages slow because the modal render performs
 * expensive pricing-plan queries on every page load.
 *
 * @return void
 */
function dlt_pricing_plans_views_fix_scope_hooks() {
	if ( ! class_exists( 'ATPP_Views' ) ) {
		return;
	}

	$modal_callbacks = dlt_pricing_plans_views_fix_remove_class_callbacks( 'wp_footer', 'ATPP_Views', 'atpp_plan_change_modal' );
	$tab_callbacks   = dlt_pricing_plans_views_fix_remove_class_callbacks( 'directorist_dashboard_tabs', 'ATPP_Views', 'directorist_dashboard_tabs' );

	if ( is_admin() || dlt_pricing_plans_views_fix_is_elementor_request() || ! dlt_pricing_plans_views_fix_is_dashboard_page() ) {
		return;
	}

	foreach ( $modal_callbacks as $callback ) {
		add_action( 'wp_footer', array( $callback['object'], 'atpp_plan_change_modal' ), $callback['priority'] );
	}

	foreach ( $tab_callbacks as $callback ) {
		add_action( 'directorist_dashboard_tabs', array( $callback['object'], 'directorist_dashboard_tabs' ), $callback['priority'] );
	}
}
add_action( 'wp', 'dlt_pricing_plans_views_fix_scope_hooks', 20 );
