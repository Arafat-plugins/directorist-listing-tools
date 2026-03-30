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
		add_action( 'plugins_loaded', array( $this, 'maybe_load_features' ), 30 );
	}

	/**
	 * Register feature list (add new entries here as you ship more fixes).
	 */
	private function define_features() {
		$this->features = array(
			'directory_taxonomy_sync' => array(
				'label'       => __( 'Directory column & taxonomy sync(Type missing fix)', 'directorist-listing-tools' ),
				'description' => __( 'Fixes the All Listings “Directory” column so it matches the directory selected on the edit screen (_directory_type meta). Also aligns taxonomy on save. Safe to use with multi-directory Directorist.', 'directorist-listing-tools' ),
			),
			'wc_plan_checkout_bridge' => array(
				'label'       => __( 'WooCommerce plan checkout bridge(Plan missing fix)', 'directorist-listing-tools' ),
				'description' => __( 'When Directorist WooCommerce Pricing Plans is active: fills the cart and checkout summary after submit/preview when the user lands on checkout with only the listing ID in the URL. Resolves “Nothing is available to buy” in that flow.', 'directorist-listing-tools' ),
				'requires_wc' => true,
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
			'directory_taxonomy_sync' => false,
			'wc_plan_checkout_bridge' => false,
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
		$out   = self::get_defaults();
		foreach ( array_keys( $this->features ) as $key ) {
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

		$opts = self::get_options();

		if ( ! empty( $opts['directory_taxonomy_sync'] ) ) {
			require_once DLT_DIR . 'includes/apply-features/directory-taxonomy-sync.php';
			dlt_af_bootstrap_directory_taxonomy_sync();
		}

		if ( ! empty( $opts['wc_plan_checkout_bridge'] ) ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}
			require_once DLT_DIR . 'includes/apply-features/wc-plan-checkout-bridge.php';
			dlt_af_bootstrap_wc_plan_checkout_bridge();
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
