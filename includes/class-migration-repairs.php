<?php
/**
 * Directorist migration repair tools.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-only repairs for migrated Directorist directory metadata.
 */
class Directorist_Listing_Tools_Migration_Repairs {

	const RESULT_OPTION = 'dlt_migration_repairs_last_result';
	const BACKUP_OPTION = 'dlt_migration_repairs_last_backup';

	/**
	 * Singleton instance.
	 *
	 * @var Directorist_Listing_Tools_Migration_Repairs|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Directorist_Listing_Tools_Migration_Repairs
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
			add_action( 'admin_post_dlt_scan_migration_repairs', array( $this, 'handle_scan' ) );
			add_action( 'admin_post_dlt_run_migration_repairs', array( $this, 'handle_repair' ) );
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		$result = get_option( self::RESULT_OPTION, array() );
		$backup = get_option( self::BACKUP_OPTION, array() );
		?>
		<div class="wrap dlt-migration-repairs-page">
			<h2><?php esc_html_e( 'Migration Repairs', 'directorist-listing-tools' ); ?></h2>
			<p>
				<?php esc_html_e( 'Use this after migrating a Directorist site when listing archives, homepage listing shortcodes, or search forms break because saved directory builder metadata is malformed.', 'directorist-listing-tools' ); ?>
			</p>
			<p>
				<strong><?php esc_html_e( 'Scope:', 'directorist-listing-tools' ); ?></strong>
				<?php esc_html_e( 'This only scans and repairs Directorist directory term meta. It does not edit listing posts, users, pricing plans, plugin code, theme code, or frontend runtime hooks.', 'directorist-listing-tools' ); ?>
			</p>

			<?php if ( ! empty( $result['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( ! empty( $result['notice'] ) ? $result['notice'] : 'info' ); ?> is-dismissible">
					<p><strong><?php echo esc_html( $result['message'] ); ?></strong></p>
					<?php if ( ! empty( $result['lines'] ) && is_array( $result['lines'] ) ) : ?>
						<ul style="list-style:disc;margin-left:20px;">
							<?php foreach ( $result['lines'] as $line ) : ?>
								<li><code><?php echo esc_html( $line ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="dlt-builder-preset-panel">
				<h3><?php esc_html_e( 'Scan and repair migrated metadata', 'directorist-listing-tools' ); ?></h3>
				<p>
					<?php esc_html_e( 'Scan first. If issues are found, run repair after taking a DB backup. The repair creates a copy of changed term meta in this plugin option before updating.', 'directorist-listing-tools' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
					<input type="hidden" name="action" value="dlt_scan_migration_repairs">
					<?php wp_nonce_field( 'dlt_migration_repairs' ); ?>
					<?php submit_button( __( 'Scan Migration Issues', 'directorist-listing-tools' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<input type="hidden" name="action" value="dlt_run_migration_repairs">
					<?php wp_nonce_field( 'dlt_migration_repairs' ); ?>
					<?php submit_button( __( 'Run Migration Repair', 'directorist-listing-tools' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<?php if ( ! empty( $backup['ran_at'] ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date/time */
						esc_html__( 'Last internal metadata backup was created at %s.', 'directorist-listing-tools' ),
						esc_html( $backup['ran_at'] )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="dlt-builder-preset-log">
				<h3><?php esc_html_e( 'What this checks', 'directorist-listing-tools' ); ?></h3>
				<ul style="list-style:disc;margin-left:20px;">
					<li><?php esc_html_e( 'Submission form select/radio/checkbox fields whose options are strings, empty while listings have values, or missing option_value / option_label keys.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Search form groups that point to fields no longer saved in search_form_fields.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Search form choice fields with malformed options containers.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Listing card/list layouts that contain scalar items where Directorist expects field arrays.', 'directorist-listing-tools' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle scan request.
	 */
	public function handle_scan() {
		$this->handle_action( false );
	}

	/**
	 * Handle repair request.
	 */
	public function handle_repair() {
		$this->handle_action( true );
	}

	/**
	 * Shared admin-post handler.
	 *
	 * @param bool $write Whether to write fixes.
	 */
	private function handle_action( $write ) {
		check_admin_referer( 'dlt_migration_repairs' );

		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have permission to run this repair.', 'directorist-listing-tools' ) );
		}

		$result = self::scan_or_repair( (bool) $write );
		update_option( self::RESULT_OPTION, $result, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => dlt_get_post_type(),
					'page'      => 'directorist-listing-tools-migration-repairs',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Scan or repair all directory types.
	 *
	 * @param bool $write Whether to write fixes.
	 * @return array
	 */
	public static function scan_or_repair( $write = false ) {
		$taxonomy = dlt_get_listing_types_taxonomy();

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'notice'  => 'error',
				'message' => __( 'Directorist directory type taxonomy is not registered yet.', 'directorist-listing-tools' ),
				'lines'   => array(),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array(
				'notice'  => 'warning',
				'message' => __( 'No Directorist directory types were found.', 'directorist-listing-tools' ),
				'lines'   => array(),
			);
		}

		$lines       = array();
		$issue_count = 0;
		$fix_count   = 0;
		$backup      = array(
			'ran_at' => current_time( 'mysql' ),
			'terms'  => array(),
		);

		foreach ( $terms as $term ) {
			$term_backup = array();

			$submission_result = self::repair_submission_form_fields( $term, $write, $term_backup );
			$search_result     = self::repair_search_form_fields( $term, $write, $term_backup );
			$layout_result     = self::repair_listing_card_layouts( $term, $write, $term_backup );

			foreach ( array_merge( $submission_result['lines'], $search_result['lines'], $layout_result['lines'] ) as $line ) {
				$lines[] = $line;
			}

			$issue_count += $submission_result['issues'] + $search_result['issues'] + $layout_result['issues'];
			$fix_count   += $submission_result['fixes'] + $search_result['fixes'] + $layout_result['fixes'];

			if ( $write && ! empty( $term_backup ) ) {
				$backup['terms'][ $term->term_id ] = array(
					'name' => $term->name,
					'slug' => $term->slug,
					'meta' => $term_backup,
				);
			}
		}

		if ( $write && ! empty( $backup['terms'] ) ) {
			update_option( self::BACKUP_OPTION, $backup, false );
		}

		if ( 0 === $issue_count ) {
			return array(
				'notice'  => 'success',
				'message' => sprintf(
					/* translators: %d: directory count */
					__( 'Scan complete. Checked %d Directorist directory type(s). No migrated metadata issues found.', 'directorist-listing-tools' ),
					count( $terms )
				),
				'lines'   => array(),
			);
		}

		if ( ! $write ) {
			return array(
				'notice'  => 'warning',
				'message' => sprintf(
					/* translators: %d: issue count */
					__( 'Scan complete. Found %d repairable migrated metadata issue(s).', 'directorist-listing-tools' ),
					$issue_count
				),
				'lines'   => $lines,
			);
		}

		return array(
			'notice'  => 'success',
			'message' => sprintf(
				/* translators: 1: fix count, 2: issue count */
				__( 'Repair complete. Applied %1$d fix(es) for %2$d migrated metadata issue(s). Clear cache and test the frontend.', 'directorist-listing-tools' ),
				$fix_count,
				$issue_count
			),
			'lines'   => $lines,
		);
	}

	/**
	 * Repair submission form choice fields.
	 *
	 * @param WP_Term $term        Directory term.
	 * @param bool    $write       Whether to write fixes.
	 * @param array   $term_backup Backup bucket.
	 * @return array
	 */
	private static function repair_submission_form_fields( $term, $write, &$term_backup ) {
		$form = get_term_meta( $term->term_id, 'submission_form_fields', true );

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return self::empty_result();
		}

		$issues  = 0;
		$fixes   = 0;
		$lines   = array();
		$changed = false;

		foreach ( $form['fields'] as $field_id => $field ) {
			if ( ! is_array( $field ) || ! self::is_choice_field( $field ) ) {
				continue;
			}

			$current_options = isset( $field['options'] ) ? $field['options'] : null;
			$normalized      = self::normalize_choice_options( $current_options );
			$published       = self::get_published_values_for_field( (int) $term->term_id, isset( $field['field_key'] ) ? (string) $field['field_key'] : '' );
			$merged          = self::merge_option_rows( $normalized, self::build_options( $published ) );
			$valid           = self::is_valid_choice_options( $current_options );
			$needs_repair    = ! $valid || ( empty( $normalized ) && ! empty( $published ) );

			if ( ! $needs_repair ) {
				continue;
			}

			$issues++;
			$form['fields'][ $field_id ]['options'] = $merged;
			$changed = true;

			$lines[] = sprintf(
				'%s (%d) submission field "%s" choice options normalized to %d option(s)',
				$term->slug,
				$term->term_id,
				isset( $field['label'] ) ? $field['label'] : $field_id,
				count( $merged )
			);
		}

		if ( $write && $changed ) {
			$term_backup['submission_form_fields'] = get_term_meta( $term->term_id, 'submission_form_fields', true );
			update_term_meta( $term->term_id, 'submission_form_fields', $form );
			wp_cache_delete( $term->term_id, 'term_meta' );
			$fixes++;
		}

		return compact( 'issues', 'fixes', 'lines' );
	}

	/**
	 * Repair search form stored metadata.
	 *
	 * @param WP_Term $term        Directory term.
	 * @param bool    $write       Whether to write fixes.
	 * @param array   $term_backup Backup bucket.
	 * @return array
	 */
	private static function repair_search_form_fields( $term, $write, &$term_backup ) {
		$search = get_term_meta( $term->term_id, 'search_form_fields', true );

		if ( ! is_array( $search ) ) {
			return self::empty_result();
		}

		$issues  = 0;
		$fixes   = 0;
		$lines   = array();
		$changed = false;

		if ( ! empty( $search['groups'] ) && is_array( $search['groups'] ) && ! empty( $search['fields'] ) && is_array( $search['fields'] ) ) {
			foreach ( $search['groups'] as $group_index => $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
					continue;
				}

				$clean_fields = array_values( array_filter( $group['fields'], function( $field_key ) use ( $search ) {
					return isset( $search['fields'][ $field_key ] );
				} ) );

				if ( $clean_fields !== $group['fields'] ) {
					$issues++;
					$changed = true;
					$search['groups'][ $group_index ]['fields'] = $clean_fields;
					$lines[] = sprintf( '%s (%d) search form group orphan field references removed', $term->slug, $term->term_id );
				}
			}
		}

		if ( ! empty( $search['fields'] ) && is_array( $search['fields'] ) ) {
			foreach ( $search['fields'] as $field_id => $field ) {
				if ( ! is_array( $field ) || ! self::is_search_choice_field( $field ) ) {
					continue;
				}

				if ( ! isset( $field['options'] ) || ! is_array( $field['options'] ) ) {
					$issues++;
					$changed = true;
					$search['fields'][ $field_id ]['options'] = array();
					$lines[] = sprintf( '%s (%d) search field "%s" malformed options container reset', $term->slug, $term->term_id, isset( $field['label'] ) ? $field['label'] : $field_id );
				}
			}
		}

		if ( $write && $changed ) {
			$term_backup['search_form_fields'] = get_term_meta( $term->term_id, 'search_form_fields', true );
			update_term_meta( $term->term_id, 'search_form_fields', $search );
			wp_cache_delete( $term->term_id, 'term_meta' );
			$fixes++;
		}

		return compact( 'issues', 'fixes', 'lines' );
	}

	/**
	 * Repair scalar values inside listing card/list layout field arrays.
	 *
	 * @param WP_Term $term        Directory term.
	 * @param bool    $write       Whether to write fixes.
	 * @param array   $term_backup Backup bucket.
	 * @return array
	 */
	private static function repair_listing_card_layouts( $term, $write, &$term_backup ) {
		$issues = 0;
		$fixes  = 0;
		$lines  = array();

		foreach ( array( 'listings_card_grid_view', 'listings_card_list_view' ) as $meta_key ) {
			$layout = get_term_meta( $term->term_id, $meta_key, true );

			if ( ! is_array( $layout ) ) {
				continue;
			}

			$removed = 0;
			$clean   = self::remove_scalar_layout_items( $layout, $removed );

			if ( 0 === $removed ) {
				continue;
			}

			$issues += $removed;
			$lines[] = sprintf( '%s (%d) %s removed %d scalar layout item(s)', $term->slug, $term->term_id, $meta_key, $removed );

			if ( $write ) {
				if ( ! isset( $term_backup[ $meta_key ] ) ) {
					$term_backup[ $meta_key ] = $layout;
				}
				update_term_meta( $term->term_id, $meta_key, $clean );
				wp_cache_delete( $term->term_id, 'term_meta' );
				$fixes++;
			}
		}

		return compact( 'issues', 'fixes', 'lines' );
	}

	/**
	 * Empty result helper.
	 *
	 * @return array
	 */
	private static function empty_result() {
		return array(
			'issues' => 0,
			'fixes'  => 0,
			'lines'  => array(),
		);
	}

	/**
	 * Is field a choice custom field.
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	private static function is_choice_field( $field ) {
		return in_array( isset( $field['type'] ) ? $field['type'] : '', array( 'select', 'radio', 'checkbox' ), true );
	}

	/**
	 * Is search field a choice UI field.
	 *
	 * @param array $field Field config.
	 * @return bool
	 */
	private static function is_search_choice_field( $field ) {
		return in_array( isset( $field['widget_name'] ) ? $field['widget_name'] : '', array( 'select', 'radio', 'checkbox' ), true );
	}

	/**
	 * Check flat Directorist option rows.
	 *
	 * @param mixed $options Options value.
	 * @return bool
	 */
	private static function is_valid_choice_options( $options ) {
		if ( ! is_array( $options ) ) {
			return false;
		}

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) || ! array_key_exists( 'option_value', $option ) || ! array_key_exists( 'option_label', $option ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize common migrated option shapes.
	 *
	 * @param mixed $raw Raw options.
	 * @return array
	 */
	private static function normalize_choice_options( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		if ( isset( $raw['options']['value'] ) && is_array( $raw['options']['value'] ) ) {
			return self::normalize_choice_options( $raw['options']['value'] );
		}

		if ( isset( $raw['options'] ) && is_array( $raw['options'] ) ) {
			return self::normalize_choice_options( $raw['options'] );
		}

		if ( isset( $raw['option_value'] ) || isset( $raw['value'] ) ) {
			$value = isset( $raw['option_value'] ) ? $raw['option_value'] : $raw['value'];
			$label = isset( $raw['option_label'] ) ? $raw['option_label'] : ( isset( $raw['label'] ) ? $raw['label'] : $value );
			$value = trim( (string) $value );
			$label = trim( (string) $label );

			return '' === $value ? array() : array(
				array(
					'option_value' => $value,
					'option_label' => '' !== $label ? $label : $value,
				),
			);
		}

		$options = array();

		foreach ( $raw as $key => $option ) {
			if ( is_array( $option ) ) {
				$value = isset( $option['option_value'] ) ? $option['option_value'] : ( isset( $option['value'] ) ? $option['value'] : '' );
				$label = isset( $option['option_label'] ) ? $option['option_label'] : ( isset( $option['label'] ) ? $option['label'] : $value );
			} else {
				$value = is_string( $key ) ? $key : $option;
				$label = $option;
			}

			$value = trim( (string) $value );
			$label = trim( (string) $label );

			if ( '' === $value ) {
				continue;
			}

			$options[] = array(
				'option_value' => $value,
				'option_label' => '' !== $label ? $label : $value,
			);
		}

		return $options;
	}

	/**
	 * Get published listing values for a field.
	 *
	 * @param int    $directory_id Directory term ID.
	 * @param string $field_key    Field key.
	 * @return array
	 */
	private static function get_published_values_for_field( $directory_id, $field_key ) {
		global $wpdb;

		if ( '' === $field_key ) {
			return array();
		}

		$values = array();

		foreach ( array_unique( array( '_' . $field_key, $field_key ) ) as $meta_key ) {
			$rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					INNER JOIN {$wpdb->postmeta} dt
						ON dt.post_id = pm.post_id
						AND dt.meta_key = '_directory_type'
						AND dt.meta_value = %s
					WHERE pm.meta_key = %s
						AND pm.meta_value <> ''
						AND p.post_type = %s
						AND p.post_status = 'publish'",
					(string) $directory_id,
					$meta_key,
					dlt_get_post_type()
				)
			);

			if ( empty( $rows ) ) {
				$rows = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT pm.meta_value
						FROM {$wpdb->postmeta} pm
						INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = pm.post_id
						INNER JOIN {$wpdb->term_taxonomy} tt
							ON tt.term_taxonomy_id = tr.term_taxonomy_id
							AND tt.taxonomy = %s
							AND tt.term_id = %d
						WHERE pm.meta_key = %s
							AND pm.meta_value <> ''
							AND p.post_type = %s
							AND p.post_status = 'publish'",
						dlt_get_listing_types_taxonomy(),
						$directory_id,
						$meta_key,
						dlt_get_post_type()
					)
				);
			}

			foreach ( $rows as $raw_value ) {
				foreach ( self::parse_saved_values( $raw_value ) as $value ) {
					$values[ $value ] = true;
				}
			}
		}

		return array_keys( $values );
	}

	/**
	 * Parse saved listing custom field values.
	 *
	 * @param mixed $raw_value Raw meta value.
	 * @return array
	 */
	private static function parse_saved_values( $raw_value ) {
		$raw_value = trim( (string) $raw_value );

		if ( '' === $raw_value ) {
			return array();
		}

		$decoded = maybe_unserialize( $raw_value );
		$items   = is_array( $decoded ) ? $decoded : explode( '|', $raw_value );
		$values  = array();

		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				foreach ( $item as $nested ) {
					$nested = trim( (string) $nested );
					if ( '' !== $nested ) {
						$values[] = $nested;
					}
				}
				continue;
			}

			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$values[] = $item;
			}
		}

		return $values;
	}

	/**
	 * Build option rows from values.
	 *
	 * @param array $values Values.
	 * @return array
	 */
	private static function build_options( $values ) {
		$options = array();

		foreach ( $values as $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$options[] = array(
				'option_value' => $value,
				'option_label' => $value,
			);
		}

		return $options;
	}

	/**
	 * Merge option rows by option_value.
	 *
	 * @param array $primary   Primary rows.
	 * @param array $secondary Secondary rows.
	 * @return array
	 */
	private static function merge_option_rows( $primary, $secondary ) {
		$merged = array();

		foreach ( array_merge( $primary, $secondary ) as $option ) {
			if ( empty( $option['option_value'] ) ) {
				continue;
			}
			$merged[ (string) $option['option_value'] ] = array(
				'option_value' => (string) $option['option_value'],
				'option_label' => isset( $option['option_label'] ) && '' !== (string) $option['option_label'] ? (string) $option['option_label'] : (string) $option['option_value'],
			);
		}

		return array_values( $merged );
	}

	/**
	 * Remove scalar items from numeric layout field lists.
	 *
	 * @param mixed $node    Layout node.
	 * @param int   $removed Removed counter.
	 * @return mixed
	 */
	private static function remove_scalar_layout_items( $node, &$removed ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}

		$is_numeric_list = ! empty( $node ) && array_keys( $node ) === range( 0, count( $node ) - 1 );
		$clean           = array();

		foreach ( $node as $key => $value ) {
			if ( $is_numeric_list && ! is_array( $value ) ) {
				$removed++;
				continue;
			}

			$clean[ $key ] = self::remove_scalar_layout_items( $value, $removed );
		}

		return $is_numeric_list ? array_values( $clean ) : $clean;
	}
}
