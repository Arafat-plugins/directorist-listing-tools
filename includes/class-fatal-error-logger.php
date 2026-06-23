<?php
/**
 * Fatal Error Logger — captures PHP fatal errors on front-end requests so white-screen
 * crashes (e.g. Directorist single listing pages) can be diagnosed without needing
 * server-level error log access.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DLT_FATAL_LOG_ENABLED_OPTION', 'dlt_fatal_logger_enabled' );
define( 'DLT_FATAL_LOG_DATA_OPTION', 'dlt_fatal_logger_log' );
define( 'DLT_FATAL_LOG_MAX_ENTRIES', 100 );

/**
 * Class Directorist_Listing_Tools_Fatal_Error_Logger
 */
class Directorist_Listing_Tools_Fatal_Error_Logger {

	/** @var Directorist_Listing_Tools_Fatal_Error_Logger|null */
	private static $instance = null;

	/**
	 * @return Directorist_Listing_Tools_Fatal_Error_Logger
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
		add_action( 'admin_post_dlt_save_fatal_logger_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_dlt_download_fatal_log', array( $this, 'handle_download_log' ) );
		add_action( 'admin_post_dlt_clear_fatal_log', array( $this, 'handle_clear_log' ) );

		// Only hook the catcher on front-end requests, and only when enabled.
		if ( ! is_admin() && self::is_enabled() ) {
			register_shutdown_function( array( $this, 'capture_fatal' ) );
		}
	}

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( DLT_FATAL_LOG_ENABLED_OPTION, false );
	}

	/**
	 * Shutdown handler — runs at the end of every front-end request when enabled.
	 * Only writes a log entry if the request actually ended in a fatal error.
	 */
	public function capture_fatal() {
		$error = error_get_last();

		if ( ! $error ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );

		if ( ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}

		$log = get_option( DLT_FATAL_LOG_DATA_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'time'        => current_time( 'mysql' ),
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'type'        => $error['type'],
			'message'     => $error['message'],
			'file'        => str_replace( ABSPATH, '', (string) $error['file'] ),
			'line'        => $error['line'],
		);

		if ( count( $log ) > DLT_FATAL_LOG_MAX_ENTRIES ) {
			$log = array_slice( $log, -1 * DLT_FATAL_LOG_MAX_ENTRIES );
		}

		update_option( DLT_FATAL_LOG_DATA_OPTION, $log, false );
	}

	/**
	 * Save the enable/disable toggle.
	 */
	public function handle_save_settings() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}
		check_admin_referer( 'dlt_save_fatal_logger_settings' );

		$enabled = ! empty( $_POST['dlt_fatal_logger_enabled'] );
		update_option( DLT_FATAL_LOG_ENABLED_OPTION, $enabled );

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . dlt_get_post_type() . '&page=directorist-listing-tools-fatal-error-log&dlt_fel_saved=1' )
		);
		exit;
	}

	/**
	 * Stream the log as a downloadable, pretty-printed JSON file.
	 */
	public function handle_download_log() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}
		check_admin_referer( 'dlt_download_fatal_log' );

		$log = get_option( DLT_FATAL_LOG_DATA_OPTION, array() );

		$report = array(
			'generated_at' => current_time( 'mysql' ),
			'site_url'     => home_url(),
			'fatal_log'    => is_array( $log ) ? $log : array(),
		);

		$json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="dlt-fatal-error-log-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Clear the stored log.
	 */
	public function handle_clear_log() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}
		check_admin_referer( 'dlt_clear_fatal_log' );

		delete_option( DLT_FATAL_LOG_DATA_OPTION );

		wp_safe_redirect(
			admin_url( 'edit.php?post_type=' . dlt_get_post_type() . '&page=directorist-listing-tools-fatal-error-log&dlt_fel_cleared=1' )
		);
		exit;
	}

	/**
	 * Admin page output.
	 */
	public function render_page() {
		if ( ! dlt_current_user_can() ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'directorist-listing-tools' ) );
		}

		dlt_render_main_settings_tabs();

		if ( ! empty( $_GET['dlt_fel_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'directorist-listing-tools' ) . '</p></div>';
		}
		if ( ! empty( $_GET['dlt_fel_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Log cleared.', 'directorist-listing-tools' ) . '</p></div>';
		}

		$enabled  = self::is_enabled();
		$log      = get_option( DLT_FATAL_LOG_DATA_OPTION, array() );
		$log      = is_array( $log ) ? $log : array();
		$count    = count( $log );
		$recent   = array_slice( $log, -10 );
		$form_act = admin_url( 'admin-post.php' );
		?>
		<div class="wrap dlt-fatal-logger-wrap">
			<h2 class="screen-reader-text"><?php esc_html_e( 'Fatal Error Log', 'directorist-listing-tools' ); ?></h2>

			<p class="description">
				<?php esc_html_e( 'Captures PHP fatal errors on front-end requests (file, line, message, URL) without needing server-level error log access. Useful for diagnosing white-screen crashes.', 'directorist-listing-tools' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( $form_act ); ?>" style="margin: 16px 0;">
				<input type="hidden" name="action" value="dlt_save_fatal_logger_settings" />
				<?php wp_nonce_field( 'dlt_save_fatal_logger_settings' ); ?>
				<label class="dlt-ds-toggle" style="display:inline-flex;align-items:center;gap:8px;">
					<input class="dlt-ds-toggle-input" type="checkbox" name="dlt_fatal_logger_enabled" value="1" <?php checked( $enabled ); ?> />
					<span class="dlt-ds-toggle-slider" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Enable fatal error logging', 'directorist-listing-tools' ); ?></strong>
				</label>
				<?php submit_button( __( 'Save', 'directorist-listing-tools' ), 'primary', 'submit', false ); ?>
			</form>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of logged entries */
						__( 'Logged fatal errors: %d', 'directorist-listing-tools' ),
						$count
					)
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( $form_act ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="dlt_download_fatal_log" />
				<?php wp_nonce_field( 'dlt_download_fatal_log' ); ?>
				<?php submit_button( __( 'Download Log JSON', 'directorist-listing-tools' ), 'primary', 'submit', false ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( $form_act ); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear the log?', 'directorist-listing-tools' ) ); ?>');">
				<input type="hidden" name="action" value="dlt_clear_fatal_log" />
				<?php wp_nonce_field( 'dlt_clear_fatal_log' ); ?>
				<?php submit_button( __( 'Clear Log', 'directorist-listing-tools' ), 'delete', 'submit', false ); ?>
			</form>

			<?php if ( ! empty( $recent ) ) : ?>
				<h3 style="margin-top:24px;"><?php esc_html_e( 'Most recent entries (newest first)', 'directorist-listing-tools' ); ?></h3>
				<pre style="background:#fff;border:1px solid #c3c4c7;padding:12px;max-height:500px;overflow:auto;"><?php echo esc_html( wp_json_encode( array_reverse( $recent ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}
}
