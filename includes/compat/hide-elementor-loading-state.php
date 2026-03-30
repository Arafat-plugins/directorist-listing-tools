<?php
/**
 * Hide Elementor editor loading overlay (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_HIDE_ELEMENTOR_LOADING' ) ) {
	return;
}
define( 'DLT_AF_LOADED_HIDE_ELEMENTOR_LOADING', true );

add_action(
	'elementor/editor/after_enqueue_styles',
	function () {
		$css = 'body.elementor-panel-loading #elementor-panel-state-loading { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }';
		wp_add_inline_style( 'elementor-editor', $css );
	},
	9999
);

add_action(
	'elementor/editor/footer',
	function () {
		$css = 'body.elementor-panel-loading #elementor-panel-state-loading { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }';
		echo "\n<style id=\"hide-elementor-loading-state-editor\">" . esc_html( $css ) . "</style>\n";
	},
	99999
);

add_action(
	'elementor/editor/after_enqueue_scripts',
	function () {
		$script = <<<'JS'
(function() {
	function hide(el) {
		if (el && el.style) {
			el.style.setProperty('display', 'none', 'important');
			el.style.setProperty('visibility', 'hidden', 'important');
			el.style.setProperty('opacity', '0', 'important');
			el.style.setProperty('pointer-events', 'none', 'important');
		}
	}
	function run() {
		hide(document.getElementById('elementor-panel-state-loading'));
	}
	run();
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', run);
	}
	setTimeout(run, 50);
	setTimeout(run, 200);
	setTimeout(run, 500);
	var observer = new MutationObserver(function() { run(); });
	observer.observe(document.body, { childList: true, subtree: true });
})();
JS;
		wp_add_inline_script( 'elementor-editor', $script, 'after' );
	},
	9999
);

add_action(
	'admin_footer',
	function () {
		$css = 'body.elementor-panel-loading #elementor-panel-state-loading { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }';
		echo "\n<style id=\"hide-elementor-loading-state-css\">" . esc_html( $css ) . "</style>\n";
	},
	99999
);
add_action(
	'wp_footer',
	function () {
		$css = 'body.elementor-panel-loading #elementor-panel-state-loading { display: none !important; visibility: hidden !important; opacity: 0 !important; pointer-events: none !important; }';
		echo "\n<style id=\"hide-elementor-loading-state-css\">" . esc_html( $css ) . "</style>\n";
	},
	99999
);
