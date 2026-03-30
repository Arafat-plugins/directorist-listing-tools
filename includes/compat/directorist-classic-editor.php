<?php
/**
 * Directorist Classic Editor Link Fix (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_CLASSIC_EDITOR' ) ) {
	return;
}
define( 'DLT_AF_LOADED_CLASSIC_EDITOR', true );

add_filter(
	'use_block_editor_for_post_type',
	function ( $use_block_editor, $post_type ) {
		if ( 'at_biz_dir' === $post_type ) {
			return false;
		}
		return $use_block_editor;
	},
	20,
	2
);

add_filter(
	'mce_buttons',
	function ( $buttons ) {
		if ( ! is_admin() ) {
			return $buttons;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'at_biz_dir' !== $screen->post_type ) {
			return $buttons;
		}
		foreach ( array( 'link', 'unlink' ) as $btn ) {
			if ( ! in_array( $btn, $buttons, true ) ) {
				$buttons[] = $btn;
			}
		}
		return $buttons;
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'at_biz_dir' !== $screen->post_type ) {
			return;
		}
		wp_enqueue_editor();
		wp_enqueue_script( 'editor' );
		wp_enqueue_script( 'wplink' );
		wp_enqueue_script( 'wpdialogs' );
		wp_enqueue_style( 'editor-buttons' );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_style( 'thickbox' );
		wp_enqueue_script( 'thickbox' );
	},
	20
);

function dlt_af_directorist_classic_editor_print_link_dialog() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'at_biz_dir' !== $screen->post_type ) {
		return;
	}
	if ( function_exists( 'wp_link_dialog' ) ) {
		wp_link_dialog();
	}
}
add_action( 'admin_footer-post.php', 'dlt_af_directorist_classic_editor_print_link_dialog' );
add_action( 'admin_footer-post-new.php', 'dlt_af_directorist_classic_editor_print_link_dialog' );

function dlt_af_directorist_classic_editor_force_wplink_js() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'at_biz_dir' !== $screen->post_type ) {
		return;
	}
	?>
	<script type="text/javascript">
	jQuery(function($) {
		if (typeof window.wpLink !== 'undefined' && typeof window.wpLink.init === 'function') {
			try { window.wpLink.init(); } catch (e) {}
		}
		$(document).on('click', '#wp-content-wrap .mce-i-link', function(event) {
			if (typeof window.wpLink === 'undefined' || typeof tinymce === 'undefined') {
				return;
			}
			var editor = tinymce.get('content');
			if (!editor) { return; }
			event.preventDefault();
			editor.focus();
			if (typeof window.wpLink.open === 'function') {
				window.wpLink.open('content');
			}
		});
	});
	</script>
	<?php
}
add_action( 'admin_footer-post.php', 'dlt_af_directorist_classic_editor_force_wplink_js', 20 );
add_action( 'admin_footer-post-new.php', 'dlt_af_directorist_classic_editor_force_wplink_js', 20 );
