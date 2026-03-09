/**
 * Admin JavaScript for Directorist Listing Tools
 *
 * @package DirectoristListingTools
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// Select all checkboxes functionality.
		$('#cb-select-all').on('change', function() {
			$('.listing-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Update select all checkbox state when individual checkboxes change.
		$('.listing-checkbox').on('change', function() {
			var total = $('.listing-checkbox').length;
			var checked = $('.listing-checkbox:checked').length;
			$('#cb-select-all').prop('checked', total === checked);
		});

		// Confirm before bulk delete.
		$('#dlt-pending-form').on('submit', function(e) {
			var action = $('#bulk-action-selector').val();
			var checked = $('.listing-checkbox:checked').length;

			if (checked === 0) {
				e.preventDefault();
				alert(dltAdmin.strings?.noSelection || 'Please select at least one listing.');
				return false;
			}

			if (action === 'delete') {
				if (!confirm(dltAdmin.strings?.confirmDelete || 'Are you sure you want to delete the selected listings? This action cannot be undone.')) {
					e.preventDefault();
					return false;
				}
			}
		});

		// Handle delete selected button click with AJAX.
		$(document).on('click', '.dlt-delete-selected-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var checked = $('.listing-checkbox:checked').length;

			if (checked === 0) {
				alert('Please select at least one listing to delete.');
				return false;
			}

			if (!confirm('Are you sure you want to delete ' + checked + ' selected listing(s)? This action cannot be undone.')) {
				return false;
			}

			// Get selected IDs.
			var listingIds = [];
			$('.listing-checkbox:checked').each(function() {
				listingIds.push($(this).val());
			});

			// Show loading message.
			$('#dlt-ajax-message').html('<div class="notice notice-info"><p>Deleting ' + checked + ' listing(s), please wait...</p></div>').show();
			$('html, body').animate({ scrollTop: 0 }, 300);

			// Disable buttons.
			var $btn = $(this);
			$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', true);

			// Send AJAX request.
			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_bulk_delete',
					nonce: dltAdmin.nonce,
					listing_ids: JSON.stringify(listingIds)
				},
				success: function(response) {
					if (response.success) {
						$('#dlt-ajax-message').html(response.data.message);
						
						// Remove deleted listings from table.
						var deletedCount = 0;
						var totalToDelete = checked;
						
						$('.listing-checkbox:checked').each(function() {
							var $row = $(this).closest('tr');
							$row.fadeOut(300, function() {
								$(this).remove();
								deletedCount++;
								
								if (deletedCount === totalToDelete) {
									// Update count display.
									var remaining = $('.listing-checkbox').length;
									var totalText = remaining + ' listing' + (remaining !== 1 ? 's' : '') + ' found';
									$('.dlt-listing-count').text(totalText);
									
									// Uncheck all.
									$('#cb-select-all').prop('checked', false);
									
									// Re-enable buttons.
									$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
									
									// If no listings left, reload after 1 second.
									if (remaining === 0) {
										setTimeout(function() {
											location.reload();
										}, 1000);
									}
								}
							});
						});
					} else {
						$('#dlt-ajax-message').html('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
						$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
					}
				},
				error: function(xhr, status, error) {
					$('#dlt-ajax-message').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
					$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
				}
			});

			return false;
		});

		// Handle delete all button.
		$('.dlt-delete-all-btn').on('click', function(e) {
			e.preventDefault();
			var total = $('.listing-checkbox').length;
			var totalCount = $(this).closest('.tablenav').find('.dlt-listing-count').text();
			
			if (total === 0) {
				alert('No listings found to delete.');
				return false;
			}

			if (!confirm('Are you sure you want to delete ALL listings (' + totalCount + ')? This action cannot be undone and is irreversible!')) {
				return false;
			}

			// Submit the delete all form.
			$('#dlt-delete-all-form').submit();
		});

		// Handle delete by count button with AJAX.
		$(document).on('click', '.dlt-delete-by-count-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();
			
			var count = $('#dlt-delete-count').val();
			
			if (!count || count <= 0) {
				alert('Please enter a valid number.');
				$('#dlt-delete-count').focus();
				return false;
			}

			if (!confirm('Are you sure you want to delete ' + count + ' listing(s)? This action cannot be undone.')) {
				return false;
			}

			// Show loading message.
			$('#dlt-ajax-message').html('<div class="notice notice-info"><p>Deleting ' + count + ' listing(s), please wait...</p></div>').show();
			$('html, body').animate({ scrollTop: 0 }, 300);

			// Disable buttons.
			$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', true);

			// Send AJAX request.
			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_delete_by_count',
					nonce: dltAdmin.nonce,
					delete_count: count
				},
				success: function(response) {
					if (response.success) {
						$('#dlt-ajax-message').html(response.data.message);
						
						// Get deleted listing IDs and remove from table.
						if (response.data.listing_ids && response.data.listing_ids.length > 0) {
							var deletedCount = 0;
							var totalToDelete = response.data.listing_ids.length;
							
							response.data.listing_ids.forEach(function(listingId) {
								var $row = $('.listing-checkbox[value="' + listingId + '"]').closest('tr');
								if ($row.length) {
									$row.fadeOut(300, function() {
										$(this).remove();
										deletedCount++;
										
										if (deletedCount === totalToDelete) {
											// Update count display.
											var remaining = $('.listing-checkbox').length;
											var totalText = remaining + ' listing' + (remaining !== 1 ? 's' : '') + ' found';
											$('.dlt-listing-count').text(totalText);
											
											// Uncheck all.
											$('#cb-select-all').prop('checked', false);
											
											// Re-enable buttons.
											$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
											
											// Clear input.
											$('#dlt-delete-count').val('');
											
											// If no listings left, reload after 1 second.
											if (remaining === 0) {
												setTimeout(function() {
													location.reload();
												}, 1000);
											}
										}
									});
								} else {
									deletedCount++;
									if (deletedCount === totalToDelete) {
										// Update count display.
										var remaining = $('.listing-checkbox').length;
										var totalText = remaining + ' listing' + (remaining !== 1 ? 's' : '') + ' found';
										$('.dlt-listing-count').text(totalText);
										$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
										$('#dlt-delete-count').val('');
									}
								}
							});
						} else {
							$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
							$('#dlt-delete-count').val('');
						}
					} else {
						$('#dlt-ajax-message').html('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
						$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
					}
				},
				error: function(xhr, status, error) {
					$('#dlt-ajax-message').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
					$('.dlt-delete-selected-btn, .dlt-delete-all-btn, .dlt-delete-by-count-btn').prop('disabled', false);
				}
			});

			return false;
		});

		// Type Manager: Select all checkboxes.
		$(document).on('change', '#dlt-type-cb-select-all', function() {
			$('.dlt-type-listing-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Update select all checkbox state when individual checkboxes change.
		$(document).on('change', '.dlt-type-listing-checkbox', function() {
			var total = $('.dlt-type-listing-checkbox').length;
			var checked = $('.dlt-type-listing-checkbox:checked').length;
			$('#dlt-type-cb-select-all').prop('checked', total === checked);
		});

		// Type Manager: Handle apply type button with AJAX.
		$(document).on('click', '.dlt-apply-type-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();

			var listingTypeId = $('#dlt-listing-type-select').val();
			var checked = $('.dlt-type-listing-checkbox:checked').length;

			if (!listingTypeId) {
				alert('Please select a listing type.');
				$('#dlt-listing-type-select').focus();
				return false;
			}

			if (checked === 0) {
				alert('Please select at least one listing.');
				return false;
			}

			var listingTypeName = $('#dlt-listing-type-select option:selected').text();
			if (!confirm('Are you sure you want to apply "' + listingTypeName + '" to ' + checked + ' selected listing(s)?')) {
				return false;
			}

			// Get selected IDs.
			var listingIds = [];
			$('.dlt-type-listing-checkbox:checked').each(function() {
				listingIds.push($(this).val());
			});

			// Show loading message.
			$('#dlt-type-ajax-message').html('<div class="notice notice-info"><p>Applying listing type, please wait...</p></div>').show();
			$('html, body').animate({ scrollTop: 0 }, 300);

			// Disable button.
			var $btn = $(this);
			$btn.prop('disabled', true);

			// Send AJAX request.
			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_set_listing_type',
					nonce: dltAdmin.nonce,
					listing_ids: JSON.stringify(listingIds),
					listing_type: listingTypeId
				},
				success: function(response) {
					if (response.success) {
						$('#dlt-type-ajax-message').html(response.data.message);

						// Update current type column for successfully updated listings.
						if (response.data.success_count > 0) {
							$('.dlt-type-listing-checkbox:checked').each(function() {
								var $row = $(this).closest('tr');
								$row.find('.column-type').text(response.data.type_name);
								$(this).prop('checked', false);
							});
							$('#dlt-type-cb-select-all').prop('checked', false);
						}

						// Re-enable button.
						$btn.prop('disabled', false);
					} else {
						$('#dlt-type-ajax-message').html('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
						$btn.prop('disabled', false);
					}
				},
				error: function(xhr, status, error) {
					$('#dlt-type-ajax-message').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
					$btn.prop('disabled', false);
				}
			});

			return false;
		});

		// Location Manager: Select all checkboxes.
		$(document).on('change', '#dlt-location-cb-select-all', function() {
			$('.dlt-location-listing-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Update select all checkbox state when individual checkboxes change.
		$(document).on('change', '.dlt-location-listing-checkbox', function() {
			var total = $('.dlt-location-listing-checkbox').length;
			var checked = $('.dlt-location-listing-checkbox:checked').length;
			$('#dlt-location-cb-select-all').prop('checked', total === checked);
		});

		// Location Manager: Handle apply location button with AJAX.
		$(document).on('click', '.dlt-apply-location-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();

			var locationId = $('#dlt-listing-location-select').val();
			var checked = $('.dlt-location-listing-checkbox:checked').length;

			if (!locationId) {
				alert('Please select a location.');
				$('#dlt-listing-location-select').focus();
				return false;
			}

			if (checked === 0) {
				alert('Please select at least one listing.');
				return false;
			}

			var locationName = $('#dlt-listing-location-select option:selected').text();
			if (!confirm('Are you sure you want to apply "' + locationName + '" to ' + checked + ' selected listing(s)?')) {
				return false;
			}

			// Get selected IDs.
			var listingIds = [];
			$('.dlt-location-listing-checkbox:checked').each(function() {
				listingIds.push($(this).val());
			});

			// Show loading message.
			$('#dlt-location-ajax-message').html('<div class="notice notice-info"><p>Applying location, please wait...</p></div>').show();
			$('html, body').animate({ scrollTop: 0 }, 300);

			// Disable button.
			var $btn = $(this);
			$btn.prop('disabled', true);

			// Send AJAX request.
			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_set_listing_location',
					nonce: dltAdmin.nonce,
					listing_ids: JSON.stringify(listingIds),
					location: locationId
				},
				success: function(response) {
					if (response.success) {
						$('#dlt-location-ajax-message').html(response.data.message);

						// Update current location column for successfully updated listings.
						if (response.data.success_count > 0) {
							$('.dlt-location-listing-checkbox:checked').each(function() {
								var $row = $(this).closest('tr');
								$row.find('.column-location').text(response.data.location_name);
								$(this).prop('checked', false);
							});
							$('#dlt-location-cb-select-all').prop('checked', false);
						}

						// Re-enable button.
						$btn.prop('disabled', false);
					} else {
						$('#dlt-location-ajax-message').html('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
						$btn.prop('disabled', false);
					}
				},
				error: function(xhr, status, error) {
					$('#dlt-location-ajax-message').html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
					$btn.prop('disabled', false);
				}
			});

			return false;
		});
	});

	/* ================================================================
	   Display Settings Page — AJAX Toggle Switches
	   ================================================================ */

	if ( $( '.dlt-display-settings-wrap' ).length ) {

		/* ── Shared utility ───────────────────────────────────────────── */

		/**
		 * Show a dismissible notice in the global message bar.
		 *
		 * @param {string} message  HTML/text to show.
		 * @param {string} type     'success' | 'error' | 'info'.
		 */
		function dltDsShowNotice( message, type ) {
			type = type || 'success';
			var html = '<div class="notice notice-' + type + ' is-dismissible">' +
				'<p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss">' +
				'<span class="screen-reader-text">Dismiss</span></button>' +
				'</div>';
			$( '#dlt-ds-global-message' ).html( html ).show();
			$( 'html, body' ).animate( { scrollTop: 0 }, 250 );

			if ( type === 'success' ) {
				setTimeout( function () {
					$( '#dlt-ds-global-message' ).fadeOut( 300, function () {
						$( this ).html( '' );
					} );
				}, 4000 );
			}
		}

		$( document ).on( 'click', '#dlt-ds-global-message .notice-dismiss', function () {
			$( '#dlt-ds-global-message' ).fadeOut( 200, function () { $( this ).html( '' ); } );
		} );

		/* ── Global Setting Toggles ───────────────────────────────────── */

		$( document ).on( 'change', '.dlt-ds-toggle-input', function () {
			var $input       = $( this );
			var optionKey    = $input.data( 'option-key' );
			var optionLabel  = $input.data( 'label' );
			var newValue     = $input.is( ':checked' ) ? 1 : 0;
			var $row         = $input.closest( 'tr.dlt-ds-row' );
			var $cell        = $input.closest( '.dlt-ds-toggle-cell' );
			var $badge       = $cell.find( '.dlt-ds-badge' );
			var $spinner     = $cell.find( '.dlt-ds-spinner' );
			var $toggleLabel = $input.closest( '.dlt-ds-toggle' );

			$spinner.show();
			$toggleLabel.addClass( 'is-loading' );
			$input.prop( 'disabled', true );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action       : 'dlt_toggle_display_setting',
					nonce        : dltAdmin.nonce,
					option_key   : optionKey,
					option_value : newValue
				},
				success: function ( response ) {
					$spinner.hide();
					$toggleLabel.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );

					if ( response.success ) {
						if ( newValue ) {
							$badge.text( 'On' ).removeClass( 'dlt-ds-badge-off' ).addClass( 'dlt-ds-badge-on' );
							$row.removeClass( 'is-inactive' ).addClass( 'is-active' );
						} else {
							$badge.text( 'Off' ).removeClass( 'dlt-ds-badge-on' ).addClass( 'dlt-ds-badge-off' );
							$row.removeClass( 'is-active' ).addClass( 'is-inactive' );
						}
						dltDsShowNotice( response.data.message, 'success' );
					} else {
						$input.prop( 'checked', ! newValue );
						var errMsg = ( response.data && response.data.message )
							? response.data.message
							: 'An error occurred while saving "' + optionLabel + '".';
						dltDsShowNotice( errMsg, 'error' );
					}
				},
				error: function () {
					$spinner.hide();
					$toggleLabel.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );
					$input.prop( 'checked', ! newValue );
					dltDsShowNotice( 'A server error occurred while saving "' + optionLabel + '". Please try again.', 'error' );
				}
			} );
		} );

		/* ── Per-Directory Thumbnail Settings ────────────────────────── */

		/**
		 * Build the tbody HTML rows for the directory type settings table.
		 *
		 * @param {Object} data  Response data from dlt_load_directory_type_settings.
		 * @returns {string}
		 */
		function dltDsBuildDirectoryRows( data ) {
			if ( ! data.settings || ! data.settings.length ) {
				return '<tr><td colspan="3" style="padding:14px;color:#646970;">' +
					'No thumbnail settings found for this directory type.</td></tr>';
			}

			var html = '';
			$.each( data.settings, function ( i, s ) {
				var isOn       = s.value;
				var badgeClass = isOn ? 'dlt-ds-badge-on' : 'dlt-ds-badge-off';
				var badgeText  = isOn ? 'On' : 'Off';
				var rowClass   = isOn ? 'is-active' : 'is-inactive';

				html += '<tr class="dlt-ds-row dlt-ds-dir-row ' + rowClass + '"' +
					' data-term-id="' + data.term_id + '"' +
					' data-setting-key="' + s.key + '">' +

					'<td class="dlt-ds-label-cell">' +
						'<strong>' + s.label + '</strong>' +
						'<br><code class="dlt-ds-option-key" style="font-size:11px;color:#8c8f94;">' + s.meta_label + '</code>' +
					'</td>' +

					'<td class="dlt-ds-desc-cell">' + s.description + '</td>' +

					'<td class="dlt-ds-toggle-cell">' +
						'<label class="dlt-ds-toggle" title="' + s.label + '">' +
							'<input type="checkbox"' +
								' class="dlt-ds-dir-toggle-input"' +
								' data-term-id="' + data.term_id + '"' +
								' data-setting-key="' + s.key + '"' +
								( isOn ? ' checked' : '' ) + ' />' +
							'<span class="dlt-ds-toggle-slider"></span>' +
						'</label>' +
						'<span class="dlt-ds-badge ' + badgeClass + '">' + badgeText + '</span>' +
						'<span class="dlt-ds-spinner spinner" style="display:none;float:none;margin:0 4px;"></span>' +
					'</td></tr>';
			} );
			return html;
		}

		/**
		 * Load settings for a given directory type term ID via AJAX.
		 *
		 * @param {number} termId
		 */
		function dltDsLoadDirectorySettings( termId ) {
			var $tbody   = $( '#dlt-ds-directory-tbody' );
			var $spinner = $( '.dlt-ds-dir-spinner' );

			$tbody.html(
				'<tr><td colspan="3" style="text-align:center;padding:18px;">' +
				'<span class="spinner is-active" style="float:none;vertical-align:middle;"></span> Loading\u2026</td></tr>'
			);
			$spinner.css( 'visibility', 'visible' );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action  : 'dlt_load_directory_type_settings',
					nonce   : dltAdmin.nonce,
					term_id : termId
				},
				success: function ( response ) {
					$spinner.css( 'visibility', 'hidden' );
					if ( response.success ) {
						$tbody.html( dltDsBuildDirectoryRows( response.data ) );
					} else {
						var msg = response.data && response.data.message ? response.data.message : 'Error loading settings.';
						$tbody.html( '<tr><td colspan="3"><div class="notice notice-error inline"><p>' + msg + '</p></div></td></tr>' );
					}
				},
				error: function () {
					$spinner.css( 'visibility', 'hidden' );
					$tbody.html( '<tr><td colspan="3"><div class="notice notice-error inline"><p>Server error. Please try again.</p></div></td></tr>' );
				}
			} );
		}

		// Auto-load settings for the first directory type on page load.
		var $dirSelect = $( '#dlt-ds-directory-type-select' );
		if ( $dirSelect.length ) {
			var initialTermId = parseInt( $dirSelect.data( 'first-id' ), 10 );
			if ( initialTermId ) {
				dltDsLoadDirectorySettings( initialTermId );
			}

			// Reload when the user picks a different directory type.
			$dirSelect.on( 'change', function () {
				var termId = parseInt( $( this ).val(), 10 );
				if ( termId ) {
					dltDsLoadDirectorySettings( termId );
				}
			} );
		}

		// Handle per-directory thumbnail toggle changes.
		$( document ).on( 'change', '.dlt-ds-dir-toggle-input', function () {
			var $input       = $( this );
			var termId       = $input.data( 'term-id' );
			var settingKey   = $input.data( 'setting-key' );
			var newValue     = $input.is( ':checked' ) ? 1 : 0;
			var $row         = $input.closest( 'tr.dlt-ds-row' );
			var $cell        = $input.closest( '.dlt-ds-toggle-cell' );
			var $badge       = $cell.find( '.dlt-ds-badge' );
			var $spinner     = $cell.find( '.dlt-ds-spinner' );
			var $toggleLabel = $input.closest( '.dlt-ds-toggle' );

			$spinner.show();
			$toggleLabel.addClass( 'is-loading' );
			$input.prop( 'disabled', true );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action      : 'dlt_toggle_directory_type_setting',
					nonce       : dltAdmin.nonce,
					term_id     : termId,
					setting_key : settingKey,
					value       : newValue
				},
				success: function ( response ) {
					$spinner.hide();
					$toggleLabel.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );

					if ( response.success ) {
						if ( newValue ) {
							$badge.text( 'On' ).removeClass( 'dlt-ds-badge-off' ).addClass( 'dlt-ds-badge-on' );
							$row.removeClass( 'is-inactive' ).addClass( 'is-active' );
						} else {
							$badge.text( 'Off' ).removeClass( 'dlt-ds-badge-on' ).addClass( 'dlt-ds-badge-off' );
							$row.removeClass( 'is-active' ).addClass( 'is-inactive' );
						}
						// Update the meta label code tag.
						$row.find( 'code.dlt-ds-option-key' ).text( response.data.meta_label );
						dltDsShowNotice( response.data.message, 'success' );
					} else {
						$input.prop( 'checked', ! newValue );
						var errMsg = ( response.data && response.data.message )
							? response.data.message : 'An error occurred. Please try again.';
						dltDsShowNotice( errMsg, 'error' );
					}
				},
				error: function () {
					$spinner.hide();
					$toggleLabel.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );
					$input.prop( 'checked', ! newValue );
					dltDsShowNotice( 'Server error. Please try again.', 'error' );
				}
			} );
		} );

	} // end .dlt-display-settings-wrap

	/* ================================================================
	   Plan Price Manager — Inline Save
	   ================================================================ */

	if ( $( '.dlt-plan-manager-wrap' ).length ) {

		/**
		 * Show a notice at the top of the plan manager page.
		 * Reuses the same #dlt-pm-global-message div.
		 */
		function dltPmShowNotice( message, type ) {
			type = type || 'success';
			var html = '<div class="notice notice-' + type + ' is-dismissible">' +
				'<p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>';
			$( '#dlt-pm-global-message' ).html( html ).show();
			$( 'html, body' ).animate( { scrollTop: 0 }, 250 );

			if ( type === 'success' ) {
				setTimeout( function () {
					$( '#dlt-pm-global-message' ).fadeOut( 300, function () { $( this ).html( '' ); } );
				}, 4000 );
			}
		}

		$( document ).on( 'click', '#dlt-pm-global-message .notice-dismiss', function () {
			$( '#dlt-pm-global-message' ).fadeOut( 200, function () { $( this ).html( '' ); } );
		} );

		/* When "Free Plan" toggle is switched on, grey-out ONLY price field (not tax — user may still configure it) */
		function dltPmApplyFreePlanState( $row ) {
			var isFree = $row.find( '.dlt-pm-free-plan' ).is( ':checked' );
			$row.find( '.dlt-pm-price' )
				.prop( 'disabled', isFree )
				.toggleClass( 'dlt-pm-field-disabled', isFree );
		}

		$( document ).on( 'change', '.dlt-pm-free-plan', function () {
			dltPmApplyFreePlanState( $( this ).closest( 'tr.dlt-pm-row' ) );
		} );

		/* Trigger the free-plan state on page load */
		$( 'tr.dlt-pm-row' ).each( function () {
			dltPmApplyFreePlanState( $( this ) );
		} );

		/* When Tax toggle is off, disable tax type / amount */
		function dltPmApplyTaxState( $row ) {
			var taxOn = $row.find( '.dlt-pm-tax-toggle' ).is( ':checked' );
			$row.find( '.dlt-pm-tax-type, .dlt-pm-tax-amount' )
				.prop( 'disabled', ! taxOn )
				.toggleClass( 'dlt-pm-field-disabled', ! taxOn );
		}

		$( document ).on( 'change', '.dlt-pm-tax-toggle', function () {
			dltPmApplyTaxState( $( this ).closest( 'tr.dlt-pm-row' ) );
		} );

		/* Trigger on load */
		$( 'tr.dlt-pm-row' ).each( function () {
			dltPmApplyTaxState( $( this ) );
		} );

		/* Save button */
		$( document ).on( 'click', '.dlt-pm-save-btn', function () {
			var $btn     = $( this );
			var planId   = $btn.data( 'plan-id' );
			var $row     = $btn.closest( 'tr.dlt-pm-row' );
			var $spinner = $row.find( '.dlt-pm-row-spinner' );

			var fm_price      = $row.find( '.dlt-pm-price' ).val() || 0;
			var free_plan     = $row.find( '.dlt-pm-free-plan' ).is( ':checked' ) ? 1 : 0;
			var plan_tax      = $row.find( '.dlt-pm-tax-toggle' ).is( ':checked' ) ? 1 : 0;
			var plan_tax_type = $row.find( '.dlt-pm-tax-type' ).val();
			var fm_tax        = $row.find( '.dlt-pm-tax-amount' ).val() || 0;

			$btn.prop( 'disabled', true );
			$spinner.css( 'visibility', 'visible' );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action        : 'dlt_save_plan_prices',
					nonce         : dltAdmin.nonce,
					plan_id       : planId,
					fm_price      : fm_price,
					free_plan     : free_plan,
					plan_tax      : plan_tax,
					plan_tax_type : plan_tax_type,
					fm_tax        : fm_tax
				},
				success: function ( response ) {
					$btn.prop( 'disabled', false );
					$spinner.css( 'visibility', 'hidden' );

					if ( response.success ) {
						/* Update the effective price cell */
						$row.find( '.dlt-pm-effective-value' ).text( response.data.effective );
						/* Flash the row green briefly */
						$row.addClass( 'dlt-pm-saved' );
						setTimeout( function () { $row.removeClass( 'dlt-pm-saved' ); }, 1600 );
						dltPmShowNotice( response.data.message, 'success' );
					} else {
						var msg = ( response.data && response.data.message ) ? response.data.message : 'An error occurred.';
						dltPmShowNotice( msg, 'error' );
					}
				},
				error: function () {
					$btn.prop( 'disabled', false );
					$spinner.css( 'visibility', 'hidden' );
					dltPmShowNotice( 'Server error. Please try again.', 'error' );
				}
			} );
		} );

	} // end .dlt-plan-manager-wrap

})(jQuery);

