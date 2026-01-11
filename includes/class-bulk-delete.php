<?php
/**
 * Bulk Delete Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk delete handler class.
 */
class Directorist_Listing_Tools_Bulk_Delete {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Bulk_Delete
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Bulk_Delete
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
		add_action( 'admin_post_dlt_delete_all', array( $this, 'handle_delete_all' ) );
		add_action( 'wp_ajax_dlt_ajax_bulk_delete', array( $this, 'handle_ajax_bulk_delete' ) );
		add_action( 'wp_ajax_dlt_ajax_delete_by_count', array( $this, 'handle_ajax_delete_by_count' ) );
	}

	/**
	 * Render bulk delete page.
	 */
	public function render_page() {
		$results = isset( $_GET['results'] ) ? sanitize_text_field( wp_unslash( $_GET['results'] ) ) : '';
		
		// Pagination - allow user to select per page.
		$per_page_options = array( 20, 50, 100, 200, 500, 1000, -1 );
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
		
		// Validate per_page value.
		if ( ! in_array( $per_page, $per_page_options, true ) && $per_page !== -1 ) {
			$per_page = 20;
		}
		
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		
		// If per_page is -1, show all (no pagination).
		if ( $per_page === -1 ) {
			$offset = 0;
			$posts_per_page = -1;
		} else {
			$offset = ( $current_page - 1 ) * $per_page;
			$posts_per_page = $per_page;
		}
		
		// Get listings with pagination.
		$listings_data = $this->get_listings_with_pagination( $posts_per_page, $offset );
		$listings = $listings_data['listings'];
		$total = $listings_data['total'];
		$total_pages = ( $per_page === -1 ) ? 1 : ceil( $total / $per_page );
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bulk Delete Listings', 'directorist-listing-tools' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Select listings to delete, delete by number, or delete all listings at once.', 'directorist-listing-tools' ); ?>
			</p>

			<?php if ( ! empty( $results ) ) : ?>
				<div class="dlt-results">
					<?php echo wp_kses_post( urldecode( $results ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $listings ) ) : ?>
				<p><?php esc_html_e( 'No listings found.', 'directorist-listing-tools' ); ?></p>
			<?php else : ?>
				<div id="dlt-ajax-message" style="display:none; margin: 20px 0;"></div>
				<div id="dlt-bulk-delete-container">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<input type="number" id="dlt-delete-count" name="delete_count" min="1" placeholder="<?php esc_attr_e( 'Number', 'directorist-listing-tools' ); ?>" class="small-text" style="width: 80px;">
							<button type="button" class="button secondary dlt-delete-by-count-btn"><?php esc_html_e( 'Delete by Number', 'directorist-listing-tools' ); ?></button>
							<button type="button" class="button button-danger dlt-delete-selected-btn"><?php esc_html_e( 'Delete Selected', 'directorist-listing-tools' ); ?></button>
							<?php submit_button( esc_html__( 'Delete All', 'directorist-listing-tools' ), 'delete', 'delete_all_btn', false, array( 'class' => 'button-danger dlt-delete-all-btn' ) ); ?>
						</div>
						<div class="alignright">
							<form method="get" style="display:inline-block; margin-right: 10px;">
								<input type="hidden" name="post_type" value="<?php echo esc_attr( dlt_get_post_type() ); ?>">
								<input type="hidden" name="page" value="directorist-listing-tools-bulk-delete">
								<label for="dlt-per-page" style="margin-right: 5px;">
									<?php esc_html_e( 'Per page:', 'directorist-listing-tools' ); ?>
								</label>
								<select id="dlt-per-page" name="per_page" onchange="this.form.submit();" style="margin-right: 10px;">
									<?php foreach ( $per_page_options as $option ) : ?>
										<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option ); ?>>
											<?php
											if ( $option === -1 ) {
												esc_html_e( 'All', 'directorist-listing-tools' );
											} else {
												echo esc_html( number_format_i18n( $option ) );
											}
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</form>
							<span class="dlt-listing-count">
								<?php
								printf(
									esc_html( _n( '%s listing found', '%s listings found', $total, 'directorist-listing-tools' ) ),
									number_format_i18n( $total )
								);
								?>
							</span>
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="cb-select-all">
								</td>
								<th scope="col" class="manage-column column-title column-primary">
									<?php esc_html_e( 'Title', 'directorist-listing-tools' ); ?>
								</th>
								<th scope="col" class="manage-column column-author">
									<?php esc_html_e( 'Author', 'directorist-listing-tools' ); ?>
								</th>
								<th scope="col" class="manage-column column-date">
									<?php esc_html_e( 'Date', 'directorist-listing-tools' ); ?>
								</th>
								<th scope="col" class="manage-column column-status">
									<?php esc_html_e( 'Status', 'directorist-listing-tools' ); ?>
								</th>
								<th scope="col" class="manage-column column-id">
									<?php esc_html_e( 'ID', 'directorist-listing-tools' ); ?>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $listings as $listing ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing->ID ); ?>" class="listing-checkbox">
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
										echo esc_html( $author ? $author->display_name : '—' );
										?>
									</td>
									<td class="date column-date">
										<?php echo esc_html( get_the_date( '', $listing->ID ) ); ?>
									</td>
									<td class="status column-status">
										<?php echo esc_html( ucfirst( $listing->post_status ) ); ?>
									</td>
									<td class="id column-id">
										<?php echo esc_html( $listing->ID ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 && $per_page !== -1 ) : ?>
						<div class="tablenav bottom">
							<div class="tablenav-pages">
								<?php
								$base_url = add_query_arg(
									array(
										'page'     => 'directorist-listing-tools-bulk-delete',
										'per_page' => $per_page,
									),
									admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
								);
								$page_links = paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%', $base_url ),
										'format'    => '',
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
										'total'     => $total_pages,
										'current'   => $current_page,
									)
								);
								echo wp_kses_post( $page_links );
								?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dlt-delete-all-form" style="display:none;">
					<?php wp_nonce_field( 'dlt_delete_all_action', 'dlt_delete_all_nonce' ); ?>
					<input type="hidden" name="action" value="dlt_delete_all">
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get all Directorist listings.
	 *
	 * @return array Array of WP_Post objects.
	 */
	private function get_all_listings() {
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Get listings with pagination.
	 *
	 * @param int $per_page Items per page (-1 for all).
	 * @param int $offset Offset.
	 * @return array Array with 'listings' and 'total'.
	 */
	private function get_listings_with_pagination( $per_page = 20, $offset = 0 ) {
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		
		// Only set offset if not showing all.
		if ( $per_page !== -1 ) {
			$args['offset'] = $offset;
		}

		$query = new WP_Query( $args );
		
		return array(
			'listings' => $query->posts,
			'total'    => $query->found_posts,
		);
	}

	/**
	 * Handle delete all form submission.
	 */
	public function handle_delete_all() {
		// Check nonce.
		if ( ! isset( $_POST['dlt_delete_all_nonce'] ) || ! dlt_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dlt_delete_all_nonce'] ) ), 'dlt_delete_all_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'directorist-listing-tools' ) );
		}

		// Check capability.
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) );
		}

		// Get all listing IDs.
		$all_listings = $this->get_all_listings();
		$listing_ids = wp_list_pluck( $all_listings, 'ID' );

		if ( empty( $listing_ids ) ) {
			$redirect_url = add_query_arg(
				array(
					'page'    => 'directorist-listing-tools-bulk-delete',
					'results' => urlencode( dlt_format_notice( esc_html__( 'No listings found to delete.', 'directorist-listing-tools' ), 'error' ) ),
				),
				admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Process deletions.
		$results = $this->delete_listings( $listing_ids );

		// Build results message.
		$message = $this->format_results_message( $results );

		// Redirect with results.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'directorist-listing-tools-bulk-delete',
				'results' => urlencode( $message ),
			),
			admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle AJAX delete by count.
	 */
	public function handle_ajax_delete_by_count() {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dlt_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'directorist-listing-tools' ) ) );
		}

		// Check capability.
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) ) );
		}

		// Get delete count.
		$delete_count = isset( $_POST['delete_count'] ) ? absint( $_POST['delete_count'] ) : 0;

		if ( $delete_count <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter a valid number.', 'directorist-listing-tools' ) ) );
		}

		// Get listing IDs (limit by count).
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'any',
			'posts_per_page' => $delete_count,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );
		$listing_ids = $query->posts;

		if ( empty( $listing_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No listings found to delete.', 'directorist-listing-tools' ) ) );
		}

		// Process deletions.
		$results = $this->delete_listings( $listing_ids );

		// Build response.
		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );
		$message = $this->format_results_message( $results );

		wp_send_json_success(
			array(
				'message'       => $message,
				'success_count' => $success_count,
				'failed_count'  => $failed_count,
				'listing_ids'   => $listing_ids,
			)
		);
	}

	/**
	 * Delete listings.
	 *
	 * @param array $listing_ids Array of listing IDs.
	 * @return array Results array with success/fail counts.
	 */
	private function delete_listings( $listing_ids ) {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		$post_type = dlt_get_post_type();

		foreach ( $listing_ids as $listing_id ) {
			// Verify post exists and is correct post type.
			$post = get_post( $listing_id );

			if ( ! $post || $post->post_type !== $post_type ) {
				$results['failed'][ $listing_id ] = esc_html__( 'Listing not found or invalid post type.', 'directorist-listing-tools' );
				continue;
			}

			// Delete the post.
			$deleted = wp_delete_post( $listing_id, true );

			if ( $deleted ) {
				$results['success'][] = $listing_id;
			} else {
				$results['failed'][ $listing_id ] = esc_html__( 'Failed to delete.', 'directorist-listing-tools' );
			}
		}

		return $results;
	}

	/**
	 * Format results message.
	 *
	 * @param array $results Results array.
	 * @return string Formatted HTML message.
	 */
	private function format_results_message( $results ) {
		$message_parts = array();

		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );

		if ( $success_count > 0 ) {
			$message_parts[] = '<strong>' . esc_html__( 'Success:', 'directorist-listing-tools' ) . '</strong> ' . esc_html( sprintf( _n( '%d listing deleted.', '%d listings deleted.', $success_count, 'directorist-listing-tools' ), $success_count ) );
			if ( ! empty( $results['success'] ) ) {
				$message_parts[] = esc_html__( 'IDs:', 'directorist-listing-tools' ) . ' ' . esc_html( implode( ', ', $results['success'] ) );
			}
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
	 * Handle AJAX bulk delete.
	 */
	public function handle_ajax_bulk_delete() {
		// Check nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'dlt_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'directorist-listing-tools' ) ) );
		}

		// Check capability.
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) ) );
		}

		// Get listing IDs from JSON.
		$listing_ids_json = isset( $_POST['listing_ids'] ) ? wp_unslash( $_POST['listing_ids'] ) : '';
		$listing_ids = json_decode( $listing_ids_json, true );

		if ( ! is_array( $listing_ids ) || empty( $listing_ids ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No listings selected.', 'directorist-listing-tools' ) ) );
		}

		// Sanitize IDs.
		$listing_ids = array_map( 'absint', $listing_ids );

		// Process deletions.
		$results = $this->delete_listings( $listing_ids );

		// Build response.
		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );
		$message = $this->format_results_message( $results );

		wp_send_json_success(
			array(
				'message'      => $message,
				'success_count' => $success_count,
				'failed_count'  => $failed_count,
			)
		);
	}
}

