<?php
/**
 * Listing Refresh Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refresh imported listings so Directorist display data is normalized.
 */
class Directorist_Listing_Tools_Listing_Refresh {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Listing_Refresh
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Listing_Refresh
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
		add_action( 'admin_post_dlt_refresh_listings', array( $this, 'handle_refresh_listings' ) );
		add_action( 'wp_ajax_dlt_ajax_refresh_selected', array( $this, 'handle_ajax_refresh_selected' ) );
		add_action( 'wp_ajax_dlt_ajax_refresh_by_count', array( $this, 'handle_ajax_refresh_by_count' ) );
		add_action( 'wp_ajax_dlt_ajax_refresh_batch', array( $this, 'handle_ajax_refresh_batch' ) );
	}

	/**
	 * Render listing refresh page.
	 */
	public function render_page() {
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		$total   = $this->count_listings();
		$pending = $this->count_unrefreshed_listings();

		$per_page_options = array( 20, 50, 100, 200, 500, 1000, -1 );
		$per_page         = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		$per_page         = ( -1 === $per_page || in_array( $per_page, $per_page_options, true ) ) ? $per_page : 20;
		$current_page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset           = ( -1 === $per_page ) ? 0 : ( $current_page - 1 ) * $per_page;
		$listings_data    = $this->get_listings_with_pagination( $per_page, $offset );
		$listings         = $listings_data['listings'];
		$total_pages      = ( -1 === $per_page ) ? 1 : (int) ceil( $listings_data['total'] / $per_page );
		?>
		<div class="wrap dlt-listing-refresh-wrap">
			<h1><?php esc_html_e( 'Refresh Listings', 'directorist-listing-tools' ); ?></h1>

			<?php if ( ! empty( $message ) ) : ?>
				<div class="dlt-message">
					<?php echo wp_kses_post( urldecode( $message ) ); ?>
				</div>
			<?php endif; ?>

			<div class="notice notice-info">
				<p>
					<?php esc_html_e( 'Use this after importing listings if a single listing page opens but the listing content area is empty until you manually click Update.', 'directorist-listing-tools' ); ?>
				</p>
			</div>

			<div id="dlt-refresh-ajax-message" style="display:none; margin: 20px 0;"></div>

			<div class="dlt-refresh-summary">
				<h2><?php esc_html_e( 'What this refresh does', 'directorist-listing-tools' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'Re-syncs the Directorist directory type meta and taxonomy term.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Copies imported description meta into the native listing content field when the listing content is blank.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Adds missing default expiration, never-expire, featured, and listing status metadata.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Sets the WordPress featured image from the Directorist preview image when possible.', 'directorist-listing-tools' ); ?></li>
					<li><?php esc_html_e( 'Runs a normal WordPress post update and clears listing cache.', 'directorist-listing-tools' ); ?></li>
				</ul>
			</div>

			<?php if ( empty( $listings ) ) : ?>
				<p><?php esc_html_e( 'No listings found.', 'directorist-listing-tools' ); ?></p>
			<?php else : ?>
				<div id="dlt-refresh-container">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<input type="number" id="dlt-refresh-count" min="1" max="1000" value="100" class="small-text" style="width: 80px;">
							<button type="button" class="button secondary dlt-refresh-by-count-btn"><?php esc_html_e( 'Refresh by Number', 'directorist-listing-tools' ); ?></button>
							<button type="button" class="button button-primary dlt-refresh-selected-btn"><?php esc_html_e( 'Refresh Selected', 'directorist-listing-tools' ); ?></button>
							<button type="button" class="button dlt-refresh-all-btn"><?php esc_html_e( 'Refresh All Remaining', 'directorist-listing-tools' ); ?></button>
						</div>
						<div class="alignright">
							<form method="get" style="display:inline-block; margin-right: 10px;">
								<input type="hidden" name="post_type" value="<?php echo esc_attr( dlt_get_post_type() ); ?>">
								<input type="hidden" name="page" value="directorist-listing-tools-refresh">
								<label for="dlt-refresh-per-page" style="margin-right: 5px;">
									<?php esc_html_e( 'Per page:', 'directorist-listing-tools' ); ?>
								</label>
								<select id="dlt-refresh-per-page" name="per_page" onchange="this.form.submit();" style="margin-right: 10px;">
									<?php foreach ( $per_page_options as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
											<?php echo esc_html( -1 === $option ? __( 'All', 'directorist-listing-tools' ) : number_format_i18n( $option ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</form>
							<span class="dlt-listing-count">
								<?php
								printf(
									esc_html__( '%1$s listings found, %2$s remaining', 'directorist-listing-tools' ),
									esc_html( number_format_i18n( $total ) ),
									'<span class="dlt-refresh-pending-count">' . esc_html( number_format_i18n( $pending ) ) . '</span>'
								);
								?>
							</span>
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="dlt-refresh-cb-select-all">
								</td>
								<th scope="col" class="manage-column column-title column-primary"><?php esc_html_e( 'Title', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-author"><?php esc_html_e( 'Author', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-directory"><?php esc_html_e( 'Directory', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-content"><?php esc_html_e( 'Content', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-refresh-status"><?php esc_html_e( 'Refresh', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-date"><?php esc_html_e( 'Date', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-status"><?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?></th>
								<th scope="col" class="manage-column column-id"><?php esc_html_e( 'ID', 'directorist-listing-tools' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $listings as $listing ) : ?>
								<?php
								$directory_name = $this->get_listing_directory_name( $listing->ID );
								$content_status = empty( trim( wp_strip_all_tags( $listing->post_content ) ) ) ? __( 'Empty', 'directorist-listing-tools' ) : __( 'Has content', 'directorist-listing-tools' );
								$refreshed_at   = get_post_meta( $listing->ID, '_dlt_listing_tools_refreshed_at', true );
								$refresh_error  = get_post_meta( $listing->ID, '_dlt_listing_tools_refresh_error', true );
								$refresh_label  = $refreshed_at ? mysql2date( get_option( 'date_format' ), $refreshed_at ) : __( 'Not yet', 'directorist-listing-tools' );
								if ( $refresh_error ) {
									$refresh_label = __( 'Failed', 'directorist-listing-tools' );
								}
								?>
								<tr data-listing-id="<?php echo esc_attr( $listing->ID ); ?>">
									<th scope="row" class="check-column">
										<input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing->ID ); ?>" class="dlt-refresh-listing-checkbox">
									</th>
									<td class="title column-title column-primary">
										<strong>
											<a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>">
												<?php echo esc_html( $listing->post_title ? $listing->post_title : esc_html__( '(No Title)', 'directorist-listing-tools' ) ); ?>
											</a>
										</strong>
									</td>
									<td class="author column-author">
										<?php
										$author = get_userdata( $listing->post_author );
										echo esc_html( $author ? $author->display_name : '-' );
										?>
									</td>
									<td class="directory column-directory"><?php echo esc_html( $directory_name ); ?></td>
									<td class="content column-content"><?php echo esc_html( $content_status ); ?></td>
									<td class="refresh-status column-refresh-status"><?php echo esc_html( $refresh_label ); ?></td>
									<td class="date column-date"><?php echo esc_html( get_the_date( '', $listing->ID ) ); ?></td>
									<td class="status column-status"><?php echo esc_html( ucfirst( $listing->post_status ) ); ?></td>
									<td class="id column-id"><?php echo esc_html( $listing->ID ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 && -1 !== $per_page ) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<?php
								$base_url = add_query_arg(
									array(
										'page'     => 'directorist-listing-tools-refresh',
										'per_page' => $per_page,
									),
									admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
								);
								echo wp_kses_post(
									paginate_links(
										array(
											'base'      => add_query_arg( 'paged', '%#%', $base_url ),
											'format'    => '',
											'prev_text' => '&laquo;',
											'next_text' => '&raquo;',
											'total'     => $total_pages,
											'current'   => $current_page,
										)
									)
								);
								?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle refresh form submission.
	 */
	public function handle_refresh_listings() {
		if ( ! isset( $_POST['dlt_refresh_listings_nonce'] ) || ! dlt_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dlt_refresh_listings_nonce'] ) ), 'dlt_refresh_listings_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'directorist-listing-tools' ) );
		}

		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) );
		}

		$ids_string  = isset( $_POST['listing_ids'] ) ? sanitize_textarea_field( wp_unslash( $_POST['listing_ids'] ) ) : '';
		$listing_ids = dlt_sanitize_listing_ids( $ids_string );
		$refresh_all = ! empty( $_POST['refresh_all'] );
		$limit       = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
		$limit       = max( 1, min( 1000, $limit ) );

		if ( empty( $listing_ids ) ) {
			$listing_ids = $this->get_listing_ids( $refresh_all ? -1 : $limit );
		}

		if ( empty( $listing_ids ) ) {
			$this->redirect_with_message( dlt_format_notice( esc_html__( 'No listings found to refresh.', 'directorist-listing-tools' ), 'error' ) );
		}

		$results = $this->refresh_listings( $listing_ids );
		$this->redirect_with_message( $this->format_results_message( $results ) );
	}

	/**
	 * Handle AJAX selected refresh.
	 */
	public function handle_ajax_refresh_selected() {
		$this->verify_ajax_request();

		$listing_ids_json = isset( $_POST['listing_ids'] ) ? wp_unslash( $_POST['listing_ids'] ) : '';
		$listing_ids      = json_decode( $listing_ids_json, true );

		if ( ! is_array( $listing_ids ) || empty( $listing_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No listings selected.', 'directorist-listing-tools' ) ) );
		}

		$listing_ids = array_map( 'absint', $listing_ids );
		$results     = $this->refresh_listings( $listing_ids );

		wp_send_json_success(
			array(
				'message'       => $this->format_results_message( $results ),
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
				'listing_ids'   => $results['success'],
				'pending_count' => $this->count_unrefreshed_listings(),
				'refreshed_at'  => mysql2date( get_option( 'date_format' ), current_time( 'mysql' ) ),
			)
		);
	}

	/**
	 * Handle AJAX refresh by count.
	 */
	public function handle_ajax_refresh_by_count() {
		$this->verify_ajax_request();

		$count = isset( $_POST['refresh_count'] ) ? absint( $_POST['refresh_count'] ) : 0;
		if ( ! $count ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter a valid number.', 'directorist-listing-tools' ) ) );
		}
		$count = max( 1, min( 1000, $count ) );

		$listing_ids = $this->get_unrefreshed_listing_ids( $count );

		if ( empty( $listing_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No remaining unrefreshed listings found. Select specific listings if you want to refresh them again.', 'directorist-listing-tools' ) ) );
		}

		$results = $this->refresh_listings( $listing_ids );

		wp_send_json_success(
			array(
				'message'       => $this->format_results_message( $results ),
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
				'listing_ids'   => $results['success'],
				'pending_count' => $this->count_unrefreshed_listings(),
				'refreshed_at'  => mysql2date( get_option( 'date_format' ), current_time( 'mysql' ) ),
			)
		);
	}

	/**
	 * Handle AJAX refresh batch for all remaining listings.
	 */
	public function handle_ajax_refresh_batch() {
		$this->verify_ajax_request();

		$batch_size  = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 25;
		$batch_size  = max( 1, min( 100, $batch_size ) );
		$listing_ids = $this->get_unrefreshed_listing_ids( $batch_size );

		if ( empty( $listing_ids ) ) {
			wp_send_json_success(
				array(
					'done'          => true,
					'message'       => dlt_format_notice( esc_html__( 'All remaining listings have already been refreshed.', 'directorist-listing-tools' ), 'success' ),
					'success_count' => 0,
					'failed_count'  => 0,
					'listing_ids'   => array(),
					'pending_count' => 0,
				)
			);
		}

		$results       = $this->refresh_listings( $listing_ids );
		$pending_count = $this->count_unrefreshed_listings();

		wp_send_json_success(
			array(
				'done'          => 0 === $pending_count,
				'message'       => $this->format_results_message( $results ),
				'success_count' => count( $results['success'] ),
				'failed_count'  => count( $results['failed'] ),
				'listing_ids'   => $results['success'],
				'pending_count' => $pending_count,
				'refreshed_at'  => mysql2date( get_option( 'date_format' ), current_time( 'mysql' ) ),
			)
		);
	}

	/**
	 * Verify AJAX nonce and capability.
	 */
	private function verify_ajax_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dlt_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'directorist-listing-tools' ) ) );
		}

		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) ) );
		}
	}

	/**
	 * Refresh multiple listings.
	 *
	 * @param array $listing_ids Listing IDs.
	 * @return array
	 */
	private function refresh_listings( $listing_ids ) {
		$results = array(
			'success' => array(),
			'failed'  => array(),
			'changed' => array(),
		);

		foreach ( $listing_ids as $listing_id ) {
			$listing_id = absint( $listing_id );
			$post       = get_post( $listing_id );

			if ( ! $post || dlt_get_post_type() !== $post->post_type ) {
				$results['failed'][ $listing_id ] = esc_html__( 'Listing not found or invalid post type.', 'directorist-listing-tools' );
				continue;
			}

			$changes = array();

			$directory_result = $this->refresh_directory_type( $listing_id );
			if ( is_wp_error( $directory_result ) ) {
				$results['failed'][ $listing_id ] = $directory_result->get_error_message();
				$this->mark_refresh_attempt( $listing_id, $directory_result->get_error_message() );
				continue;
			}
			if ( $directory_result ) {
				$changes[] = sprintf(
					/* translators: %d: directory type ID */
					esc_html__( 'directory #%d', 'directorist-listing-tools' ),
					$directory_result
				);
			}

			$meta_changes = $this->refresh_required_meta( $listing_id, $directory_result );
			$changes      = array_merge( $changes, $meta_changes );

			if ( $this->refresh_thumbnail( $listing_id ) ) {
				$changes[] = esc_html__( 'thumbnail', 'directorist-listing-tools' );
			}

			$post_update = array(
				'ID' => $listing_id,
			);

			$content = $this->get_fallback_post_content( $post );
			if ( '' !== $content ) {
				$post_update['post_content'] = $content;
				$changes[]                   = esc_html__( 'listing content', 'directorist-listing-tools' );
			}

			$updated = wp_update_post( $post_update, true );

			if ( is_wp_error( $updated ) ) {
				$results['failed'][ $listing_id ] = $updated->get_error_message();
				$this->mark_refresh_attempt( $listing_id, $updated->get_error_message() );
				continue;
			}

			clean_post_cache( $listing_id );
			wp_cache_delete( $listing_id, 'posts' );
			wp_cache_delete( $listing_id, 'post_meta' );
			$this->mark_refresh_attempt( $listing_id );

			$results['success'][]             = $listing_id;
			$results['changed'][ $listing_id ] = $changes;
		}

		return $results;
	}

	/**
	 * Mark a listing as attempted so all-remaining batches can continue.
	 *
	 * @param int    $listing_id Listing ID.
	 * @param string $error Optional error message.
	 */
	private function mark_refresh_attempt( $listing_id, $error = '' ) {
		update_post_meta( $listing_id, '_dlt_listing_tools_refreshed_at', current_time( 'mysql' ) );

		if ( '' !== $error ) {
			update_post_meta( $listing_id, '_dlt_listing_tools_refresh_error', sanitize_text_field( $error ) );
			return;
		}

		delete_post_meta( $listing_id, '_dlt_listing_tools_refresh_error' );
	}

	/**
	 * Re-sync Directorist directory type meta and taxonomy.
	 *
	 * @param int $listing_id Listing ID.
	 * @return int|WP_Error Directory ID, or error.
	 */
	private function refresh_directory_type( $listing_id ) {
		$taxonomy     = dlt_get_listing_types_taxonomy();
		$directory_id = absint( get_post_meta( $listing_id, '_directory_type', true ) );
		$term_ids     = wp_get_object_terms( $listing_id, $taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $term_ids ) ) {
			return $term_ids;
		}

		if ( ! $this->is_valid_directory( $directory_id ) && ! empty( $term_ids ) ) {
			$directory_id = absint( $term_ids[0] );
		}

		if ( ! $this->is_valid_directory( $directory_id ) && function_exists( 'directorist_get_listing_directory' ) ) {
			$directory_id = absint( directorist_get_listing_directory( $listing_id ) );
		}

		if ( ! $this->is_valid_directory( $directory_id ) ) {
			$directory_id = $this->get_default_directory_id();
		}

		if ( ! $this->is_valid_directory( $directory_id ) ) {
			return new WP_Error( 'dlt_no_directory', esc_html__( 'Could not resolve a valid Directorist directory type.', 'directorist-listing-tools' ) );
		}

		if ( function_exists( 'directorist_set_listing_directory' ) ) {
			$result = directorist_set_listing_directory( $listing_id, $directory_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		} else {
			update_post_meta( $listing_id, '_directory_type', $directory_id );
			$result = wp_set_object_terms( $listing_id, array( $directory_id ), $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		update_post_meta( $listing_id, '_directory_type', $directory_id );

		return $directory_id;
	}

	/**
	 * Add common Directorist metadata that imports can miss.
	 *
	 * @param int $listing_id Listing ID.
	 * @param int $directory_id Directory ID.
	 * @return array Changed labels.
	 */
	private function refresh_required_meta( $listing_id, $directory_id ) {
		$changes = array();

		if ( ! metadata_exists( 'post', $listing_id, '_featured' ) ) {
			update_post_meta( $listing_id, '_featured', 0 );
			$changes[] = esc_html__( 'featured meta', 'directorist-listing-tools' );
		}

		if ( ! metadata_exists( 'post', $listing_id, '_listing_status' ) ) {
			update_post_meta( $listing_id, '_listing_status', 'post_status' );
			$changes[] = esc_html__( 'listing status meta', 'directorist-listing-tools' );
		}

		$has_expiry       = metadata_exists( 'post', $listing_id, '_expiry_date' ) && get_post_meta( $listing_id, '_expiry_date', true );
		$has_never_expire = metadata_exists( 'post', $listing_id, '_never_expire' ) && get_post_meta( $listing_id, '_never_expire', true );

		if ( ! $has_expiry && ! $has_never_expire ) {
			$default_expiration = function_exists( 'directorist_get_default_expiration' ) ? (int) directorist_get_default_expiration( $directory_id ) : 0;

			if ( $default_expiration <= 0 ) {
				update_post_meta( $listing_id, '_never_expire', 1 );
				$changes[] = esc_html__( 'never expire meta', 'directorist-listing-tools' );
			} elseif ( function_exists( 'calc_listing_expiry_date' ) ) {
				update_post_meta( $listing_id, '_expiry_date', calc_listing_expiry_date( '', $default_expiration, $directory_id ) );
				$changes[] = esc_html__( 'expiry date', 'directorist-listing-tools' );
			}
		}

		return $changes;
	}

	/**
	 * Set post thumbnail from Directorist preview image when needed.
	 *
	 * @param int $listing_id Listing ID.
	 * @return bool
	 */
	private function refresh_thumbnail( $listing_id ) {
		$preview_id = absint( get_post_meta( $listing_id, '_listing_prv_img', true ) );

		if ( ! $preview_id ) {
			$thumbnail_id = absint( get_post_thumbnail_id( $listing_id ) );
			if ( $thumbnail_id ) {
				update_post_meta( $listing_id, '_listing_prv_img', $thumbnail_id );
				return true;
			}

			return false;
		}

		if ( get_post_thumbnail_id( $listing_id ) ) {
			return false;
		}

		if ( 'attachment' !== get_post_type( $preview_id ) ) {
			return false;
		}

		return (bool) set_post_thumbnail( $listing_id, $preview_id );
	}

	/**
	 * Get fallback content from common imported meta keys when post_content is empty.
	 *
	 * @param WP_Post $post Listing post.
	 * @return string
	 */
	private function get_fallback_post_content( $post ) {
		if ( ! empty( $post->post_content ) ) {
			return '';
		}

		$meta_keys = array(
			'_listing_content',
			'listing_content',
			'_description',
			'description',
			'_content',
			'content',
		);

		foreach ( $meta_keys as $meta_key ) {
			$value = get_post_meta( $post->ID, $meta_key, true );

			if ( ! is_string( $value ) ) {
				continue;
			}

			$value = trim( $value );

			if ( '' === $value ) {
				continue;
			}

			return wp_kses_post( $value );
		}

		return '';
	}

	/**
	 * Check whether a directory term is valid.
	 *
	 * @param int $directory_id Directory ID.
	 * @return bool
	 */
	private function is_valid_directory( $directory_id ) {
		$directory_id = absint( $directory_id );

		if ( ! $directory_id ) {
			return false;
		}

		if ( function_exists( 'directorist_is_directory' ) ) {
			return (bool) directorist_is_directory( $directory_id );
		}

		return (bool) term_exists( $directory_id, dlt_get_listing_types_taxonomy() );
	}

	/**
	 * Get default Directorist directory ID.
	 *
	 * @return int
	 */
	private function get_default_directory_id() {
		if ( function_exists( 'directorist_get_default_directory' ) ) {
			return absint( directorist_get_default_directory() );
		}

		if ( function_exists( 'default_directory_type' ) ) {
			return absint( default_directory_type() );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => dlt_get_listing_types_taxonomy(),
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return 0;
		}

		return absint( $terms[0] );
	}

	/**
	 * Get listing directory name for table display.
	 *
	 * @param int $listing_id Listing ID.
	 * @return string
	 */
	private function get_listing_directory_name( $listing_id ) {
		$directory_id = absint( get_post_meta( $listing_id, '_directory_type', true ) );

		if ( ! $directory_id ) {
			$terms = wp_get_object_terms( $listing_id, dlt_get_listing_types_taxonomy(), array( 'fields' => 'all' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				return $terms[0]->name;
			}

			return '-';
		}

		$term = get_term( $directory_id, dlt_get_listing_types_taxonomy() );

		if ( ! $term || is_wp_error( $term ) ) {
			return sprintf(
				/* translators: %d: directory ID */
				__( '#%d missing', 'directorist-listing-tools' ),
				$directory_id
			);
		}

		return $term->name;
	}

	/**
	 * Get listings with pagination.
	 *
	 * @param int $per_page Items per page.
	 * @param int $offset Query offset.
	 * @return array
	 */
	private function get_listings_with_pagination( $per_page = 20, $offset = 0 ) {
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( -1 !== $per_page ) {
			$args['offset'] = $offset;
		}

		$query = new WP_Query( $args );

		return array(
			'listings' => $query->posts,
			'total'    => $query->found_posts,
		);
	}

	/**
	 * Get IDs for listings that have not been refreshed by this tool yet.
	 *
	 * @param int $limit Number of IDs.
	 * @return array
	 */
	private function get_unrefreshed_listing_ids( $limit ) {
		$query = new WP_Query(
			array(
				'post_type'      => dlt_get_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_dlt_listing_tools_refreshed_at',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Count listings not refreshed by this tool yet.
	 *
	 * @return int
	 */
	private function count_unrefreshed_listings() {
		$query = new WP_Query(
			array(
				'post_type'      => dlt_get_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_dlt_listing_tools_refreshed_at',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Get listing IDs.
	 *
	 * @param int $limit Query limit.
	 * @return array
	 */
	private function get_listing_ids( $limit ) {
		$query = new WP_Query(
			array(
				'post_type'      => dlt_get_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			)
		);

		return array_map( 'absint', $query->posts );
	}

	/**
	 * Count listings.
	 *
	 * @return int
	 */
	private function count_listings() {
		$counts = wp_count_posts( dlt_get_post_type() );

		if ( empty( $counts ) ) {
			return 0;
		}

		$total = 0;
		foreach ( get_object_vars( $counts ) as $count ) {
			$total += (int) $count;
		}

		return $total;
	}

	/**
	 * Format results message.
	 *
	 * @param array $results Results array.
	 * @return string
	 */
	private function format_results_message( $results ) {
		$message_parts = array();
		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );

		if ( $success_count > 0 ) {
			$message_parts[] = '<strong>' . esc_html__( 'Success:', 'directorist-listing-tools' ) . '</strong> ' . esc_html( sprintf( _n( '%d listing refreshed.', '%d listings refreshed.', $success_count, 'directorist-listing-tools' ), $success_count ) );
			$message_parts[] = esc_html__( 'IDs:', 'directorist-listing-tools' ) . ' ' . esc_html( implode( ', ', $results['success'] ) );
		}

		if ( $failed_count > 0 ) {
			$message_parts[] = '<strong>' . esc_html__( 'Failed:', 'directorist-listing-tools' ) . '</strong> ' . esc_html( sprintf( _n( '%d listing failed.', '%d listings failed.', $failed_count, 'directorist-listing-tools' ), $failed_count ) );
			foreach ( $results['failed'] as $id => $reason ) {
				$message_parts[] = esc_html__( 'ID', 'directorist-listing-tools' ) . ' ' . esc_html( $id ) . ': ' . esc_html( $reason );
			}
		}

		$type = ( $failed_count > 0 ) ? 'warning' : 'success';
		return dlt_format_notice( implode( '<br>', $message_parts ), $type );
	}

	/**
	 * Redirect back to the refresh page with a message.
	 *
	 * @param string $message Message HTML.
	 */
	private function redirect_with_message( $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'directorist-listing-tools-refresh',
					'message' => urlencode( $message ),
				),
				admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
			)
		);
		exit;
	}
}
