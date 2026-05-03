<?php
/**
 * Apply Functions — optional compatibility / fix toggles (extensible for future features).
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option key for stored toggles.
 */
define( 'DLT_APPLY_FUNCTIONS_OPTION', 'dlt_apply_functions' );

/**
 * Class Directorist_Listing_Tools_Apply_Functions
 */
class Directorist_Listing_Tools_Apply_Functions {

	/** @var Directorist_Listing_Tools_Apply_Functions|null */
	private static $instance = null;

	/**
	 * Feature definitions: id => [ label, description ].
	 *
	 * @var array
	 */
	private $features = array();

	/**
	 * @return Directorist_Listing_Tools_Apply_Functions
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
		$this->define_features();
		add_action( 'admin_post_dlt_save_apply_functions', array( $this, 'handle_form_save' ) );
		add_action( 'wp_ajax_dlt_get_apply_function_why', array( $this, 'ajax_get_feature_why' ) );
		// After this plugin (plugins_loaded 20): run early but not before callbacks registered on this hook finish.
		add_action( 'plugins_loaded', array( $this, 'maybe_load_features' ), 25 );
	}

	/**
	 * Register feature list (migrated from former mu-plugins; add new slugs here only with a matching compat/apply-features file).
	 */
	private function define_features() {
		$this->features = array(
			'directory_taxonomy_sync'          => array(
				'label'       => __( 'Directory column & taxonomy sync', 'directorist-listing-tools' ),
				'description' => __( 'Syncs “Directory” column from meta and aligns taxonomy on save.', 'directorist-listing-tools' ),
			),
			'wc_plan_checkout_bridge'          => array(
				'label'       => __( 'WooCommerce plan checkout bridge', 'directorist-listing-tools' ),
				'description' => __( 'Bridges cart + checkout summary for WC Pricing Plans.', 'directorist-listing-tools' ),
				'requires_wc' => true,
			),
			'wc_checkout_endpoint_conflict_fix' => array(
				'label'       => __( 'WooCommerce checkout endpoint conflict fix', 'directorist-listing-tools' ),
				'description' => __( 'Restores WooCommerce order-received and order-pay routes when generic Directorist checkout rewrites hijack them.', 'directorist-listing-tools' ),
				'requires_wc' => true,
			),
			'directorist_classic_editor'       => array(
				'label'       => __( 'Classic editor & link dialog', 'directorist-listing-tools' ),
				'description' => __( 'Uses classic editor for listings with TinyMCE link dialog.', 'directorist-listing-tools' ),
			),
			'dlist_listing_bg_lazyfix'          => array(
				'label'       => __( 'Listing hero background lazy-load fix', 'directorist-listing-tools' ),
				'description' => __( 'Fixes hero background when image optimizers replace src.', 'directorist-listing-tools' ),
			),
			'fix_directorist_google_signin'     => array(
				'label'       => __( 'Google sign-in fallback', 'directorist-listing-tools' ),
				'description' => __( 'Renders Google Identity button if default render fails.', 'directorist-listing-tools' ),
			),
			'directorist_directory_type_guard'  => array(
				'label'       => __( 'Directory type guard', 'directorist-listing-tools' ),
				'description' => __( 'Re-applies directory type after create/update and self-heals URLs.', 'directorist-listing-tools' ),
			),
			'directorist_term_directory_assignment_fix' => array(
				'label'       => __( 'Term directory assignment fix', 'directorist-listing-tools' ),
				'description' => __( 'Preserves multiple directory types on category/location term save.', 'directorist-listing-tools' ),
			),
			'directorist_wordfence_fix'         => array(
				'label'       => __( 'Wordfence / AJAX login fix', 'directorist-listing-tools' ),
				'description' => __( 'Fixes Directorist AJAX login with Wordfence active.', 'directorist-listing-tools' ),
			),
			'wpml_rewritebase_fix'              => array(
				'label'       => __( 'WPML .htaccess fix', 'directorist-listing-tools' ),
				'description' => __( 'Normalizes rewrite rules when WPML modifies .htaccess.', 'directorist-listing-tools' ),
			),
			'directorist_css_variables_fix'     => array(
				'label'       => __( 'CSS variables in wp_head', 'directorist-listing-tools' ),
				'description' => __( 'Outputs Directorist :root CSS variables early on frontend.', 'directorist-listing-tools' ),
			),
			'directorist_listing_expiration_fix' => array(
				'label'       => __( 'Listing expiration fix', 'directorist-listing-tools' ),
				'description' => __( 'Fixes expired meta handling and renewal queries.', 'directorist-listing-tools' ),
			),
			'enqueue_line_awesome'              => array(
				'label'       => __( 'Load Line Awesome icons', 'directorist-listing-tools' ),
				'description' => __( 'Enqueues Line Awesome CSS on the frontend.', 'directorist-listing-tools' ),
			),
			'fix_directorist_bh_add_time_slot' => array(
				'label'       => __( 'Business hours Select2 fix', 'directorist-listing-tools' ),
				'description' => __( 'Fixes Select2 conflict with WooCommerce on listing edit.', 'directorist-listing-tools' ),
				'requires_wc' => true,
			),
			'hide_elementor_loading_state'      => array(
				'label'       => __( 'Hide Elementor loading overlay', 'directorist-listing-tools' ),
				'description' => __( 'Hides the Elementor editor loading state panel.', 'directorist-listing-tools' ),
				'requires_elementor' => true,
			),
			'conflict_wp_rocket_pagination'   => array(
				'label'       => __( 'WP Rocket pagination fix', 'directorist-listing-tools' ),
				'description' => __( 'Excludes Directorist JS from WP Rocket optimization.', 'directorist-listing-tools' ),
			),
			'directorist_category_filter_fix' => array(
				'label'       => __( 'Category filter fix', 'directorist-listing-tools' ),
				'description' => __( 'Shows all listings in a category regardless of directory type.', 'directorist-listing-tools' ),
			),
			'directorist_all_categories_page_fix' => array(
				'label'       => __( 'All categories page fix', 'directorist-listing-tools' ),
				'description' => __( 'Makes the Directorist all-categories page respect shortcode directory type settings and show all categories when no directory type is selected.', 'directorist-listing-tools' ),
			),
			'directorist_keyword_search_fix' => array(
				'label'       => __( 'Keyword search fix', 'directorist-listing-tools' ),
				'description' => __( 'Improves Directorist keyword search for AJAX and archive results by matching title, key meta fields, and taxonomies only.', 'directorist-listing-tools' ),
			),
			'directorist_zip_prefix_search_fix' => array(
				'label'       => __( 'ZIP prefix search fix', 'directorist-listing-tools' ),
				'description' => __( 'When ZIP radius search is based on a short ZIP prefix like "70", match listings whose postal code starts with that prefix instead of matching the digits anywhere.', 'directorist-listing-tools' ),
			),
			'fix_builder_title_toggle_save' => array(
				'label'       => __( 'Builder title toggle save fix', 'directorist-listing-tools' ),
				'description' => __( 'Fixes listing header toggles not saving in directory builder.', 'directorist-listing-tools' ),
			),
			'directorist_auto_approve_author' => array(
				'label'       => __( 'Auto-approve author registration', 'directorist-listing-tools' ),
				'description' => __( 'Automatically approves author accounts on registration — removes the pending approve/deny step from the admin user list.', 'directorist-listing-tools' ),
			),
			'pricing_type_tabs_open_add_listing_page' => array(
				'label'       => __( 'Pricing type tabs open add listing page', 'directorist-listing-tools' ),
				'description' => __( 'Makes pricing plan directory type tabs open the configured Add Listing page with the clicked directory type.', 'directorist-listing-tools' ),
			),
			'directorist_pricing_plans_dashboard_views_fix' => array(
				'label'       => __( 'Pricing plans dashboard views fix', 'directorist-listing-tools' ),
				'description' => __( 'Scopes heavy Directorist Pricing Plans dashboard modal and tabs to the real user dashboard so Elementor-edited pages do not become slow.', 'directorist-listing-tools' ),
				'why'         => implode(
					'',
					array(
						'<p><strong>' . esc_html__( 'Why this exists:', 'directorist-listing-tools' ) . '</strong> ' . esc_html__( 'Some Directorist Pricing Plans versions attach heavy ATPP_Views callbacks too broadly.', 'directorist-listing-tools' ) . '</p>',
						'<p>' . esc_html__( 'The two problem callbacks are the footer plan-change modal and the dashboard tabs renderer. When they are active everywhere, Elementor preview/editor requests also load them, and the modal renderer performs expensive pricing-plan queries before anyone even clicks anything.', 'directorist-listing-tools' ) . '</p>',
						'<p>' . esc_html__( 'That makes Elementor-edited pages become extremely slow or appear blank on weaker/live hosting.', 'directorist-listing-tools' ) . '</p>',
						'<p><strong>' . esc_html__( 'What this fix does:', 'directorist-listing-tools' ) . '</strong> ' . esc_html__( 'It removes those ATPP_Views callbacks from global page loads and adds them back only on the real Directorist user dashboard page. Elementor editor and preview requests are intentionally skipped.', 'directorist-listing-tools' ) . '</p>',
						'<p><strong>' . esc_html__( 'What it should not affect:', 'directorist-listing-tools' ) . '</strong> ' . esc_html__( 'Pricing plan purchase logic, plan restrictions, payments, subscriptions, and normal dashboard pricing-plan features should still work. The change is only about where the heavy dashboard/modal UI is allowed to render.', 'directorist-listing-tools' ) . '</p>',
					)
				),
			),
		);
	}

	/**
	 * @return array
	 */
	public function get_features() {
		return $this->features;
	}

	/**
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'directory_taxonomy_sync'           => false,
			'wc_plan_checkout_bridge'          => false,
			'wc_checkout_endpoint_conflict_fix' => true,
			'directorist_classic_editor'        => false,
			'dlist_listing_bg_lazyfix'          => false,
			'fix_directorist_google_signin'     => false,
			'directorist_directory_type_guard' => false,
			'directorist_term_directory_assignment_fix' => false,
			'directorist_wordfence_fix'         => false,
			'wpml_rewritebase_fix'              => false,
			'directorist_css_variables_fix'      => false,
			'directorist_listing_expiration_fix' => false,
			'enqueue_line_awesome'              => false,
			'fix_directorist_bh_add_time_slot'  => false,
			'hide_elementor_loading_state'      => false,
			'conflict_wp_rocket_pagination'    => false,
			'directorist_category_filter_fix'  => false,
			'directorist_all_categories_page_fix' => false,
			'directorist_keyword_search_fix'   => true,
			'directorist_zip_prefix_search_fix' => false,
			'fix_builder_title_toggle_save'    => false,
			'directorist_auto_approve_author'  => false,
			'pricing_type_tabs_open_add_listing_page' => true,
			'directorist_pricing_plans_dashboard_views_fix' => false,
		);
	}

	/**
	 * @return array
	 */
	public static function get_options() {
		$opts = get_option( DLT_APPLY_FUNCTIONS_OPTION, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		return wp_parse_args( $opts, self::get_defaults() );
	}

	/**
	 * Save toggles (admin-post.php — respects dlt_manage_listing_tools, not only manage_options).
	 */
	public function handle_form_save() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}
		check_admin_referer( 'dlt_save_apply_functions' );

		$input = isset( $_POST['dlt_apply'] ) && is_array( $_POST['dlt_apply'] ) ? wp_unslash( $_POST['dlt_apply'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$prev  = get_option( DLT_APPLY_FUNCTIONS_OPTION, array() );
		$out   = self::get_defaults();
		foreach ( array_keys( $this->features ) as $key ) {
			$meta        = isset( $this->features[ $key ] ) ? $this->features[ $key ] : array();
			$needs_wc    = ! empty( $meta['requires_wc'] ) && ! class_exists( 'WooCommerce' );
			$needs_el    = ! empty( $meta['requires_elementor'] ) && ! defined( 'ELEMENTOR_VERSION' );
			if ( $needs_wc || $needs_el ) {
				$out[ $key ] = ! empty( $prev[ $key ] );
				continue;
			}
			$out[ $key ] = ! empty( $input[ $key ] );
		}

		update_option( DLT_APPLY_FUNCTIONS_OPTION, $out );

		$redirect = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : '';
		if ( empty( $redirect ) ) {
			$redirect = admin_url( 'edit.php?post_type=' . dlt_get_post_type() . '&page=directorist-listing-tools-apply-functions' );
		}
		wp_safe_redirect( add_query_arg( 'dlt_apply_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * Return the full "why this exists" note for one feature over AJAX.
	 *
	 * @return void
	 */
	public function ajax_get_feature_why() {
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'You do not have permission to view this note.', 'directorist-listing-tools' ),
				),
				403
			);
		}

		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		$feature_id = isset( $_POST['feature_id'] ) ? sanitize_key( wp_unslash( $_POST['feature_id'] ) ) : '';
		$feature    = isset( $this->features[ $feature_id ] ) ? $this->features[ $feature_id ] : array();
		$why        = isset( $feature['why'] ) ? (string) $feature['why'] : '';

		if ( '' === $why ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'No detailed note is available for this apply function yet.', 'directorist-listing-tools' ),
				),
				404
			);
		}

		wp_send_json_success(
			array(
				'html' => wp_kses_post( $why ),
			)
		);
	}

	/**
	 * Load feature files and bootstrap when enabled.
	 */
	public function maybe_load_features() {
		if ( ! function_exists( 'dlt_is_directorist_active' ) || ! dlt_is_directorist_active() ) {
			return;
		}

		$opts       = self::get_options();
		$compat_dir = DLT_DIR . 'includes/compat/';

		if ( ! empty( $opts['directory_taxonomy_sync'] ) ) {
			require_once DLT_DIR . 'includes/apply-features/directory-taxonomy-sync.php';
			dlt_af_bootstrap_directory_taxonomy_sync();
		}

		if ( ! empty( $opts['wc_plan_checkout_bridge'] ) && class_exists( 'WooCommerce' ) ) {
			require_once DLT_DIR . 'includes/apply-features/wc-plan-checkout-bridge.php';
			dlt_af_bootstrap_wc_plan_checkout_bridge();
		}
		if ( ! empty( $opts['wc_checkout_endpoint_conflict_fix'] ) && class_exists( 'WooCommerce' ) ) {
			require_once DLT_DIR . 'includes/apply-features/wc-checkout-endpoint-conflict-fix.php';
			dlt_af_bootstrap_wc_checkout_endpoint_conflict_fix();
		}

		if ( ! empty( $opts['directorist_classic_editor'] ) ) {
			require_once $compat_dir . 'directorist-classic-editor.php';
		}
		if ( ! empty( $opts['dlist_listing_bg_lazyfix'] ) ) {
			require_once $compat_dir . 'dlist-listing-bg-lazyfix.php';
		}
		if ( ! empty( $opts['fix_directorist_google_signin'] ) ) {
			require_once $compat_dir . 'fix-directorist-google-signin.php';
		}
		if ( ! empty( $opts['directorist_directory_type_guard'] ) ) {
			require_once $compat_dir . 'directorist-directory-type-guard.php';
		}
		if ( ! empty( $opts['directorist_term_directory_assignment_fix'] ) ) {
			require_once $compat_dir . 'directorist-term-directory-assignment-fix.php';
		}
		if ( ! empty( $opts['directorist_wordfence_fix'] ) ) {
			require_once $compat_dir . 'directorist-wordfence-fix.php';
		}
		if ( ! empty( $opts['wpml_rewritebase_fix'] ) ) {
			require_once $compat_dir . 'wpml-rewritebase-fix.php';
		}
		if ( ! empty( $opts['directorist_css_variables_fix'] ) ) {
			require_once $compat_dir . 'directorist-css-variables-fix.php';
		}
		if ( ! empty( $opts['directorist_listing_expiration_fix'] ) ) {
			require_once $compat_dir . 'directorist-listing-expiration-fix.php';
		}
		if ( ! empty( $opts['enqueue_line_awesome'] ) ) {
			require_once $compat_dir . 'enqueue-line-awesome.php';
		}
		if ( ! empty( $opts['fix_directorist_bh_add_time_slot'] ) && class_exists( 'WooCommerce' ) ) {
			require_once $compat_dir . 'fix-directorist-bh-add-time-slot.php';
		}
		if ( ! empty( $opts['hide_elementor_loading_state'] ) ) {
			require_once $compat_dir . 'hide-elementor-loading-state.php';
		}
		if ( ! empty( $opts['conflict_wp_rocket_pagination'] ) ) {
			require_once $compat_dir . 'conflict-wp-rocket-pagination.php';
		}
		if ( ! empty( $opts['directorist_category_filter_fix'] ) ) {
			require_once $compat_dir . 'directorist-category-filter-fix.php';
		}
		if ( ! empty( $opts['directorist_all_categories_page_fix'] ) ) {
			require_once $compat_dir . 'directorist-all-categories-page-fix.php';
		}
		if ( ! empty( $opts['directorist_keyword_search_fix'] ) ) {
			require_once $compat_dir . 'directorist-keyword-search-fix.php';
		}
		if ( ! empty( $opts['directorist_zip_prefix_search_fix'] ) ) {
			require_once $compat_dir . 'directorist-zip-prefix-search-fix.php';
		}
		if ( ! empty( $opts['fix_builder_title_toggle_save'] ) ) {
			require_once $compat_dir . 'fix-builder-title-toggle-save.php';
		}
		if ( ! empty( $opts['directorist_auto_approve_author'] ) ) {
			require_once $compat_dir . 'directorist-auto-approve-author.php';
		}
		if ( ! empty( $opts['pricing_type_tabs_open_add_listing_page'] ) ) {
			require_once $compat_dir . 'pricing-type-tabs-open-add-listing-page.php';
		}
		if ( ! empty( $opts['directorist_pricing_plans_dashboard_views_fix'] ) ) {
			require_once $compat_dir . 'directorist-pricing-plans-dashboard-views-fix.php';
		}
	}

	/**
	 * Admin page output.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		if ( ! empty( $_GET['dlt_apply_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Apply functions settings saved.', 'directorist-listing-tools' ) . '</p></div>';
		}

		$opts     = self::get_options();
		$features = $this->get_features();
		$form_act   = admin_url( 'admin-post.php' );
		$return_url = admin_url( 'edit.php?post_type=' . dlt_get_post_type() . '&page=directorist-listing-tools-apply-functions' );
		?>
		<div class="wrap dlt-apply-functions-wrap">
			<h2 class="screen-reader-text"><?php esc_html_e( 'Apply Functions', 'directorist-listing-tools' ); ?></h2>

			<form method="post" action="<?php echo esc_url( $form_act ); ?>" class="dlt-af-form">
				<input type="hidden" name="action" value="dlt_save_apply_functions" />
				<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( $return_url ); ?>" />
				<?php wp_nonce_field( 'dlt_save_apply_functions' ); ?>

				<ul class="dlt-af-feature-list">
					<?php foreach ( $features as $id => $meta ) : ?>
						<?php
						$disabled_note = '';
						if ( ! empty( $meta['requires_wc'] ) && ! class_exists( 'WooCommerce' ) ) {
							$disabled_note = __( 'WooCommerce is not active — enable WooCommerce to use this.', 'directorist-listing-tools' );
						}
						if ( $disabled_note === '' && ! empty( $meta['requires_elementor'] ) && ! defined( 'ELEMENTOR_VERSION' ) ) {
							$disabled_note = __( 'Elementor is not active — install Elementor to use this.', 'directorist-listing-tools' );
						}
						$checked = ! empty( $opts[ $id ] );
						$row_id  = 'dlt-af-' . sanitize_html_class( $id );
						?>
						<li class="dlt-af-card">
							<div class="dlt-af-card__body">
								<div class="dlt-af-card__header">
									<label class="dlt-ds-toggle dlt-af-toggle" for="<?php echo esc_attr( $row_id ); ?>">
										<span class="dlt-af-card__title"><?php echo esc_html( $meta['label'] ); ?></span>
										<input
											class="dlt-ds-toggle-input"
											type="checkbox"
											name="dlt_apply[<?php echo esc_attr( $id ); ?>]"
											id="<?php echo esc_attr( $row_id ); ?>"
											value="1"
											<?php checked( $checked ); ?>
											<?php echo $disabled_note ? ' disabled="disabled"' : ''; ?>
										/>
										<span class="dlt-ds-toggle-slider" aria-hidden="true"></span>
									</label>
								</div>
								<p class="dlt-af-card__desc description"><?php echo esc_html( $meta['description'] ); ?></p>
								<?php if ( ! empty( $meta['why'] ) ) : ?>
									<div class="dlt-af-card__why-wrap">
										<button
											type="button"
											class="button button-secondary dlt-af-why-button"
											data-feature-id="<?php echo esc_attr( $id ); ?>"
											data-expand-text="<?php echo esc_attr__( 'Why this..?', 'directorist-listing-tools' ); ?>"
											data-collapse-text="<?php echo esc_attr__( 'Hide details', 'directorist-listing-tools' ); ?>"
										>
											<?php esc_html_e( 'Why this..?', 'directorist-listing-tools' ); ?>
										</button>
										<div class="dlt-af-why-panel" hidden></div>
									</div>
								<?php endif; ?>
								<?php if ( $disabled_note ) : ?>
									<p class="dlt-af-card__notice"><strong><?php echo esc_html( $disabled_note ); ?></strong></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php submit_button( __( 'Save apply functions', 'directorist-listing-tools' ), 'primary large', 'submit', true ); ?>
			</form>
		</div>
		<?php
	}
}
