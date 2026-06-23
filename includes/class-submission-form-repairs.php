<?php
/**
 * Directorist submission form repair tools.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only repairs for stored Directorist add-listing form config.
 */
class Directorist_Listing_Tools_Submission_Form_Repairs {

	const RESULT_OPTION = 'dlt_submission_form_repairs_last_result';

	/**
	 * Instance.
	 *
	 * @var Directorist_Listing_Tools_Submission_Form_Repairs|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Directorist_Listing_Tools_Submission_Form_Repairs
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
		if ( is_admin() ) {
			add_action( 'admin_post_dlt_fix_hidden_listing_type_required', array( $this, 'handle_fix_hidden_listing_type_required' ) );
		}
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$taxonomy = dlt_get_listing_types_taxonomy();
		$rows     = self::get_directory_rows( $taxonomy );
		$result   = get_option( self::RESULT_OPTION, array() );
		?>
		<div class="wrap dlt-form-repair-page">
			<h2><?php esc_html_e( 'Add Listing Form Repairs', 'directorist-listing-tools' ); ?></h2>

			<?php if ( ! empty( $result['message'] ) ) : ?>
				<div class="notice notice-<?php echo ! empty( $result['notice'] ) ? esc_attr( $result['notice'] ) : 'success'; ?> is-dismissible">
					<p><?php echo esc_html( $result['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="dlt-builder-preset-panel">
				<h3><?php esc_html_e( 'Fix hidden required field validation', 'directorist-listing-tools' ); ?></h3>
				<p>
					<?php esc_html_e( 'Use this when the frontend submit page says a required field is missing even though the field is not visible in the Add Listing Form. The safe mode fixes only Listing Type. The advanced mode fixes required fields that are stored in Directorist builder data but not placed in any form section.', 'directorist-listing-tools' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="dlt_fix_hidden_listing_type_required">
					<?php wp_nonce_field( 'dlt_fix_hidden_listing_type_required' ); ?>
					<p>
						<label for="dlt-form-repair-directory"><strong><?php esc_html_e( 'Directory type', 'directorist-listing-tools' ); ?></strong></label>
						<select id="dlt-form-repair-directory" name="directory_id">
							<option value="all"><?php esc_html_e( 'All affected directories', 'directorist-listing-tools' ); ?></option>
							<?php foreach ( $rows as $row ) : ?>
								<option value="<?php echo esc_attr( $row['term_id'] ); ?>" <?php selected( 'business', $row['slug'] ); ?>>
									<?php echo esc_html( $row['name'] . ' (' . $row['slug'] . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="dlt-form-repair-target"><strong><?php esc_html_e( 'Repair target', 'directorist-listing-tools' ); ?></strong></label>
						<select id="dlt-form-repair-target" name="repair_target">
							<option value="listing_type"><?php esc_html_e( 'Hidden Listing Type only', 'directorist-listing-tools' ); ?></option>
							<option value="unplaced_required"><?php esc_html_e( 'All hidden required fields not placed in the form', 'directorist-listing-tools' ); ?></option>
						</select>
						<?php submit_button( __( 'Apply Fix', 'directorist-listing-tools' ), 'primary', 'submit', false ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Safe for live use after taking a DB backup. It does not edit listing posts, listing categories, users, pricing plans, or frontend runtime code.', 'directorist-listing-tools' ); ?>
					</p>
				</form>
			</div>

			<div class="dlt-builder-preset-log">
				<h3><?php esc_html_e( 'Current directory status', 'directorist-listing-tools' ); ?></h3>
				<?php if ( empty( $rows ) ) : ?>
					<p><?php esc_html_e( 'No Directorist directory types were found.', 'directorist-listing-tools' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Directory', 'directorist-listing-tools' ); ?></th>
								<th><?php esc_html_e( 'Listing Type required', 'directorist-listing-tools' ); ?></th>
								<th><?php esc_html_e( 'Builder placement', 'directorist-listing-tools' ); ?></th>
								<th><?php esc_html_e( 'Other hidden required fields', 'directorist-listing-tools' ); ?></th>
								<th><?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $row['name'] ); ?></strong>
										<br><span class="description"><?php echo esc_html( $row['slug'] . ' / ID ' . $row['term_id'] ); ?></span>
									</td>
									<td><?php echo $row['required'] ? '<span class="dlt-status-warning">Yes</span>' : '<span class="dlt-status-ok">No</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									<td><?php echo $row['placed'] ? esc_html__( 'Stored in a group', 'directorist-listing-tools' ) : esc_html__( 'Not stored in a group', 'directorist-listing-tools' ); ?></td>
									<td>
										<?php
										if ( ! empty( $row['hidden_required_fields'] ) ) {
											echo esc_html( implode( ', ', $row['hidden_required_fields'] ) );
										} else {
											echo '<span class="dlt-status-ok">' . esc_html__( 'None', 'directorist-listing-tools' ) . '</span>';
										}
										?>
									</td>
									<td>
										<?php if ( $row['needs_fix'] ) : ?>
											<span class="dlt-status-warning"><?php esc_html_e( 'Needs fix', 'directorist-listing-tools' ); ?></span>
										<?php else : ?>
											<span class="dlt-status-ok"><?php esc_html_e( 'OK', 'directorist-listing-tools' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the one-click repair form.
	 */
	public function handle_fix_hidden_listing_type_required() {
		check_admin_referer( 'dlt_fix_hidden_listing_type_required' );

		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to run this repair.', 'directorist-listing-tools' ) );
		}

		$taxonomy     = dlt_get_listing_types_taxonomy();
		$directory_id = isset( $_POST['directory_id'] ) ? sanitize_text_field( wp_unslash( $_POST['directory_id'] ) ) : 'all';
		$repair_target = isset( $_POST['repair_target'] ) ? sanitize_key( wp_unslash( $_POST['repair_target'] ) ) : 'listing_type';
		$result        = self::run_hidden_listing_type_required_fix( $directory_id, $taxonomy, $repair_target );

		update_option( self::RESULT_OPTION, $result, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => dlt_get_post_type(),
					'page'      => 'directorist-listing-tools-form-repairs',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Apply the listing_type required repair.
	 *
	 * @param string $directory_id  Directory term ID or all.
	 * @param string $taxonomy      Directory type taxonomy.
	 * @param string $repair_target listing_type or unplaced_required.
	 * @return array
	 */
	public static function run_hidden_listing_type_required_fix( $directory_id, $taxonomy, $repair_target = 'listing_type' ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'notice'  => 'error',
				'message' => __( 'Directory type taxonomy is not registered yet.', 'directorist-listing-tools' ),
			);
		}

		if ( ! in_array( $repair_target, array( 'listing_type', 'unplaced_required' ), true ) ) {
			$repair_target = 'listing_type';
		}

		$term_ids = array();

		if ( 'all' === $directory_id ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);

			if ( is_wp_error( $terms ) ) {
				return array(
					'notice'  => 'error',
					'message' => $terms->get_error_message(),
				);
			}

			$term_ids = array_map( 'absint', $terms );
		} else {
			$term_ids = array( absint( $directory_id ) );
		}

		$checked = 0;
		$updated = 0;
		$errors  = array();

		foreach ( $term_ids as $term_id ) {
			if ( ! $term_id ) {
				continue;
			}

			$checked++;
			$result = self::repair_hidden_required_fields_for_term( $term_id, $taxonomy, $repair_target );

			if ( ! empty( $result['updated_count'] ) ) {
				$updated += (int) $result['updated_count'];
			}

			if ( ! empty( $result['error'] ) ) {
				$errors[] = $result['error'];
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'notice'  => 'error',
				'message' => implode( ' ', array_slice( $errors, 0, 3 ) ),
			);
		}

		return array(
			'notice'  => 'success',
			'message' => sprintf(
				/* translators: 1: updated fields, 2: checked directories */
				__( 'Hidden required flag fixed for %1$d field(s) across %2$d checked directories.', 'directorist-listing-tools' ),
				$updated,
				$checked
			),
		);
	}

	/**
	 * Repair hidden required fields for one directory term.
	 *
	 * @param int    $term_id       Directory term ID.
	 * @param string $taxonomy      Directory type taxonomy.
	 * @param string $repair_target listing_type or unplaced_required.
	 * @return array
	 */
	private static function repair_hidden_required_fields_for_term( $term_id, $taxonomy, $repair_target ) {
		$term = get_term( $term_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			return array(
				'updated_count' => 0,
				'error'         => __( 'Invalid directory selected.', 'directorist-listing-tools' ),
			);
		}

		$form = get_term_meta( $term_id, 'submission_form_fields', true );

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array( 'updated_count' => 0 );
		}

		$groups     = ! empty( $form['groups'] ) && is_array( $form['groups'] ) ? $form['groups'] : array();
		$field_keys = array();

		if ( 'listing_type' === $repair_target ) {
			$field_keys = array( 'listing_type' );
		} else {
			foreach ( $form['fields'] as $field_key => $field ) {
				if (
					is_array( $field )
					&& ! self::field_is_placed( $groups, $field_key )
					&& isset( $field['required'] )
					&& self::is_truthy_required_value( $field['required'] )
				) {
					$field_keys[] = $field_key;
				}
			}
		}

		$updated_count = 0;

		foreach ( $field_keys as $field_key ) {
			if ( empty( $form['fields'][ $field_key ] ) || ! is_array( $form['fields'][ $field_key ] ) ) {
				continue;
			}

			$required = isset( $form['fields'][ $field_key ]['required'] ) ? $form['fields'][ $field_key ]['required'] : '';

			if ( ! self::is_truthy_required_value( $required ) ) {
				continue;
			}

			$form['fields'][ $field_key ]['required'] = '';
			$updated_count++;
		}

		if ( $updated_count > 0 ) {
			update_term_meta( $term_id, 'submission_form_fields', $form );
			wp_cache_delete( $term_id, 'term_meta' );
		}

		return array( 'updated_count' => $updated_count );
	}

	/**
	 * Get status rows for all directory types.
	 *
	 * @param string $taxonomy Directory type taxonomy.
	 * @return array
	 */
	private static function get_directory_rows( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$rows = array();

		foreach ( $terms as $term ) {
			$form     = get_term_meta( $term->term_id, 'submission_form_fields', true );
			$field    = isset( $form['fields']['listing_type'] ) && is_array( $form['fields']['listing_type'] ) ? $form['fields']['listing_type'] : array();
			$required = isset( $field['required'] ) ? self::is_truthy_required_value( $field['required'] ) : false;
			$placed   = ! empty( $form['groups'] ) && is_array( $form['groups'] ) ? self::field_is_placed( $form['groups'], 'listing_type' ) : false;
			$hidden_required_fields = self::get_hidden_required_fields( $form );

			$rows[] = array(
				'term_id'   => (int) $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'required'  => $required,
				'placed'    => $placed,
				'hidden_required_fields' => $hidden_required_fields,
				'needs_fix' => $required || ! empty( $hidden_required_fields ),
			);
		}

		return $rows;
	}

	/**
	 * Get required fields that are not placed in any builder group.
	 *
	 * @param array $form Submission form fields meta.
	 * @return array
	 */
	private static function get_hidden_required_fields( $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array();
		}

		$groups = ! empty( $form['groups'] ) && is_array( $form['groups'] ) ? $form['groups'] : array();
		$fields = array();

		foreach ( $form['fields'] as $field_key => $field ) {
			if (
				is_array( $field )
				&& ! self::field_is_placed( $groups, $field_key )
				&& isset( $field['required'] )
				&& self::is_truthy_required_value( $field['required'] )
			) {
				$fields[] = $field_key;
			}
		}

		return $fields;
	}

	/**
	 * Determine if a field is stored in any builder group.
	 *
	 * @param array  $groups    Builder groups.
	 * @param string $field_key Field key.
	 * @return bool
	 */
	private static function field_is_placed( $groups, $field_key ) {
		foreach ( $groups as $group ) {
			if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) && in_array( $field_key, $group['fields'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize Directorist required values.
	 *
	 * @param mixed $value Required value.
	 * @return bool
	 */
	private static function is_truthy_required_value( $value ) {
		return in_array( $value, array( true, 1, '1', 'true', 'on', 'yes' ), true );
	}
}
