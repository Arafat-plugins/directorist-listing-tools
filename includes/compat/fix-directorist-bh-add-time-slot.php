<?php
/**
 * Directorist Business Hours add time slot / Select2 fix (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_BH_TIME_SLOT' ) ) {
	return;
}
define( 'DLT_AF_LOADED_BH_TIME_SLOT', true );

add_action( 'admin_enqueue_scripts', 'dlt_af_fix_directorist_bh_add_time_slot', 999 );

/**
 * Dequeue WC Select2 on listing edit; patch Select2 destroy for BH script.
 */
function dlt_af_fix_directorist_bh_add_time_slot() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return;
	}
	$is_at_biz_dir = ( isset( $screen->post_type ) && $screen->post_type === 'at_biz_dir' )
		|| ( 'post' === $screen->base && isset( $_GET['post_type'] ) && sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) === 'at_biz_dir' );
	if ( ! $is_at_biz_dir ) {
		return;
	}

	if ( class_exists( 'WooCommerce' ) ) {
		wp_dequeue_script( 'selectWoo' );
		wp_dequeue_script( 'wc-select2' );
		wp_dequeue_script( 'select2' );
		wp_dequeue_style( 'select2' );
		wp_deregister_script( 'selectWoo' );
		wp_deregister_script( 'wc-select2' );
		wp_deregister_script( 'select2' );
		wp_deregister_style( 'select2' );
	}

	$patch = "(function($){if(typeof $.fn.select2==='undefined')return;var o=$.fn.select2;$.fn.select2=function(){if(arguments[0]==='destroy'&&this.length&&!this.data('select2'))return this;return o.apply(this,arguments);};})(jQuery);";
	wp_add_inline_script( 'directorist-select2-script', $patch, 'after' );
	wp_add_inline_script( 'bdbh_main_script', $patch, 'before' );
}
