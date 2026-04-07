<?php
/**
 * Type Manager Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listing type manager class.
 */
class Directorist_Listing_Tools_Type_Manager {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Type_Manager
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Type_Manager
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
		add_action( 'wp_ajax_dlt_ajax_set_listing_type', array( $this, 'handle_ajax_set_type' ) );
	}

	/**
	 * Render type manager page.
	 */
	public function render_page() {
		$listing_types = $this->get_listing_types();

		// Pagination.
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;

		// Get listings with pagination.
		$listings_data = $this->get_listings_with_pagination( $per_page, $offset );
		$listings = $listings_data['listings'];
		$total = $listings_data['total'];
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Type Manager', 'directorist-listing-tools' ); ?></h1>

			<div id="dlt-type-ajax-message" style="display:none; margin: 20px 0;"></div>

			<?php if ( empty( $listing_types ) ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No listing types found. Please create listing types in Directorist first.', 'directorist-listing-tools' ); ?></p>
				</div>
			<?php else : ?>
				<div id="dlt-type-manager-container">
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<select id="dlt-listing-type-select" class="regular-text" style="min-width: 200px;">
								<option value=""><?php esc_html_e( '— Select Listing Type —', 'directorist-listing-tools' ); ?></option>
								<?php foreach ( $listing_types as $type ) : ?>
									<option value="<?php echo esc_attr( $type->term_id ); ?>">
										<?php echo esc_html( $type->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button button-primary dlt-apply-type-btn"><?php esc_html_e( 'Apply Type', 'directorist-listing-tools' ); ?></button>
						</div>
						<div class="alignright">
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

					<?php if ( empty( $listings ) ) : ?>
						<p><?php esc_html_e( 'No listings found.', 'directorist-listing-tools' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped table-view-list">
							<thead>
								<tr>
									<td class="manage-column column-cb check-column">
										<input type="checkbox" id="dlt-type-cb-select-all">
									</td>
									<th scope="col" class="manage-column column-title column-primary">
										<?php esc_html_e( 'Title', 'directorist-listing-tools' ); ?>
									</th>
									<th scope="col" class="manage-column column-author">
										<?php esc_html_e( 'Author', 'directorist-listing-tools' ); ?>
									</th>
									<th scope="col" class="manage-column column-type">
										<?php esc_html_e( 'Current Type', 'directorist-listing-tools' ); ?>
									</th>
									<th scope="col" class="manage-column column-date">
										<?php esc_html_e( 'Date', 'directorist-listing-tools' ); ?>
									</th>
									<th scope="col" class="manage-column column-id">
										<?php esc_html_e( 'ID', 'directorist-listing-tools' ); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $listings as $listing ) : ?>
									<?php
									$current_types = wp_get_object_terms( $listing->ID, dlt_get_listing_types_taxonomy(), array( 'fields' => 'names' ) );
									$current_type_name = ! empty( $current_types ) && ! is_wp_error( $current_types ) ? implode( ', ', $current_types ) : '—';
									?>
									<tr>
										<th scope="row" class="check-column">
											<input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing->ID ); ?>" class="dlt-type-listing-checkbox">
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
										<td class="type column-type">
											<?php echo esc_html( $current_type_name ); ?>
										</td>
										<td class="date column-date">
											<?php echo esc_html( get_the_date( '', $listing->ID ) ); ?>
										</td>
										<td class="id column-id">
											<?php echo esc_html( $listing->ID ); ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<?php if ( $total_pages > 1 ) : ?>
							<div class="tablenav bottom">
								<div class="tablenav-pages">
									<?php
									$base_url = add_query_arg(
										array(
											'page' => 'directorist-listing-tools-type-manager',
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
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get listing types.
	 *
	 * @return array Array of WP_Term objects.
	 */
	private function get_listing_types() {
		$taxonomy = dlt_get_listing_types_taxonomy();
		$terms    = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Get listings with pagination.
	 *
	 * @param int $per_page Items per page.
	 * @param int $offset Offset.
	 * @return array Array with 'listings' and 'total'.
	 */
	private function get_listings_with_pagination( $per_page = 20, $offset = 0 ) {
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'any',
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );

		return array(
			'listings' => $query->posts,
			'total'    => $query->found_posts,
		);
	}

	/**
	 * Handle AJAX set type.
	 */
	public function handle_ajax_set_type() {
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

		// Get listing type.
		$listing_type_id = isset( $_POST['listing_type'] ) ? absint( $_POST['listing_type'] ) : 0;

		if ( empty( $listing_type_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select a listing type.', 'directorist-listing-tools' ) ) );
		}

		// Validate term exists.
		$taxonomy = dlt_get_listing_types_taxonomy();
		$term     = get_term( $listing_type_id, $taxonomy );

		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid listing type selected.', 'directorist-listing-tools' ) ) );
		}

		// Process type assignment.
		$results = $this->set_listing_type( $listing_ids, $listing_type_id );

		// Build response.
		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );
		$message = $this->format_results_message( $results, $term->name );

		wp_send_json_success(
			array(
				'message'       => $message,
				'success_count' => $success_count,
				'failed_count'  => $failed_count,
				'type_name'     => $term->name,
			)
		);
	}

	/**
	 * Set listing type for multiple listings.
	 *
	 * @param array $listing_ids Array of listing IDs.
	 * @param int   $type_id Listing type term ID.
	 * @return array Results array.
	 */
	private function set_listing_type( $listing_ids, $type_id ) {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		$taxonomy = dlt_get_listing_types_taxonomy();
		$post_type = dlt_get_post_type();

		foreach ( $listing_ids as $listing_id ) {
			// Verify post exists and is correct post type.
			$post = get_post( $listing_id );

			if ( ! $post || $post->post_type !== $post_type ) {
				$results['failed'][ $listing_id ] = esc_html__( 'Listing not found or invalid post type.', 'directorist-listing-tools' );
				continue;
			}

			// Set the term (replace existing terms).
			$result = wp_set_object_terms( $listing_id, array( $type_id ), $taxonomy, false );

			if ( is_wp_error( $result ) ) {
				$results['failed'][ $listing_id ] = $result->get_error_message();
			} else {
				$results['success'][] = $listing_id;
			}
		}

		return $results;
	}

	/**
	 * Format results message.
	 *
	 * @param array  $results Results array.
	 * @param string $type_name Listing type name.
	 * @return string Formatted HTML message.
	 */
	private function format_results_message( $results, $type_name ) {
		$message_parts = array();

		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );

		if ( $success_count > 0 ) {
			$message_parts[] = '<strong>' . esc_html__( 'Success:', 'directorist-listing-tools' ) . '</strong> ' . esc_html( sprintf( _n( '%d listing assigned to type "%s".', '%d listings assigned to type "%s".', $success_count, 'directorist-listing-tools' ), $success_count, $type_name ) );
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
}

