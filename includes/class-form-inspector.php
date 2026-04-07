<?php
/**
 * Add Listing Form Inspector — per directory type submission form snapshot.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Directorist_Listing_Tools_Form_Inspector
 */
class Directorist_Listing_Tools_Form_Inspector {

	/**
	 * Instance.
	 *
	 * @var Directorist_Listing_Tools_Form_Inspector|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Directorist_Listing_Tools_Form_Inspector
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
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		if ( ! function_exists( 'directorist_get_listing_form_fields' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Directorist is required for this tool.', 'directorist-listing-tools' ) . '</p></div>';
			return;
		}

		echo '<div class="wrap dlt-form-inspector-page">';

		$taxonomy     = dlt_get_listing_types_taxonomy();
		$directory_id = isset( $_GET['dlt_directory'] ) ? absint( wp_unslash( $_GET['dlt_directory'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$types = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $types ) ) {
			$types = array();
		}

		$parent_base = admin_url( 'edit.php?post_type=' . dlt_get_post_type() );
		$base_url    = add_query_arg( array( 'page' => 'directorist-listing-tools-form-inspector' ), $parent_base );

		$this->render_global_notes();
		$this->render_type_switcher( $base_url, $types, $directory_id );

		if ( $directory_id > 0 ) {
			$term = get_term( $directory_id, $taxonomy );
			if ( ! $term || is_wp_error( $term ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Invalid directory type selected.', 'directorist-listing-tools' ) . '</p></div>';
			} else {
				$this->render_single_type_detail( $term, $base_url );
			}
		} else {
			$this->render_all_types_overview( $types, $base_url );
		}

		echo '</div><!-- .dlt-form-inspector-page -->';
	}

	/**
	 * Directorist-wide options that affect gallery / images on all types.
	 */
	private function render_global_notes() {
		$display_gallery = function_exists( 'get_directorist_option' ) ? get_directorist_option( 'display_gallery_field', null ) : null;
		$require_gallery = function_exists( 'get_directorist_option' ) ? get_directorist_option( 'require_gallery_img', null ) : null;

		?>
		<div class="dlt-form-inspector-global card" style="max-width:960px;padding:12px 16px;margin:16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Global image options', 'directorist-listing-tools' ); ?></h2>
			<table class="widefat striped" style="max-width:720px;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'display_gallery_field', 'directorist-listing-tools' ); ?></strong></td>
						<td>
							<?php
							if ( null === $display_gallery ) {
								esc_html_e( 'n/a (get_directorist_option unavailable)', 'directorist-listing-tools' );
							} else {
								echo esc_html( is_bool( $display_gallery ) ? ( $display_gallery ? 'true' : 'false' ) : (string) $display_gallery );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'require_gallery_img', 'directorist-listing-tools' ); ?></strong></td>
						<td>
							<?php
							if ( null === $require_gallery ) {
								esc_html_e( 'n/a', 'directorist-listing-tools' );
							} else {
								echo esc_html( is_bool( $require_gallery ) ? ( $require_gallery ? 'true' : 'false' ) : (string) $require_gallery );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Type dropdown + link back to overview.
	 *
	 * @param string $base_url    Inspector page URL without query args except page.
	 * @param array  $types       Term objects.
	 * @param int    $directory_id Selected term id (0 = overview).
	 */
	private function render_type_switcher( $base_url, $types, $directory_id ) {
		$overview_url = remove_query_arg( 'dlt_directory', $base_url );
		?>
		<div class="dlt-form-inspector-nav" style="margin:16px 0;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
			<label for="dlt-inspector-directory">
				<strong><?php esc_html_e( 'Directory type', 'directorist-listing-tools' ); ?></strong>
			</label>
			<select id="dlt-inspector-directory" class="regular-text" style="min-width:280px;"
				onchange="if(this.value){window.location.href=this.value;}else{window.location.href='<?php echo esc_url( $overview_url ); ?>';}">
				<option value=""><?php esc_html_e( '— All types (overview) —', 'directorist-listing-tools' ); ?></option>
				<?php foreach ( $types as $t ) : ?>
					<?php
					$url = add_query_arg( array( 'dlt_directory' => $t->term_id ), $base_url );
					?>
					<option value="<?php echo esc_url( $url ); ?>" <?php selected( $directory_id, $t->term_id ); ?>>
						<?php echo esc_html( $t->name . ' (ID ' . $t->term_id . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<a class="button" href="<?php echo esc_url( $overview_url ); ?>"><?php esc_html_e( 'Overview table', 'directorist-listing-tools' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Overview table for every directory type.
	 *
	 * @param array  $types    Term objects.
	 * @param string $base_url Base inspector URL.
	 */
	private function render_all_types_overview( $types, $base_url ) {
		if ( empty( $types ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No directory types found.', 'directorist-listing-tools' ) . '</p></div>';
			return;
		}

		?>
		<h2><?php esc_html_e( 'Submission form overview', 'directorist-listing-tools' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'ID', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Active fields', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Gallery (image_upload)', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Required', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Orphans', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Risk flags', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Details', 'directorist-listing-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $types as $term ) : ?>
					<?php
					$snapshot = $this->get_directory_form_snapshot( $term->term_id );
					$detail   = add_query_arg( array( 'dlt_directory' => $term->term_id ), $base_url );
					?>
					<tr>
						<td><strong><?php echo esc_html( $term->name ); ?></strong></td>
						<td><?php echo esc_html( (string) $term->term_id ); ?></td>
						<td><?php echo esc_html( (string) $snapshot['active_field_count'] ); ?></td>
						<td>
							<?php echo $snapshot['has_image_in_groups'] ? '<span style="color:#007017;">' . esc_html__( 'In form', 'directorist-listing-tools' ) . '</span>' : '<span style="color:#666;">' . esc_html__( 'Not in form', 'directorist-listing-tools' ) . '</span>'; ?>
						</td>
						<td>
							<?php
							if ( ! empty( $snapshot['image_required_anywhere'] ) ) {
								echo '<span style="color:#b32d2e;">' . esc_html__( 'Yes', 'directorist-listing-tools' ) . '</span>';
								if ( ! empty( $snapshot['image_required_in_stored_only'] ) ) {
									echo ' <span class="description">(' . esc_html__( 'stored only', 'directorist-listing-tools' ) . ')</span>';
								}
							} else {
								echo esc_html__( 'No', 'directorist-listing-tools' );
							}
							?>
						</td>
						<td><?php echo esc_html( (string) count( $snapshot['orphan_field_keys'] ) ); ?></td>
						<td><?php echo wp_kses_post( $this->format_risk_flags_cell( $snapshot ) ); ?></td>
						<td><a href="<?php echo esc_url( $detail ); ?>" class="button button-small"><?php esc_html_e( 'View', 'directorist-listing-tools' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Single directory detail view.
	 *
	 * @param WP_Term $term     Directory term.
	 * @param string  $base_url Base URL for switcher.
	 */
	private function render_single_type_detail( $term, $base_url ) {
		$snapshot = $this->get_directory_form_snapshot( $term->term_id );

		?>
		<h2>
			<?php
			printf(
				/* translators: %s: directory type name */
				esc_html__( 'Submission form: %s', 'directorist-listing-tools' ),
				esc_html( $term->name )
			);
			?>
		</h2>

		<div class="dlt-form-inspector-flags card" style="padding:12px 16px;margin:16px 0;max-width:960px;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Quick flags', 'directorist-listing-tools' ); ?></h3>
			<ul style="list-style:disc;margin-left:1.5em;">
				<li>
					<?php
					printf(
						/* translators: %d: count */
						esc_html__( 'Active fields (in groups): %d', 'directorist-listing-tools' ),
						(int) $snapshot['active_field_count']
					);
					?>
				</li>
				<li>
					<?php
					echo $snapshot['has_image_in_groups']
						? esc_html__( 'Gallery is in a form section.', 'directorist-listing-tools' )
						: esc_html__( 'Gallery is not in any section.', 'directorist-listing-tools' );
					?>
				</li>
				<li>
					<?php
					if ( ! empty( $snapshot['image_required_in_form'] ) ) {
						echo '<strong style=”color:#b32d2e;”>' . esc_html__( 'Gallery is required on the form.', 'directorist-listing-tools' ) . '</strong>';
					} elseif ( ! empty( $snapshot['image_required_in_stored_only'] ) ) {
						echo '<strong style=”color:#b32d2e;”>' . esc_html__( 'Gallery marked required but not in any section (risk of hidden errors).', 'directorist-listing-tools' ) . '</strong>';
					} else {
						echo esc_html__( 'Gallery is not required.', 'directorist-listing-tools' );
					}
					?>
				</li>
				<?php if ( ! empty( $snapshot['orphan_field_keys'] ) ) : ?>
					<li>
						<strong><?php esc_html_e( 'Orphan field keys (not in any group):', 'directorist-listing-tools' ); ?></strong>
						<code><?php echo esc_html( implode( ', ', $snapshot['orphan_field_keys'] ) ); ?></code>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $snapshot['broken_group_refs'] ) ) : ?>
					<li>
						<strong style="color:#b32d2e;"><?php esc_html_e( 'Broken references (group points to missing field key):', 'directorist-listing-tools' ); ?></strong>
						<code><?php echo esc_html( implode( ', ', $snapshot['broken_group_refs'] ) ); ?></code>
					</li>
				<?php endif; ?>
			</ul>
			<?php echo wp_kses_post( $this->format_risk_flags_cell( $snapshot, true ) ); ?>
		</div>

		<h3><?php esc_html_e( 'Form sections (groups) and field order', 'directorist-listing-tools' ); ?></h3>
		<?php $this->render_groups_table( $snapshot['groups'], $snapshot['fields_by_key'] ); ?>

		<h3><?php esc_html_e( 'Active fields (detail)', 'directorist-listing-tools' ); ?></h3>
		<?php $this->render_fields_table( $snapshot['ordered_field_keys'], $snapshot['fields_by_key'] ); ?>

		<h3><?php esc_html_e( 'image_upload field (Directorist preset key)', 'directorist-listing-tools' ); ?></h3>
		<?php
		$img = $snapshot['image_upload_raw'];
		if ( empty( $img ) ) {
			echo '<p class="description">' . esc_html__( 'No image_upload config found.', 'directorist-listing-tools' ) . '</p>';
		} else {
			echo '<pre style="overflow:auto;max-height:320px;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;">' . esc_html( wp_json_encode( $img, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
		}
		?>

		<h3><?php esc_html_e( 'Raw JSON', 'directorist-listing-tools' ); ?></h3>
		<?php
		$raw = $snapshot['raw_submission_form'];
		if ( empty( $raw ) ) {
			echo '<p class="description">' . esc_html__( 'No submission_form_fields term meta found.', 'directorist-listing-tools' ) . '</p>';
		} else {
			echo '<pre style="overflow:auto;max-height:480px;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7;">' . esc_html( wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
		}
	}

	/**
	 * Groups table.
	 *
	 * @param array $groups         From directorist_get_listing_form_groups.
	 * @param array $fields_by_key  Field configs keyed by widget_key.
	 */
	private function render_groups_table( $groups, $fields_by_key ) {
		if ( empty( $groups ) ) {
			echo '<p class="description">' . esc_html__( 'No groups defined.', 'directorist-listing-tools' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Section', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Fields (order)', 'directorist-listing-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $groups as $g ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $g['label'] ?? '' ); ?></strong></td>
						<td>
							<?php
							$keys = isset( $g['fields'] ) && is_array( $g['fields'] ) ? $g['fields'] : array();
							$labels = array();
							foreach ( $keys as $fk ) {
								$cfg = $fields_by_key[ $fk ] ?? array();
								$lab = isset( $cfg['label'] ) ? (string) $cfg['label'] : '';
								$labels[] = $lab ? sprintf( '%s (%s)', $fk, $lab ) : $fk;
							}
							echo esc_html( implode( ' → ', $labels ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Fields table for active keys.
	 *
	 * @param array $ordered Keys in form order.
	 * @param array $fields_by_key Configs.
	 */
	private function render_fields_table( $ordered, $fields_by_key ) {
		if ( empty( $ordered ) ) {
			echo '<p class="description">' . esc_html__( 'No active fields.', 'directorist-listing-tools' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Key', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Label', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'widget_name', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'field_key (POST)', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Required', 'directorist-listing-tools' ); ?></th>
					<th><?php esc_html_e( 'Admin only', 'directorist-listing-tools' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$i = 0;
				foreach ( $ordered as $key ) {
					++$i;
					$cfg      = $fields_by_key[ $key ] ?? array();
					$label    = isset( $cfg['label'] ) ? (string) $cfg['label'] : '';
					$widget   = isset( $cfg['widget_name'] ) ? (string) $cfg['widget_name'] : '';
					$fkey     = isset( $cfg['field_key'] ) ? (string) $cfg['field_key'] : '';
					$req      = ! empty( $cfg['required'] );
					$admin    = ! empty( $cfg['only_for_admin'] );
					?>
					<tr>
						<td><?php echo esc_html( (string) $i ); ?></td>
						<td><code><?php echo esc_html( $key ); ?></code></td>
						<td><?php echo esc_html( $label ); ?></td>
						<td><code><?php echo esc_html( $widget ); ?></code></td>
						<td><code><?php echo esc_html( $fkey ); ?></code></td>
						<td><?php echo $req ? '<strong style="color:#b32d2e;">' . esc_html__( 'Yes', 'directorist-listing-tools' ) . '</strong>' : esc_html__( 'No', 'directorist-listing-tools' ); ?></td>
						<td><?php echo $admin ? esc_html__( 'Yes', 'directorist-listing-tools' ) : esc_html__( 'No', 'directorist-listing-tools' ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Build snapshot array for a directory term.
	 *
	 * @param int $directory_id Term ID.
	 * @return array
	 */
	private function get_directory_form_snapshot( $directory_id ) {
		$form = function_exists( 'directorist_get_directory_meta' )
			? directorist_get_directory_meta( $directory_id, 'submission_form_fields' )
			: get_term_meta( $directory_id, 'submission_form_fields', true );

		if ( ! is_array( $form ) ) {
			$form = array();
		}

		$fields_all   = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$groups_raw   = isset( $form['groups'] ) && is_array( $form['groups'] ) ? $form['groups'] : array();
		$keys_in_form = function_exists( 'directorist_get_listing_form_fields' )
			? directorist_get_listing_form_fields( $directory_id )
			: array();

		$ordered_field_keys = array_keys( $keys_in_form );
		$fields_by_key      = array();
		foreach ( $ordered_field_keys as $k ) {
			$fields_by_key[ $k ] = isset( $fields_all[ $k ] ) && is_array( $fields_all[ $k ] ) ? $fields_all[ $k ] : array();
		}

		$keys_in_groups = array();
		foreach ( $groups_raw as $g ) {
			if ( ! empty( $g['fields'] ) && is_array( $g['fields'] ) ) {
				$keys_in_groups = array_merge( $keys_in_groups, $g['fields'] );
			}
		}
		$keys_in_groups = array_unique( $keys_in_groups );

		$orphans = array();
		foreach ( array_keys( $fields_all ) as $fk ) {
			if ( ! in_array( $fk, $keys_in_groups, true ) ) {
				$orphans[] = $fk;
			}
		}
		sort( $orphans );

		$broken = array();
		foreach ( $keys_in_groups as $gk ) {
			if ( ! isset( $fields_all[ $gk ] ) ) {
				$broken[] = $gk;
			}
		}
		sort( $broken );

		$has_image_in_groups = in_array( 'image_upload', $keys_in_groups, true );
		$image_active        = isset( $keys_in_form['image_upload'] ) ? $keys_in_form['image_upload'] : array();
		$image_upload_raw    = isset( $fields_all['image_upload'] ) ? $fields_all['image_upload'] : array();
		$image_required_in_active_form   = $has_image_in_groups && ! empty( $image_active['required'] );
		$image_required_in_stored_only = ! $has_image_in_groups && ! empty( $image_upload_raw['required'] );
		$image_required_anywhere       = $image_required_in_active_form || ! empty( $image_upload_raw['required'] );

		$orphans_has_required_image = false;
		foreach ( $orphans as $ok ) {
			$c = $fields_all[ $ok ] ?? array();
			if ( ! empty( $c['required'] ) && isset( $c['widget_name'] ) && 'image_upload' === $c['widget_name'] ) {
				$orphans_has_required_image = true;
				break;
			}
		}

		return array(
			'raw_submission_form'             => $form,
			'groups'                          => function_exists( 'directorist_get_listing_form_groups' ) ? directorist_get_listing_form_groups( $directory_id ) : array(),
			'ordered_field_keys'             => $ordered_field_keys,
			'fields_by_key'                   => $fields_by_key,
			'active_field_count'             => count( $ordered_field_keys ),
			'has_image_in_groups'            => $has_image_in_groups,
			'image_required_in_form'        => $image_required_in_active_form,
			'image_required_in_stored_only' => $image_required_in_stored_only,
			'image_required_anywhere'       => $image_required_anywhere,
			'orphan_field_keys'             => $orphans,
			'broken_group_refs'             => $broken,
			'image_upload_raw'              => $image_upload_raw,
			'orphans_required_image'        => $orphans_has_required_image,
		);
	}

	/**
	 * Format risk flags for table cell or block.
	 *
	 * @param array $snapshot Snapshot.
	 * @param bool  $as_block Block HTML vs inline.
	 * @return string HTML
	 */
	private function format_risk_flags_cell( $snapshot, $as_block = false ) {
		$flags = array();

		if ( ! empty( $snapshot['image_required_in_stored_only'] ) ) {
			$flags[] = __( 'Required gallery in saved config but image_upload not in any group — can cause submit/UI mismatch.', 'directorist-listing-tools' );
		}

		$req_glob = function_exists( 'get_directorist_option' ) ? (bool) get_directorist_option( 'require_gallery_img', false ) : false;
		if ( $req_glob && ! $snapshot['has_image_in_groups'] ) {
			$flags[] = __( 'Global require_gallery_img is on but this type has no gallery in form groups.', 'directorist-listing-tools' );
		}

		if ( ! empty( $snapshot['broken_group_refs'] ) ) {
			$flags[] = __( 'Broken group references (missing field definitions).', 'directorist-listing-tools' );
		}

		if ( ! empty( $snapshot['orphan_field_keys'] ) ) {
			$flags[] = __( 'Orphan field keys present (saved but not in any section).', 'directorist-listing-tools' );
		}

		if ( ! empty( $snapshot['orphans_required_image'] ) ) {
			$flags[] = __( 'Orphan image field marked required — may not render on frontend.', 'directorist-listing-tools' );
		}

		if ( empty( $flags ) ) {
			$inner = '<span style="color:#007017;">' . esc_html__( 'None highlighted', 'directorist-listing-tools' ) . '</span>';
		} else {
			$inner = '<ul style="margin:0.5em 0 0 1.2em;"><li>' . implode( '</li><li>', array_map( 'esc_html', $flags ) ) . '</li></ul>';
		}

		if ( $as_block ) {
			return '<div class="dlt-risk-flags"><p><strong>' . esc_html__( 'Risk flags (heuristic)', 'directorist-listing-tools' ) . '</strong></p>' . $inner . '</div>';
		}

		if ( empty( $flags ) ) {
			return '<span style="color:#007017;">—</span>';
		}

		return '<span style="color:#b32d2e;">' . esc_html( (string) count( $flags ) ) . '</span>';
	}
}
