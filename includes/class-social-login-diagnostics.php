<?php
/**
 * Social Login Credential Diagnostics
 *
 * Real-time OAuth flow tests for Directorist Social Login credentials.
 * Tests hit the actual Google and Facebook OAuth dialog endpoints with the
 * configured credentials and parse the responses to detect real errors.
 *
 * @package DirectoristListingTools
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social Login diagnostics handler.
 */
class Directorist_Listing_Tools_Social_Login_Diagnostics {

	const AJAX_ACTION_CHECK = 'dlt_social_login_credentials_check';
	const NONCE_ACTION      = 'dlt_social_login_check_nonce';

	/** @var Directorist_Listing_Tools_Social_Login_Diagnostics */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION_CHECK, array( $this, 'ajax_run_credentials_check' ) );
	}

	/**
	 * Get Directorist option (works with or without Directorist helper).
	 */
	private function get_directorist_option( $key, $default = null ) {
		if ( function_exists( 'get_directorist_option' ) ) {
			return get_directorist_option( $key, $default );
		}
		$options = (array) get_option( 'atbdp_option', array() );
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Run real-time OAuth flow tests for Google and Facebook.
	 *
	 * @return array
	 */
	public function run_credentials_check() {
		$google_api = trim( (string) $this->get_directorist_option( 'google_api', '' ) );
		$fb_app_id  = trim( (string) $this->get_directorist_option( 'atbdp_fb_app_id', '' ) );
		$enable     = (bool) $this->get_directorist_option( 'enable_social_login', true );

		return array(
			'enable_social_login' => $enable,
			'google'              => $this->check_google( $google_api ),
			'facebook'            => $this->check_facebook( $fb_app_id ),
		);
	}

	// ──────────────────────────────────────────────────────────────
	// GOOGLE
	// ──────────────────────────────────────────────────────────────

	/**
	 * Real Google Client ID test via the OAuth 2.0 Token endpoint.
	 *
	 * Strategy: POST an intentionally invalid `code` to the token exchange endpoint
	 * with the real Client ID. Google's response is always JSON with an `error` field:
	 *
	 *  - "invalid_client"  → Client ID does not exist in Google Cloud Console (or was deleted).
	 *  - "invalid_grant"   → Client ID IS valid; the fake code was rejected as expected.
	 *  - "redirect_uri_mismatch" → Client ID IS valid; the redirect URI isn't whitelisted.
	 *  - "unauthorized_client" → Client ID valid but app type misconfigured.
	 *
	 * This is 100 % reliable because the token endpoint always returns JSON and
	 * `invalid_client` is ONLY returned when the client_id itself does not exist.
	 *
	 * @param string $client_id
	 * @return array
	 */
	private function check_google( $client_id ) {
		if ( $client_id === '' ) {
			return $this->result( false, 'not_configured',
				__( 'Google Client ID is not set.', 'directorist-listing-tools' ),
				__( 'Go to Directorist → Extension Settings → Social Login and enter your Google Client ID.', 'directorist-listing-tools' )
			);
		}

		if ( ! preg_match( '/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/i', $client_id ) ) {
			return $this->result( false, 'bad_format',
				__( 'Google Client ID format is wrong.', 'directorist-listing-tools' ),
				__( 'It must look like: 123456789012-abcdef.apps.googleusercontent.com — copy it exactly from Google Cloud Console → APIs & Services → Credentials.', 'directorist-listing-tools' )
			);
		}

		// Use the same redirect_uri the plugin registers (dashboard/?directorist-google-login).
		if ( class_exists( 'ATBDP_Permalink' ) && method_exists( 'ATBDP_Permalink', 'get_dashboard_page_link' ) ) {
			$redirect_uri = add_query_arg( 'directorist-google-login', '', ATBDP_Permalink::get_dashboard_page_link() );
		} else {
			$redirect_uri = home_url( '/' );
		}

		// POST to the token endpoint with an intentionally invalid code.
		// The only thing that determines `invalid_client` vs `invalid_grant` is
		// whether the client_id exists — not whether our code/secret are correct.
		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout'   => 15,
			'sslverify' => true,
			'body'      => array(
				'grant_type'    => 'authorization_code',
				'client_id'     => $client_id,
				'client_secret' => 'dlt_test_intentionally_invalid',
				'code'          => 'dlt_test_intentionally_invalid_code',
				'redirect_uri'  => $redirect_uri,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $this->result( false, 'network_error',
				__( 'Could not reach Google servers.', 'directorist-listing-tools' ),
				$response->get_error_message()
			);
		}

		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$error = isset( $body['error'] ) ? (string) $body['error'] : '';
		$desc  = isset( $body['error_description'] ) ? (string) $body['error_description'] : '';

		// ── Client ID does not exist ──────────────────────────────────────────────
		if ( $error === 'invalid_client' ) {
			return $this->result( false, 'invalid_client',
				__( 'Google rejected this Client ID — it does not exist.', 'directorist-listing-tools' ),
				sprintf(
					/* translators: 1: error description from Google, 2: link hint */
					__( 'Google says: "%1$s". The Client ID was deleted or never existed. Copy the correct one from Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client IDs.', 'directorist-listing-tools' ),
					$desc ?: 'The OAuth client was not found.'
				)
			);
		}

		// ── Client ID is valid — the fake-code/secret error is the expected result ─
		if ( $error === 'invalid_grant' || $error === 'redirect_uri_mismatch' || $error === 'unauthorized_client' ) {

			// Check whether the site origin is added to Authorized JavaScript Origins.
			// (For the GIS "one-tap" button this is what actually matters.)
			$site_origin = home_url( '/', 'https' );
			if ( $error === 'redirect_uri_mismatch' ) {
				return $this->result( 'warning', 'uri_mismatch',
					__( 'Client ID is valid but the login URI / JavaScript origin is not authorised.', 'directorist-listing-tools' ),
					sprintf(
						__( 'The Client ID exists but Google returned "redirect_uri_mismatch". Fix in Google Cloud Console → APIs & Services → Credentials → your OAuth 2.0 Client ID:%1$s1. Add "%2$s" to Authorised redirect URIs.%1$s2. Add "%3$s" to Authorised JavaScript origins.', 'directorist-listing-tools' ),
						"\n",
						esc_url( $redirect_uri ),
						esc_url( $site_origin )
					)
				);
			}

			return $this->result( true, 'ok',
				__( 'Google Client ID is valid and recognised by Google.', 'directorist-listing-tools' ),
				sprintf(
					__( 'The Client ID exists (Google returned "%1$s" for the test request, which confirms the Client ID is real). Also ensure "%2$s" is in Authorised JavaScript origins in Google Cloud Console.', 'directorist-listing-tools' ),
					$error,
					esc_url( $site_origin )
				)
			);
		}

		// Completely unexpected response — show raw details.
		return $this->result( false, 'unknown',
			__( 'Google returned an unexpected response.', 'directorist-listing-tools' ),
			sprintf( __( 'error="%1$s" description="%2$s"', 'directorist-listing-tools' ), $error, $desc )
		);
	}

	// ──────────────────────────────────────────────────────────────
	// FACEBOOK
	// ──────────────────────────────────────────────────────────────

	/**
	 * Real Facebook App ID test via the Graph API App Token endpoint.
	 *
	 * Strategy: request a client_credentials token with the real App ID but an
	 * intentionally wrong App Secret. Facebook's JSON response tells us:
	 *
	 *  - Error code 101  → "The application does not exist" — App ID is invalid.
	 *  - Error code 1    → "Invalid client_secret" — App ID EXISTS (wrong secret expected).
	 *  - Error code 200+ → App ID exists but has access/permission issues.
	 *
	 * After confirming the App ID exists we do a second check on the public graph
	 * endpoint to detect Development-mode apps (they are not publicly accessible).
	 *
	 * @param string $app_id
	 * @return array
	 */
	private function check_facebook( $app_id ) {
		if ( $app_id === '' ) {
			return $this->result( false, 'not_configured',
				__( 'Facebook App ID is not set.', 'directorist-listing-tools' ),
				__( 'Go to Directorist → Extension Settings → Social Login and enter your Facebook App ID.', 'directorist-listing-tools' )
			);
		}

		if ( ! preg_match( '/^[0-9]+$/', $app_id ) ) {
			return $this->result( false, 'bad_format',
				__( 'Facebook App ID must be numeric.', 'directorist-listing-tools' ),
				__( 'Get it from developers.facebook.com → Your Apps → select your app → Dashboard → App ID.', 'directorist-listing-tools' )
			);
		}

		// ── Step 1: Token endpoint — JSON, deterministic error codes ─────────────
		// We intentionally send a wrong secret. The error code in the response tells us
		// whether the App ID itself is valid or not.
		$token_url = add_query_arg( array(
			'client_id'     => $app_id,
			'client_secret' => 'dlt_test_intentionally_invalid_secret',
			'grant_type'    => 'client_credentials',
		), 'https://graph.facebook.com/oauth/access_token' );

		$tok_response = wp_remote_get( $token_url, array(
			'timeout'   => 15,
			'sslverify' => true,
		) );

		if ( is_wp_error( $tok_response ) ) {
			return $this->result( false, 'network_error',
				__( 'Could not reach Facebook servers.', 'directorist-listing-tools' ),
				$tok_response->get_error_message()
			);
		}

		$tok_body  = json_decode( wp_remote_retrieve_body( $tok_response ), true );
		$fb_code   = isset( $tok_body['error']['code'] ) ? (int) $tok_body['error']['code'] : 0;
		$fb_msg    = isset( $tok_body['error']['message'] ) ? (string) $tok_body['error']['message'] : '';
		$fb_type   = isset( $tok_body['error']['type'] ) ? (string) $tok_body['error']['type'] : '';

		// Error code 101 = "The application does not exist" — App ID is invalid.
		// Also catch slightly different phrasings Facebook occasionally uses.
		if (
			$fb_code === 101
			|| strpos( strtolower( $fb_msg ), 'does not exist' ) !== false
			|| strpos( strtolower( $fb_msg ), 'invalid app id' ) !== false
			|| strpos( strtolower( $fb_msg ), 'application does not exist' ) !== false
		) {
			return $this->result( false, 'invalid_app',
				__( 'Facebook says: this App ID does not exist.', 'directorist-listing-tools' ),
				sprintf(
					__( 'Facebook error: "%1$s" (code %2$d). Get the correct App ID from developers.facebook.com → Your Apps.', 'directorist-listing-tools' ),
					$fb_msg ?: 'The application does not exist.',
					$fb_code
				)
			);
		}

		// Any other error (code 1 = "Invalid client_secret", etc.) means the App ID EXISTS.
		// We got the expected "wrong secret" error — the App ID is real.

		// ── Step 2: Public Graph API — detect Development-mode apps ──────────────
		// Live/public apps return their name; Development-mode apps are private
		// and the Graph API returns an access-permissions error.
		$pub_url  = add_query_arg( array( 'fields' => 'id,name' ), 'https://graph.facebook.com/v18.0/' . rawurlencode( $app_id ) );
		$pub_resp = wp_remote_get( $pub_url, array( 'timeout' => 10, 'sslverify' => true ) );

		if ( ! is_wp_error( $pub_resp ) ) {
			$pub_body = json_decode( wp_remote_retrieve_body( $pub_resp ), true );
			$pub_code = isset( $pub_body['error']['code'] ) ? (int) $pub_body['error']['code'] : 0;
			$pub_msg  = isset( $pub_body['error']['message'] ) ? (string) $pub_body['error']['message'] : '';

			// App ID matches and name returned = Live public app.
			if ( ! empty( $pub_body['id'] ) && (string) $pub_body['id'] === (string) $app_id ) {
				$app_name = isset( $pub_body['name'] ) ? $pub_body['name'] : '';
				return $this->result( true, 'ok',
					__( 'Facebook App ID is valid and the app is Live.', 'directorist-listing-tools' ),
					sprintf(
						__( 'App name: "%1$s". Ensure your site domain "%2$s" is listed in App Domains (developers.facebook.com → Your App → Settings → Basic).', 'directorist-listing-tools' ),
						$app_name,
						(string) wp_parse_url( home_url(), PHP_URL_HOST )
					)
				);
			}

			// Code 190 / 200 / permissions-type errors usually mean the app exists
			// but is in Development mode (not accessible without being an app tester).
			if (
				$pub_code === 190
				|| strpos( strtolower( $pub_msg ), 'development' ) !== false
				|| strpos( strtolower( $pub_msg ), 'not set up' ) !== false
				|| strpos( strtolower( $pub_msg ), 'permission' ) !== false
			) {
				return $this->result( false, 'dev_mode',
					__( 'Facebook App ID is valid but the app is in Development mode.', 'directorist-listing-tools' ),
					sprintf(
						__( 'Facebook returned: "%1$s". Only app admins and testers can log in when the app is in Development mode. Switch to Live: developers.facebook.com → Your App → top-right toggle "In development" → Live.', 'directorist-listing-tools' ),
						$pub_msg ?: 'App is not publicly accessible.'
					)
				);
			}
		}

		// App ID is confirmed valid (step 1), public check inconclusive.
		return $this->result( 'warning', 'app_id_valid',
			__( 'Facebook App ID exists but app status could not be confirmed.', 'directorist-listing-tools' ),
			sprintf(
				__( 'The App ID is real (Facebook confirmed it). If the login button is not working, check that the app is set to Live mode and that "%s" is in your App Domains.', 'directorist-listing-tools' ),
				(string) wp_parse_url( home_url(), PHP_URL_HOST )
			)
		);
	}

	// ──────────────────────────────────────────────────────────────
	// HELPERS
	// ──────────────────────────────────────────────────────────────

	/**
	 * Build a standardised result array.
	 * $ok can be true | false | 'warning'.
	 *
	 * @param bool|string $ok
	 * @param string      $status
	 * @param string      $message
	 * @param string      $detail
	 * @return array
	 */
	private function result( $ok, $status, $message, $detail ) {
		return array(
			'ok'      => $ok,
			'status'  => $status,
			'message' => $message,
			'detail'  => $detail,
		);
	}

	// ──────────────────────────────────────────────────────────────
	// AJAX
	// ──────────────────────────────────────────────────────────────

	public function ajax_run_credentials_check() {
		if ( ! dlt_current_user_can() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'directorist-listing-tools' ) ), 403 );
		}
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'directorist-listing-tools' ) ), 403 );
		}
		if ( ! dlt_is_social_login_active() ) {
			wp_send_json_error( array( 'message' => __( 'Social Login extension is not active.', 'directorist-listing-tools' ) ), 400 );
		}

		$results = $this->run_credentials_check();
		wp_send_json_success( $results );
	}

	// ──────────────────────────────────────────────────────────────
	// PAGE RENDER
	// ──────────────────────────────────────────────────────────────

	public function render_page() {
		$nonce         = wp_create_nonce( self::NONCE_ACTION );
		$google_stored = trim( (string) $this->get_directorist_option( 'google_api', '' ) );
		$fb_stored     = trim( (string) $this->get_directorist_option( 'atbdp_fb_app_id', '' ) );
		?>
		<div class="wrap dlt-social-login-diagnostics-wrap">
			<h2><?php esc_html_e( 'Social Login Credential Check', 'directorist-listing-tools' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Performs a real OAuth flow test against Google and Facebook with your stored credentials, and reports exactly what is wrong.', 'directorist-listing-tools' ); ?>
			</p>

			<div class="dlt-sld-configured-values">
				<table class="widefat dlt-sld-config-table" style="max-width:700px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Setting', 'directorist-listing-tools' ); ?></th>
							<th><?php esc_html_e( 'Stored value', 'directorist-listing-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Google Client ID', 'directorist-listing-tools' ); ?></strong></td>
							<td>
								<?php if ( $google_stored ) : ?>
									<code><?php echo esc_html( $google_stored ); ?></code>
								<?php else : ?>
									<em class="dlt-sld-empty"><?php esc_html_e( '(not set)', 'directorist-listing-tools' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Facebook App ID', 'directorist-listing-tools' ); ?></strong></td>
							<td>
								<?php if ( $fb_stored ) : ?>
									<code><?php echo esc_html( $fb_stored ); ?></code>
								<?php else : ?>
									<em class="dlt-sld-empty"><?php esc_html_e( '(not set)', 'directorist-listing-tools' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="dlt-sld-actions" style="margin-top:16px;">
				<button type="button" class="button button-primary" id="dlt-sld-run-check">
					<span class="dashicons dashicons-update dlt-sld-spin-on-loading"></span>
					<?php esc_html_e( 'Run live credential check', 'directorist-listing-tools' ); ?>
				</button>
				<span class="spinner dlt-sld-spinner"></span>
			</div>

			<div id="dlt-sld-results" class="dlt-sld-results" style="display:none;margin-top:18px;"></div>
		</div>

		<script type="application/json" id="dlt-sld-config"><?php echo wp_json_encode( array( 'nonce' => $nonce, 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) ); ?></script>
		<script>
		(function($){
			var config = {};

			function init() {
				var el = document.getElementById('dlt-sld-config');
				if ( el && el.textContent ) {
					try { config = JSON.parse(el.textContent); } catch(e) {}
				}
			}

			function esc(s) {
				if (s == null) return '';
				return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			function runCheck() {
				if ( !config.ajaxUrl ) { init(); }
				var $btn     = $('#dlt-sld-run-check');
				var $spinner = $('.dlt-sld-spinner');
				var $results = $('#dlt-sld-results');

				$btn.prop('disabled', true);
				$spinner.addClass('is-active');
				$results.hide().empty();

				$.post(config.ajaxUrl, {
					action: 'dlt_social_login_credentials_check',
					nonce:  config.nonce
				})
				.done(function(res) {
					if ( res.success && res.data ) {
						renderResults(res.data);
					} else {
						$results.html('<div class="dlt-sld-error"><span class="dashicons dashicons-warning"></span> ' + esc(res.data && res.data.message ? res.data.message : 'Check failed.') + '</div>').show();
					}
				})
				.fail(function() {
					$results.html('<div class="dlt-sld-error"><span class="dashicons dashicons-warning"></span> Request failed &mdash; check your internet connection and try again.</div>').show();
				})
				.always(function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				});
			}

			function providerCard(label, data) {
				var isOk      = data.ok === true;
				var isWarn    = data.ok === 'warning';
				var isFail    = data.ok === false;

				var statusClass = isOk ? 'dlt-sld-ok' : ( isWarn ? 'dlt-sld-warn' : 'dlt-sld-fail' );
				var icon        = isOk ? 'dashicons-yes-alt' : ( isWarn ? 'dashicons-info' : 'dashicons-dismiss' );
				var badgeLabel  = isOk ? 'Working' : ( isWarn ? 'Action needed' : 'Error' );

				return '<div class="dlt-sld-card">' +
					'<div class="dlt-sld-card-header">' +
						'<h3>' + esc(label) + '</h3>' +
						'<span class="dlt-sld-badge ' + statusClass + '">' + badgeLabel + '</span>' +
					'</div>' +
					'<div class="dlt-sld-status ' + statusClass + '">' +
						'<span class="dashicons ' + icon + '"></span>' +
						'<strong>' + esc(data.message) + '</strong>' +
					'</div>' +
					'<p class="dlt-sld-detail">' + esc(data.detail) + '</p>' +
				'</div>';
			}

			function renderResults(data) {
				var html = '';

				if ( data.enable_social_login === false ) {
					html += '<div class="dlt-sld-notice dlt-sld-notice-warning">' +
						'<span class="dashicons dashicons-info"></span>' +
						' <strong>Social Login is disabled.</strong> Enable it in Directorist &rarr; Extension Settings &rarr; Social Login toggle.' +
					'</div>';
				}

				html += providerCard('Google', data.google);
				html += providerCard('Facebook', data.facebook);

				$('#dlt-sld-results').html(html).show();
			}

			$(function() {
				if ( !$('.dlt-social-login-diagnostics-wrap').length ) return;
				init();
				$('#dlt-sld-run-check').on('click', runCheck);
				// Auto-run on page load.
				runCheck();
			});
		})(jQuery);
		</script>
		<?php
	}
}
