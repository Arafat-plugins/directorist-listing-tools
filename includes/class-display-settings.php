<?php
/**
 * Display Settings Class
 *
 * Provides a sub-page to toggle all Directorist listing display options
 * for All Listings and Single Listing via AJAX toggles.
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display Settings handler class.
 */
class Directorist_Listing_Tools_Display_Settings {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Display_Settings
	 */
	private static $instance = null;

	/**
	 * All toggle settings definitions.
	 * key => [ label, default, section, description ]
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Section definitions.
	 *
	 * @var array
	 */
	private $sections = array();

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Display_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_sections();
		$this->define_settings();
		// Global display setting toggles.
		add_action( 'wp_ajax_dlt_toggle_display_setting',        array( $this, 'handle_ajax_toggle' ) );
		// Per-directory type setting toggles.
		add_action( 'wp_ajax_dlt_load_directory_type_settings',  array( $this, 'handle_ajax_load_directory_type_settings' ) );
		add_action( 'wp_ajax_dlt_toggle_directory_type_setting', array( $this, 'handle_ajax_toggle_directory_type_setting' ) );
		// Inject saved option values into Directorist shortcode defaults.
		add_filter( 'atbdp_all_listings_params', array( $this, 'inject_saved_display_params' ), 10, 1 );
	}

	// ────────────────────────────────────────────────────────────────
	// Directory Type Helpers
	// ────────────────────────────────────────────────────────────────

	/**
	 * Get all Directorist directory type terms.
	 *
	 * @return WP_Term[]
	 */
	private function get_directory_types() {
		$terms = get_terms(
			array(
				'taxonomy'   => dlt_get_listing_types_taxonomy(),
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		return ( is_wp_error( $terms ) || empty( $terms ) ) ? array() : $terms;
	}

	/**
	 * Get per-directory display settings (thumbnail active_template) for a term.
	 *
	 * @param int $term_id Directory type term ID.
	 * @return array
	 */
	private function get_directory_type_display_settings( $term_id ) {
		$grid_meta = get_term_meta( $term_id, 'listings_card_grid_view', true );
		$list_meta = get_term_meta( $term_id, 'listings_card_list_view', true );

		$grid_template = ! empty( $grid_meta['active_template'] ) ? $grid_meta['active_template'] : '';
		$list_template = ! empty( $list_meta['active_template'] ) ? $list_meta['active_template'] : '';

		return array(
			array(
				'key'         => 'grid_thumbnail',
				'label'       => __( 'Grid View — Show Thumbnail Image', 'directorist-listing-tools' ),
				'description' => __( 'When ON, grid listing cards show a thumbnail/preview image. When OFF, cards switch to the compact "no thumbnail" layout.', 'directorist-listing-tools' ),
				'value'       => ( $grid_template === 'grid_view_with_thumbnail' ),
				'meta_label'  => $grid_template ?: __( '(not set — defaults to with thumbnail)', 'directorist-listing-tools' ),
			),
			array(
				'key'         => 'list_thumbnail',
				'label'       => __( 'List View — Show Thumbnail Image', 'directorist-listing-tools' ),
				'description' => __( 'When ON, list view cards show a thumbnail/preview image. When OFF, cards switch to the compact "no thumbnail" layout.', 'directorist-listing-tools' ),
				'value'       => ( $list_template === 'list_view_with_thumbnail' ),
				'meta_label'  => $list_template ?: __( '(not set — defaults to with thumbnail)', 'directorist-listing-tools' ),
			),
		);
	}

	// ────────────────────────────────────────────────────────────────
	// Per-Directory AJAX Handlers
	// ────────────────────────────────────────────────────────────────

	/**
	 * AJAX: Load per-directory display settings for a given directory type.
	 */
	public function handle_ajax_load_directory_type_settings() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ) );
		}

		$term_id = absint( isset( $_POST['term_id'] ) ? $_POST['term_id'] : 0 );
		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid directory type ID.', 'directorist-listing-tools' ) ) );
		}

		$term = get_term( $term_id, dlt_get_listing_types_taxonomy() );
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Directory type not found.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success(
			array(
				'term_id'  => $term_id,
				'name'     => $term->name,
				'settings' => $this->get_directory_type_display_settings( $term_id ),
			)
		);
	}

	/**
	 * AJAX: Toggle a per-directory setting (active_template in term meta).
	 */
	public function handle_ajax_toggle_directory_type_setting() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ) );
		}

		$term_id     = absint( isset( $_POST['term_id'] ) ? $_POST['term_id'] : 0 );
		$setting_key = isset( $_POST['setting_key'] ) ? sanitize_key( $_POST['setting_key'] ) : '';
		$value       = isset( $_POST['value'] ) ? rest_sanitize_boolean( $_POST['value'] ) : false;

		if ( ! $term_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid directory type ID.', 'directorist-listing-tools' ) ) );
		}

		$allowed = array( 'grid_thumbnail', 'list_thumbnail' );
		if ( ! in_array( $setting_key, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid setting key.', 'directorist-listing-tools' ) ) );
		}

		if ( 'grid_thumbnail' === $setting_key ) {
			$meta_key         = 'listings_card_grid_view';
			$with_template    = 'grid_view_with_thumbnail';
			$without_template = 'grid_view_without_thumbnail';
			$label            = __( 'Grid View Thumbnail', 'directorist-listing-tools' );
		} else {
			$meta_key         = 'listings_card_list_view';
			$with_template    = 'list_view_with_thumbnail';
			$without_template = 'list_view_without_thumbnail';
			$label            = __( 'List View Thumbnail', 'directorist-listing-tools' );
		}

		$new_template = $value ? $with_template : $without_template;

		$current = get_term_meta( $term_id, $meta_key, true );
		if ( empty( $current ) || ! is_array( $current ) ) {
			$current = array();
		}
		$current['active_template'] = $new_template;
		update_term_meta( $term_id, $meta_key, $current );

		$term   = get_term( $term_id, dlt_get_listing_types_taxonomy() );
		$name   = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $term_id;
		$status = $value ? __( 'Enabled', 'directorist-listing-tools' ) : __( 'Disabled', 'directorist-listing-tools' );

		wp_send_json_success(
			array(
				'message'     => sprintf(
					/* translators: 1: setting label, 2: directory name, 3: enabled/disabled */
					__( '"%1$s" for directory "%2$s" has been %3$s.', 'directorist-listing-tools' ),
					$label,
					$name,
					$status
				),
				'setting_key' => $setting_key,
				'value'       => $value,
				'term_id'     => $term_id,
				'meta_label'  => $new_template,
			)
		);
	}

	// ────────────────────────────────────────────────────────────────
	// Frontend Filter: inject saved options into Directorist defaults
	// ────────────────────────────────────────────────────────────────

	/**
	 * Inject saved display options into Directorist All Listings shortcode defaults.
	 * Directorist hardcodes params like display_preview_image => 'yes' and does not
	 * read them from atbdp_option, so we override them here via the filter.
	 *
	 * @param array $defaults Shortcode param defaults.
	 * @return array
	 */
	public function inject_saved_display_params( $defaults ) {
		if ( ! function_exists( 'get_directorist_option' ) ) {
			return $defaults;
		}

		// yes/no params we manage via atbdp_option.
		$yes_no_params = array(
			'display_preview_image', 'display_listings_header', 'listing_filters_button',
			'display_sort_by', 'display_view_as', 'display_listings_count', 'display_title',
			'display_author_image', 'display_tagline_field', 'enable_review', 'display_contact_info',
			'display_phone', 'display_website', 'display_category', 'display_view_count',
			'display_mark_as_fav', 'display_feature_badge_cart', 'display_popular_badge',
			'display_new_badge', 'enable_excerpt', 'display_publish_date', 'display_readmore',
			'info_display_in_single_line', 'display_map_info', 'display_image_map',
			'display_title_map', 'display_address_map', 'display_direction_map',
			'display_favorite_badge_map', 'display_user_avatar_map', 'display_review_map',
			'display_price_map', 'display_phone_map',
		);

		foreach ( $yes_no_params as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$setting = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : array( 'default' => 1 );
			$raw     = get_directorist_option( $key, $setting['default'] );
			$defaults[ $key ] = ! empty( $raw ) ? 'yes' : 'no';
		}

		// Inverse: stored as disable_* (1 = hide).
		$inverse_params = array( 'disable_list_price', 'disable_single_listing', 'disable_contact_info' );
		foreach ( $inverse_params as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			$setting = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : array( 'default' => 0 );
			$raw     = get_directorist_option( $key, $setting['default'] );
			$defaults[ $key ] = ! empty( $raw ) ? 'yes' : 'no';
		}

		return $defaults;
	}

	/**
	 * Define section labels and icons.
	 */
	private function define_sections() {
		$this->sections = array(
			'all_listings_header' => array(
				'label'       => __( 'All Listings — Header & Navigation', 'directorist-listing-tools' ),
				'description' => __( 'Controls the header bar, filters, sorting, and view-switching options shown above the listings grid/list.', 'directorist-listing-tools' ),
				'icon'        => 'dashicons-menu-alt',
			),
			'card_display'        => array(
				'label'       => __( 'All Listings — Card Display', 'directorist-listing-tools' ),
				'description' => __( 'Controls what information is shown on each listing card in grid and list views.', 'directorist-listing-tools' ),
				'icon'        => 'dashicons-grid-view',
			),
			'single_listing'      => array(
				'label'       => __( 'Single Listing', 'directorist-listing-tools' ),
				'description' => __( 'Controls options for the individual listing detail page.', 'directorist-listing-tools' ),
				'icon'        => 'dashicons-admin-post',
			),
			'map_popup'           => array(
				'label'       => __( 'Map — Listing Popup', 'directorist-listing-tools' ),
				'description' => __( 'Controls what appears in the map marker popup when a listing pin is clicked.', 'directorist-listing-tools' ),
				'icon'        => 'dashicons-location-alt',
			),
		);
	}

	/**
	 * Define all toggleable settings.
	 */
	private function define_settings() {
		$this->settings = array(

			// ── All Listings Header ───────────────────────────────────────────
			'display_listings_header'    => array(
				'label'       => __( 'Enable Listings Header', 'directorist-listing-tools' ),
				'description' => __( 'Show the header bar above the listings with count, filters, sort and view-as controls.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'all_listings_header',
			),
			'listing_filters_button'     => array(
				'label'       => __( 'Display Filters Button', 'directorist-listing-tools' ),
				'description' => __( 'Show the "Filters" button in the header bar (for no-sidebar layout).', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'all_listings_header',
			),
			'display_sort_by'            => array(
				'label'       => __( 'Enable Sorting Options', 'directorist-listing-tools' ),
				'description' => __( 'Show the "Sort By" dropdown in the listings header.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'all_listings_header',
			),
			'display_view_as'            => array(
				'label'       => __( 'Display "View As" Switcher', 'directorist-listing-tools' ),
				'description' => __( 'Show the Grid / List / Map view switcher in the listings header.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'all_listings_header',
			),
			'display_listings_count'     => array(
				'label'       => __( 'Display Listings Count', 'directorist-listing-tools' ),
				'description' => __( 'Show the total number of listings found in the header.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'all_listings_header',
			),

			// ── All Listings Card Display ─────────────────────────────────────
			'display_preview_image'      => array(
				'label'       => __( 'Display Preview / Thumbnail Image', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing thumbnail image on cards. Disabling this switches cards to the "no thumbnail" layout.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_title'              => array(
				'label'       => __( 'Display Listing Title', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing title on each card.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_author_image'       => array(
				'label'       => __( 'Display Author / Owner Image', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing owner\'s avatar on cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_tagline_field'      => array(
				'label'       => __( 'Display Tagline', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing tagline below the title on cards.', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'card_display',
			),
			'enable_review'              => array(
				'label'       => __( 'Enable Rating & Reviews', 'directorist-listing-tools' ),
				'description' => __( 'Show the star rating on listing cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_contact_info'       => array(
				'label'       => __( 'Display Contact Info', 'directorist-listing-tools' ),
				'description' => __( 'Show contact information (address, phone, etc.) on listing cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_phone'              => array(
				'label'       => __( 'Display Phone Number', 'directorist-listing-tools' ),
				'description' => __( 'Show the phone number field on listing cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_website'            => array(
				'label'       => __( 'Display Website URL', 'directorist-listing-tools' ),
				'description' => __( 'Show the website link on listing cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'disable_list_price'         => array(
				'label'       => __( 'Hide Pricing on Cards', 'directorist-listing-tools' ),
				'description' => __( 'When ON, the price/pricing field is hidden from listing cards.', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'card_display',
				'inverse'     => true,
			),
			'display_category'           => array(
				'label'       => __( 'Display Category', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing category badge/label on cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_view_count'         => array(
				'label'       => __( 'Display View Count', 'directorist-listing-tools' ),
				'description' => __( 'Show the number of views in the card footer.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_mark_as_fav'        => array(
				'label'       => __( 'Display Favourite / Bookmark Button', 'directorist-listing-tools' ),
				'description' => __( 'Show the heart/bookmark icon to let visitors save listings.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_feature_badge_cart' => array(
				'label'       => __( 'Display Featured Badge', 'directorist-listing-tools' ),
				'description' => __( 'Show a "Featured" badge on promoted listing cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_popular_badge'      => array(
				'label'       => __( 'Display Popular Badge', 'directorist-listing-tools' ),
				'description' => __( 'Show a "Popular" badge on high-traffic or high-rated listings.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_new_badge'          => array(
				'label'       => __( 'Display New Badge', 'directorist-listing-tools' ),
				'description' => __( 'Show a "New" badge on recently added listings.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'enable_excerpt'             => array(
				'label'       => __( 'Enable Excerpt / Description', 'directorist-listing-tools' ),
				'description' => __( 'Show a short excerpt from the listing description on cards.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_publish_date'       => array(
				'label'       => __( 'Display Published Date', 'directorist-listing-tools' ),
				'description' => __( 'Show the date the listing was published.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'card_display',
			),
			'display_readmore'           => array(
				'label'       => __( 'Display "Read More" Button', 'directorist-listing-tools' ),
				'description' => __( 'Show a "Read More" link at the bottom of listing cards.', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'card_display',
			),
			'info_display_in_single_line' => array(
				'label'       => __( 'Display Card Info in Single Line', 'directorist-listing-tools' ),
				'description' => __( 'Compress card information (address, phone, etc.) into a single line layout.', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'card_display',
			),

			// ── Single Listing ────────────────────────────────────────────────
			'disable_single_listing'     => array(
				'label'       => __( 'Disable Single Listing Page', 'directorist-listing-tools' ),
				'description' => __( 'When ON, clicking a listing card does not go to a detail page (useful for directory-only layouts).', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'single_listing',
				'inverse'     => true,
			),
			'disable_contact_info'       => array(
				'label'       => __( 'Disable Contact Info on Single Page', 'directorist-listing-tools' ),
				'description' => __( 'When ON, the contact info section is hidden on individual listing pages.', 'directorist-listing-tools' ),
				'default'     => 0,
				'section'     => 'single_listing',
				'inverse'     => true,
			),

			// ── Map Popup ─────────────────────────────────────────────────────
			'display_map_info'           => array(
				'label'       => __( 'Enable Map Listing Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show an info popup when a map marker is clicked.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_image_map'          => array(
				'label'       => __( 'Display Image in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing thumbnail inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_title_map'          => array(
				'label'       => __( 'Display Title in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing title inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_address_map'        => array(
				'label'       => __( 'Display Address in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing address inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_direction_map'      => array(
				'label'       => __( 'Display Directions Link in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show a "Get Directions" link inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_favorite_badge_map' => array(
				'label'       => __( 'Display Favourite Button in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the bookmark/heart button inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_user_avatar_map'    => array(
				'label'       => __( 'Display Author Avatar in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing owner\'s avatar inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_review_map'         => array(
				'label'       => __( 'Display Rating in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the star rating inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_price_map'          => array(
				'label'       => __( 'Display Price in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the listing price inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
			'display_phone_map'          => array(
				'label'       => __( 'Display Phone in Map Popup', 'directorist-listing-tools' ),
				'description' => __( 'Show the phone number inside the map marker popup.', 'directorist-listing-tools' ),
				'default'     => 1,
				'section'     => 'map_popup',
			),
		);
	}

	/**
	 * Get all settings as a flat array of option_key => current_value.
	 *
	 * @return array
	 */
	private function get_current_values() {
		$current = array();
		foreach ( $this->settings as $key => $setting ) {
			if ( function_exists( 'get_directorist_option' ) ) {
				$raw = get_directorist_option( $key, $setting['default'] );
			} else {
				$options = (array) get_option( 'atbdp_option', array() );
				$raw     = isset( $options[ $key ] ) ? $options[ $key ] : $setting['default'];
			}
			// Normalize to bool.
			$current[ $key ] = ! empty( $raw ) ? true : false;
		}
		return $current;
	}

	/**
	 * AJAX handler: toggle a single display setting.
	 */
	public function handle_ajax_toggle() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ) );
		}

		$option_key   = isset( $_POST['option_key'] ) ? sanitize_key( $_POST['option_key'] ) : '';
		$option_value = isset( $_POST['option_value'] ) ? rest_sanitize_boolean( $_POST['option_value'] ) : false;

		if ( empty( $option_key ) || ! array_key_exists( $option_key, $this->settings ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid option key.', 'directorist-listing-tools' ) ) );
		}

		// Save to atbdp_option.
		$options            = (array) get_option( 'atbdp_option', array() );
		$options[ $option_key ] = $option_value ? 1 : 0;
		update_option( 'atbdp_option', $options );

		$setting = $this->settings[ $option_key ];
		$label   = $setting['label'];
		$status  = $option_value
			? __( 'Enabled', 'directorist-listing-tools' )
			: __( 'Disabled', 'directorist-listing-tools' );

		wp_send_json_success(
			array(
				'message'      => sprintf(
					/* translators: 1: setting label, 2: enabled/disabled */
					__( '"%1$s" has been %2$s.', 'directorist-listing-tools' ),
					$label,
					$status
				),
				'option_key'   => $option_key,
				'option_value' => $option_value,
			)
		);
	}

	/**
	 * Render the Display Settings admin page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$current_values  = $this->get_current_values();
		$directory_types = $this->get_directory_types();
		$first_term_id   = ! empty( $directory_types ) ? (int) $directory_types[0]->term_id : 0;

		// Group global settings by section.
		$grouped = array();
		foreach ( $this->settings as $key => $setting ) {
			$grouped[ $setting['section'] ][ $key ] = $setting;
		}
		?>
		<div class="wrap dlt-display-settings-wrap">

			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-visibility" style="font-size:28px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
				<?php esc_html_e( 'Listing Display Settings', 'directorist-listing-tools' ); ?>
			</h1>
			<p class="description" style="margin-top:6px;">
				<?php esc_html_e( 'Toggle Directorist listing display options on or off. Changes save instantly via AJAX — no page reload needed.', 'directorist-listing-tools' ); ?>
			</p>

			<div id="dlt-ds-global-message" style="display:none;margin:15px 0;"></div>

			<?php /* ── STEP 1: Directory Type Selector ───────────────────────── */ ?>
			<div class="dlt-ds-directory-selector-card postbox">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:6px;"></span>
						<?php esc_html_e( 'Directory Type — Thumbnail Settings', 'directorist-listing-tools' ); ?>
					</h2>
				</div>
				<div class="inside">
					<p class="dlt-ds-section-desc">
						<?php esc_html_e( 'Select a directory type to view and toggle its thumbnail visibility for Grid and List views. These settings are saved per-directory in term meta.', 'directorist-listing-tools' ); ?>
					</p>

					<?php if ( empty( $directory_types ) ) : ?>
						<div class="notice notice-warning inline"><p>
							<?php esc_html_e( 'No directory types found. Please create a directory type in Directorist first.', 'directorist-listing-tools' ); ?>
						</p></div>
					<?php else : ?>

					<div class="dlt-ds-selector-row">
						<label for="dlt-ds-directory-type-select">
							<strong><?php esc_html_e( 'Select Directory Type:', 'directorist-listing-tools' ); ?></strong>
						</label>
						<select id="dlt-ds-directory-type-select" data-first-id="<?php echo esc_attr( $first_term_id ); ?>">
							<?php foreach ( $directory_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type->term_id ); ?>">
									<?php echo esc_html( $type->name ); ?>
									<?php if ( ! empty( $type->description ) ) : ?>
										(<?php echo esc_html( $type->slug ); ?>)
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="dlt-ds-dir-spinner spinner" style="float:none;visibility:hidden;margin:0 8px;vertical-align:middle;"></span>
					</div>

					<?php /* Per-directory settings table — populated by AJAX */ ?>
					<div id="dlt-ds-directory-settings-wrap" style="margin-top:16px;">
						<table class="dlt-ds-table widefat" id="dlt-ds-directory-table">
							<thead>
								<tr>
									<th style="width:42%;"><?php esc_html_e( 'View Option', 'directorist-listing-tools' ); ?></th>
									<th><?php esc_html_e( 'Description', 'directorist-listing-tools' ); ?></th>
									<th style="width:160px;text-align:center;"><?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?></th>
								</tr>
							</thead>
							<tbody id="dlt-ds-directory-tbody">
								<tr>
									<td colspan="3" style="text-align:center;padding:20px;color:#646970;">
										<span class="spinner is-active" style="float:none;"></span>
										<?php esc_html_e( 'Loading…', 'directorist-listing-tools' ); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

					<?php endif; ?>
				</div><!-- .inside -->
			</div><!-- .dlt-ds-directory-selector-card -->

			<?php /* ── STEP 2: Global Settings ───────────────────────────────── */ ?>
			<h2 class="dlt-ds-global-heading">
				<span class="dashicons dashicons-admin-settings" style="vertical-align:middle;margin-right:6px;"></span>
				<?php esc_html_e( 'Global Display Settings', 'directorist-listing-tools' ); ?>
			</h2>
			<p class="description" style="margin-bottom:14px;">
				<?php esc_html_e( 'These settings apply globally to all listings and are stored in the WordPress options table (atbdp_option).', 'directorist-listing-tools' ); ?>
			</p>

			<div class="dlt-ds-sections">
				<?php foreach ( $this->sections as $section_id => $section ) : ?>
					<?php if ( empty( $grouped[ $section_id ] ) ) : continue; endif; ?>

					<div class="dlt-ds-section postbox">
						<div class="postbox-header">
							<h2 class="hndle">
								<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" style="vertical-align:middle;margin-right:6px;"></span>
								<?php echo esc_html( $section['label'] ); ?>
							</h2>
						</div>
						<div class="inside">
							<?php if ( ! empty( $section['description'] ) ) : ?>
								<p class="dlt-ds-section-desc"><?php echo esc_html( $section['description'] ); ?></p>
							<?php endif; ?>

							<table class="dlt-ds-table widefat">
								<thead>
									<tr>
										<th style="width:42%;"><?php esc_html_e( 'Option', 'directorist-listing-tools' ); ?></th>
										<th><?php esc_html_e( 'Description', 'directorist-listing-tools' ); ?></th>
										<th style="width:120px;text-align:center;"><?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $grouped[ $section_id ] as $key => $setting ) :
										$is_enabled  = $current_values[ $key ];
										$is_inverse  = ! empty( $setting['inverse'] );
										$toggle_on   = $is_enabled;
										$badge_class = $toggle_on ? 'dlt-ds-badge-on' : 'dlt-ds-badge-off';
										$badge_text  = $toggle_on ? __( 'On', 'directorist-listing-tools' ) : __( 'Off', 'directorist-listing-tools' );
									?>
									<tr class="dlt-ds-row <?php echo $toggle_on ? 'is-active' : 'is-inactive'; ?>"
										data-option-key="<?php echo esc_attr( $key ); ?>">

										<td class="dlt-ds-label-cell">
											<strong><?php echo esc_html( $setting['label'] ); ?></strong>
											<?php if ( $is_inverse ) : ?>
												<span class="dlt-ds-inverse-note">
													<?php esc_html_e( '(ON = hides / disables)', 'directorist-listing-tools' ); ?>
												</span>
											<?php endif; ?>
											<code class="dlt-ds-option-key"><?php echo esc_html( $key ); ?></code>
										</td>

										<td class="dlt-ds-desc-cell">
											<?php echo esc_html( $setting['description'] ); ?>
										</td>

										<td class="dlt-ds-toggle-cell">
											<label class="dlt-ds-toggle" title="<?php echo esc_attr( $setting['label'] ); ?>">
												<input
													type="checkbox"
													class="dlt-ds-toggle-input"
													data-option-key="<?php echo esc_attr( $key ); ?>"
													data-label="<?php echo esc_attr( $setting['label'] ); ?>"
													<?php checked( $toggle_on, true ); ?>
												/>
												<span class="dlt-ds-toggle-slider"></span>
											</label>
											<span class="dlt-ds-badge <?php echo esc_attr( $badge_class ); ?>">
												<?php echo esc_html( $badge_text ); ?>
											</span>
											<span class="dlt-ds-spinner spinner" style="display:none;float:none;margin:0 4px;"></span>
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div><!-- .inside -->
					</div><!-- .dlt-ds-section -->

				<?php endforeach; ?>
			</div><!-- .dlt-ds-sections -->

			<div class="dlt-ds-footer">
				<p>
					<span class="dashicons dashicons-info-outline" style="vertical-align:middle;"></span>
					<?php
					printf(
						/* translators: Option key name */
						esc_html__( 'Global settings are stored in the %s WordPress option. Directory-type thumbnail settings are stored in term meta.', 'directorist-listing-tools' ),
						'<code>atbdp_option</code>'
					);
					?>
				</p>
			</div>

		</div><!-- .dlt-display-settings-wrap -->
		<?php
	}
}
