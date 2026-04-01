<?php
/**
 * DList listing hero background lazy-load fix (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_DLIST_BG_LAZYFIX' ) ) {
	return;
}
define( 'DLT_AF_LOADED_DLIST_BG_LAZYFIX', true );

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() ) {
			return;
		}
		$deps = array( 'jquery' );
		if ( wp_script_is( 'dlist-main', 'registered' ) ) {
			$deps[] = 'dlist-main';
		}
		wp_register_script(
			'dlist-listing-bg-lazyfix',
			false,
			$deps,
			'1.0.0',
			true
		);
		wp_enqueue_script( 'dlist-listing-bg-lazyfix' );

		$js = <<<'JS'
(function ($) {
	function isPlaceholderSrc(url) {
		if (!url || typeof url !== 'string') {
			return true;
		}
		var u = url.trim().toLowerCase();
		return u.indexOf('data:image/svg+xml') === 0 || u.indexOf('data:image/gif;base64') === 0;
	}
	function resolveRealImageUrl($img) {
		var attrs = ['data-src', 'data-lazy-src', 'data-original', 'data-litespeed-src'];
		var i, v;
		for (i = 0; i < attrs.length; i++) {
			v = $img.attr(attrs[i]);
			if (v && !isPlaceholderSrc(v) && v.indexOf('http') === 0) {
				return v.trim();
			}
		}
		for (i = 0; i < attrs.length; i++) {
			v = $img.attr(attrs[i]);
			if (v && !isPlaceholderSrc(v)) {
				return v.trim();
			}
		}
		var src = $img.attr('src');
		if (src && !isPlaceholderSrc(src)) {
			return src.trim();
		}
		if ($img[0] && $img[0].currentSrc && !isPlaceholderSrc($img[0].currentSrc)) {
			return $img[0].currentSrc;
		}
		return '';
	}
	function applyListingBg($holder) {
		var $img = $holder.children('img').first();
		if (!$img.length) {
			return;
		}
		var url = resolveRealImageUrl($img);
		if (!url) {
			return;
		}
		var safe = url.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
		$holder.css({
			'background-image': 'url("' + safe + '")',
			opacity: '1'
		});
		$img.attr('alt', url);
	}
	function run() {
		$('.listing-details-wrapper.bgimage .bg_image_holder, .header-breadcrumb.bgimage .bg_image_holder').each(function () {
			applyListingBg($(this));
		});
	}
	$(function () {
		run();
	});
	$(document).on('lazyloaded', '.listing-details-wrapper .bg_image_holder img, .header-breadcrumb .bg_image_holder img', function () {
		applyListingBg($(this).closest('.bg_image_holder'));
	});
})(jQuery);
JS;

		wp_add_inline_script( 'dlist-listing-bg-lazyfix', $js );
	},
	100
);
