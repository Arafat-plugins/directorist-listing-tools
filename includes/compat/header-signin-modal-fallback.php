<?php
/**
 * Header Sign In modal fallback.
 *
 * Opens the existing Onelisting/Directorist login modal without depending on
 * Bootstrap's modal API. This protects sites where Bootstrap 4/5 handlers are
 * mixed or optimized scripts expose an incomplete modal object.
 *
 * @package DirectoristListingTools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'DLT_SIGNIN_MODAL_FALLBACK_LOADED' ) ) {
	return;
}
define( 'DLT_SIGNIN_MODAL_FALLBACK_LOADED', true );

/**
 * Print minimal fallback styles for the Sign In modal.
 *
 * These only take effect when the JavaScript fallback adds the helper classes.
 *
 * @return void
 */
function dlt_header_signin_modal_fallback_styles() {
	if ( is_admin() ) {
		return;
	}
	?>
	<style id="dlt-header-signin-modal-fallback">
		#theme-login-modal.dlt-mu-modal-open {
			display: block !important;
			opacity: 1 !important;
		}
		.dlt-mu-modal-backdrop {
			position: fixed;
			inset: 0;
			background: rgba(0, 0, 0, 0.5);
			z-index: 1040;
		}
		body.dlt-mu-modal-lock {
			overflow: hidden;
		}
		body.dlt-mu-modal-lock #theme-login-modal.dlt-mu-modal-open {
			z-index: 1050;
		}
		#theme-login-modal.dlt-mu-modal-open .modal-dialog {
			pointer-events: auto;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'dlt_header_signin_modal_fallback_styles', 99 );

/**
 * Print the Sign In modal fallback script.
 *
 * @return void
 */
function dlt_header_signin_modal_fallback_script() {
	if ( is_admin() ) {
		return;
	}
	?>
	<script id="dlt-header-signin-modal-fallback-js">
	(function() {
		var modalSelector = '#theme-login-modal';
		var triggerSelector = '[data-bs-target="#theme-login-modal"], [data-target="#theme-login-modal"], a[href="#theme-login-modal"]';
		var backdropClass = 'dlt-mu-modal-backdrop';
		var lockClass = 'dlt-mu-modal-lock';
		var openClass = 'dlt-mu-modal-open';
		var previousFocus = null;

		function haltEvent(event) {
			if (!event) {
				return;
			}

			event.preventDefault();
			event.stopPropagation();

			if (typeof event.stopImmediatePropagation === 'function') {
				event.stopImmediatePropagation();
			}
		}

		function getModal() {
			return document.querySelector(modalSelector);
		}

		function getBackdrop() {
			return document.querySelector('.' + backdropClass);
		}

		function removeBackdrop() {
			var backdrop = getBackdrop();
			if (backdrop) {
				backdrop.remove();
			}
		}

		function focusFirstInput(modal) {
			var focusable = modal.querySelector('input:not([type="hidden"]), button, [href], select, textarea, [tabindex]:not([tabindex="-1"])');

			if (!focusable) {
				return;
			}

			try {
				focusable.focus({ preventScroll: true });
			} catch (error) {
				focusable.focus();
			}
		}

		function closeModal() {
			var modal = getModal();
			if (!modal) {
				return;
			}

			modal.classList.remove('show', openClass);
			modal.setAttribute('aria-hidden', 'true');
			modal.removeAttribute('aria-modal');
			modal.style.display = 'none';
			document.body.classList.remove('modal-open', lockClass);
			removeBackdrop();

			if (previousFocus && typeof previousFocus.focus === 'function') {
				try {
					previousFocus.focus({ preventScroll: true });
				} catch (error) {
					previousFocus.focus();
				}
			}

			previousFocus = null;
		}

		function openModal() {
			var modal = getModal();
			if (!modal) {
				return;
			}

			previousFocus = document.activeElement;
			removeBackdrop();

			var backdrop = document.createElement('div');
			backdrop.className = backdropClass;
			backdrop.addEventListener('click', function(event) {
				haltEvent(event);
				closeModal();
			}, true);
			document.body.appendChild(backdrop);

			modal.style.display = 'block';
			modal.classList.add('show', openClass);
			modal.setAttribute('aria-hidden', 'false');
			modal.setAttribute('aria-modal', 'true');
			modal.setAttribute('role', 'dialog');
			document.body.classList.add('modal-open', lockClass);

			window.setTimeout(function() {
				focusFirstInput(modal);
			}, 30);
		}

		document.addEventListener('click', function(event) {
			var trigger = event.target.closest(triggerSelector);
			if (trigger) {
				haltEvent(event);
				openModal();
				return;
			}

			var dismiss = event.target.closest('[data-bs-dismiss="modal"], [data-dismiss="modal"], #theme-login-modal .theme-close');
			if (dismiss) {
				haltEvent(event);
				closeModal();
				return;
			}

			var modal = getModal();
			if (modal && event.target === modal) {
				haltEvent(event);
				closeModal();
			}
		}, true);

		document.addEventListener('keydown', function(event) {
			if (event.key === 'Escape' && document.body.classList.contains(lockClass)) {
				haltEvent(event);
				closeModal();
			}
		});
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'dlt_header_signin_modal_fallback_script', 999 );
