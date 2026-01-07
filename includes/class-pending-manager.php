<?php
/**
 * Pending Manager Class
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pending listings manager class.
 */
class Directorist_Listing_Tools_Pending_Manager {

	/**
	 * Instance of this class.
	 *
	 * @var Directorist_Listing_Tools_Pending_Manager
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Directorist_Listing_Tools_Pending_Manager
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
		add_action( 'admin_post_dlt_pending_bulk_action', array( $this, 'handle_bulk_action' ) );
	}

	/**
	 * Render pending manager page.
	 */
	public function render_page() {
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
		$pending_listings = $this->get_pending_listings();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Listings Manager', 'directorist-listing-tools' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Manage pending listings. Select multiple listings and apply bulk actions.', 'directorist-listing-tools' ); ?>
			</p>

			<?php if ( ! empty( $message ) ) : ?>
				<div class="dlt-message">
					<?php echo wp_kses_post( urldecode( $message ) ); ?>
				</div>
			<?php endif; ?>

			<?php if ( empty( $pending_listings ) ) : ?>
				<p><?php esc_html_e( 'No pending listings found.', 'directorist-listing-tools' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dlt-pending-form">
					<?php wp_nonce_field( 'dlt_pending_bulk_action', 'dlt_pending_nonce' ); ?>
					<input type="hidden" name="action" value="dlt_pending_bulk_action">

					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<select name="bulk_action" id="bulk-action-selector" required>
								<option value=""><?php esc_html_e( 'Bulk Actions', 'directorist-listing-tools' ); ?></option>
								<option value="publish"><?php esc_html_e( 'Publish Selected', 'directorist-listing-tools' ); ?></option>
								<option value="delete"><?php esc_html_e( 'Delete Selected', 'directorist-listing-tools' ); ?></option>
							</select>
							<?php submit_button( esc_html__( 'Apply', 'directorist-listing-tools' ), 'action', 'submit', false, array( 'id' => 'doaction' ) ); ?>
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
								<th scope="col" class="manage-column column-id">
									<?php esc_html_e( 'ID', 'directorist-listing-tools' ); ?>
								</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pending_listings as $listing ) : ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="listing_ids[]" value="<?php echo esc_attr( $listing->ID ); ?>" class="listing-checkbox">
									</th>
									<td class="title column-title column-primary">
										<strong>
											<a href="<?php echo esc_url( get_edit_post_link( $listing->ID ) ); ?>">
												<?php echo esc_html( $listing->post_title ); ?>
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
									<td class="id column-id">
										<?php echo esc_html( $listing->ID ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get pending listings.
	 *
	 * @return array Array of WP_Post objects.
	 */
	private function get_pending_listings() {
		$args = array(
			'post_type'      => dlt_get_post_type(),
			'post_status'    => 'pending',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Handle bulk action form submission.
	 */
	public function handle_bulk_action() {
		// Check nonce.
		if ( ! isset( $_POST['dlt_pending_nonce'] ) || ! dlt_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dlt_pending_nonce'] ) ), 'dlt_pending_bulk_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'directorist-listing-tools' ) );
		}

		// Check capability.
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'directorist-listing-tools' ) );
		}

		// Get bulk action.
		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';

		if ( empty( $bulk_action ) || ! in_array( $bulk_action, array( 'publish', 'delete' ), true ) ) {
			$redirect_url = add_query_arg(
				array(
					'page'    => 'directorist-listing-tools-pending',
					'message' => urlencode( dlt_format_notice( esc_html__( 'Invalid bulk action selected.', 'directorist-listing-tools' ), 'error' ) ),
				),
				admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Get listing IDs.
		$listing_ids = isset( $_POST['listing_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['listing_ids'] ) ) : array();

		if ( empty( $listing_ids ) ) {
			$redirect_url = add_query_arg(
				array(
					'page'    => 'directorist-listing-tools-pending',
					'message' => urlencode( dlt_format_notice( esc_html__( 'No listings selected.', 'directorist-listing-tools' ), 'error' ) ),
				),
				admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Process bulk action.
		$results = array();
		if ( 'publish' === $bulk_action ) {
			$results = $this->publish_listings( $listing_ids );
		} elseif ( 'delete' === $bulk_action ) {
			$results = $this->delete_listings( $listing_ids );
		}

		// Build message.
		$message = $this->format_results_message( $results, $bulk_action );

		// Redirect with message.
		$redirect_url = add_query_arg(
			array(
				'page'    => 'directorist-listing-tools-pending',
				'message' => urlencode( $message ),
			),
			admin_url( 'edit.php?post_type=' . dlt_get_post_type() )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Publish listings.
	 *
	 * @param array $listing_ids Array of listing IDs.
	 * @return array Results array.
	 */
	private function publish_listings( $listing_ids ) {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ( $listing_ids as $listing_id ) {
			$post = get_post( $listing_id );

			if ( ! $post || $post->post_type !== dlt_get_post_type() ) {
				$results['failed'][ $listing_id ] = esc_html__( 'Listing not found.', 'directorist-listing-tools' );
				continue;
			}

			$updated = wp_update_post(
				array(
					'ID'          => $listing_id,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $updated ) ) {
				$results['failed'][ $listing_id ] = $updated->get_error_message();
			} else {
				$results['success'][] = $listing_id;
			}
		}

		return $results;
	}

	/**
	 * Delete listings.
	 *
	 * @param array $listing_ids Array of listing IDs.
	 * @return array Results array.
	 */
	private function delete_listings( $listing_ids ) {
		$results = array(
			'success' => array(),
			'failed'  => array(),
		);

		foreach ( $listing_ids as $listing_id ) {
			$post = get_post( $listing_id );

			if ( ! $post || $post->post_type !== dlt_get_post_type() ) {
				$results['failed'][ $listing_id ] = esc_html__( 'Listing not found.', 'directorist-listing-tools' );
				continue;
			}

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
	 * @param array  $results Results array.
	 * @param string $action Action performed.
	 * @return string Formatted HTML message.
	 */
	private function format_results_message( $results, $action ) {
		$message_parts = array();

		$success_count = count( $results['success'] );
		$failed_count  = count( $results['failed'] );

		$action_label = ( 'publish' === $action ) ? esc_html__( 'published', 'directorist-listing-tools' ) : esc_html__( 'deleted', 'directorist-listing-tools' );

		if ( $success_count > 0 ) {
			$message_parts[] = '<strong>' . esc_html__( 'Success:', 'directorist-listing-tools' ) . '</strong> ' . esc_html( sprintf( _n( '%d listing %s.', '%d listings %s.', $success_count, 'directorist-listing-tools' ), $success_count, $action_label ) );
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

