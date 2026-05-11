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

		// Listing Refresh: Select all checkboxes.
		$(document).on('change', '#dlt-refresh-cb-select-all', function() {
			$('.dlt-refresh-listing-checkbox').prop('checked', $(this).prop('checked'));
		});

		// Listing Refresh: Update select all checkbox state.
		$(document).on('change', '.dlt-refresh-listing-checkbox', function() {
			var total = $('.dlt-refresh-listing-checkbox').length;
			var checked = $('.dlt-refresh-listing-checkbox:checked').length;
			$('#dlt-refresh-cb-select-all').prop('checked', total > 0 && total === checked);
		});

		function dltRefreshSetBusy(isBusy) {
			$('.dlt-refresh-selected-btn, .dlt-refresh-by-count-btn, .dlt-refresh-all-btn').prop('disabled', isBusy);
			$('#dlt-refresh-count').prop('disabled', isBusy);
		}

		function dltRefreshShowMessage(html) {
			$('#dlt-refresh-ajax-message').html(html).show();
			$('html, body').animate({ scrollTop: 0 }, 300);
		}

		function dltRefreshApplySuccess(data) {
			var refreshedAt = data.refreshed_at || 'Done';

			if (data.listing_ids && data.listing_ids.length) {
				data.listing_ids.forEach(function(listingId) {
					var $row = $('#dlt-refresh-container tr[data-listing-id="' + listingId + '"]');
					$row.find('.column-refresh-status').text(refreshedAt);
					$row.find('.dlt-refresh-listing-checkbox').prop('checked', false);
				});
			}

			$('#dlt-refresh-cb-select-all').prop('checked', false);

			if (typeof data.pending_count !== 'undefined') {
				$('.dlt-refresh-pending-count').text(data.pending_count);
			}
		}

		// Listing Refresh: selected rows.
		$(document).on('click', '.dlt-refresh-selected-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();

			var listingIds = [];
			$('.dlt-refresh-listing-checkbox:checked').each(function() {
				listingIds.push($(this).val());
			});

			if (!listingIds.length) {
				alert('Please select at least one listing.');
				return false;
			}

			if (!confirm('Refresh ' + listingIds.length + ' selected listing(s)?')) {
				return false;
			}

			dltRefreshSetBusy(true);
			dltRefreshShowMessage('<div class="notice notice-info"><p>Refreshing selected listing(s), please wait...</p></div>');

			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_refresh_selected',
					nonce: dltAdmin.nonce,
					listing_ids: JSON.stringify(listingIds)
				},
				success: function(response) {
					if (response.success) {
						dltRefreshShowMessage(response.data.message);
						dltRefreshApplySuccess(response.data);
					} else {
						dltRefreshShowMessage('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
					}
					dltRefreshSetBusy(false);
				},
				error: function() {
					dltRefreshShowMessage('<div class="notice notice-error"><p>Server error. Please try a smaller batch.</p></div>');
					dltRefreshSetBusy(false);
				}
			});

			return false;
		});

		// Listing Refresh: next unrefreshed listings by count.
		$(document).on('click', '.dlt-refresh-by-count-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();

			var count = parseInt($('#dlt-refresh-count').val(), 10);

			if (!count || count <= 0) {
				alert('Please enter a valid number.');
				$('#dlt-refresh-count').focus();
				return false;
			}

			if (!confirm('Refresh the next ' + count + ' unrefreshed listing(s)?')) {
				return false;
			}

			dltRefreshSetBusy(true);
			dltRefreshShowMessage('<div class="notice notice-info"><p>Refreshing next ' + count + ' listing(s), please wait...</p></div>');

			$.ajax({
				url: dltAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'dlt_ajax_refresh_by_count',
					nonce: dltAdmin.nonce,
					refresh_count: count
				},
				success: function(response) {
					if (response.success) {
						dltRefreshShowMessage(response.data.message);
						dltRefreshApplySuccess(response.data);
					} else {
						dltRefreshShowMessage('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
					}
					dltRefreshSetBusy(false);
				},
				error: function() {
					dltRefreshShowMessage('<div class="notice notice-error"><p>Server error. Please try a smaller number.</p></div>');
					dltRefreshSetBusy(false);
				}
			});

			return false;
		});

		// Listing Refresh: all remaining in small AJAX batches to avoid 503 timeouts.
		$(document).on('click', '.dlt-refresh-all-btn', function(e) {
			e.preventDefault();
			e.stopPropagation();

			var batchSize = parseInt($('#dlt-refresh-count').val(), 10) || 25;
			batchSize = Math.max(1, Math.min(100, batchSize));

			if (!confirm('Refresh all remaining listings in batches of ' + batchSize + '?')) {
				return false;
			}

			var totalSuccess = 0;
			var totalFailed = 0;

			dltRefreshSetBusy(true);
			dltRefreshShowMessage('<div class="notice notice-info"><p>Refreshing remaining listings. Please keep this page open...</p></div>');

			function runBatch() {
				$.ajax({
					url: dltAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'dlt_ajax_refresh_batch',
						nonce: dltAdmin.nonce,
						batch_size: batchSize
					},
					success: function(response) {
						if (!response.success) {
							dltRefreshShowMessage('<div class="notice notice-error"><p>' + (response.data.message || 'An error occurred.') + '</p></div>');
							dltRefreshSetBusy(false);
							return;
						}

						totalSuccess += parseInt(response.data.success_count || 0, 10);
						totalFailed += parseInt(response.data.failed_count || 0, 10);
						dltRefreshApplySuccess(response.data);

						dltRefreshShowMessage(
							'<div class="notice notice-info"><p>Processed ' + totalSuccess + ' listing(s). Failed: ' + totalFailed + '. Remaining: ' + response.data.pending_count + '.</p></div>'
						);

						if (response.data.done) {
							dltRefreshShowMessage('<div class="notice notice-success"><p>Refresh complete. Processed ' + totalSuccess + ' listing(s). Failed: ' + totalFailed + '.</p></div>');
							dltRefreshSetBusy(false);
							return;
						}

						runBatch();
					},
					error: function() {
						dltRefreshShowMessage('<div class="notice notice-error"><p>Server error during batch refresh. Try again with a smaller batch size.</p></div>');
						dltRefreshSetBusy(false);
					}
				});
			}

			runBatch();
			return false;
		});
	});

	/* ================================================================
	   Display Settings Page — Tabs, Toggles, and LWM Settings
	   ================================================================ */

	if ( $( '.dlt-display-settings-wrap' ).length ) {

		var DLT_TAB_KEY = 'dlt_ds_active_tab';

		/* ── Shared utility: dismissible notice ──────────────────────── */

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

		/* ── Tab Switching ────────────────────────────────────────────── */

		/**
		 * Activate the given tab panel and update nav link state.
		 *
		 * @param {string} tabId  The data-tab value (e.g. 'thumbnail', 'card_display').
		 */
		function dltDsSwitchTab( tabId ) {
			// Update nav links.
			$( '.dlt-ds-tab-link' ).removeClass( 'nav-tab-active' ).attr( 'aria-selected', 'false' );
			$( '.dlt-ds-tab-link[data-tab="' + tabId + '"]' )
				.addClass( 'nav-tab-active' )
				.attr( 'aria-selected', 'true' );

			// Show / hide panels.
			$( '.dlt-ds-tab-panel' ).hide();
			$( '#dlt-tab-' + tabId ).show();

			// Persist for next page load.
			try {
				sessionStorage.setItem( DLT_TAB_KEY, tabId );
			} catch ( e ) { /* private browsing — ignore */ }

			// Thumbnail + Single Listing panels load per-directory data via the same AJAX action.
			if ( tabId === 'thumbnail' || tabId === 'single_listing' ) {
				var termIdTab = parseInt( $( '#dlt-ds-directory-type-select' ).val(), 10 );
				if ( termIdTab ) {
					dltDsLoadDirectorySettings( termIdTab );
				}
			}
		}

		// Restore last active tab from sessionStorage on page load.
		( function () {
			var savedTab = '';
			try { savedTab = sessionStorage.getItem( DLT_TAB_KEY ) || ''; } catch ( e ) {}
			if ( savedTab && $( '.dlt-ds-tab-link[data-tab="' + savedTab + '"]' ).length ) {
				dltDsSwitchTab( savedTab );
			}
		} )();

		// Tab click handler.
		$( document ).on( 'click', '.dlt-ds-tab-link', function ( e ) {
			e.preventDefault();
			dltDsSwitchTab( $( this ).data( 'tab' ) );
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

		/* ── Per-Directory Thumbnail + Single (custom page) via AJAX ─── */

		function dltDsEscapeHtml( s ) {
			return $( '<div/>' ).text( s == null ? '' : String( s ) ).html();
		}

		/**
		 * Render rows in Single Listing tab: custom single ON/OFF + page dropdown (all AJAX-driven).
		 *
		 * @param {object} data Response from dlt_load_directory_type_settings or save/toggle handlers.
		 */
		function dltDsRenderSingleListingPanel( data ) {
			var $tbody = $( '#dlt-ds-single-type-tbody' );
			if ( ! $tbody.length || ! data || ! data.single_listing ) {
				return;
			}

			var sl       = data.single_listing;
			var termId   = parseInt( data.term_id, 10 );
			var choices  = $.isArray( data.page_choices ) ? data.page_choices : [];
			var isOn     = !! sl.enable_custom_single;
			var badgeOn  = isOn ? 'dlt-ds-badge-on' : 'dlt-ds-badge-off';
			var badgeTxt = isOn ? 'On' : 'Off';
			var rowCls   = isOn ? 'is-active' : 'is-inactive';

			var detailsCol = '';
			if ( sl.issue ) {
				detailsCol += '<div class="notice notice-warning inline" style="margin:0 0 8px;padding:6px 10px;"><p style="margin:0;">' +
					dltDsEscapeHtml( sl.issue ) + '</p></div>';
			}
			detailsCol += '<p class="dlt-ds-single-summary" style="margin:0 0 6px;"><strong>Assigned:</strong> ';
			if ( sl.page_id > 0 ) {
				detailsCol += 'ID ' + parseInt( sl.page_id, 10 );
				if ( sl.page_title ) {
					detailsCol += ' — ' + dltDsEscapeHtml( sl.page_title );
				}
				if ( ! sl.valid_page ) {
					detailsCol += ' <em>(invalid)</em>';
				}
			} else {
				detailsCol += '<em>None</em>';
			}
			detailsCol += '</p>';
			if ( sl.edit_url ) {
				detailsCol += '<p style="margin:0;"><a href="' + dltDsEscapeHtml( sl.edit_url ) + '">Edit page</a>';
				if ( sl.view_url ) {
					detailsCol += ' · <a href="' + dltDsEscapeHtml( sl.view_url ) + '" target="_blank" rel="noopener noreferrer">View</a>';
				}
				detailsCol += '</p>';
			}

			var sel  = '<select class="dlt-ds-single-page-select" data-term-id="' + termId + '" style="max-width:220px;">';
			sel += '<option value="0">— ' + 'None' + ' —</option>';
			var currentId = parseInt( sl.page_id, 10 );
			var found     = false;
			var c;
			for ( c = 0; c < choices.length; c++ ) {
				var pid = parseInt( choices[ c ].id, 10 );
				if ( pid === currentId ) {
					found = true;
				}
				sel += '<option value="' + pid + '"' + ( pid === currentId ? ' selected' : '' ) + '>' +
					dltDsEscapeHtml( choices[ c ].title ) + '</option>';
			}
			if ( currentId > 0 && ! found ) {
				sel += '<option value="' + currentId + '" selected>' +
					dltDsEscapeHtml( sl.page_title || ( '#' + currentId ) ) + ' (saved)</option>';
			}
			sel += '</select>';
			sel += '<span class="dlt-ds-single-page-spinner spinner" style="float:none;visibility:hidden;margin:0 6px;"></span>';
			sel += '<span class="dlt-ds-single-page-saved" style="display:none;color:#00a32a;font-size:12px;">✓</span>';

			var rowToggle =
				'<tr class="dlt-ds-row ' + rowCls + '">' +
					'<td class="dlt-ds-label-cell"><strong>Custom single listing page</strong><br>' +
						'<span class="description">Uses a WordPress Page (e.g. Elementor) instead of native Directorist sections.</span><br>' +
						'<code class="dlt-ds-option-key">enable_single_listing_page</code>' +
					'</td>' +
					'<td class="dlt-ds-desc-cell">' + detailsCol + '</td>' +
					'<td class="dlt-ds-toggle-cell" style="text-align:center;">' +
						'<label class="dlt-ds-toggle">' +
							'<input type="checkbox" class="dlt-ds-single-type-toggle-input"' +
								' data-term-id="' + termId + '"' +
								' data-setting-key="enable_custom_single_page"' +
								( isOn ? ' checked' : '' ) + ' />' +
							'<span class="dlt-ds-toggle-slider"></span>' +
						'</label>' +
						'<span class="dlt-ds-badge ' + badgeOn + '">' + badgeTxt + '</span>' +
						'<span class="dlt-ds-spinner spinner" style="display:none;float:none;margin:0 4px;"></span>' +
					'</td>' +
				'</tr>';

			var rowPage =
				'<tr class="dlt-ds-row">' +
					'<td class="dlt-ds-label-cell"><strong>Page assignment</strong><br>' +
						'<code class="dlt-ds-option-key">single_listing_page</code>' +
					'</td>' +
					'<td class="dlt-ds-desc-cell">' +
						'<span class="description">Choose which Page Directorist renders for this directory type when custom single is ON.</span>' +
					'</td>' +
					'<td class="dlt-ds-toggle-cell" style="text-align:center;">' + sel + '</td>' +
				'</tr>';

			$tbody.html( rowToggle + rowPage );
		}

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
						dltDsRenderSingleListingPanel( response.data );
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

		// Per-directory data: load once when directory type is known (thumbnail + single listing UIs).
		var $dirSelect = $( '#dlt-ds-directory-type-select' );
		if ( $dirSelect.length ) {
			var initialTermId = parseInt( $dirSelect.data( 'first-id' ), 10 );
			if ( initialTermId ) {
				dltDsLoadDirectorySettings( initialTermId );
			}

			$dirSelect.on( 'change', function () {
				var termIdCh = parseInt( $( this ).val(), 10 );
				if ( termIdCh ) {
					dltDsLoadDirectorySettings( termIdCh );
				}
			} );
		}

		// Custom single listing page — ON/OFF (term meta), AJAX only.
		$( document ).on( 'change', '.dlt-ds-single-type-toggle-input', function () {
			var $input       = $( this );
			var termIdS      = $input.data( 'term-id' );
			var settingKeyS  = $input.data( 'setting-key' );
			var newValS      = $input.is( ':checked' ) ? 1 : 0;
			var $rowS        = $input.closest( 'tr.dlt-ds-row' );
			var $cellS       = $input.closest( '.dlt-ds-toggle-cell' );
			var $badgeS      = $cellS.find( '.dlt-ds-badge' );
			var $spinnerS    = $cellS.find( '.dlt-ds-spinner' );
			var $toggleLblS  = $input.closest( '.dlt-ds-toggle' );

			$spinnerS.css( 'visibility', 'visible' ).show();
			$toggleLblS.addClass( 'is-loading' );
			$input.prop( 'disabled', true );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action      : 'dlt_toggle_directory_type_setting',
					nonce       : dltAdmin.nonce,
					term_id     : termIdS,
					setting_key : settingKeyS,
					value       : newValS
				},
				success: function ( response ) {
					$spinnerS.hide();
					$toggleLblS.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );

					if ( response.success && response.data.single_listing ) {
						if ( newValS ) {
							$badgeS.text( 'On' ).removeClass( 'dlt-ds-badge-off' ).addClass( 'dlt-ds-badge-on' );
							$rowS.removeClass( 'is-inactive' ).addClass( 'is-active' );
						} else {
							$badgeS.text( 'Off' ).removeClass( 'dlt-ds-badge-on' ).addClass( 'dlt-ds-badge-off' );
							$rowS.removeClass( 'is-active' ).addClass( 'is-inactive' );
						}
						dltDsRenderSingleListingPanel( response.data );
						dltDsShowNotice( response.data.message, 'success' );
					} else {
						$input.prop( 'checked', ! newValS );
						var errS = ( response.data && response.data.message )
							? response.data.message : 'Save failed.';
						dltDsShowNotice( errS, 'error' );
					}
				},
				error: function () {
					$spinnerS.hide();
					$toggleLblS.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );
					$input.prop( 'checked', ! newValS );
					dltDsShowNotice( 'Server error. Please try again.', 'error' );
				}
			} );
		} );

		// Assigned page — save via AJAX (single_listing_page term meta).
		var dltSinglePageTimer = null;
		$( document ).on( 'change', '.dlt-ds-single-page-select', function () {
			var $sel   = $( this );
			var termPg = parseInt( $sel.data( 'term-id' ), 10 );
			var pageId = parseInt( $sel.val(), 10 );
			var $cell  = $sel.closest( 'td' );
			var $spinP = $cell.find( '.dlt-ds-single-page-spinner' );
			var $ok    = $cell.find( '.dlt-ds-single-page-saved' );

			$sel.prop( 'disabled', true );
			$spinP.css( 'visibility', 'visible' ).show();
			$ok.hide();

			clearTimeout( dltSinglePageTimer );
			dltSinglePageTimer = setTimeout( function () {
				$.ajax( {
					url  : dltAdmin.ajaxUrl,
					type : 'POST',
					data : {
						action  : 'dlt_save_directory_type_single_listing_page',
						nonce   : dltAdmin.nonce,
						term_id : termPg,
						page_id : pageId
					},
					success: function ( response ) {
						$spinP.css( 'visibility', 'hidden' ).hide();
						$sel.prop( 'disabled', false );
						if ( response.success && response.data.single_listing ) {
							dltDsRenderSingleListingPanel( response.data );
							$ok = $( '.dlt-ds-single-page-saved' ).first();
							$ok.fadeIn( 150 );
							setTimeout( function () { $ok.fadeOut( 600 ); }, 1800 );
							dltDsShowNotice( response.data.message, 'success' );
						} else {
							dltDsShowNotice( ( response.data && response.data.message ) ? response.data.message : 'Save failed.', 'error' );
							dltDsLoadDirectorySettings( termPg );
						}
					},
					error: function () {
						$spinP.css( 'visibility', 'hidden' ).hide();
						$sel.prop( 'disabled', false );
						dltDsShowNotice( 'Server error while saving page.', 'error' );
						dltDsLoadDirectorySettings( termPg );
					}
				} );
			}, 200 );
		} );

		// Per-directory thumbnail toggle handler.
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

		/* ── Listings With Map — Select Save (AJAX on change) ─────────── */

		var dltLwmSelectTimer = null;

		$( document ).on( 'change', '.dlt-ds-lwm-select', function () {
			var $select     = $( this );
			var optionKey   = $select.data( 'option-key' );
			var optionLabel = $select.data( 'label' );
			var newValue    = $select.val();
			var $cell       = $select.closest( '.dlt-ds-toggle-cell' );
			var $spinner    = $cell.find( '.dlt-ds-spinner' );
			var $saved      = $cell.find( '.dlt-ds-lwm-saved-msg' );

			$spinner.show();
			$saved.hide();
			$select.prop( 'disabled', true );

			clearTimeout( dltLwmSelectTimer );
			dltLwmSelectTimer = setTimeout( function () {
				$.ajax( {
					url  : dltAdmin.ajaxUrl,
					type : 'POST',
					data : {
						action       : 'dlt_save_lwm_setting',
						nonce        : dltAdmin.nonce,
						option_key   : optionKey,
						option_value : newValue
					},
					success: function ( response ) {
						$spinner.hide();
						$select.prop( 'disabled', false );
						if ( response.success ) {
							$saved.fadeIn( 200 );
							setTimeout( function () { $saved.fadeOut( 600 ); }, 2000 );
							dltDsShowNotice( response.data.message, 'success' );
						} else {
							var errMsg = ( response.data && response.data.message )
								? response.data.message
								: 'An error occurred while saving "' + optionLabel + '".';
							dltDsShowNotice( errMsg, 'error' );
						}
					},
					error: function () {
						$spinner.hide();
						$select.prop( 'disabled', false );
						dltDsShowNotice( 'Server error while saving "' + optionLabel + '". Please try again.', 'error' );
					}
				} );
			}, 300 );
		} );

		/* ── Listings With Map — Toggle Save (AJAX on change) ─────────── */

		$( document ).on( 'change', '.dlt-ds-lwm-toggle-input', function () {
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
					action       : 'dlt_save_lwm_setting',
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
					dltDsShowNotice( 'Server error while saving "' + optionLabel + '". Please try again.', 'error' );
				}
			} );
		} );

		/* ── User Roles — Capability Toggles (AJAX on change) ─────────── */

		$( document ).on( 'change', '.dlt-ds-role-cap-input', function () {
			var $input      = $( this );
			var role        = $input.data( 'role' );
			var capability  = $input.data( 'capability' );
			var label       = $input.data( 'label' );
			var newValue    = $input.is( ':checked' ) ? 1 : 0;
			var $cell       = $input.closest( '.dlt-ds-toggle-cell' );
			var $badge      = $cell.find( '.dlt-ds-badge' );
			var $spinner    = $cell.find( '.dlt-ds-spinner' );
			var $toggleWrap = $input.closest( '.dlt-ds-toggle' );

			// Administrators are hard-locked in PHP; JS guard is just extra safety.
			if ( role === 'administrator' ) {
				$input.prop( 'checked', true );
				return;
			}

			$spinner.show();
			$toggleWrap.addClass( 'is-loading' );
			$input.prop( 'disabled', true );

			$.ajax( {
				url  : dltAdmin.ajaxUrl,
				type : 'POST',
				data : {
					action     : 'dlt_save_role_capability',
					nonce      : dltAdmin.nonce,
					role       : role,
					capability : capability,
					value      : newValue
				},
				success: function ( response ) {
					$spinner.hide();
					$toggleWrap.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );

					if ( response.success ) {
						if ( newValue ) {
							$badge.text( 'On' ).removeClass( 'dlt-ds-badge-off' ).addClass( 'dlt-ds-badge-on' );
						} else {
							$badge.text( 'Off' ).removeClass( 'dlt-ds-badge-on' ).addClass( 'dlt-ds-badge-off' );
						}
						dltDsShowNotice( response.data.message, 'success' );
					} else {
						// Revert UI on error.
						$input.prop( 'checked', ! newValue );
						var errMsg = ( response.data && response.data.message )
							? response.data.message
							: 'An error occurred while saving "' + label + '" for this role.';
						dltDsShowNotice( errMsg, 'error' );
					}
				},
				error: function () {
					$spinner.hide();
					$toggleWrap.removeClass( 'is-loading' );
					$input.prop( 'disabled', false );
					$input.prop( 'checked', ! newValue );
					dltDsShowNotice( 'Server error while saving "' + label + '" for this role. Please try again.', 'error' );
				}
			} );
		} );

	} // end .dlt-display-settings-wrap

	/* ================================================================
	   Plan Price Manager — Inline Save
	   ================================================================ */

	if ( $( '.dlt-plan-manager-wrap' ).length ) {

		/* Safety check: if dltAdmin is not defined the script loaded but localization failed. */
		if ( typeof dltAdmin === 'undefined' ) {
			$( '#dlt-pm-global-message' )
				.html( '<div class="notice notice-error"><p><strong>Directorist Listing Tools:</strong> Script configuration missing (dltAdmin undefined). Please deactivate and re-activate the plugin, or clear your site cache.</p></div>' )
				.show();
			return; // Stop plan manager code — AJAX would fail anyway.
		}

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

	$( document ).on( 'click', '.dlt-af-why-button', function(e) {
		e.preventDefault();

		var $button = $( this );
		var $panel = $button.siblings( '.dlt-af-why-panel' );
		var featureId = $button.data( 'feature-id' );
		var expandText = $button.data( 'expand-text' ) || 'Why this..?';
		var collapseText = $button.data( 'collapse-text' ) || 'Hide details';

		if ( ! $panel.length || ! featureId ) {
			return;
		}

		if ( $panel.data( 'loaded' ) && ! $panel.prop( 'hidden' ) ) {
			$panel.prop( 'hidden', true ).removeClass( 'is-open' );
			$button.text( expandText ).attr( 'aria-expanded', 'false' );
			return;
		}

		if ( $panel.data( 'loaded' ) ) {
			$panel.prop( 'hidden', false ).addClass( 'is-open' );
			$button.text( collapseText ).attr( 'aria-expanded', 'true' );
			return;
		}

		$button.prop( 'disabled', true ).addClass( 'is-loading' );
		$panel
			.prop( 'hidden', false )
			.addClass( 'is-open is-loading' )
			.html( '<p class="dlt-af-why-loading">Loading details...</p>' );

		$.ajax( {
			url: dltAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dlt_get_apply_function_why',
				nonce: dltAdmin.nonce,
				feature_id: featureId
			}
		} )
			.done( function( response ) {
				if ( response && response.success && response.data && response.data.html ) {
					$panel.html( response.data.html ).data( 'loaded', true );
					$button.text( collapseText ).attr( 'aria-expanded', 'true' );
					return;
				}

				var message = response && response.data && response.data.message ? response.data.message : 'Could not load details.';
				$panel.html( '<p class="dlt-af-why-error">' + message + '</p>' );
				$button.text( expandText ).attr( 'aria-expanded', 'false' );
			} )
			.fail( function() {
				$panel.html( '<p class="dlt-af-why-error">Could not load details.</p>' );
				$button.text( expandText ).attr( 'aria-expanded', 'false' );
			} )
			.always( function() {
				$button.prop( 'disabled', false ).removeClass( 'is-loading' );
				$panel.removeClass( 'is-loading' );
			} );
	} );

})(jQuery);

