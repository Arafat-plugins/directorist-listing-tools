<?php
/**
 * File Manager Class
 *
 * Safe file and folder management from an allowed root (default: application/WordPress root).
 * Paths can include ".." and "."; they are resolved and must stay under the root.
 * Delete actions require front-end confirmation popup; backend verifies nonce and capability.
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File Manager handler class.
 */
class Directorist_Listing_Tools_File_Manager {

	const AJAX_ACTION_LIST    = 'dlt_fm_list';
	const AJAX_ACTION_CREATE  = 'dlt_fm_create_folder';
	const AJAX_ACTION_CREATE_FILE = 'dlt_fm_create_file';
	const AJAX_ACTION_UPLOAD  = 'dlt_fm_upload';
	const AJAX_ACTION_DELETE  = 'dlt_fm_delete';
	const AJAX_ACTION_DOWNLOAD = 'dlt_fm_download';
	const AJAX_ACTION_RENAME  = 'dlt_fm_rename';
	const AJAX_ACTION_GET_FILE = 'dlt_fm_get_file';
	const AJAX_ACTION_SAVE_FILE = 'dlt_fm_save_file';
	const NONCE_ACTION        = 'dlt_fm_nonce';
	const OPTION_ROOT         = 'dlt_file_manager_root';

	/** @var Directorist_Listing_Tools_File_Manager */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Directorist_Listing_Tools_File_Manager
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
		add_action( 'wp_ajax_' . self::AJAX_ACTION_LIST, array( $this, 'ajax_list' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_CREATE, array( $this, 'ajax_create_folder' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_CREATE_FILE, array( $this, 'ajax_create_file' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_UPLOAD, array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_DELETE, array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_DOWNLOAD, array( $this, 'ajax_download' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_RENAME, array( $this, 'ajax_rename' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_GET_FILE, array( $this, 'ajax_get_file' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SAVE_FILE, array( $this, 'ajax_save_file' ) );
	}

	/**
	 * Whether current user can modify filesystem (create/upload/edit/rename/delete).
	 *
	 * @return bool
	 */
	private function can_modify_files() {
		// File operations are high-risk on live sites; only allow administrators by default.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the allowed file manager root directory (absolute path).
	 * Default: application root (ABSPATH). Option can be set to a path relative to ABSPATH,
	 * e.g. "wp-content" or ".." for parent of ABSPATH (e.g. "app" folder).
	 *
	 * @return string Absolute path, no trailing slash.
	 */
	public function get_root_path() {
		$rel = get_option( self::OPTION_ROOT, '' );
		$rel = trim( str_replace( '\\', '/', $rel ), '/' );

		// Empty = application (WordPress) root.
		if ( $rel === '' ) {
			$resolved = realpath( ABSPATH );
			return false !== $resolved ? $resolved : rtrim( ABSPATH, '/\\' );
		}

		// ".." = parent of ABSPATH (e.g. "app" folder containing public/).
		if ( $rel === '..' ) {
			$parent = realpath( ABSPATH . '/..' );
			return false !== $parent ? $parent : rtrim( dirname( ABSPATH ), '/\\' );
		}

		$abs = path_join( ABSPATH, $rel );
		$resolved = realpath( $abs );
		if ( false === $resolved || $resolved === '' ) {
			// Fallback to ABSPATH if option path is invalid.
			$resolved = realpath( ABSPATH );
			return false !== $resolved ? $resolved : rtrim( ABSPATH, '/\\' );
		}
		$abspath = realpath( ABSPATH );
		if ( $abspath && strpos( $resolved, $abspath ) !== 0 ) {
			return $abspath;
		}
		return $resolved;
	}

	/**
	 * Resolve and validate a path under the file manager root.
	 * Subpath can contain ".." and "."; they are resolved and must stay under root.
	 *
	 * @param string $subpath Relative path (e.g. "wp-content/uploads", "wp-content/../wp-admin").
	 * @return array{ 'valid' => bool, 'absolute' => string }
	 */
	public function resolve_path( $subpath ) {
		$root = $this->get_root_path();
		$subpath = trim( (string) $subpath, "/\\" );
		$subpath = str_replace( array( '\\' ), array( '/' ), $subpath );
		// Allow ".." and "." in path; realpath will resolve them.
		if ( $subpath !== '' ) {
			$full = path_join( $root, $subpath );
		} else {
			$full = $root;
		}
		$resolved = realpath( $full );
		if ( false === $resolved ) {
			$parent = dirname( $full );
			$parent_resolved = realpath( $parent );
			if ( false !== $parent_resolved && ( $parent_resolved === $root || strpos( $parent_resolved, $root . DIRECTORY_SEPARATOR ) === 0 ) ) {
				return array( 'valid' => true, 'absolute' => $full );
			}
			return array( 'valid' => false, 'absolute' => '' );
		}
		// Resolved path must be inside root (no escape above root).
		if ( $resolved !== $root && strpos( $resolved, $root . DIRECTORY_SEPARATOR ) !== 0 ) {
			return array( 'valid' => false, 'absolute' => '' );
		}
		return array( 'valid' => true, 'absolute' => $resolved );
	}

	/**
	 * Check capability and nonce for AJAX.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private function check_ajax_auth( $action = self::NONCE_ACTION ) {
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ), 403 );
		}
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'directorist-listing-tools' ) ), 403 );
		}
	}

	/**
	 * Check that user can modify files.
	 *
	 * @return void
	 */
	private function check_modify_permission() {
		if ( ! $this->can_modify_files() ) {
			wp_send_json_error( array( 'message' => __( 'Only administrators can modify files.', 'directorist-listing-tools' ) ), 403 );
		}
	}

	/**
	 * Get allowed editable extensions.
	 *
	 * @return string[]
	 */
	private function get_editable_extensions() {
		return array( 'txt', 'md', 'log', 'json', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'js', 'php', 'scss', 'less', 'ini', 'htaccess' );
	}

	/**
	 * Whether a file path is editable as text.
	 *
	 * @param string $abs_path Absolute file path.
	 * @return bool
	 */
	private function is_editable_file( $abs_path ) {
		$base = basename( $abs_path );
		if ( $base === '.env' ) {
			return false;
		}
		if ( $base === '.htaccess' ) {
			return true;
		}
		$ext = strtolower( pathinfo( $abs_path, PATHINFO_EXTENSION ) );
		if ( $ext === '' ) {
			return false;
		}
		return in_array( $ext, $this->get_editable_extensions(), true );
	}

	/**
	 * AJAX: List directory contents.
	 */
	public function ajax_list() {
		$this->check_ajax_auth();
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$result = $this->resolve_path( $subpath );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		$dir = $result['absolute'];
		if ( ! is_dir( $dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Not a directory.', 'directorist-listing-tools' ) ) );
		}
		$items = array();
		$root = $this->get_root_path();
		$list = @scandir( $dir );
		if ( false === $list ) {
			wp_send_json_error( array( 'message' => __( 'Could not read directory.', 'directorist-listing-tools' ) ) );
		}
		foreach ( $list as $name ) {
			if ( $name === '.' || $name === '..' ) {
				continue;
			}
			$full = path_join( $dir, $name );
			$resolved = realpath( $full );
			if ( false === $resolved ) {
				continue;
			}
			$root_with_sep = $root . DIRECTORY_SEPARATOR;
			if ( $resolved !== $root && strpos( $resolved, $root_with_sep ) !== 0 ) {
				continue;
			}
			$is_dir = is_dir( $full );
			$rel = ltrim( str_replace( $root, '', $resolved ), "/\\" );
			$rel = str_replace( DIRECTORY_SEPARATOR, '/', $rel );
			$items[] = array(
				'name'   => $name,
				'path'   => $rel,
				'is_dir' => $is_dir,
				'size'   => $is_dir ? null : filesize( $full ),
				'mtime'  => filemtime( $full ),
			);
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['is_dir'] !== $b['is_dir'] ) {
				return $a['is_dir'] ? -1 : 1;
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );
		wp_send_json_success( array(
			'path'  => $subpath,
			'items' => $items,
			'root_label' => basename( $root ),
		) );
	}

	/**
	 * AJAX: Create folder.
	 */
	public function ajax_create_folder() {
		$this->check_ajax_auth();
		$this->check_modify_permission();
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$name = isset( $_REQUEST['name'] ) ? sanitize_file_name( wp_unslash( $_REQUEST['name'] ) ) : '';
		if ( '' === $name || preg_match( '/[\/\\\\]/', $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder name.', 'directorist-listing-tools' ) ) );
		}
		$result = $this->resolve_path( $subpath === '' ? $name : $subpath . '/' . $name );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		$target = $result['absolute'];
		if ( file_exists( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'A file or folder with that name already exists.', 'directorist-listing-tools' ) ) );
		}
		if ( ! wp_mkdir_p( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create folder.', 'directorist-listing-tools' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'Folder created.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Create empty file.
	 */
	public function ajax_create_file() {
		$this->check_ajax_auth();
		$this->check_modify_permission();
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$name = isset( $_REQUEST['name'] ) ? sanitize_file_name( wp_unslash( $_REQUEST['name'] ) ) : '';
		if ( '' === $name || preg_match( '/[\/\\\\]/', $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file name.', 'directorist-listing-tools' ) ) );
		}
		$parent = $this->resolve_path( $subpath );
		if ( ! $parent['valid'] || ! is_dir( $parent['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parent directory.', 'directorist-listing-tools' ) ) );
		}
		$target = path_join( $parent['absolute'], $name );
		$under_root = $this->resolve_path( $subpath . '/' . $name );
		if ( ! $under_root['valid'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		if ( file_exists( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'A file or folder with that name already exists.', 'directorist-listing-tools' ) ) );
		}
		if ( @file_put_contents( $target, '' ) === false ) {
			wp_send_json_error( array( 'message' => __( 'Could not create file.', 'directorist-listing-tools' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'File created.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Upload file.
	 */
	public function ajax_upload() {
		$this->check_ajax_auth();
		$this->check_modify_permission();
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$parent = $this->resolve_path( $subpath );
		if ( ! $parent['valid'] || ! is_dir( $parent['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload directory.', 'directorist-listing-tools' ) ) );
		}
		if ( empty( $_FILES['file'] ) || ! is_uploaded_file( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'directorist-listing-tools' ) ) );
		}
		$name = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file name.', 'directorist-listing-tools' ) ) );
		}

		// Optional relative path for folder uploads (drag-and-drop / webkitdirectory).
		$relative = isset( $_REQUEST['rel_path'] ) ? (string) wp_unslash( $_REQUEST['rel_path'] ) : '';
		$relative = str_replace( '\\', '/', $relative );
		$relative = trim( $relative, "/ \t\n\r\0\x0B" );
		if ( $relative !== '' ) {
			$parts = array_filter( array_map( 'sanitize_file_name', explode( '/', $relative ) ) );
			$parts = array_values( $parts );
			$relative = implode( '/', $parts );
		}

		$dest_rel = $name;
		if ( $relative !== '' ) {
			$dest_rel = $relative;
			$leaf = basename( $dest_rel );
			if ( $leaf === '' ) {
				$dest_rel = $name;
			}
		}

		$rel = $subpath === '' ? $dest_rel : $subpath . '/' . $dest_rel;
		$under = $this->resolve_path( $rel );
		if ( ! $under['valid'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}

		$target = $under['absolute'];
		$target_dir = dirname( $target );
		if ( ! is_dir( $target_dir ) && ! wp_mkdir_p( $target_dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create destination directory.', 'directorist-listing-tools' ) ) );
		}

		if ( ! move_uploaded_file( $_FILES['file']['tmp_name'], $target ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save uploaded file.', 'directorist-listing-tools' ) ) );
		}
		wp_send_json_success( array( 'message' => __( 'File uploaded.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Delete file or folder.
	 */
	public function ajax_delete() {
		$this->check_ajax_auth();
		$this->check_modify_permission();
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		if ( '' === $subpath ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete root.', 'directorist-listing-tools' ) ) );
		}
		$result = $this->resolve_path( $subpath );
		if ( ! $result['valid'] ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		$target = $result['absolute'];
		$root = $this->get_root_path();
		if ( $target === $root || strpos( $target, $root ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ) );
		}
		if ( ! file_exists( $target ) ) {
			wp_send_json_error( array( 'message' => __( 'File or folder not found.', 'directorist-listing-tools' ) ) );
		}
		if ( is_dir( $target ) ) {
			$this->delete_recursive( $target );
		} else {
			if ( ! @unlink( $target ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not delete file.', 'directorist-listing-tools' ) ) );
			}
		}
		wp_send_json_success( array( 'message' => __( 'Deleted.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * Recursively delete a directory. Path must already be validated.
	 *
	 * @param string $dir Absolute path.
	 * @return void
	 */
	private function delete_recursive( $dir ) {
		$list = @scandir( $dir );
		if ( false === $list ) {
			wp_send_json_error( array( 'message' => __( 'Could not read directory.', 'directorist-listing-tools' ) ) );
		}
		foreach ( $list as $name ) {
			if ( $name === '.' || $name === '..' ) {
				continue;
			}
			$full = path_join( $dir, $name );
			if ( is_dir( $full ) ) {
				$this->delete_recursive( $full );
			} else {
				@unlink( $full );
			}
		}
		if ( ! @rmdir( $dir ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete folder.', 'directorist-listing-tools' ) ) );
		}
	}

	/**
	 * AJAX: Download file (output file and exit).
	 */
	public function ajax_download() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'Permission denied.', 'directorist-listing-tools' ), 403 );
		}
		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		if ( '' === $subpath ) {
			wp_die( esc_html__( 'Invalid path.', 'directorist-listing-tools' ), 400 );
		}
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Security check failed.', 'directorist-listing-tools' ), 403 );
		}
		$result = $this->resolve_path( $subpath );
		if ( ! $result['valid'] || ! is_file( $result['absolute'] ) ) {
			wp_die( esc_html__( 'File not found.', 'directorist-listing-tools' ), 404 );
		}
		$path = $result['absolute'];
		$name = basename( $path );
		$mime = wp_check_filetype( $path, null )['type'];
		if ( ! $mime ) {
			$mime = 'application/octet-stream';
		}
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
		exit;
	}

	/**
	 * AJAX: Rename file or folder.
	 */
	public function ajax_rename() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$old = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$new_name = isset( $_REQUEST['new_name'] ) ? sanitize_file_name( wp_unslash( $_REQUEST['new_name'] ) ) : '';

		if ( $old === '' ) {
			wp_send_json_error( array( 'message' => __( 'Cannot rename root.', 'directorist-listing-tools' ) ) );
		}
		if ( $new_name === '' || preg_match( '/[\/\\\\]/', $new_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid name.', 'directorist-listing-tools' ) ) );
		}

		$old_res = $this->resolve_path( $old );
		if ( ! $old_res['valid'] || ! file_exists( $old_res['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'File or folder not found.', 'directorist-listing-tools' ) ) );
		}

		$old_abs = $old_res['absolute'];
		$parent_abs = dirname( $old_abs );
		$new_abs = path_join( $parent_abs, $new_name );

		// Validate the new path stays under root.
		$root = $this->get_root_path();
		$parent_resolved = realpath( $parent_abs );
		if ( false === $parent_resolved || ( $parent_resolved !== $root && strpos( $parent_resolved, $root . DIRECTORY_SEPARATOR ) !== 0 ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid destination.', 'directorist-listing-tools' ) ) );
		}

		if ( file_exists( $new_abs ) ) {
			wp_send_json_error( array( 'message' => __( 'A file or folder with that name already exists.', 'directorist-listing-tools' ) ) );
		}

		if ( ! @rename( $old_abs, $new_abs ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not rename.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Renamed.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Get file content for editor.
	 */
	public function ajax_get_file() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		if ( $subpath === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		$res = $this->resolve_path( $subpath );
		if ( ! $res['valid'] || ! is_file( $res['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'directorist-listing-tools' ) ) );
		}
		$abs = $res['absolute'];

		// Size guard (2MB).
		$size = filesize( $abs );
		if ( $size !== false && $size > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'File too large to edit (max 2MB).', 'directorist-listing-tools' ) ) );
		}

		$base = basename( $abs );
		if ( $base === '.env' ) {
			wp_send_json_error( array( 'message' => __( 'Editing this file is not allowed.', 'directorist-listing-tools' ) ) );
		}

		$contents = @file_get_contents( $abs );
		if ( $contents === false ) {
			wp_send_json_error( array( 'message' => __( 'Could not read file.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success( array(
			'path' => $subpath,
			'name' => $base,
			'content' => $contents,
		) );
	}

	/**
	 * AJAX: Save file content from editor.
	 */
	public function ajax_save_file() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$content = isset( $_REQUEST['content'] ) ? (string) wp_unslash( $_REQUEST['content'] ) : '';

		if ( $subpath === '' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid path.', 'directorist-listing-tools' ) ) );
		}
		$res = $this->resolve_path( $subpath );
		if ( ! $res['valid'] || ! is_file( $res['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'File not found.', 'directorist-listing-tools' ) ) );
		}

		$abs = $res['absolute'];
		$base = basename( $abs );
		if ( $base === '.env' ) {
			wp_send_json_error( array( 'message' => __( 'Editing this file is not allowed.', 'directorist-listing-tools' ) ) );
		}

		// Guard: do not allow huge saves (2MB).
		if ( strlen( $content ) > 2 * 1024 * 1024 ) {
			wp_send_json_error( array( 'message' => __( 'Content too large (max 2MB).', 'directorist-listing-tools' ) ) );
		}

		if ( @file_put_contents( $abs, $content ) === false ) {
			wp_send_json_error( array( 'message' => __( 'Could not save file.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Saved.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * Render the File Manager admin page.
	 */
	public function render_page() {
		$root = $this->get_root_path();
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		?>
		<div class="wrap dlt-file-manager-wrap">
			<h2><?php esc_html_e( 'File Managing', 'directorist-listing-tools' ); ?></h2>
			<p class="description">
				<?php echo esc_html( sprintf( __( 'Root directory: %s', 'directorist-listing-tools' ), $root ) ); ?>
			</p>
			<div class="dlt-fm-toolbar">
				<div class="dlt-fm-breadcrumb" aria-label="<?php esc_attr_e( 'Current folder', 'directorist-listing-tools' ); ?>"></div>
				<div class="dlt-fm-actions">
					<button type="button" class="button button-secondary dlt-fm-btn dlt-fm-new-folder"><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e( 'New folder', 'directorist-listing-tools' ); ?></button>
					<button type="button" class="button button-secondary dlt-fm-btn dlt-fm-new-file"><span class="dashicons dashicons-media-default"></span> <?php esc_html_e( 'New file', 'directorist-listing-tools' ); ?></button>
					<div class="dlt-fm-upload-wrap">
						<input type="file" id="dlt-fm-upload-input" class="dlt-fm-upload-input" multiple>
						<input type="file" id="dlt-fm-upload-folder-input" class="dlt-fm-upload-input" webkitdirectory directory multiple>
						<button type="button" class="button button-secondary dlt-fm-btn dlt-fm-upload-btn"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button button-secondary dlt-fm-btn dlt-fm-upload-folder-btn"><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Upload folder', 'directorist-listing-tools' ); ?></button>
					</div>
				</div>
			</div>
			<p class="description dlt-fm-drop-hint"><?php esc_html_e( 'Tip: Drag and drop files or folders directly into the file list area.', 'directorist-listing-tools' ); ?></p>
			<div class="dlt-fm-list-wrap">
				<div class="dlt-fm-loading" style="display:none;"><?php esc_html_e( 'Loading…', 'directorist-listing-tools' ); ?></div>
				<div class="dlt-fm-list" role="list"></div>
				<div class="dlt-fm-empty" style="display:none;"><?php esc_html_e( 'This folder is empty.', 'directorist-listing-tools' ); ?></div>
			</div>
			<div id="dlt-fm-modal" class="dlt-fm-modal" role="dialog" aria-modal="true" aria-labelledby="dlt-fm-modal-title" style="display:none;">
				<div class="dlt-fm-modal-content">
					<h3 id="dlt-fm-modal-title" class="dlt-fm-modal-title"></h3>
					<div class="dlt-fm-modal-body"></div>
					<div class="dlt-fm-modal-footer">
						<button type="button" class="button button-primary dlt-fm-modal-confirm"><?php esc_html_e( 'Confirm', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button dlt-fm-modal-cancel"><?php esc_html_e( 'Cancel', 'directorist-listing-tools' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<script type="application/json" id="dlt-fm-config"><?php echo wp_json_encode( array( 'nonce' => $nonce, 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'rootLabel' => basename( $root ) ) ); ?></script>
		<?php
	}
}
