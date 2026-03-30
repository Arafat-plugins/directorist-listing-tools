<?php
/**
 * Fix Directorist Google Sign-In button fallback (from MU plugin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'DLT_AF_LOADED_GOOGLE_SIGNIN' ) ) {
	return;
}
define( 'DLT_AF_LOADED_GOOGLE_SIGNIN', true );

add_action( 'wp_footer', 'dls_fix_google_signin_button_fallback', 999 );

function dls_fix_google_signin_button_fallback() {
	?>
	<script>
	(function() {
		function getCookie(name) {
			var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
			return v ? v[2] : '';
		}
		function tryRenderGoogleButton() {
			var signinEl = document.querySelector('.g_id_signin');
			var onloadEl = document.getElementById('g_id_onload');
			if (!signinEl || !onloadEl) return false;
			if (signinEl.children.length > 0) return false;
			var google = window.google;
			if (!google || !google.accounts || !google.accounts.id) return false;
			var clientId = signinEl.getAttribute('data-client_id') || onloadEl.getAttribute('data-client_id');
			var loginUri = signinEl.getAttribute('data-login_uri') || onloadEl.getAttribute('data-login_uri');
			var wpnonce = signinEl.getAttribute('data-wpnonce') || onloadEl.getAttribute('data-wpnonce');
			if (!clientId || !loginUri) return false;
			google.accounts.id.initialize({
				client_id: clientId,
				callback: function(response) {
					var form = document.createElement('form');
					form.method = 'POST';
					form.action = loginUri;
					var c = document.createElement('input');
					c.type = 'hidden';
					c.name = 'credential';
					c.value = response.credential;
					form.appendChild(c);
					var n = document.createElement('input');
					n.type = 'hidden';
					n.name = 'wpnonce';
					n.value = wpnonce || '';
					form.appendChild(n);
					var csrf = getCookie('g_csrf_token');
					if (csrf) {
						var g = document.createElement('input');
						g.type = 'hidden';
						g.name = 'g_csrf_token';
						g.value = csrf;
						form.appendChild(g);
					}
					document.body.appendChild(form);
					form.submit();
				}
			});
			var opts = {
				type: 'standard',
				theme: (signinEl.getAttribute('data-theme') || 'filled_blue'),
				size: (signinEl.getAttribute('data-size') || 'large'),
				text: (signinEl.getAttribute('data-text') || 'continue_with'),
				shape: (signinEl.getAttribute('data-shape') || 'rectangular'),
				logo_alignment: (signinEl.getAttribute('data-logo_alignment') || 'left'),
				width: parseInt(signinEl.getAttribute('data-width'), 10) || 275
			};
			google.accounts.id.renderButton(signinEl, opts);
			return true;
		}
		var attempts = 0;
		var maxAttempts = 50;
		var interval = setInterval(function() {
			attempts++;
			if (tryRenderGoogleButton() || attempts >= maxAttempts) {
				clearInterval(interval);
			}
		}, 200);
	})();
	</script>
	<?php
}
