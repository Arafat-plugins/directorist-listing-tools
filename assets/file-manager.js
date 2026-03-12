/**
 * File Manager – Directorist Listing Tools
 * List, create folder/file, upload, download, delete (with confirmation).
 */
(function($) {
	'use strict';

	var config = {};
	var currentPath = '';
	var modalConfirmCallback = null;

	function getConfig() {
		var el = document.getElementById('dlt-fm-config');
		if (el && el.textContent) {
			try {
				config = JSON.parse(el.textContent);
			} catch (e) {}
		}
		return config.ajaxUrl && config.nonce;
	}

	function showLoading(show) {
		$('.dlt-fm-loading').toggle(!!show);
		$('.dlt-fm-list').toggle(!show);
		$('.dlt-fm-empty').hide();
	}

	function renderBreadcrumb(path, rootLabel) {
		var parts = path ? path.split('/').filter(Boolean) : [];
		var html = '<a href="#" class="dlt-fm-breadcrumb-item" data-path="">' + (rootLabel || 'Root') + '</a>';
		for (var i = 0; i < parts.length; i++) {
			var seg = parts.slice(0, i + 1).join('/');
			html += ' <span class="dlt-fm-breadcrumb-sep">/</span> <a href="#" class="dlt-fm-breadcrumb-item" data-path="' + escapeAttr(seg) + '">' + escapeHtml(parts[i]) + '</a>';
		}
		$('.dlt-fm-breadcrumb').html(html);
	}

	function escapeAttr(s) {
		if (s == null) return '';
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function escapeHtml(s) {
		if (s == null) return '';
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatSize(bytes) {
		if (bytes == null) return '—';
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	function formatDate(timestamp) {
		if (timestamp == null) return '—';
		var d = new Date(timestamp * 1000);
		return d.toLocaleString();
	}

	function loadList() {
		if (!getConfig()) return;
		showLoading(true);
		$.ajax({
			url: config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dlt_fm_list',
				nonce: config.nonce,
				path: currentPath
			},
			success: function(res) {
				showLoading(false);
				if (res.success && res.data) {
					renderBreadcrumb(res.data.path, res.data.root_label);
					var items = res.data.items || [];
					if (items.length === 0) {
						$('.dlt-fm-empty').show();
						$('.dlt-fm-list').empty();
					} else {
						$('.dlt-fm-empty').hide();
						var html = '';
						items.forEach(function(it) {
							var pathAttr = escapeAttr(it.path);
							var nameHtml = escapeHtml(it.name);
							if (it.is_dir) {
								html += '<div class="dlt-fm-row dlt-fm-dir" role="listitem" data-path="' + pathAttr + '">' +
									'<span class="dlt-fm-icon dashicons dashicons-category"></span>' +
									'<a href="#" class="dlt-fm-name dlt-fm-nav">' + nameHtml + '</a>' +
									'<span class="dlt-fm-size">—</span>' +
									'<span class="dlt-fm-mtime">' + escapeHtml(formatDate(it.mtime)) + '</span>' +
									'<span class="dlt-fm-actions">' +
									'<button type="button" class="button button-small dlt-fm-rename" data-path="' + pathAttr + '" data-name="' + escapeAttr(it.name) + '" data-dir="1" title="Rename folder">Rename</button> ' +
									'<button type="button" class="button button-small dlt-fm-delete" data-path="' + pathAttr + '" data-name="' + escapeAttr(it.name) + '" data-dir="1" title="Delete folder">Delete</button>' +
									'</span></div>';
							} else {
								var downloadUrl = config.ajaxUrl + '?action=dlt_fm_download&nonce=' + encodeURIComponent(config.nonce) + '&path=' + encodeURIComponent(it.path);
								html += '<div class="dlt-fm-row dlt-fm-file" role="listitem">' +
									'<span class="dlt-fm-icon dashicons dashicons-media-default"></span>' +
									'<span class="dlt-fm-name">' + nameHtml + '</span>' +
									'<span class="dlt-fm-size">' + escapeHtml(formatSize(it.size)) + '</span>' +
									'<span class="dlt-fm-mtime">' + escapeHtml(formatDate(it.mtime)) + '</span>' +
									'<span class="dlt-fm-actions">' +
									'<a href="' + escapeAttr(downloadUrl) + '" class="button button-small dlt-fm-download" download>Download</a> ' +
									'<button type="button" class="button button-small dlt-fm-edit" data-path="' + pathAttr + '" data-name="' + escapeAttr(it.name) + '" title="Edit file">Edit</button> ' +
									'<button type="button" class="button button-small dlt-fm-rename" data-path="' + pathAttr + '" data-name="' + escapeAttr(it.name) + '" data-dir="0" title="Rename file">Rename</button> ' +
									'<button type="button" class="button button-small dlt-fm-delete" data-path="' + pathAttr + '" data-name="' + escapeAttr(it.name) + '" data-dir="0" title="Delete file">Delete</button>' +
									'</span></div>';
							}
						});
						$('.dlt-fm-list').html(html);
					}
				} else {
					$('.dlt-fm-list').empty();
					$('.dlt-fm-empty').hide();
					alert(res.data && res.data.message ? res.data.message : 'Error loading list.');
				}
			},
			error: function(xhr, status, err) {
				showLoading(false);
				$('.dlt-fm-list').empty();
				alert('Request failed. Please try again.');
			}
		});
	}

	function showModal(title, bodyHtml, onConfirm) {
		modalConfirmCallback = onConfirm || null;
		$('#dlt-fm-modal .dlt-fm-modal-title').text(title);
		$('#dlt-fm-modal .dlt-fm-modal-body').html(bodyHtml);
		$('#dlt-fm-modal').show();
	}

	function hideModal() {
		$('#dlt-fm-modal').hide();
		modalConfirmCallback = null;
	}

	function showDeleteConfirm(path, name, isDir) {
		var typeLabel = isDir ? 'folder' : 'file';
		var msg = 'Are you sure you want to delete \"' + escapeHtml(name) + '\"? This action cannot be undone.';
		if (isDir) {
			msg += ' All files and subfolders inside will be permanently deleted.';
		}
		showModal('Delete ' + typeLabel, '<p>' + msg + '</p>', function() {
			doDelete(path);
		});
	}

	function doRename(path, newName) {
		if (!getConfig()) return;
		showLoading(true);
		$.ajax({
			url: config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dlt_fm_rename',
				nonce: config.nonce,
				path: path,
				new_name: newName
			},
			success: function(res) {
				showLoading(false);
				if (res.success) {
					loadList();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Rename failed.');
				}
			},
			error: function() {
				showLoading(false);
				alert('Request failed. Please try again.');
			}
		});
	}

	function showRenameModal(path, oldName) {
		showPromptModal('Rename', 'New name', oldName, function(val) {
			doRename(path, val);
		});
	}

	function detectEditorModeByName(name) {
		var lower = (name || '').toLowerCase();
		if (lower.endsWith('.php')) return 'application/x-httpd-php';
		if (lower.endsWith('.js')) return 'application/javascript';
		if (lower.endsWith('.css')) return 'text/css';
		if (lower.endsWith('.json')) return 'application/json';
		if (lower.endsWith('.xml')) return 'application/xml';
		if (lower.endsWith('.html') || lower.endsWith('.htm')) return 'text/html';
		if (lower.endsWith('.md')) return 'text/x-markdown';
		return 'text/plain';
	}

	function showEditorModal(path, name) {
		if (!getConfig()) return;
		showLoading(true);
		$.ajax({
			url: config.ajaxUrl,
			type: 'POST',
			data: { action: 'dlt_fm_get_file', nonce: config.nonce, path: path },
			success: function(res) {
				showLoading(false);
				if (!res.success) {
					alert(res.data && res.data.message ? res.data.message : 'Could not load file.');
					return;
				}
				var content = res.data && res.data.content != null ? res.data.content : '';
				var textareaId = 'dlt-fm-editor-' + Date.now();
				var body = '<div class="dlt-fm-editor-wrap">' +
					'<div class="dlt-fm-editor-path"><strong>' + escapeHtml(name) + '</strong></div>' +
					'<textarea id="' + textareaId + '" class="dlt-fm-editor-textarea"></textarea>' +
					'</div>';

				showModal('Edit file', body, function() {
					var val = $('#' + textareaId).val();
					showLoading(true);
					$.ajax({
						url: config.ajaxUrl,
						type: 'POST',
						data: { action: 'dlt_fm_save_file', nonce: config.nonce, path: path, content: val },
						success: function(saveRes) {
							showLoading(false);
							if (saveRes.success) {
								alert('Saved.');
								loadList();
							} else {
								alert(saveRes.data && saveRes.data.message ? saveRes.data.message : 'Save failed.');
							}
						},
						error: function() {
							showLoading(false);
							alert('Request failed.');
						}
					});
				});

				// Set initial content after modal is visible.
				setTimeout(function() {
					var $ta = $('#' + textareaId);
					$ta.val(content);

					// Initialize WordPress CodeMirror editor if available.
					if (window.wp && wp.codeEditor && window.dltFmEditorSettings) {
						var settings = $.extend(true, {}, window.dltFmEditorSettings);
						settings.codemirror = settings.codemirror || {};
						settings.codemirror.mode = detectEditorModeByName(name);
						try {
							wp.codeEditor.initialize(textareaId, settings);
						} catch (e) {}
					}
				}, 50);
			},
			error: function() {
				showLoading(false);
				alert('Request failed.');
			}
		});
	}

	function doDelete(path) {
		if (!getConfig()) return;
		hideModal();
		showLoading(true);
		$.ajax({
			url: config.ajaxUrl,
			type: 'POST',
			data: {
				action: 'dlt_fm_delete',
				nonce: config.nonce,
				path: path
			},
			success: function(res) {
				showLoading(false);
				if (res.success) {
					loadList();
				} else {
					alert(res.data && res.data.message ? res.data.message : 'Delete failed.');
				}
			},
			error: function() {
				showLoading(false);
				alert('Request failed. Please try again.');
			}
		});
	}

	function showPromptModal(title, label, placeholder, onConfirm) {
		var inputId = 'dlt-fm-prompt-input-' + Date.now();
		var body = '<label for="' + inputId + '">' + escapeHtml(label) + '</label><input type="text" id="' + inputId + '" class="dlt-fm-prompt-input regular-text" placeholder="' + escapeAttr(placeholder || '') + '">';
		showModal(title, body, function() {
			var val = $('#' + inputId).val().trim();
			if (val) {
				onConfirm(val);
			}
		});
		setTimeout(function() {
			$('#' + inputId).focus();
		}, 100);
	}

	$(document).ready(function() {
		if (!$('.dlt-file-manager-wrap').length) return;
		getConfig();
		loadList();

		// Breadcrumb navigation
		$(document).on('click', '.dlt-fm-breadcrumb-item', function(e) {
			e.preventDefault();
			currentPath = $(this).data('path') || '';
			loadList();
		});

		// Folder row: click name to navigate
		$(document).on('click', '.dlt-fm-nav', function(e) {
			e.preventDefault();
			currentPath = $(this).closest('.dlt-fm-row').data('path') || '';
			loadList();
		});

		// Delete button: show confirmation popup, then delete
		$(document).on('click', '.dlt-fm-delete', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var path = $(this).data('path');
			var name = $(this).data('name');
			var isDir = $(this).data('dir') === 1;
			showDeleteConfirm(path, name, isDir);
		});

		// Rename button
		$(document).on('click', '.dlt-fm-rename', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var path = $(this).data('path');
			var name = $(this).data('name');
			showRenameModal(path, name);
		});

		// Edit button
		$(document).on('click', '.dlt-fm-edit', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var path = $(this).data('path');
			var name = $(this).data('name');
			showEditorModal(path, name);
		});

		// Modal confirm
		$(document).on('click', '.dlt-fm-modal-confirm', function() {
			var $input = $('#dlt-fm-modal .dlt-fm-prompt-input');
			if ($input.length) {
				var val = $input.val().trim();
				if (!val) {
					alert('Please enter a name.');
					return;
				}
				modalConfirmCallback(val);
			} else if (modalConfirmCallback) {
				modalConfirmCallback();
			}
			hideModal();
		});

		$(document).on('click', '.dlt-fm-modal-cancel', function() {
			hideModal();
		});

		// New folder
		$('.dlt-fm-new-folder').on('click', function() {
			showPromptModal('New folder', 'Folder name', 'my-folder', function(name) {
				if (!getConfig()) return;
				showLoading(true);
				$.ajax({
					url: config.ajaxUrl,
					type: 'POST',
					data: {
						action: 'dlt_fm_create_folder',
						nonce: config.nonce,
						path: currentPath,
						name: name
					},
					success: function(res) {
						showLoading(false);
						if (res.success) {
							loadList();
						} else {
							alert(res.data && res.data.message ? res.data.message : 'Failed to create folder.');
						}
					},
					error: function() {
						showLoading(false);
						alert('Request failed.');
					}
				});
			});
		});

		// New file
		$('.dlt-fm-new-file').on('click', function() {
			showPromptModal('New file', 'File name', 'file.txt', function(name) {
				if (!getConfig()) return;
				showLoading(true);
				$.ajax({
					url: config.ajaxUrl,
					type: 'POST',
					data: {
						action: 'dlt_fm_create_file',
						nonce: config.nonce,
						path: currentPath,
						name: name
					},
					success: function(res) {
						showLoading(false);
						if (res.success) {
							loadList();
						} else {
							alert(res.data && res.data.message ? res.data.message : 'Failed to create file.');
						}
					},
					error: function() {
						showLoading(false);
						alert('Request failed.');
					}
				});
			});
		});

		// Upload
		$('.dlt-fm-upload-btn').on('click', function() {
			$('#dlt-fm-upload-input').click();
		});

		$('#dlt-fm-upload-input').on('change', function() {
			var files = this.files;
			if (!files || !files.length || !getConfig()) return;
			var index = 0;
			function uploadNext() {
				if (index >= files.length) {
					loadList();
					$('#dlt-fm-upload-input').val('');
					return;
				}
				var fd = new FormData();
				fd.append('action', 'dlt_fm_upload');
				fd.append('nonce', config.nonce);
				fd.append('path', currentPath);
				fd.append('file', files[index]);
				$.ajax({
					url: config.ajaxUrl,
					type: 'POST',
					data: fd,
					processData: false,
					contentType: false,
					success: function() { index++; uploadNext(); },
					error: function() {
						alert('Upload failed for: ' + files[index].name);
						index++;
						uploadNext();
					}
				});
			}
			uploadNext();
		});
	});
})(jQuery);
