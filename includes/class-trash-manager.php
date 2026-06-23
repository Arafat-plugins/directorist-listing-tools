<?php
/**
 * File Trash Manager — files/folders deleted via the File Manager tool are moved
 * here instead of being unlinked immediately, so an accidental delete (e.g. a
 * working mu-plugin fix) can be restored.
 *
 * Scope: only protects deletions performed through this plugin's File Manager.
 * Deletions made directly via FTP/SSH/hosting control panel never run WordPress
 * code, so they cannot be intercepted or recovered here.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DLT_TRASH_MANIFEST_OPTION', 'dlt_file_trash_manifest' );
define( 'DLT_TRASH_DIR_NAME', 'dlt-trash' );

/**
 * Class Directorist_Listing_Tools_Trash_Manager
 */
class Directorist_Listing_Tools_Trash_Manager {

	const AJAX_RESTORE        = 'dlt_trash_restore';
	const AJAX_DELETE_FOREVER = 'dlt_trash_delete_forever';
	const NONCE_ACTION        = 'dlt_trash_nonce';

	/** @var Directorist_Listing_Tools_Trash_Manager|null */
	private static $instance = null;

	/**
	 * @return Directorist_Listing_Tools_Trash_Manager
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
		add_action( 'wp_ajax_' . self::AJAX_RESTORE, array( $this, 'ajax_restore' ) );
		add_action( 'wp_ajax_' . self::AJAX_DELETE_FOREVER, array( $this, 'ajax_delete_forever' ) );
	}

	/**
	 * Get (and create) the trash directory. Locked down so it is never web-accessible
	 * and never auto-loaded by WordPress, even if a trashed item was a PHP file.
	 *
	 * @return string Absolute path, no trailing slash.
	 */
	public static function get_trash_dir() {
		$dir = trailingslashit( WP_CONTENT_DIR ) . DLT_TRASH_DIR_NAME;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
		}

		return rtrim( $dir, '/\\' );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function get_manifest() {
		$manifest = get_option( DLT_TRASH_MANIFEST_OPTION, array() );
		return is_array( $manifest ) ? $manifest : array();
	}

	/**
	 * @param array<string,array<string,mixed>> $manifest Manifest data.
	 * @return void
	 */
	private function save_manifest( $manifest ) {
		update_option( DLT_TRASH_MANIFEST_OPTION, $manifest, false );
	}

	/**
	 * Move a file or folder into trash instead of deleting it.
	 *
	 * @param string $abs_path Absolute path of the item to trash.
	 * @return bool True on success.
	 */
	public function move_to_trash( $abs_path ) {
		if ( ! file_exists( $abs_path ) ) {
			return false;
		}

		$trash_dir = self::get_trash_dir();
		$id        = gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 8, false, false );
		$basename  = basename( $abs_path );
		$dest      = trailingslashit( $trash_dir ) . $id . '__' . $basename;

		if ( ! @rename( $abs_path, $dest ) ) {
			return false;
		}

		$manifest        = $this->get_manifest();
		$manifest[ $id ] = array(
			'id'            => $id,
			'original_path' => $abs_path,
			'trash_path'    => $dest,
			'is_dir'        => is_dir( $dest ),
			'trashed_at'    => current_time( 'mysql' ),
			'trashed_by'    => get_current_user_id(),
		);
		$this->save_manifest( $manifest );

		return true;
	}

	/**
	 * Check auth/nonce for trash AJAX actions.
	 *
	 * @return void
	 */
	private function check_auth() {
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ), 403 );
		}
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'directorist-listing-tools' ) ), 403 );
		}
	}

	/**
	 * AJAX: Restore a trashed item to its original location.
	 */
	public function ajax_restore() {
		$this->check_auth();

		$id       = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';
		$manifest = $this->get_manifest();

		if ( ! isset( $manifest[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Trash item not found.', 'directorist-listing-tools' ) ) );
		}

		$entry = $manifest[ $id ];

		if ( ! file_exists( $entry['trash_path'] ) ) {
			unset( $manifest[ $id ] );
			$this->save_manifest( $manifest );
			wp_send_json_error( array( 'message' => __( 'Trashed item is missing on disk.', 'directorist-listing-tools' ) ) );
		}

		if ( file_exists( $entry['original_path'] ) ) {
			wp_send_json_error( array( 'message' => __( 'A file already exists at the original location. Remove or rename it first.', 'directorist-listing-tools' ) ) );
		}

		$dest_parent = dirname( $entry['original_path'] );
		if ( ! is_dir( $dest_parent ) ) {
			wp_mkdir_p( $dest_parent );
		}

		if ( ! @rename( $entry['trash_path'], $entry['original_path'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not restore item.', 'directorist-listing-tools' ) ) );
		}

		unset( $manifest[ $id ] );
		$this->save_manifest( $manifest );

		wp_send_json_success( array( 'message' => __( 'Restored.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Permanently delete a trashed item.
	 */
	public function ajax_delete_forever() {
		$this->check_auth();

		$id       = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';
		$manifest = $this->get_manifest();

		if ( ! isset( $manifest[ $id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Trash item not found.', 'directorist-listing-tools' ) ) );
		}

		$entry = $manifest[ $id ];

		if ( file_exists( $entry['trash_path'] ) ) {
			if ( is_dir( $entry['trash_path'] ) ) {
				$this->delete_recursive( $entry['trash_path'] );
			} else {
				@unlink( $entry['trash_path'] );
			}
		}

		unset( $manifest[ $id ] );
		$this->save_manifest( $manifest );

		wp_send_json_success( array( 'message' => __( 'Permanently deleted.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * Recursively delete a directory inside trash.
	 *
	 * @param string $dir Absolute path.
	 * @return void
	 */
	private function delete_recursive( $dir ) {
		$list = @scandir( $dir );
		if ( false === $list ) {
			return;
		}
		foreach ( $list as $name ) {
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			$full = path_join( $dir, $name );
			if ( is_dir( $full ) ) {
				$this->delete_recursive( $full );
			} else {
				@unlink( $full );
			}
		}
		@rmdir( $dir );
	}

	/**
	 * Get all trash entries (newest first), pruning any whose file is already gone.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_all_trash_items() {
		$manifest = $this->get_manifest();
		$valid    = array();

		foreach ( $manifest as $id => $entry ) {
			if ( file_exists( $entry['trash_path'] ) ) {
				$valid[ $id ] = $entry;
			}
		}

		if ( count( $valid ) !== count( $manifest ) ) {
			$this->save_manifest( $valid );
		}

		uasort(
			$valid,
			function ( $a, $b ) {
				return strcmp( $b['trashed_at'], $a['trashed_at'] );
			}
		);

		return $valid;
	}

	/**
	 * Admin page output.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		$items = $this->get_all_trash_items();
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="wrap dlt-trash-wrap">
			<h2 class="screen-reader-text"><?php esc_html_e( 'File Trash', 'directorist-listing-tools' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Files and folders deleted through the File Manager tool land here first instead of being removed immediately. Restore them, or delete permanently once you are sure.', 'directorist-listing-tools' ); ?>
			</p>
			<p class="description" style="color:#b32d2e;">
				<strong><?php esc_html_e( 'Important:', 'directorist-listing-tools' ); ?></strong>
				<?php esc_html_e( ' This only protects deletions made through this File Manager. Files deleted directly via FTP, SSH, or your hosting control panel never run WordPress code, so they cannot be recovered here.', 'directorist-listing-tools' ); ?>
			</p>

			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'Trash is empty.', 'directorist-listing-tools' ); ?></p>
			<?php else : ?>
				<table class="widefat striped dlt-trash-table" style="max-width:1100px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Original path', 'directorist-listing-tools' ); ?></th>
							<th><?php esc_html_e( 'Type', 'directorist-listing-tools' ); ?></th>
							<th><?php esc_html_e( 'Deleted at', 'directorist-listing-tools' ); ?></th>
							<th><?php esc_html_e( 'Deleted by', 'directorist-listing-tools' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'directorist-listing-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $id => $entry ) : ?>
							<?php $user = get_userdata( (int) $entry['trashed_by'] ); ?>
							<tr data-trash-id="<?php echo esc_attr( $id ); ?>">
								<td><code><?php echo esc_html( $entry['original_path'] ); ?></code></td>
								<td><?php echo $entry['is_dir'] ? esc_html__( 'Folder', 'directorist-listing-tools' ) : esc_html__( 'File', 'directorist-listing-tools' ); ?></td>
								<td><?php echo esc_html( $entry['trashed_at'] ); ?></td>
								<td><?php echo esc_html( $user ? $user->display_name : '—' ); ?></td>
								<td>
									<button type="button" class="button button-primary dlt-trash-restore"><?php esc_html_e( 'Restore', 'directorist-listing-tools' ); ?></button>
									<button type="button" class="button dlt-trash-delete-forever"><?php esc_html_e( 'Delete permanently', 'directorist-listing-tools' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<script type="application/json" id="dlt-trash-config"><?php echo wp_json_encode( array( 'nonce' => $nonce, 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) ); ?></script>
		<script>
		(function($){
			var config = {};
			function init() {
				var el = document.getElementById('dlt-trash-config');
				if ( el && el.textContent ) {
					try { config = JSON.parse(el.textContent); } catch(e) {}
				}
			}
			$(function(){
				if ( !$('.dlt-trash-wrap').length ) return;
				init();

				$('.dlt-trash-restore').on('click', function(){
					var $row = $(this).closest('tr');
					var id = $row.data('trash-id');
					if ( !window.confirm('<?php echo esc_js( __( 'Restore this item to its original location?', 'directorist-listing-tools' ) ); ?>') ) return;
					$.post(config.ajaxUrl, { action: 'dlt_trash_restore', nonce: config.nonce, id: id })
						.done(function(res){
							if ( res.success ) { $row.remove(); } else { window.alert(res.data && res.data.message ? res.data.message : 'Failed.'); }
						});
				});

				$('.dlt-trash-delete-forever').on('click', function(){
					var $row = $(this).closest('tr');
					var id = $row.data('trash-id');
					if ( !window.confirm('<?php echo esc_js( __( 'Permanently delete this item? This cannot be undone.', 'directorist-listing-tools' ) ); ?>') ) return;
					$.post(config.ajaxUrl, { action: 'dlt_trash_delete_forever', nonce: config.nonce, id: id })
						.done(function(res){
							if ( res.success ) { $row.remove(); } else { window.alert(res.data && res.data.message ? res.data.message : 'Failed.'); }
						});
				});
			});
		})(jQuery);
		</script>
		<?php
	}
}
