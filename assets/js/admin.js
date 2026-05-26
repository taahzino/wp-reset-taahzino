(function ($) {
	'use strict';

	function BatchProcessor(config) {
		this.$button        = $(config.buttonId);
		this.$cancelButton  = $(config.cancelButtonId);
		this.$progressWrap  = $(config.progressWrapId);
		this.$progressFill  = $(config.progressFillId);
		this.$progressText  = $(config.progressTextId);
		this.$result        = $(config.resultId);
		this.$count         = $(config.countId);
		this.action         = config.action;
		this.nonce          = config.nonce;
		this.confirmMsg     = config.confirmMsg;
		this.processingMsg  = config.processingMsg;
		this.progressMsg    = config.progressMsg;
		this.completeMsg    = config.completeMsg;
		this.emptyMsg       = config.emptyMsg;
		this.cancelledMsg   = config.cancelledMsg;
		this.responseKey    = config.responseKey;
		this.extraData      = config.extraData || null;
		this.onComplete     = config.onComplete || null;
		this.totalProcessed = 0;
		this.cancelled      = false;
	}

	BatchProcessor.prototype.init = function () {
		if (!this.$button.length) {
			return;
		}
		var self = this;
		this.$button.on('click', function () {
			self.start();
		});
		this.$cancelButton.on('click', function () {
			self.cancel();
		});
	};

	BatchProcessor.prototype.start = function () {
		var msg = typeof this.confirmMsg === 'function' ? this.confirmMsg() : this.confirmMsg;
		if (!confirm(msg)) {
			return;
		}

		this.totalProcessed = 0;
		this.cancelled = false;
		this.$button.hide();
		this.$cancelButton.show().prop('disabled', false);
		this.$result.hide().empty().removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning');
		this.$progressWrap.show();
		this.$progressFill.css('width', '0%');
		this.$progressText.text(this.processingMsg);

		this.processBatch();
	};

	BatchProcessor.prototype.cancel = function () {
		this.cancelled = true;
		this.$cancelButton.prop('disabled', true);
		this.$progressText.text(wrtData.i18n.cancelling);
	};

	BatchProcessor.prototype.processBatch = function () {
		var self = this;

		var ajaxData = {
			action: self.action,
			nonce:  self.nonce,
		};

		if (typeof self.extraData === 'function') {
			$.extend(ajaxData, self.extraData());
		} else if (self.extraData) {
			$.extend(ajaxData, self.extraData);
		}

		$.ajax({
			url:      wrtData.ajaxUrl,
			type:     'POST',
			dataType: 'json',
			data:     ajaxData,
			success: function (response) {
				if (!response.success) {
					self.showError(response.data && response.data.message ? response.data.message : wrtData.i18n.error);
					return;
				}

				var data      = response.data;
				var processed = data[self.responseKey];
				var remaining = data.remaining;

				if (processed === 0 && remaining === 0) {
					if (self.totalProcessed === 0) {
						self.showResult(self.emptyMsg, 'wrt-notice-warning');
						self.resetButtons(true);
					} else {
						self.showComplete();
					}
					return;
				}

				self.totalProcessed += processed;
				self.updateProgress(self.totalProcessed, self.totalProcessed + remaining);

				if (remaining > 0) {
					if (self.cancelled) {
						self.showCancelled();
					} else {
						self.processBatch();
					}
				} else {
					self.showComplete();
				}
			},
			error: function () {
				self.showError(wrtData.i18n.error);
			},
		});
	};

	BatchProcessor.prototype.updateProgress = function (done, total) {
		var pct = total > 0 ? Math.round((done / total) * 100) : 100;
		this.$progressFill.css('width', pct + '%');

		var text = this.progressMsg
			.replace('%1$d', done)
			.replace('%2$d', total);
		this.$progressText.text(text);
	};

	BatchProcessor.prototype.showComplete = function () {
		this.$progressFill.css('width', '100%');
		this.$progressText.text('');
		this.showResult(this.completeMsg, 'wrt-notice-success');
		this.$count.text('0');
		this.resetButtons(true);

		if (typeof this.onComplete === 'function') {
			this.onComplete();
		}
	};

	BatchProcessor.prototype.showCancelled = function () {
		this.$progressText.text('');
		var message = this.cancelledMsg.replace('%d', this.totalProcessed);
		this.showResult(message, 'wrt-notice-warning');
		this.resetButtons(false);
	};

	BatchProcessor.prototype.showError = function (message) {
		this.showResult(message, 'wrt-notice-error');
		this.resetButtons(false);
	};

	BatchProcessor.prototype.resetButtons = function (disableAction) {
		this.$cancelButton.hide();
		this.$button.show().prop('disabled', disableAction);
	};

	BatchProcessor.prototype.showResult = function (message, className) {
		this.$result
			.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
			.addClass('wrt-notice ' + className)
			.html('<p>' + message + '</p>')
			.show();
	};

	$(function () {
		new BatchProcessor({
			buttonId:       '#wrt-trash-orders',
			cancelButtonId: '#wrt-cancel-trash',
			progressWrapId: '#wrt-progress-wrap',
			progressFillId: '#wrt-progress-fill',
			progressTextId: '#wrt-progress-text',
			resultId:       '#wrt-result',
			countId:        '#wrt-order-count',
			action:         'wrt_trash_orders',
			nonce:          wrtData.nonceTrash,
			confirmMsg:     wrtData.i18n.confirmTrash,
			processingMsg:  wrtData.i18n.trashing,
			progressMsg:    wrtData.i18n.trashed,
			completeMsg:    wrtData.i18n.trashComplete,
			emptyMsg:       wrtData.i18n.noOrders,
			cancelledMsg:   wrtData.i18n.cancelledTrash,
			responseKey:    'trashed',
		}).init();

		new BatchProcessor({
			buttonId:       '#wrt-empty-trash-orders',
			cancelButtonId: '#wrt-cancel-empty',
			progressWrapId: '#wrt-empty-progress-wrap',
			progressFillId: '#wrt-empty-progress-fill',
			progressTextId: '#wrt-empty-progress-text',
			resultId:       '#wrt-empty-result',
			countId:        '#wrt-trashed-count',
			action:         'wrt_empty_trash_orders',
			nonce:          wrtData.nonceEmptyTrash,
			confirmMsg:     wrtData.i18n.confirmEmpty,
			processingMsg:  wrtData.i18n.deleting,
			progressMsg:    wrtData.i18n.deleted,
			completeMsg:    wrtData.i18n.emptyComplete,
			emptyMsg:       wrtData.i18n.noTrashedOrders,
			cancelledMsg:   wrtData.i18n.cancelledEmpty,
			responseKey:    'deleted',
		}).init();

		var $roleSelect = $('#wrt-user-role');
		var $userCount  = $('#wrt-user-count');
		var $deleteBtn  = $('#wrt-delete-users');

		if ($roleSelect.length) {
			$roleSelect.on('change', function () {
				var $selected = $roleSelect.find(':selected');
				var count = parseInt($selected.data('count'), 10) || 0;
				$userCount.text(count);
				$deleteBtn.prop('disabled', count === 0 || !$selected.val());
			});

			new BatchProcessor({
				buttonId:       '#wrt-delete-users',
				cancelButtonId: '#wrt-cancel-users',
				progressWrapId: '#wrt-users-progress-wrap',
				progressFillId: '#wrt-users-progress-fill',
				progressTextId: '#wrt-users-progress-text',
				resultId:       '#wrt-users-result',
				countId:        '#wrt-user-count',
				action:         'wrt_delete_users',
				nonce:          wrtData.nonceDeleteUsers,
				confirmMsg:     wrtData.i18n.confirmDeleteUsers,
				processingMsg:  wrtData.i18n.deletingUsers,
				progressMsg:    wrtData.i18n.deletedUsers,
				completeMsg:    wrtData.i18n.deleteUsersComplete,
				emptyMsg:       wrtData.i18n.noUsersFound,
				cancelledMsg:   wrtData.i18n.cancelledUsers,
				responseKey:    'deleted',
				extraData: function () {
					return { role: $roleSelect.val() };
				},
				onComplete: function () {
					var $selected = $roleSelect.find(':selected');
					$selected.data('count', 0);
					$selected.text($selected.text().replace(/\(\d[\d,]*\)/, '(0)'));
				},
			}).init();
		}

		// ---- Media by prefix ----
		var $mediaPrefix    = $('#wrt-media-prefix');
		var $mediaSearchBtn = $('#wrt-search-media');
		var $mediaCountText = $('#wrt-media-count-text');
		var $mediaDeleteBtn = $('#wrt-delete-media');
		var mediaFoundCount = 0;

		if ($mediaPrefix.length) {
			$mediaPrefix.on('input', function () {
				mediaFoundCount = 0;
				$mediaCountText.hide().empty();
				$mediaDeleteBtn.prop('disabled', true);
			});

			$mediaSearchBtn.on('click', function () {
				var prefix = $.trim($mediaPrefix.val());
				if (!prefix) {
					$mediaCountText
						.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
						.addClass('wrt-notice wrt-notice-warning')
						.html('<p>' + wrtData.i18n.mediaPrefixRequired + '</p>')
						.show();
					return;
				}

				$mediaSearchBtn.prop('disabled', true).text(wrtData.i18n.mediaSearching);
				$mediaCountText.hide().empty();
				$mediaDeleteBtn.prop('disabled', true);
				mediaFoundCount = 0;

				$.ajax({
					url:      wrtData.ajaxUrl,
					type:     'POST',
					dataType: 'json',
					data: {
						action: 'wrt_count_media_by_prefix',
						nonce:  wrtData.nonceMedia,
						prefix: prefix,
					},
					success: function (response) {
						$mediaSearchBtn.prop('disabled', false).text('Search');
						if (!response.success) {
							$mediaCountText
								.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
								.addClass('wrt-notice wrt-notice-error')
								.html('<p>' + (response.data && response.data.message ? response.data.message : wrtData.i18n.error) + '</p>')
								.show();
							return;
						}

						mediaFoundCount = parseInt(response.data.count, 10) || 0;
						if (mediaFoundCount > 0) {
							var msg = wrtData.i18n.mediaFound.replace('%d', mediaFoundCount);
							$mediaCountText
								.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
								.addClass('wrt-notice wrt-notice-success')
								.html('<p>' + msg + '</p>')
								.show();
							$mediaDeleteBtn.prop('disabled', false);
						} else {
							$mediaCountText
								.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
								.addClass('wrt-notice wrt-notice-warning')
								.html('<p>' + wrtData.i18n.mediaNotFound + '</p>')
								.show();
						}
					},
					error: function () {
						$mediaSearchBtn.prop('disabled', false).text('Search');
						$mediaCountText
							.removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning')
							.addClass('wrt-notice wrt-notice-error')
							.html('<p>' + wrtData.i18n.error + '</p>')
							.show();
					},
				});
			});

			new BatchProcessor({
				buttonId:       '#wrt-delete-media',
				cancelButtonId: '#wrt-cancel-media',
				progressWrapId: '#wrt-media-progress-wrap',
				progressFillId: '#wrt-media-progress-fill',
				progressTextId: '#wrt-media-progress-text',
				resultId:       '#wrt-media-result',
				countId:        '#wrt-media-count-text',
				action:         'wrt_delete_media_by_prefix',
				nonce:          wrtData.nonceMedia,
				confirmMsg: function () {
					return wrtData.i18n.confirmMediaDelete.replace('%d', mediaFoundCount);
				},
				processingMsg:  wrtData.i18n.deletingMedia,
				progressMsg:    wrtData.i18n.deletedMedia,
				completeMsg:    wrtData.i18n.mediaDeleteComplete,
				emptyMsg:       wrtData.i18n.mediaNotFound,
				cancelledMsg:   wrtData.i18n.cancelledMedia,
				responseKey:    'deleted',
				extraData: function () {
					return { prefix: $.trim($mediaPrefix.val()) };
				},
				onComplete: function () {
					mediaFoundCount = 0;
					$mediaDeleteBtn.prop('disabled', true);
				},
			}).init();
		}
		// ---- End media by prefix ----

		var $cptSelect  = $('#wrt-cpt-select');
		var $cptCount   = $('#wrt-cpt-count');
		var $cptBtn     = $('#wrt-delete-cpt-items');

		if ($cptSelect.length) {
			$cptSelect.on('change', function () {
				var $selected = $cptSelect.find(':selected');
				var count = parseInt($selected.data('count'), 10) || 0;
				$cptCount.text(count);
				$cptBtn.prop('disabled', count === 0 || !$selected.val());
			});

			new BatchProcessor({
				buttonId:       '#wrt-delete-cpt-items',
				cancelButtonId: '#wrt-cancel-cpt',
				progressWrapId: '#wrt-cpt-progress-wrap',
				progressFillId: '#wrt-cpt-progress-fill',
				progressTextId: '#wrt-cpt-progress-text',
				resultId:       '#wrt-cpt-result',
				countId:        '#wrt-cpt-count',
				action:         'wrt_delete_cpt_items',
				nonce:          wrtData.nonceCptItems,
				confirmMsg:     wrtData.i18n.confirmCptDelete,
				processingMsg:  wrtData.i18n.deletingCpt,
				progressMsg:    wrtData.i18n.deletedCpt,
				completeMsg:    wrtData.i18n.cptDeleteComplete,
				emptyMsg:       wrtData.i18n.noCptItems,
				cancelledMsg:   wrtData.i18n.cancelledCpt,
				responseKey:    'deleted',
				extraData: function () {
					return { post_type: $cptSelect.val() };
				},
				onComplete: function () {
					var $selected = $cptSelect.find(':selected');
					$selected.data('count', 0);
					$selected.text($selected.text().replace(/\(\d[\d,]*\)/, '(0)'));
				},
			}).init();
		}
	});

})(jQuery);
