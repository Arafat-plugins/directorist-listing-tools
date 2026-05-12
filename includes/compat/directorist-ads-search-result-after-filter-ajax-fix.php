<?php
/**
 * Keep Directorist Ads Manager search-result after-filter ads in AJAX results.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_DIRECTORIST_ADS_SEARCH_RESULT_AFTER_FILTER_AJAX_FIX' ) ) {
	return;
}

define( 'DLT_DIRECTORIST_ADS_SEARCH_RESULT_AFTER_FILTER_AJAX_FIX', true );

/**
 * Check whether the current request is Directorist refreshing the Search Result page.
 *
 * Directorist Ads Manager checks get_queried_object_id() before rendering this slot.
 * During admin-ajax.php that ID is empty, so the ad is present on first paint but
 * missing from the refreshed archive markup.
 *
 * @return bool
 */
function dlt_compat_directorist_ads_is_search_result_ajax() {
	if ( ! function_exists( 'wp_doing_ajax' ) || ! wp_doing_ajax() ) {
		return false;
	}

	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( 'directorist_instant_search' !== $action ) {
		return false;
	}

	$data_atts = isset( $_POST['data_atts'] ) ? wp_unslash( $_POST['data_atts'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( is_array( $data_atts ) ) {
		$current_page = isset( $data_atts['_current_page'] ) ? sanitize_key( $data_atts['_current_page'] ) : '';
		if ( 'search_result' === $current_page ) {
			return true;
		}
	}

	$current_page_id       = isset( $_POST['current_page_id'] ) ? absint( wp_unslash( $_POST['current_page_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$search_result_page_id = function_exists( 'get_directorist_option' ) ? absint( get_directorist_option( 'search_result_page' ) ) : 0;

	return $search_result_page_id && $current_page_id === $search_result_page_id;
}

/**
 * Render the same markup Directorist Ads Manager renders on normal page loads.
 *
 * @return void
 */
function dlt_compat_directorist_ads_render_search_result_after_filter_ajax() {
	static $rendered = false;

	if ( $rendered || ! dlt_compat_directorist_ads_is_search_result_ajax() ) {
		return;
	}

	if ( ! class_exists( 'SWBDPAMHelperFunctions' ) ) {
		return;
	}

	$ad_id = SWBDPAMHelperFunctions::get_ad_id( 'search_result', 'swbdpam_search_result_ad_places', 'after-filter' );
	if ( empty( $ad_id ) ) {
		return;
	}

	$rendered = true;
	?>
	<div class="directorist-s-result-ad-after-filter">
		<?php echo SWBDPAMHelperFunctions::display_ad( $ad_id, 'search_result', 'after-filter' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
}

add_action( 'atbdp_before_grid_listings_loop', 'dlt_compat_directorist_ads_render_search_result_after_filter_ajax' );
add_action( 'directorist_before_grid_listings_loop', 'dlt_compat_directorist_ads_render_search_result_after_filter_ajax' );
add_action( 'atbdp_before_list_listings_loop', 'dlt_compat_directorist_ads_render_search_result_after_filter_ajax' );
add_action( 'directorist_before_list_listings_loop', 'dlt_compat_directorist_ads_render_search_result_after_filter_ajax' );
