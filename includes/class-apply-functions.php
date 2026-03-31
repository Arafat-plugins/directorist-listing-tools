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
				'description' => __( 'All Listings “Directory” column from _directory_type meta; aligns taxonomy on listing save.', 'directorist-listing-tools' ),
			),
			'wc_plan_checkout_bridge'          => array(
				'label'       => __( 'WooCommerce plan checkout bridge', 'directorist-listing-tools' ),
				'description' => __( 'With WooCommerce Pricing Plans: cart + Directorist checkout summary when redirect only passes atbdp_listing_id.', 'directorist-listing-tools' ),
				'requires_wc' => true,
			),
			'directorist_classic_editor'       => array(
				'label'       => __( 'Directorist classic editor & link dialog', 'directorist-listing-tools' ),
				'description' => __( 'Disables block editor for listings; ensures TinyMCE link / wplink on post edit screens.', 'directorist-listing-tools' ),
			),
			'dlist_listing_bg_lazyfix'          => array(
				'label'       => __( 'Listing hero background (lazy-load / Smush)', 'directorist-listing-tools' ),
				'description' => __( 'Frontend script for .listing-details-wrapper.bgimage when optimizers replace img src with placeholders.', 'directorist-listing-tools' ),
			),
			'fix_directorist_google_signin'     => array(
				'label'       => __( 'Google sign-in button fallback', 'directorist-listing-tools' ),
				'description' => __( 'Renders Google Identity button if .g_id_signin stays empty (Directorist Social Login).', 'directorist-listing-tools' ),
			),
			'directorist_directory_type_guard'  => array(
				'label'       => __( 'Directory type guard', 'directorist-listing-tools' ),
				'description' => __( 'Re-applies directory type after listing create/update and self-heals on preview/payment URLs.', 'directorist-listing-tools' ),
			),
			'directorist_term_directory_assignment_fix' => array(
				'label'       => __( 'Term directory assignment fix (category/location)', 'directorist-listing-tools' ),
				'description' => __( 'Preserves multiple selected directory types for category/location taxonomy term saves.', 'directorist-listing-tools' ),
			),
			'directorist_wordfence_fix'         => array(
				'label'       => __( 'Wordfence / AJAX login compatibility', 'directorist-listing-tools' ),
				'description' => __( 'Intercepts redirect and re-validates authenticate for Directorist AJAX login actions.', 'directorist-listing-tools' ),
			),
			'wpml_rewritebase_fix'              => array(
				'label'       => __( 'WPML RewriteBase / .htaccess fix', 'directorist-listing-tools' ),
				'description' => __( 'Normalizes mod_rewrite rules (RewriteBase /, index.php, wp-login) when WPML touches rules.', 'directorist-listing-tools' ),
			),
			'directorist_css_variables_fix'     => array(
				'label'       => __( 'Directorist CSS variables in wp_head', 'directorist-listing-tools' ),
				'description' => __( 'Prints :root block from Directorist dynamic styles early on the frontend.', 'directorist-listing-tools' ),
			),
			'directorist_listing_expiration_fix' => array(
				'label'       => __( 'Listing expiration / nearly-expired fix', 'directorist-listing-tools' ),
				'description' => __( 'Sets expired meta on atbdp_listing_expired; supplemental renewal query after atbdp_schedule_task.', 'directorist-listing-tools' ),
			),
			'enqueue_line_awesome'              => array(
				'label'       => __( 'Enqueue Line Awesome (frontend)', 'directorist-listing-tools' ),
				'description' => __( 'Loads Directorist bundled line-awesome.min.css if not already enqueued.', 'directorist-listing-tools' ),
			),
			'fix_directorist_bh_add_time_slot' => array(
				'label'       => __( 'Business hours: add time slot / Select2', 'directorist-listing-tools' ),
				'description' => __( 'On listing edit: dequeues WooCommerce Select2; safe Select2 destroy patch for BH scripts.', 'directorist-listing-tools' ),
				'requires_wc' => true,
			),
			'hide_elementor_loading_state'      => array(
				'label'       => __( 'Hide Elementor editor loading overlay', 'directorist-listing-tools' ),
				'description' => __( 'CSS/JS to hide #elementor-panel-state-loading in the Elementor editor.', 'directorist-listing-tools' ),
				'requires_elementor' => true,
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
			<h2 class="screen-reader-text"><?php esc_html_e( 'Apply functions', 'directorist-listing-tools' ); ?></h2>

			<div class="dlt-af-intro">
				<p class="description">
					<?php esc_html_e( 'Turn on optional behaviors site-wide. Leave a feature off if you do not need it or if it conflicts with custom code. More toggles may be added here over time.', 'directorist-listing-tools' ); ?>
				</p>
			</div>

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
