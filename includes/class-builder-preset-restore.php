<?php
/**
 * Restore missing Directorist builder preset fields.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repairs known directory builder preset fields without touching listings.
 */
class Directorist_Listing_Tools_Builder_Preset_Restore {

	/**
	 * Last repair log option.
	 *
	 * @var string
	 */
	const LOG_OPTION = 'dlt_builder_preset_restore_log';

	/**
	 * Instance.
	 *
	 * @var Directorist_Listing_Tools_Builder_Preset_Restore|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Directorist_Listing_Tools_Builder_Preset_Restore
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
			add_action( 'wp_ajax_dlt_ajax_builder_preset_reload', array( $this, 'ajax_run_repair' ) );
			add_action( 'wp_ajax_dlt_ajax_builder_preset_docs', array( $this, 'ajax_get_docs' ) );
		}
	}

	/**
	 * Render admin tool page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$last_log = get_option( self::LOG_OPTION, array() );
		?>
		<div class="wrap dlt-builder-preset-page">
			<h2><?php esc_html_e( 'Builder Preset Reload', 'directorist-listing-tools' ); ?></h2>

			<div id="dlt-builder-preset-message" class="dlt-message" aria-live="polite"></div>

			<div class="dlt-builder-preset-panel">
				<h3><?php esc_html_e( 'Restore missing preset fields', 'directorist-listing-tools' ); ?></h3>
				<p>
					<?php esc_html_e( 'This repairs Directorist directory builder data for the known missing preset fields without touching existing listings.', 'directorist-listing-tools' ); ?>
				</p>
				<div class="dlt-builder-preset-actions">
					<button
						type="button"
						id="dlt-builder-preset-run"
						class="button button-primary"
						data-running-label="<?php esc_attr_e( 'Reloading...', 'directorist-listing-tools' ); ?>"
						data-complete-label="<?php esc_attr_e( 'Completed', 'directorist-listing-tools' ); ?>"
					>
						<?php esc_html_e( 'Reload Builder Presets', 'directorist-listing-tools' ); ?>
					</button>
					<span class="description">
						<?php esc_html_e( 'Runs once per click, then stops. Listing counts are checked before and after.', 'directorist-listing-tools' ); ?>
					</span>
				</div>
			</div>

			<div id="dlt-builder-preset-result">
				<?php
				if ( ! empty( $last_log['directories'] ) && is_array( $last_log['directories'] ) ) {
					echo self::get_log_table_html( $last_log ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

			<div class="dlt-builder-preset-docs">
				<button type="button" id="dlt-builder-preset-docs-toggle" class="button" data-loaded="0">
					<?php esc_html_e( 'Show docs', 'directorist-listing-tools' ); ?>
				</button>
				<div id="dlt-builder-preset-docs-panel" class="dlt-builder-preset-docs-panel" hidden></div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: run repair.
	 */
	public function ajax_run_repair() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to run this repair.', 'directorist-listing-tools' ),
				),
				403
			);
		}

		$log     = self::run_repair();
		$message = ( 'updated' === $log['status'] )
			? __( 'Builder presets were reloaded successfully.', 'directorist-listing-tools' )
			: __( 'Builder preset reload finished. No new changes were needed.', 'directorist-listing-tools' );

		if ( ! empty( $log['message'] ) ) {
			$message = $log['message'];
		}

		wp_send_json_success(
			array(
				'message' => '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>',
				'html'    => self::get_log_table_html( $log ),
				'status'  => $log['status'],
			)
		);
	}

	/**
	 * AJAX: return hidden docs.
	 */
	public function ajax_get_docs() {
		check_ajax_referer( 'dlt_admin_nonce', 'nonce' );

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to view these docs.', 'directorist-listing-tools' ),
				),
				403
			);
		}

		wp_send_json_success(
			array(
				'html' => self::get_docs_html(),
			)
		);
	}

	/**
	 * Build a repair log table.
	 *
	 * @param array $log Repair log.
	 * @return string
	 */
	private static function get_log_table_html( $log ) {
		if ( empty( $log['directories'] ) || ! is_array( $log['directories'] ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="dlt-builder-preset-log">
			<h3><?php esc_html_e( 'Last reload result', 'directorist-listing-tools' ); ?></h3>
			<p class="description">
				<?php
				printf(
					/* translators: %s: date/time */
					esc_html__( 'Ran at: %s', 'directorist-listing-tools' ),
					esc_html( ! empty( $log['ran_at'] ) ? $log['ran_at'] : '-' )
				);
				?>
			</p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Directory', 'directorist-listing-tools' ); ?></th>
						<th><?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?></th>
						<th><?php esc_html_e( 'Fields', 'directorist-listing-tools' ); ?></th>
						<th><?php esc_html_e( 'Listing count', 'directorist-listing-tools' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log['directories'] as $directory ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( ! empty( $directory['name'] ) ? $directory['name'] : '-' ); ?></strong>
								<?php if ( ! empty( $directory['term_id'] ) ) : ?>
									<br><span class="description"><?php echo esc_html( 'ID ' . (int) $directory['term_id'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( ! empty( $directory['status'] ) ? $directory['status'] : '-' ); ?></td>
							<td>
								<?php
								$restored = array_merge(
									! empty( $directory['restored_definitions'] ) && is_array( $directory['restored_definitions'] ) ? $directory['restored_definitions'] : array(),
									! empty( $directory['restored_placements'] ) && is_array( $directory['restored_placements'] ) ? $directory['restored_placements'] : array()
								);
								$restored = array_values( array_unique( $restored ) );
								echo esc_html( ! empty( $restored ) ? implode( ', ', $restored ) : __( 'No field changes', 'directorist-listing-tools' ) );
								?>
							</td>
							<td>
								<?php
								if ( isset( $directory['before_count'], $directory['after_count'] ) ) {
									echo esc_html( (int) $directory['before_count'] . ' -> ' . (int) $directory['after_count'] );
									echo ! empty( $directory['counts_unchanged'] ) ? ' <span class="dlt-status-ok">' . esc_html__( 'unchanged', 'directorist-listing-tools' ) . '</span>' : ' <span class="dlt-status-warning">' . esc_html__( 'changed', 'directorist-listing-tools' ) . '</span>';
								} else {
									echo esc_html__( 'n/a', 'directorist-listing-tools' );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Documentation content loaded only on demand.
	 *
	 * @return string
	 */
	private static function get_docs_html() {
		ob_start();
		?>
		<div class="dlt-builder-preset-docs-content">
			<h3><?php esc_html_e( 'Why this tool exists', 'directorist-listing-tools' ); ?></h3>
			<p><?php esc_html_e( 'Directorist stores builder data separately for each directory type. A preset field can exist in the stored field dictionary but be missing from every builder section. When that happens, the field is hidden from the Add Listing Form and also removed from the available preset list because the builder thinks it is already selected.', 'directorist-listing-tools' ); ?></p>

			<h4><?php esc_html_e( 'What this reload fixes', 'directorist-listing-tools' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Charters & Accommodations: restores Map and Tags placement.', 'directorist-listing-tools' ); ?></li>
				<li><?php esc_html_e( 'Marinas & Services: restores Map and Video placement.', 'directorist-listing-tools' ); ?></li>
			</ul>

			<h4><?php esc_html_e( 'What it changes', 'directorist-listing-tools' ); ?></h4>
			<p><?php esc_html_e( 'It only updates the affected directory type submission_form_fields termmeta. It does not edit listing posts, listing postmeta, taxonomy relationships, published status, or listing content.', 'directorist-listing-tools' ); ?></p>

			<h4><?php esc_html_e( 'How to verify', 'directorist-listing-tools' ); ?></h4>
			<ul>
				<li><?php esc_html_e( 'Run Builder Preset Reload once.', 'directorist-listing-tools' ); ?></li>
				<li><?php esc_html_e( 'Confirm the listing count column stays unchanged.', 'directorist-listing-tools' ); ?></li>
				<li><?php esc_html_e( 'Open Directorist Directory Builder and check the affected directories.', 'directorist-listing-tools' ); ?></li>
				<li><?php esc_html_e( 'For Map SEO, edit/resave listings that need coordinates if they currently have address only.', 'directorist-listing-tools' ); ?></li>
			</ul>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Repair target directory submission forms.
	 *
	 * @return array Repair log.
	 */
	public static function run_repair() {
		$taxonomy = dlt_get_listing_types_taxonomy();
		$log      = array(
			'ran_at'      => current_time( 'mysql' ),
			'taxonomy'    => $taxonomy,
			'status'      => 'skipped',
			'directories' => array(),
		);

		if ( ! taxonomy_exists( $taxonomy ) ) {
			$log['message'] = __( 'Directory type taxonomy is not registered yet.', 'directorist-listing-tools' );
			update_option( self::LOG_OPTION, $log, false );
			return $log;
		}

		$targets = self::get_repair_targets();
		$changed = false;

		foreach ( $targets as $target ) {
			$term = self::get_target_term( $target, $taxonomy );

			if ( ! $term ) {
				$log['directories'][] = array(
					'name'    => $target['name'],
					'status'  => 'missing',
					'message' => __( 'Directory type was not found.', 'directorist-listing-tools' ),
				);
				continue;
			}

			$before_count = self::count_published_listings( $term->term_id, $taxonomy );
			$result       = self::repair_directory_submission_form( $term->term_id, $target['fields'] );
			$after_count  = self::count_published_listings( $term->term_id, $taxonomy );

			if ( ! empty( $result['updated'] ) ) {
				$changed = true;
			}

			$log['directories'][] = array(
				'term_id'              => (int) $term->term_id,
				'name'                 => $term->name,
				'slug'                 => $term->slug,
				'status'               => ! empty( $result['updated'] ) ? 'updated' : 'unchanged',
				'requested_fields'     => $target['fields'],
				'restored_definitions' => $result['restored_definitions'],
				'restored_placements'  => $result['restored_placements'],
				'before_count'         => $before_count,
				'after_count'          => $after_count,
				'counts_unchanged'     => ( $before_count === $after_count ),
			);
		}

		$log['status'] = $changed ? 'updated' : 'unchanged';

		update_option( self::LOG_OPTION, $log, false );

		return $log;
	}

	/**
	 * Targeted production repair map.
	 *
	 * @return array
	 */
	private static function get_repair_targets() {
		return array(
			array(
				'name'   => 'Charters & Accommodations',
				'slugs'  => array( 'charters-accommodations', 'charters-accomodations' ),
				'fields' => array( 'map', 'tag' ),
			),
			array(
				'name'   => 'Marinas & Services',
				'slugs'  => array( 'marinas-services' ),
				'fields' => array( 'map', 'video' ),
			),
		);
	}

	/**
	 * Locate a target directory term.
	 *
	 * @param array  $target   Target definition.
	 * @param string $taxonomy Directory taxonomy.
	 * @return WP_Term|null
	 */
	private static function get_target_term( $target, $taxonomy ) {
		foreach ( $target['slugs'] as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}

		$term = get_term_by( 'name', $target['name'], $taxonomy );

		return ( $term && ! is_wp_error( $term ) ) ? $term : null;
	}

	/**
	 * Repair one directory submission form.
	 *
	 * @param int   $term_id Directory term ID.
	 * @param array $fields  Preset field keys.
	 * @return array
	 */
	private static function repair_directory_submission_form( $term_id, $fields ) {
		$form = get_term_meta( $term_id, 'submission_form_fields', true );

		if ( ! is_array( $form ) ) {
			$form = array();
		}

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			$form['fields'] = array();
		}

		if ( empty( $form['groups'] ) || ! is_array( $form['groups'] ) ) {
			$form['groups'] = array();
		}

		$updated              = false;
		$restored_definitions = array();
		$restored_placements  = array();

		foreach ( $fields as $field_key ) {
			if ( empty( $form['fields'][ $field_key ] ) || ! is_array( $form['fields'][ $field_key ] ) ) {
				$form['fields'][ $field_key ] = self::get_preset_field_definition( $field_key );
				$restored_definitions[]       = $field_key;
				$updated                      = true;
			}

			if ( ! self::field_is_placed( $form['groups'], $field_key ) ) {
				$group_index = self::get_group_index_for_field( $form['groups'], $field_key );

				if ( null === $group_index ) {
					$form['groups'][] = self::get_new_group_for_field( $field_key );
					$group_index      = array_key_last( $form['groups'] );
				}

				if ( empty( $form['groups'][ $group_index ]['fields'] ) || ! is_array( $form['groups'][ $group_index ]['fields'] ) ) {
					$form['groups'][ $group_index ]['fields'] = array();
				}

				$form['groups'][ $group_index ]['fields'][] = $field_key;
				$restored_placements[]                      = $field_key;
				$updated                                    = true;
			}
		}

		if ( $updated ) {
			update_term_meta( $term_id, 'submission_form_fields', $form );
		}

		return array(
			'updated'              => $updated,
			'restored_definitions' => $restored_definitions,
			'restored_placements'  => $restored_placements,
		);
	}

	/**
	 * Check if a field is already placed in a group.
	 *
	 * @param array  $groups    Form groups.
	 * @param string $field_key Field key.
	 * @return bool
	 */
	private static function field_is_placed( $groups, $field_key ) {
		foreach ( $groups as $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			if ( in_array( $field_key, $group['fields'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the most natural group for a preset.
	 *
	 * @param array  $groups    Form groups.
	 * @param string $field_key Field key.
	 * @return int|null
	 */
	private static function get_group_index_for_field( $groups, $field_key ) {
		$preferred = array(
			'map'   => array( 'address', 'location' ),
			'tag'   => array( 'category', 'location', 'title' ),
			'video' => array( 'image_upload' ),
		);

		if ( ! empty( $preferred[ $field_key ] ) ) {
			foreach ( $groups as $index => $group ) {
				$group_fields = ! empty( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array();
				if ( array_intersect( $preferred[ $field_key ], $group_fields ) ) {
					return $index;
				}
			}
		}

		if ( ! empty( $groups ) ) {
			return array_key_last( $groups );
		}

		return null;
	}

	/**
	 * Create a new group when no suitable group exists.
	 *
	 * @param string $field_key Field key.
	 * @return array
	 */
	private static function get_new_group_for_field( $field_key ) {
		$labels = array(
			'map'   => __( 'Map', 'directorist-listing-tools' ),
			'tag'   => __( 'General Information', 'directorist-listing-tools' ),
			'video' => __( 'Images & Video', 'directorist-listing-tools' ),
		);

		return array(
			'label'  => isset( $labels[ $field_key ] ) ? $labels[ $field_key ] : __( 'Section', 'directorist-listing-tools' ),
			'fields' => array(),
		);
	}

	/**
	 * Get an existing matching preset definition or fallback to Directorist defaults.
	 *
	 * @param string $field_key Field key.
	 * @return array
	 */
	private static function get_preset_field_definition( $field_key ) {
		$donor = self::find_existing_preset_definition( $field_key );

		if ( ! empty( $donor ) ) {
			return $donor;
		}

		$definitions = array(
			'map'   => array(
				'type'           => 'map',
				'field_key'      => 'map',
				'label'          => 'Map',
				'only_for_admin' => self::get_directorist_option( 'display_map_for', false ),
				'lat_long'       => 'Or Enter Coordinates (latitude and longitude) Manually',
				'required'       => false,
				'widget_group'   => 'preset',
				'widget_name'    => 'map',
				'widget_key'     => 'map',
			),
			'tag'   => array(
				'type'           => 'multiple',
				'field_key'      => 'tax_input[at_biz_dir-tags][]',
				'label'          => self::get_directorist_option( 'tag_label', 'Tag' ),
				'required'       => self::get_directorist_option( 'require_tags', false ),
				'allow_new'      => self::get_directorist_option( 'create_new_tag', true ),
				'only_for_admin' => self::get_directorist_option( 'display_tag_for', false ),
				'widget_group'   => 'preset',
				'widget_name'    => 'tag',
				'widget_key'     => 'tag',
			),
			'video' => array(
				'type'           => 'text',
				'field_key'      => 'videourl',
				'label'          => self::get_directorist_option( 'video_label', 'Video Url' ),
				'placeholder'    => self::get_directorist_option( 'video_placeholder', 'Only YouTube & Vimeo URLs.' ),
				'required'       => self::get_directorist_option( 'require_video', false ),
				'only_for_admin' => self::get_directorist_option( 'display_video_for', false ),
				'widget_group'   => 'preset',
				'widget_name'    => 'video',
				'widget_key'     => 'video',
			),
		);

		return isset( $definitions[ $field_key ] ) ? $definitions[ $field_key ] : array();
	}

	/**
	 * Find a same-key preset definition from another directory.
	 *
	 * @param string $field_key Field key.
	 * @return array
	 */
	private static function find_existing_preset_definition( $field_key ) {
		$terms = get_terms(
			array(
				'taxonomy'   => dlt_get_listing_types_taxonomy(),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		foreach ( $terms as $term_id ) {
			$form = get_term_meta( $term_id, 'submission_form_fields', true );

			if ( empty( $form['fields'][ $field_key ] ) || ! is_array( $form['fields'][ $field_key ] ) ) {
				continue;
			}

			return $form['fields'][ $field_key ];
		}

		return array();
	}

	/**
	 * Get Directorist option with fallback.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_directorist_option( $key, $default ) {
		return function_exists( 'get_directorist_option' ) ? get_directorist_option( $key, $default ) : $default;
	}

	/**
	 * Count published listings for a directory.
	 *
	 * @param int    $term_id  Directory term ID.
	 * @param string $taxonomy Directory taxonomy.
	 * @return int
	 */
	private static function count_published_listings( $term_id, $taxonomy ) {
		$query = new WP_Query(
			array(
				'post_type'              => dlt_get_post_type(),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array( (int) $term_id ),
					),
				),
			)
		);

		return (int) $query->found_posts;
	}
}
