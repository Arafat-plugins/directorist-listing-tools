<?php
/**
 * WPML RewriteBase fix (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_WPML_REWRITEBASE' ) ) {
	return;
}
define( 'DLT_AF_LOADED_WPML_REWRITEBASE', true );

/**
 * @param string $rules Rules string.
 * @return string
 */
function wpml_mu_fix_rewrite_base( $rules ) {
	$rules = preg_replace_callback(
		'/RewriteBase\s+([^\n\r]+)/',
		function ( $matches ) {
			$base = trim( $matches[1] );
			if ( $base !== '/' ) {
				return 'RewriteBase /';
			}
			return $matches[0];
		},
		$rules
	);

	$rules = preg_replace(
		'/RewriteRule\s+\.\s+\/([a-z]{2}(?:-[a-z]{2})?)\/index\.php\s+\[L\]/i',
		'RewriteRule . /index.php [L]',
		$rules
	);

	$rules = preg_replace(
		'/RewriteRule\s+\.\s+\/([a-z]{2}(?:_[a-z]{2})?)\/index\.php\s+\[L\]/i',
		'RewriteRule . /index.php [L]',
		$rules
	);

	$rules = preg_replace(
		'/\/([a-z]{2}(?:-[a-z]{2,3})?)\/index\.php/i',
		'/index.php',
		$rules
	);

	$rules = preg_replace(
		'/\/([a-z]{2}(?:_[a-z]{2,3})?)\/index\.php/i',
		'/index.php',
		$rules
	);

	$rules = preg_replace(
		'/(RewriteRule\s+[^\s]+\s+)\/([a-z]{2}(?:-[a-z]{2})?)\/wp-login\.php(\s+\[[^\]]+\])/i',
		'$1/wp-login.php$3',
		$rules
	);

	$rules = preg_replace(
		'/(RewriteRule\s+[^\s]+\s+)\/([a-z]{2}(?:_[a-z]{2})?)\/wp-login\.php(\s+\[[^\]]+\])/i',
		'$1/wp-login.php$3',
		$rules
	);

	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		global $sitepress;
		if ( $sitepress && is_object( $sitepress ) ) {
			$language_negotiation_type = (int) $sitepress->get_setting( 'language_negotiation_type' );
			if ( 2 === $language_negotiation_type ) {
				$active_languages = $sitepress->get_active_languages();
				if ( ! empty( $active_languages ) && is_array( $active_languages ) ) {
					$language_codes = array_keys( $active_languages );

					foreach ( $language_codes as $lang_code ) {
						$rules = str_replace(
							'/' . $lang_code . '/index.php',
							'/index.php',
							$rules
						);
						$rules = str_replace(
							'/' . $lang_code . '/wp-login.php',
							'/wp-login.php',
							$rules
						);

						$lang_variants = array(
							str_replace( '_', '-', $lang_code ),
							str_replace( '-', '_', $lang_code ),
						);

						foreach ( $lang_variants as $variant ) {
							if ( $variant !== $lang_code ) {
								$rules = str_replace(
									'/' . $variant . '/index.php',
									'/index.php',
									$rules
								);
								$rules = str_replace(
									'/' . $variant . '/wp-login.php',
									'/wp-login.php',
									$rules
								);
							}
						}
					}
				}
			}
		}
	}

	return $rules;
}

add_filter( 'mod_rewrite_rules', 'wpml_mu_fix_rewrite_base', 1, 1 );
add_filter( 'mod_rewrite_rules', 'wpml_mu_fix_rewrite_base', 999, 1 );
