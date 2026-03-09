<?php
/**
 * Plan Manager Class
 *
 * Lists all Directorist Pricing Plans and lets the admin edit
 * price, free-plan flag, tax enabled, tax type and tax amount
 * inline via AJAX — no page reload needed.
 *
 * Requires the "Directorist Pricing Plans" add-on to be active
 * (post type: atbdp_pricing_plans).
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plan Manager handler class.
 */
class Directorist_Listing_Tools_Plan_Manager {

	/** @var self|null */
	private static $instance = null;

	/** Post type slug used by the Pricing Plans add-on. */
	const PLAN_POST_TYPE = 'atbdp_pricing_plans';

	// ─────────────────────────────────────────────────
	// Boot
	// ─────────────────────────────────────────────────

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_dlt_save_plan_prices', array( $this, 'handle_ajax_save' ) );
	}

	// ─────────────────────────────────────────────────
	// Helpers
	// ─────────────────────────────────────────────────

	/**
	 * Check whether the Pricing Plans add-on is active.
	 *
	 * @return bool
	 */
	private function is_pricing_plans_active() {
		return post_type_exists( self::PLAN_POST_TYPE );
	}

	/**
	 * Retrieve all pricing plans (published + draft).
	 *
	 * @return WP_Post[]
	 */
	private function get_plans() {
		return get_posts(
			array(
				'post_type'      => self::PLAN_POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/**
	 * Fetch all editable meta values for one plan.
	 *
	 * @param int $plan_id
	 * @return array
	 */
	private function get_plan_data( $plan_id ) {
		return array(
			'plan_id'       => $plan_id,
			'title'         => get_the_title( $plan_id ),
			'status'        => get_post_status( $plan_id ),
			'fm_price'      => (string) get_post_meta( $plan_id, 'fm_price', true ),
			'free_plan'     => (bool) get_post_meta( $plan_id, 'free_plan', true ),
			'plan_tax'      => (bool) get_post_meta( $plan_id, 'plan_tax', true ),
			'plan_tax_type' => get_post_meta( $plan_id, 'plan_tax_type', true ) ?: 'percent',
			'fm_tax'        => (string) get_post_meta( $plan_id, 'fm_tax', true ),
			'fm_description'=> get_post_meta( $plan_id, 'fm_description', true ),
		);
	}

	/**
	 * Calculate what the checkout will show as effective price (for display).
	 *
	 * @param array $data Plan data from get_plan_data().
	 * @return string
	 */
	private function effective_price( $data ) {
		if ( $data['free_plan'] ) {
			return '0.00 (Free)';
		}
		$price = (float) $data['fm_price'];
		if ( ! $price ) {
			return '0.00';
		}
		if ( ! $data['plan_tax'] ) {
			return number_format( $price, 2 );
		}
		$tax = (float) $data['fm_tax'];
		if ( 'percent' === $data['plan_tax_type'] ) {
			$tax_amount = ( $tax * $price ) / 100;
		} else {
			$tax_amount = $tax;
		}
		return number_format( $price, 2 ) . ' + ' . number_format( $tax_amount, 2 ) . ' tax';
	}

	// ─────────────────────────────────────────────────
	// AJAX Handler
	// ─────────────────────────────────────────────────

	/**
	 * Save plan price / tax meta via AJAX.
	 */
	public function handle_ajax_save() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ) );
		}

		$plan_id = absint( isset( $_POST['plan_id'] ) ? $_POST['plan_id'] : 0 );
		if ( ! $plan_id || get_post_type( $plan_id ) !== self::PLAN_POST_TYPE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid plan ID.', 'directorist-listing-tools' ) ) );
		}

		// Sanitize inputs.
		$fm_price      = isset( $_POST['fm_price'] ) ? floatval( wp_unslash( $_POST['fm_price'] ) ) : 0;
		$free_plan     = ! empty( $_POST['free_plan'] ) ? 1 : 0;
		$plan_tax      = ! empty( $_POST['plan_tax'] ) ? 1 : 0;
		$plan_tax_type = isset( $_POST['plan_tax_type'] ) ? sanitize_key( $_POST['plan_tax_type'] ) : 'percent';
		$fm_tax        = isset( $_POST['fm_tax'] ) ? floatval( wp_unslash( $_POST['fm_tax'] ) ) : 0;

		if ( ! in_array( $plan_tax_type, array( 'percent', 'flat' ), true ) ) {
			$plan_tax_type = 'percent';
		}

		// Save.
		update_post_meta( $plan_id, 'fm_price',      $fm_price );
		update_post_meta( $plan_id, 'free_plan',     $free_plan );
		update_post_meta( $plan_id, 'plan_tax',      $plan_tax );
		update_post_meta( $plan_id, 'plan_tax_type', $plan_tax_type );
		update_post_meta( $plan_id, 'fm_tax',        $fm_tax );

		// Rebuild effective price for display.
		$data             = $this->get_plan_data( $plan_id );
		$effective        = $this->effective_price( $data );

		wp_send_json_success(
			array(
				'message'       => sprintf(
					/* translators: %s: plan title */
					__( 'Plan "%s" saved successfully.', 'directorist-listing-tools' ),
					get_the_title( $plan_id )
				),
				'plan_id'       => $plan_id,
				'effective'     => $effective,
				'free_plan'     => $free_plan,
			)
		);
	}

	// ─────────────────────────────────────────────────
	// Render
	// ─────────────────────────────────────────────────

	/**
	 * Render the Plan Manager admin page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$pricing_active = $this->is_pricing_plans_active();
		$plans          = $pricing_active ? $this->get_plans() : array();
		$tax_label      = ucfirst( get_directorist_option( 'tax_placeholder', 'Tax' ) );
		?>
		<div class="wrap dlt-plan-manager-wrap">

			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-tag" style="font-size:28px;vertical-align:middle;margin-right:6px;color:#2271b1;"></span>
				<?php esc_html_e( 'Pricing Plan Manager', 'directorist-listing-tools' ); ?>
			</h1>
			<p class="description" style="margin-top:6px;">
				<?php esc_html_e( 'Edit the price and tax settings for each Directorist Pricing Plan. Click "Save" on any row to update instantly.', 'directorist-listing-tools' ); ?>
			</p>

			<div id="dlt-pm-global-message" style="display:none;margin:15px 0;"></div>

			<?php if ( ! $pricing_active ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'The Directorist Pricing Plans add-on is not active. Please install and activate it to use this page.', 'directorist-listing-tools' ); ?>
					</p>
				</div>
			<?php elseif ( empty( $plans ) ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						printf(
							wp_kses(
								/* translators: %s: link to add new plan */
								__( 'No pricing plans found. <a href="%s">Create your first plan</a>.', 'directorist-listing-tools' ),
								array( 'a' => array( 'href' => array() ) )
							),
							esc_url( admin_url( 'post-new.php?post_type=' . self::PLAN_POST_TYPE ) )
						);
						?>
					</p>
				</div>
			<?php else : ?>

			<div class="dlt-pm-table-wrap postbox" style="margin-top:20px;">
				<div class="postbox-header">
					<h2 class="hndle">
						<span class="dashicons dashicons-list-view" style="vertical-align:middle;margin-right:6px;"></span>
						<?php
						printf(
							/* translators: %d: plan count */
							esc_html__( 'All Plans (%d)', 'directorist-listing-tools' ),
							count( $plans )
						);
						?>
					</h2>
				</div>
				<div class="inside" style="padding:0;">
					<table class="dlt-pm-table wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width:18%;"><?php esc_html_e( 'Plan Name', 'directorist-listing-tools' ); ?></th>
								<th style="width:11%;"><?php esc_html_e( 'Price', 'directorist-listing-tools' ); ?></th>
								<th style="width:9%;text-align:center;"><?php esc_html_e( 'Free Plan', 'directorist-listing-tools' ); ?></th>
								<th style="width:9%;text-align:center;"><?php echo esc_html( $tax_label ); ?></th>
								<th style="width:10%;"><?php esc_html_e( 'Tax Type', 'directorist-listing-tools' ); ?></th>
								<th style="width:11%;"><?php esc_html_e( 'Tax Amount / %', 'directorist-listing-tools' ); ?></th>
								<th style="width:18%;"><?php esc_html_e( 'Effective Checkout Price', 'directorist-listing-tools' ); ?></th>
								<th style="width:14%;text-align:center;"><?php esc_html_e( 'Action', 'directorist-listing-tools' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $plans as $plan ) :
							$d         = $this->get_plan_data( $plan->ID );
							$effective = $this->effective_price( $d );
							$edit_url  = get_edit_post_link( $plan->ID );
						?>
						<tr class="dlt-pm-row" data-plan-id="<?php echo esc_attr( $plan->ID ); ?>">

							<!-- Plan Name -->
							<td class="dlt-pm-name-cell">
								<strong><?php echo esc_html( $d['title'] ); ?></strong>
								<br>
								<span class="dlt-pm-status <?php echo 'publish' === $d['status'] ? 'dlt-pm-status-pub' : 'dlt-pm-status-draft'; ?>">
									<?php echo esc_html( ucfirst( $d['status'] ) ); ?>
								</span>
								<?php if ( $edit_url ) : ?>
									<br><a href="<?php echo esc_url( $edit_url ); ?>" class="dlt-pm-edit-link" target="_blank">
										<?php esc_html_e( 'Edit in WP', 'directorist-listing-tools' ); ?>
									</a>
								<?php endif; ?>
							</td>

							<!-- Price -->
							<td>
								<input
									type="number"
									step="0.01"
									min="0"
									class="dlt-pm-input dlt-pm-price"
									name="fm_price"
									value="<?php echo esc_attr( $d['fm_price'] ); ?>"
									placeholder="0.00"
								/>
							</td>

							<!-- Free Plan toggle -->
							<td style="text-align:center;">
								<label class="dlt-ds-toggle" title="Free Plan">
									<input
										type="checkbox"
										class="dlt-ds-toggle-input dlt-pm-free-plan"
										name="free_plan"
										value="1"
										<?php checked( $d['free_plan'] ); ?>
									/>
									<span class="dlt-ds-toggle-slider"></span>
								</label>
							</td>

							<!-- Tax enabled toggle -->
							<td style="text-align:center;">
								<label class="dlt-ds-toggle" title="Enable Tax">
									<input
										type="checkbox"
										class="dlt-ds-toggle-input dlt-pm-tax-toggle"
										name="plan_tax"
										value="1"
										<?php checked( $d['plan_tax'] ); ?>
									/>
									<span class="dlt-ds-toggle-slider"></span>
								</label>
							</td>

							<!-- Tax Type -->
							<td>
								<select class="dlt-pm-select dlt-pm-tax-type" name="plan_tax_type">
									<option value="percent" <?php selected( $d['plan_tax_type'], 'percent' ); ?>>
										<?php esc_html_e( 'Percentage (%)', 'directorist-listing-tools' ); ?>
									</option>
									<option value="flat" <?php selected( $d['plan_tax_type'], 'flat' ); ?>>
										<?php esc_html_e( 'Flat Rate', 'directorist-listing-tools' ); ?>
									</option>
								</select>
							</td>

							<!-- Tax Amount -->
							<td>
								<input
									type="number"
									step="0.01"
									min="0"
									class="dlt-pm-input dlt-pm-tax-amount"
									name="fm_tax"
									value="<?php echo esc_attr( $d['fm_tax'] ); ?>"
									placeholder="0"
								/>
							</td>

							<!-- Effective Price (read-only display) -->
							<td class="dlt-pm-effective">
								<span class="dlt-pm-effective-value">
									<?php echo esc_html( $effective ); ?>
								</span>
							</td>

							<!-- Save button -->
							<td style="text-align:center;vertical-align:middle;">
								<button
									type="button"
									class="button button-primary dlt-pm-save-btn"
									data-plan-id="<?php echo esc_attr( $plan->ID ); ?>"
								><?php esc_html_e( 'Save', 'directorist-listing-tools' ); ?></button>
								<span class="dlt-pm-row-spinner spinner" style="float:none;visibility:hidden;margin:0 4px;vertical-align:middle;"></span>
							</td>

						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div><!-- .inside -->
			</div><!-- .postbox -->

			<div class="dlt-ds-footer" style="margin-top:12px;">
				<p>
					<span class="dashicons dashicons-info-outline" style="vertical-align:middle;"></span>
					<?php esc_html_e( 'These values are saved directly to each plan\'s post meta (fm_price, plan_tax, fm_tax, etc.) — the same fields Directorist Pricing Plans uses on the checkout page.', 'directorist-listing-tools' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'Setting "Free Plan" ON forces the checkout price to 0 regardless of the price field. Setting Price to 0 automatically removes any tax from the checkout.', 'directorist-listing-tools' ); ?>
				</p>
			</div>

			<?php endif; ?>

		</div><!-- .dlt-plan-manager-wrap -->
		<?php
	}
}
