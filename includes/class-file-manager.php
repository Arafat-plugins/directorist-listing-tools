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
	const AJAX_ACTION_FIX_PERMISSIONS = 'dlt_fm_fix_permissions';
	const AJAX_ACTION_SET_ROOT = 'dlt_fm_set_root';
	const AJAX_ACTION_SET_PARENT_ROOT = 'dlt_fm_set_parent_root';
	const AJAX_ACTION_CLEAR_DEBUG_LOG = 'dlt_fm_clear_debug_log';
	const AJAX_ACTION_SET_WP_DEBUG = 'dlt_fm_set_wp_debug';
	const AJAX_ACTION_SCAN_STORAGE = 'dlt_fm_scan_storage';
	const NONCE_ACTION        = 'dlt_fm_nonce';
	const OPTION_ROOT         = 'dlt_file_manager_root';
	const OPTION_CUSTOM_ROOT  = 'dlt_file_manager_custom_root';

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
		add_action( 'wp_ajax_' . self::AJAX_ACTION_FIX_PERMISSIONS, array( $this, 'ajax_fix_permissions' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SET_ROOT, array( $this, 'ajax_set_root' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SET_PARENT_ROOT, array( $this, 'ajax_set_parent_root' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_CLEAR_DEBUG_LOG, array( $this, 'ajax_clear_debug_log' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SET_WP_DEBUG, array( $this, 'ajax_set_wp_debug' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_SCAN_STORAGE, array( $this, 'ajax_scan_storage' ) );
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
	 * Whether current user can access file-manager data.
	 *
	 * @return bool
	 */
	private function can_access_file_manager() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Normalize a directory path for root comparisons.
	 *
	 * @param string $path Directory path.
	 * @return string
	 */
	private function normalize_root_path( $path ) {
		$resolved = realpath( $path );
		$path     = false !== $resolved ? $resolved : $path;

		return rtrim( $path, '/\\' );
	}

	/**
	 * Check whether a path is absolute on Linux/Unix or Windows.
	 *
	 * @param string $path Filesystem path.
	 * @return bool
	 */
	private function is_absolute_filesystem_path( $path ) {
		return (
			0 === strpos( $path, '/' ) ||
			0 === strpos( $path, '\\\\' ) ||
			(bool) preg_match( '/^[A-Za-z]:[\/\\\\]/', $path )
		);
	}

	/**
	 * Validate a custom root path.
	 *
	 * @param string $path User-provided absolute path.
	 * @return string Valid absolute path, or empty string.
	 */
	private function validate_custom_root_path( $path ) {
		$path = trim( str_replace( "\0", '', (string) $path ) );

		if ( '' === $path || preg_match( '/^[a-z][a-z0-9+\-.]*:\/\//i', $path ) || ! $this->is_absolute_filesystem_path( $path ) ) {
			return '';
		}

		$resolved = realpath( $path );
		if ( false === $resolved || ! is_dir( $resolved ) || ! is_readable( $resolved ) ) {
			return '';
		}

		return $this->normalize_root_path( $resolved );
	}

	/**
	 * Check if a resolved path stays inside a root.
	 *
	 * @param string $path Path to check.
	 * @param string $root Root directory.
	 * @return bool
	 */
	private function path_is_inside_root( $path, $root ) {
		$resolved_path = realpath( $path );
		$resolved_root = realpath( $root );

		if ( false === $resolved_path || false === $resolved_root ) {
			return false;
		}

		$resolved_root = rtrim( $resolved_root, '/\\' );

		return $resolved_path === $resolved_root || 0 === strpos( $resolved_path, $resolved_root . DIRECTORY_SEPARATOR );
	}

	/**
	 * Get a path relative to a base directory.
	 *
	 * @param string $path Absolute path.
	 * @param string $base Base directory.
	 * @return string
	 */
	private function get_relative_path( $path, $base ) {
		$path = wp_normalize_path( $path );
		$base = rtrim( wp_normalize_path( $base ), '/' );

		if ( 0 === strpos( $path, $base . '/' ) ) {
			return substr( $path, strlen( $base ) + 1 );
		}

		return basename( $path );
	}

	/**
	 * Get the active wp-config.php path.
	 *
	 * @return string
	 */
	private function get_wp_config_path() {
		$paths = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);

		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$resolved = realpath( $path );
				return false !== $resolved ? $resolved : $path;
			}
		}

		return ABSPATH . 'wp-config.php';
	}

	/**
	 * Get allowed root locations for the file manager.
	 *
	 * @return array<string,array{label:string,path:string,description:string}>
	 */
	private function get_root_locations() {
		$locations = array(
			'wordpress' => array(
				'label'       => __( 'WordPress root', 'directorist-listing-tools' ),
				'path'        => $this->normalize_root_path( ABSPATH ),
				'description' => __( 'Use this for wp-config.php, .htaccess, wp-admin, and wp-includes.', 'directorist-listing-tools' ),
			),
		);

		$parent = dirname( ABSPATH );
		if ( is_dir( $parent ) && is_readable( $parent ) ) {
			$parent = $this->normalize_root_path( $parent );
			if ( $parent !== $locations['wordpress']['path'] ) {
				$locations['parent'] = array(
					'label'       => __( 'Parent of WordPress root', 'directorist-listing-tools' ),
					'path'        => $parent,
					'description' => __( 'Useful on managed hosts where wp-content or config files are stored beside the WordPress folder.', 'directorist-listing-tools' ),
				);
			}
		}

		if ( defined( 'WP_CONTENT_DIR' ) && is_dir( WP_CONTENT_DIR ) ) {
			$locations['content'] = array(
				'label'       => __( 'wp-content', 'directorist-listing-tools' ),
				'path'        => $this->normalize_root_path( WP_CONTENT_DIR ),
				'description' => __( 'Actual WP_CONTENT_DIR, useful when wp-content is outside the visible WordPress root.', 'directorist-listing-tools' ),
			);
		}

		if ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ) {
			$locations['plugins'] = array(
				'label'       => __( 'Plugins', 'directorist-listing-tools' ),
				'path'        => $this->normalize_root_path( WP_PLUGIN_DIR ),
				'description' => __( 'Installed plugin files.', 'directorist-listing-tools' ),
			);
		}

		$theme_root = get_theme_root();
		if ( is_dir( $theme_root ) ) {
			$locations['themes'] = array(
				'label'       => __( 'Themes', 'directorist-listing-tools' ),
				'path'        => $this->normalize_root_path( $theme_root ),
				'description' => __( 'Installed theme files.', 'directorist-listing-tools' ),
			);
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['basedir'] ) && is_dir( $uploads['basedir'] ) ) {
			$locations['uploads'] = array(
				'label'       => __( 'Uploads', 'directorist-listing-tools' ),
				'path'        => $this->normalize_root_path( $uploads['basedir'] ),
				'description' => __( 'Media uploads directory.', 'directorist-listing-tools' ),
			);
		}

		$config_dir = dirname( $this->get_wp_config_path() );
		if ( is_dir( $config_dir ) ) {
			$config_dir = $this->normalize_root_path( $config_dir );
			if ( $config_dir !== $locations['wordpress']['path'] ) {
				$locations['config'] = array(
					'label'       => __( 'wp-config folder', 'directorist-listing-tools' ),
					'path'        => $config_dir,
					'description' => __( 'Folder that contains wp-config.php when it is outside the WordPress root.', 'directorist-listing-tools' ),
				);
			}
		}

		$custom_root = $this->validate_custom_root_path( get_option( self::OPTION_CUSTOM_ROOT, '' ) );
		if ( '' !== $custom_root ) {
			$locations['custom'] = array(
				'label'       => __( 'Custom root', 'directorist-listing-tools' ),
				'path'        => $custom_root,
				'description' => __( 'Administrator-saved custom path for emergency support on unusual hosting layouts.', 'directorist-listing-tools' ),
			);
		}

		return $locations;
	}

	/**
	 * Get selected root key.
	 *
	 * @return string
	 */
	private function get_current_root_key() {
		$stored    = (string) get_option( self::OPTION_ROOT, 'wordpress' );
		$stored    = '' === $stored ? 'wordpress' : sanitize_key( $stored );
		$locations = $this->get_root_locations();

		if ( isset( $locations[ $stored ] ) ) {
			return $stored;
		}

		// Backward compatibility for older relative-path options.
		if ( 'wp-content' === $stored && isset( $locations['content'] ) ) {
			return 'content';
		}

		return 'wordpress';
	}

	/**
	 * Get the allowed file manager root directory (absolute path).
	 * Default: WordPress root (ABSPATH).
	 *
	 * @return string Absolute path, no trailing slash.
	 */
	public function get_root_path() {
		$locations = $this->get_root_locations();
		$key       = $this->get_current_root_key();

		if ( isset( $locations[ $key ] ) ) {
			return $locations[ $key ]['path'];
		}

		return $locations['wordpress']['path'];
	}

	/**
	 * Resolve and validate a path under the file manager root.
	 * Subpath can contain ".." and "."; they are resolved and must stay under the selected root.
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
		if ( ! $this->can_access_file_manager() ) {
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
		if ( in_array( $base, array( '.htaccess', '.user.ini', 'debug.log', 'error_log', 'php.ini', 'wp-config.php' ), true ) ) {
			return true;
		}
		$ext = strtolower( pathinfo( $abs_path, PATHINFO_EXTENSION ) );
		if ( $ext === '' ) {
			return false;
		}
		return in_array( $ext, $this->get_editable_extensions(), true );
	}

	/**
	 * Get Unix-style file permissions where available.
	 *
	 * @param string $abs_path Absolute file path.
	 * @return string
	 */
	private function get_permissions_label( $abs_path ) {
		$perms = @fileperms( $abs_path );

		if ( false === $perms ) {
			return '';
		}

		return substr( sprintf( '%o', $perms ), -4 );
	}

	/**
	 * Send a clear JSON error for a non-writable path.
	 *
	 * @param string $path Absolute path.
	 * @param string $action_label Action label.
	 * @return void
	 */
	private function send_not_writable_error( $path, $action_label ) {
		wp_send_json_error(
			array(
				'message' => sprintf(
					/* translators: 1: action label, 2: path. */
					__( 'Cannot %1$s because this path is not writable by WordPress/PHP: %2$s. Try "Fix write" first; if it still fails, the host must change ownership or permissions.', 'directorist-listing-tools' ),
					$action_label,
					$path
				),
			)
		);
	}

	/**
	 * Try to make a path writable for WordPress/PHP.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	private function make_path_writable( $path ) {
		if ( is_writable( $path ) ) {
			return true;
		}

		$modes = is_dir( $path ) ? array( 0755, 0775 ) : array( 0644, 0664 );

		foreach ( $modes as $mode ) {
			@chmod( $path, $mode );
			clearstatcache( true, $path );

			if ( is_writable( $path ) ) {
				return true;
			}
		}

		return false;
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
				'name'        => $name,
				'path'        => $rel,
				'is_dir'      => $is_dir,
				'size'        => $is_dir ? null : filesize( $full ),
				'mtime'       => filemtime( $full ),
				'readable'    => is_readable( $full ),
				'writable'    => is_writable( $full ),
				'permissions' => $this->get_permissions_label( $full ),
			);
		}
		usort( $items, function ( $a, $b ) {
			if ( $a['is_dir'] !== $b['is_dir'] ) {
				return $a['is_dir'] ? -1 : 1;
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );
		wp_send_json_success( array(
			'path'                => $subpath,
			'items'               => $items,
			'root_label'          => basename( $root ),
			'current_writable'    => is_writable( $dir ),
			'current_permissions' => $this->get_permissions_label( $dir ),
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
		$target_parent = dirname( $target );
		if ( ! is_writable( $target_parent ) ) {
			$this->send_not_writable_error( $target_parent, __( 'create folder', 'directorist-listing-tools' ) );
		}
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
		if ( ! is_writable( $parent['absolute'] ) ) {
			$this->send_not_writable_error( $parent['absolute'], __( 'create file', 'directorist-listing-tools' ) );
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
		if ( ! is_writable( $parent['absolute'] ) ) {
			$this->send_not_writable_error( $parent['absolute'], __( 'upload files', 'directorist-listing-tools' ) );
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
		if ( ! is_writable( $target_dir ) ) {
			$this->send_not_writable_error( $target_dir, __( 'upload files', 'directorist-listing-tools' ) );
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
		if ( ! is_writable( dirname( $target ) ) ) {
			$this->send_not_writable_error( dirname( $target ), __( 'delete this item', 'directorist-listing-tools' ) );
		}

		// Prefer moving to trash so accidental deletes (e.g. a working mu-plugin fix) can be restored.
		if ( class_exists( 'Directorist_Listing_Tools_Trash_Manager' )
			&& Directorist_Listing_Tools_Trash_Manager::get_instance()->move_to_trash( $target )
		) {
			wp_send_json_success( array( 'message' => __( 'Moved to trash. You can restore it from Directorist Tools → Trash.', 'directorist-listing-tools' ) ) );
		}

		// Fallback (e.g. trash unavailable on this filesystem): permanent delete, same as before.
		if ( is_dir( $target ) ) {
			$this->delete_recursive( $target );
		} else {
			if ( ! @unlink( $target ) ) {
				wp_send_json_error( array( 'message' => __( 'Could not delete file.', 'directorist-listing-tools' ) ) );
			}
		}
		wp_send_json_success( array( 'message' => __( 'Deleted permanently (trash was unavailable).', 'directorist-listing-tools' ) ) );
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
		if ( ! $this->can_access_file_manager() ) {
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
		if ( ! is_writable( $parent_abs ) ) {
			$this->send_not_writable_error( $parent_abs, __( 'rename this item', 'directorist-listing-tools' ) );
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
		if ( ! is_writable( $abs ) ) {
			$this->send_not_writable_error( $abs, __( 'save this file', 'directorist-listing-tools' ) );
		}

		if ( @file_put_contents( $abs, $content ) === false ) {
			wp_send_json_error( array( 'message' => __( 'Could not save file.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Saved.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * AJAX: Try to make a file or folder writable.
	 */
	public function ajax_fix_permissions() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$result  = $this->resolve_path( $subpath );

		if ( ! $result['valid'] || ! file_exists( $result['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'File or folder not found.', 'directorist-listing-tools' ) ) );
		}

		$target = $result['absolute'];
		if ( $this->make_path_writable( $target ) ) {
			wp_send_json_success(
				array(
					'message'     => sprintf(
						/* translators: %s: path. */
						__( 'Write permission fixed for %s.', 'directorist-listing-tools' ),
						$target
					),
					'writable'    => is_writable( $target ),
					'permissions' => $this->get_permissions_label( $target ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message'     => sprintf(
					/* translators: %s: path. */
					__( 'Could not make this path writable: %s. The server likely blocks PHP from changing ownership or permissions, so hosting/FTP/SSH access is required.', 'directorist-listing-tools' ),
					$target
				),
				'writable'    => is_writable( $target ),
				'permissions' => $this->get_permissions_label( $target ),
			)
		);
	}

	/**
	 * AJAX: Switch the file manager root to an allowed WordPress location.
	 */
	public function ajax_set_root() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$root_key  = isset( $_REQUEST['root'] ) ? sanitize_key( wp_unslash( $_REQUEST['root'] ) ) : '';
		$custom    = isset( $_REQUEST['custom_path'] ) ? (string) wp_unslash( $_REQUEST['custom_path'] ) : '';
		$locations = $this->get_root_locations();

		if ( 'custom' === $root_key && '' !== trim( $custom ) ) {
			$custom_root = $this->validate_custom_root_path( $custom );

			if ( '' === $custom_root ) {
				wp_send_json_error( array( 'message' => __( 'Custom root must be a real readable server directory path.', 'directorist-listing-tools' ) ) );
			}

			update_option( self::OPTION_CUSTOM_ROOT, $custom_root, false );
			$locations['custom'] = array(
				'label'       => __( 'Custom root', 'directorist-listing-tools' ),
				'path'        => $custom_root,
				'description' => __( 'Administrator-saved custom path for emergency support on unusual hosting layouts.', 'directorist-listing-tools' ),
			);
		}

		if ( ! isset( $locations[ $root_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid root location.', 'directorist-listing-tools' ) ) );
		}

		update_option( self::OPTION_ROOT, $root_key, false );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: root location label. */
					__( 'File manager root switched to %s.', 'directorist-listing-tools' ),
					$locations[ $root_key ]['label']
				),
				'root'    => $locations[ $root_key ],
			)
		);
	}

	/**
	 * AJAX: Set the current root's parent directory as the new custom root.
	 */
	public function ajax_set_parent_root() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$current = $this->get_root_path();
		$parent  = dirname( $current );

		if ( $parent === $current ) {
			wp_send_json_error( array( 'message' => __( 'Already at the highest available directory.', 'directorist-listing-tools' ) ) );
		}

		$parent = $this->validate_custom_root_path( $parent );
		if ( '' === $parent ) {
			wp_send_json_error( array( 'message' => __( 'Parent directory is not readable by WordPress.', 'directorist-listing-tools' ) ) );
		}

		update_option( self::OPTION_CUSTOM_ROOT, $parent, false );
		update_option( self::OPTION_ROOT, 'custom', false );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: absolute directory path. */
					__( 'File manager root moved up to %s.', 'directorist-listing-tools' ),
					$parent
				),
				'root'    => array(
					'label' => __( 'Custom root', 'directorist-listing-tools' ),
					'path'  => $parent,
				),
			)
		);
	}

	/**
	 * Scan a directory for large files and aggregate first-level folder sizes.
	 *
	 * @param string $dir Directory to scan.
	 * @param string $root Selected root.
	 * @return array<string,mixed>
	 */
	private function scan_storage_dir( $dir, $root ) {
		$max_files = 5000;
		$stack     = array( $dir );
		$files     = array();
		$folders   = array();
		$total     = 0;
		$scanned   = 0;
		$skipped   = 0;
		$truncated = false;

		while ( ! empty( $stack ) ) {
			$current = array_pop( $stack );
			$list    = @scandir( $current );

			if ( false === $list ) {
				$skipped++;
				continue;
			}

			foreach ( $list as $name ) {
				if ( '.' === $name || '..' === $name ) {
					continue;
				}

				$path = path_join( $current, $name );
				if ( is_link( $path ) || ! $this->path_is_inside_root( $path, $root ) ) {
					continue;
				}

				if ( is_dir( $path ) ) {
					$stack[] = $path;
					continue;
				}

				if ( ! is_file( $path ) ) {
					continue;
				}

				$size = filesize( $path );
				if ( false === $size ) {
					$skipped++;
					continue;
				}

				$scanned++;
				$total += $size;

				$root_relative = $this->get_relative_path( $path, $root );
				$scan_relative = $this->get_relative_path( $path, $dir );
				$parts         = preg_split( '/[\/\\\\]+/', $scan_relative );
				$folder_key    = count( $parts ) > 1 ? $parts[0] : __( '(current folder)', 'directorist-listing-tools' );

				if ( ! isset( $folders[ $folder_key ] ) ) {
					$folders[ $folder_key ] = 0;
				}

				$folders[ $folder_key ] += $size;
				$files[] = array(
					'path' => $root_relative,
					'size' => $size,
				);

				if ( $scanned >= $max_files ) {
					$truncated = true;
					break 2;
				}
			}
		}

		usort(
			$files,
			function ( $a, $b ) {
				return $b['size'] <=> $a['size'];
			}
		);

		$folder_rows = array();
		foreach ( $folders as $folder => $size ) {
			$folder_rows[] = array(
				'path' => $folder,
				'size' => $size,
			);
		}

		usort(
			$folder_rows,
			function ( $a, $b ) {
				return $b['size'] <=> $a['size'];
			}
		);

		return array(
			'total'     => $total,
			'scanned'   => $scanned,
			'skipped'   => $skipped,
			'truncated' => $truncated,
			'files'     => array_slice( $files, 0, 25 ),
			'folders'   => array_slice( $folder_rows, 0, 25 ),
		);
	}

	/**
	 * AJAX: Scan current folder for large files.
	 */
	public function ajax_scan_storage() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$subpath = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['path'] ) ) : '';
		$result  = $this->resolve_path( $subpath );

		if ( ! $result['valid'] || ! is_dir( $result['absolute'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid folder for storage scan.', 'directorist-listing-tools' ) ) );
		}

		$data = $this->scan_storage_dir( $result['absolute'], $this->get_root_path() );
		$data['message'] = __( 'Storage scan completed.', 'directorist-listing-tools' );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Delete wp-content/debug.log.
	 */
	public function ajax_clear_debug_log() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$debug_log = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';

		if ( ! file_exists( $debug_log ) ) {
			wp_send_json_success( array( 'message' => __( 'debug.log does not exist.', 'directorist-listing-tools' ) ) );
		}

		if ( ! is_file( $debug_log ) ) {
			wp_send_json_error( array( 'message' => __( 'debug.log target is not a file.', 'directorist-listing-tools' ) ) );
		}

		if ( ! is_writable( $debug_log ) ) {
			wp_send_json_error( array( 'message' => __( 'debug.log is not writable by WordPress.', 'directorist-listing-tools' ) ) );
		}

		if ( ! @unlink( $debug_log ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete debug.log.', 'directorist-listing-tools' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'debug.log deleted.', 'directorist-listing-tools' ) ) );
	}

	/**
	 * Upsert one wp-config.php line.
	 *
	 * @param string $content Config content.
	 * @param string $pattern Existing-line regex.
	 * @param string $line Replacement line.
	 * @param array  $missing Missing lines.
	 * @return string
	 */
	private function upsert_wp_config_line( $content, $pattern, $line, &$missing ) {
		$count   = 0;
		$updated = preg_replace( $pattern, $line, $content, 1, $count );

		if ( null !== $updated && $count > 0 ) {
			return $updated;
		}

		$missing[] = $line;
		return $content;
	}

	/**
	 * Insert missing wp-config.php debug lines before WordPress boots.
	 *
	 * @param string $content Config content.
	 * @param array  $missing Missing lines.
	 * @return string
	 */
	private function insert_missing_wp_config_lines( $content, $missing ) {
		if ( empty( $missing ) ) {
			return $content;
		}

		$block   = "\n// Added by Directorist Listing Tools for temporary debugging.\n" . implode( "\n", $missing ) . "\n";
		$markers = array(
			'/\/\*\s*That\'s all,\s*stop editing!.*?\*\//i',
			'/require_once\s+ABSPATH\s*\.\s*[\'"]wp-settings\.php[\'"]\s*;/',
		);

		foreach ( $markers as $marker ) {
			if ( preg_match( $marker, $content ) ) {
				return preg_replace( $marker, $block . "\n$0", $content, 1 );
			}
		}

		return rtrim( $content ) . $block;
	}

	/**
	 * AJAX: Update wp-config.php debug constants.
	 */
	public function ajax_set_wp_debug() {
		$this->check_ajax_auth();
		$this->check_modify_permission();

		$mode = isset( $_REQUEST['mode'] ) ? sanitize_key( wp_unslash( $_REQUEST['mode'] ) ) : 'log';
		if ( ! in_array( $mode, array( 'log', 'display', 'off' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid debug mode.', 'directorist-listing-tools' ) ) );
		}

		$config_path = $this->get_wp_config_path();
		if ( ! is_file( $config_path ) ) {
			wp_send_json_error( array( 'message' => __( 'wp-config.php was not found.', 'directorist-listing-tools' ) ) );
		}

		if ( ! is_writable( $config_path ) ) {
			wp_send_json_error( array( 'message' => __( 'wp-config.php is not writable by WordPress.', 'directorist-listing-tools' ) ) );
		}

		$content = file_get_contents( $config_path );
		if ( false === $content ) {
			wp_send_json_error( array( 'message' => __( 'Could not read wp-config.php.', 'directorist-listing-tools' ) ) );
		}

		$debug_value   = 'off' === $mode ? 'false' : 'true';
		$log_value     = 'off' === $mode ? 'false' : 'true';
		$display_value = 'display' === $mode ? 'true' : 'false';
		$ini_value     = 'display' === $mode ? '1' : '0';
		$missing       = array();

		$content = $this->upsert_wp_config_line( $content, '/define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,\s*[^;]+?\)\s*;/', "define( 'WP_DEBUG', {$debug_value} );", $missing );
		$content = $this->upsert_wp_config_line( $content, '/define\s*\(\s*[\'"]WP_DEBUG_LOG[\'"]\s*,\s*[^;]+?\)\s*;/', "define( 'WP_DEBUG_LOG', {$log_value} );", $missing );
		$content = $this->upsert_wp_config_line( $content, '/define\s*\(\s*[\'"]WP_DEBUG_DISPLAY[\'"]\s*,\s*[^;]+?\)\s*;/', "define( 'WP_DEBUG_DISPLAY', {$display_value} );", $missing );
		$content = $this->upsert_wp_config_line( $content, '/@?ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[^)]+?\)\s*;/', "@ini_set( 'display_errors', {$ini_value} );", $missing );
		$content = $this->insert_missing_wp_config_lines( $content, $missing );

		$backup = $config_path . '.bak-dlt-' . gmdate( 'Ymd-His' );
		if ( ! @copy( $config_path, $backup ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create a wp-config.php backup, so it was not changed.', 'directorist-listing-tools' ) ) );
		}

		if ( false === file_put_contents( $config_path, $content, LOCK_EX ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not write wp-config.php.', 'directorist-listing-tools' ) ) );
		}

		$messages = array(
			'log'     => __( 'Debug logging enabled. Errors will be written to wp-content/debug.log and hidden from visitors.', 'directorist-listing-tools' ),
			'display' => __( 'Temporary debug display enabled. Turn it off after reproducing the issue.', 'directorist-listing-tools' ),
			'off'     => __( 'Debug mode disabled in wp-config.php.', 'directorist-listing-tools' ),
		);

		wp_send_json_success(
			array(
				'message' => $messages[ $mode ],
				'backup'  => $backup,
			)
		);
	}

	/**
	 * Format a byte count as a human-readable string.
	 *
	 * @param int $bytes Byte count.
	 * @return string
	 */
	private function format_bytes( $bytes ) {
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1024 * 1024 ) {
			return number_format_i18n( $bytes / 1024, 1 ) . ' KB';
		}
		return number_format_i18n( $bytes / ( 1024 * 1024 ), 1 ) . ' MB';
	}

	/**
	 * Get current debug-related wp-config constants for display.
	 *
	 * @return array<string,null|string>
	 */
	private function get_debug_constants_status() {
		return array(
			'WP_DEBUG'         => defined( 'WP_DEBUG' ) ? ( WP_DEBUG ? 'true' : 'false' ) : null,
			'WP_DEBUG_LOG'     => defined( 'WP_DEBUG_LOG' ) ? ( WP_DEBUG_LOG ? 'true' : 'false' ) : null,
			'WP_DEBUG_DISPLAY' => defined( 'WP_DEBUG_DISPLAY' ) ? ( WP_DEBUG_DISPLAY ? 'true' : 'false' ) : null,
		);
	}

	/**
	 * Render the File Manager admin page.
	 */
	public function render_page() {
		$root               = $this->get_root_path();
		$nonce              = wp_create_nonce( self::NONCE_ACTION );
		$root_locations     = $this->get_root_locations();
		$current_root_key   = $this->get_current_root_key();
		$config_path        = $this->get_wp_config_path();
		$debug_log          = trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
		$debug_log_exists   = is_file( $debug_log );
		$debug_log_size     = $debug_log_exists ? $this->format_bytes( filesize( $debug_log ) ) : '';
		$debug_constants    = $this->get_debug_constants_status();
		$display_errors_ini = ini_get( 'display_errors' );
		$custom_root        = (string) get_option( self::OPTION_CUSTOM_ROOT, '' );
		?>
		<div class="wrap dlt-file-manager-wrap">
			<h2><?php esc_html_e( 'File Managing', 'directorist-listing-tools' ); ?></h2>
			<p class="description">
				<?php echo esc_html( sprintf( __( 'Root directory: %s', 'directorist-listing-tools' ), $root ) ); ?>
			</p>
			<div class="dlt-fm-admin-panels">
				<div class="dlt-fm-admin-panel">
					<h3><?php esc_html_e( 'File Manager Root', 'directorist-listing-tools' ); ?></h3>
					<p>
						<?php esc_html_e( 'Switch to the real wp-content path even when it is stored outside the visible WordPress root.', 'directorist-listing-tools' ); ?>
					</p>
					<div class="dlt-fm-root-control">
						<select id="dlt-fm-root-select">
							<?php foreach ( $root_locations as $root_key => $location ) : ?>
								<option value="<?php echo esc_attr( $root_key ); ?>" <?php selected( $current_root_key, $root_key ); ?>>
									<?php echo esc_html( $location['label'] . ' - ' . $location['path'] ); ?>
								</option>
							<?php endforeach; ?>
							<?php if ( ! isset( $root_locations['custom'] ) ) : ?>
								<option value="custom"><?php esc_html_e( 'Custom root - enter path below', 'directorist-listing-tools' ); ?></option>
							<?php endif; ?>
						</select>
						<button type="button" class="button button-primary dlt-fm-set-root"><?php esc_html_e( 'Switch root', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button dlt-fm-parent-root"><?php esc_html_e( 'Set parent as root', 'directorist-listing-tools' ); ?></button>
					</div>
					<div class="dlt-fm-custom-root-control">
						<label for="dlt-fm-custom-root"><?php esc_html_e( 'Custom server path', 'directorist-listing-tools' ); ?></label>
						<input type="text" id="dlt-fm-custom-root" class="regular-text" value="<?php echo esc_attr( $custom_root ); ?>" placeholder="/home/site/public_html or /bitnami/wordpress/wp-content">
					</div>
					<div class="dlt-fm-root-tools">
						<button type="button" class="button dlt-fm-scan-storage"><?php esc_html_e( 'Scan current folder size', 'directorist-listing-tools' ); ?></button>
					</div>
				</div>
				<div class="dlt-fm-admin-panel dlt-fm-admin-panel--warning">
					<h3><?php esc_html_e( 'Debug Maintenance', 'directorist-listing-tools' ); ?></h3>
					<p>
						<?php esc_html_e( 'Use logging mode on live sites. Display mode is only for short reproduction windows.', 'directorist-listing-tools' ); ?>
					</p>
					<ul class="dlt-fm-debug-paths">
						<li><strong><?php esc_html_e( 'wp-config.php:', 'directorist-listing-tools' ); ?></strong> <code><?php echo esc_html( $config_path ); ?></code></li>
						<li>
							<strong><?php esc_html_e( 'debug.log:', 'directorist-listing-tools' ); ?></strong> <code><?php echo esc_html( $debug_log ); ?></code>
							<?php if ( $debug_log_exists ) : ?>
								<span class="dlt-fm-badge dlt-fm-badge--neutral"><?php echo esc_html( $debug_log_size ); ?></span>
							<?php else : ?>
								<span class="dlt-fm-badge dlt-fm-badge--muted"><?php esc_html_e( 'Not present', 'directorist-listing-tools' ); ?></span>
							<?php endif; ?>
						</li>
					</ul>
					<ul class="dlt-fm-debug-constants">
						<?php foreach ( $debug_constants as $const_name => $const_value ) : ?>
							<li>
								<strong><?php echo esc_html( $const_name ); ?>:</strong>
								<?php if ( null === $const_value ) : ?>
									<span class="dlt-fm-badge dlt-fm-badge--muted"><?php esc_html_e( 'Not defined', 'directorist-listing-tools' ); ?></span>
								<?php else : ?>
									<span class="dlt-fm-badge <?php echo 'true' === $const_value ? 'dlt-fm-badge--on' : 'dlt-fm-badge--off'; ?>"><?php echo esc_html( $const_value ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						<li>
							<strong><?php esc_html_e( 'display_errors (ini):', 'directorist-listing-tools' ); ?></strong>
							<span class="dlt-fm-badge <?php echo $display_errors_ini ? 'dlt-fm-badge--on' : 'dlt-fm-badge--off'; ?>"><?php echo esc_html( $display_errors_ini ? $display_errors_ini : '0' ); ?></span>
						</li>
					</ul>
					<div class="dlt-fm-maintenance-actions">
						<button type="button" class="button dlt-fm-clear-debug-log"><?php esc_html_e( 'Delete debug.log', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button button-primary dlt-fm-debug-log-on"><?php esc_html_e( 'Enable debug log', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button dlt-fm-debug-display-on"><?php esc_html_e( 'Enable display temporarily', 'directorist-listing-tools' ); ?></button>
						<button type="button" class="button dlt-fm-debug-off"><?php esc_html_e( 'Disable debug', 'directorist-listing-tools' ); ?></button>
					</div>
				</div>
			</div>
			<div class="dlt-fm-toolbar">
				<div class="dlt-fm-breadcrumb" aria-label="<?php esc_attr_e( 'Current folder', 'directorist-listing-tools' ); ?>"></div>
				<div class="dlt-fm-write-status" aria-live="polite"></div>
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
				<div class="dlt-fm-list-header" aria-hidden="true">
					<span class="dlt-fm-list-header-icon"></span>
					<span class="dlt-fm-list-header-name"><?php esc_html_e( 'Name', 'directorist-listing-tools' ); ?></span>
					<span class="dlt-fm-list-header-size"><?php esc_html_e( 'Size', 'directorist-listing-tools' ); ?></span>
					<span class="dlt-fm-list-header-mtime"><?php esc_html_e( 'Modified', 'directorist-listing-tools' ); ?></span>
					<span class="dlt-fm-list-header-permissions"><?php esc_html_e( 'Permissions', 'directorist-listing-tools' ); ?></span>
					<span class="dlt-fm-list-header-actions"><?php esc_html_e( 'Actions', 'directorist-listing-tools' ); ?></span>
				</div>
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
