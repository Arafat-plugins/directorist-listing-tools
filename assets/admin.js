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
})(jQuery);

