(function ($) {
	'use strict';

	function BatchProcessor(config) {
		this.$button       = $(config.buttonId);
		this.$progressWrap = $(config.progressWrapId);
		this.$progressFill = $(config.progressFillId);
		this.$progressText = $(config.progressTextId);
		this.$result       = $(config.resultId);
		this.$count        = $(config.countId);
		this.action        = config.action;
		this.nonce         = config.nonce;
		this.confirmMsg    = config.confirmMsg;
		this.processingMsg = config.processingMsg;
		this.progressMsg   = config.progressMsg;
		this.completeMsg   = config.completeMsg;
		this.emptyMsg      = config.emptyMsg;
		this.responseKey   = config.responseKey;
		this.totalProcessed = 0;
	}

	BatchProcessor.prototype.init = function () {
		if (!this.$button.length) {
			return;
		}
		var self = this;
		this.$button.on('click', function () {
			self.start();
		});
	};

	BatchProcessor.prototype.start = function () {
		if (!confirm(this.confirmMsg)) {
			return;
		}

		this.totalProcessed = 0;
		this.$button.prop('disabled', true);
		this.$result.hide().empty().removeClass('wrt-notice-success wrt-notice-error wrt-notice-warning');
		this.$progressWrap.show();
		this.$progressFill.css('width', '0%');
		this.$progressText.text(this.processingMsg);

		this.processBatch();
	};

	BatchProcessor.prototype.processBatch = function () {
		var self = this;

		$.ajax({
			url:      wrtData.ajaxUrl,
			type:     'POST',
			dataType: 'json',
			data: {
				action: self.action,
				nonce:  self.nonce,
			},
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
					} else {
						self.showComplete();
					}
					return;
				}

				self.totalProcessed += processed;
				self.updateProgress(self.totalProcessed, self.totalProcessed + remaining);

				if (remaining > 0) {
					self.processBatch();
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
		this.$button.prop('disabled', true);
	};

	BatchProcessor.prototype.showError = function (message) {
		this.showResult(message, 'wrt-notice-error');
		this.$button.prop('disabled', false);
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
			responseKey:    'trashed',
		}).init();

		new BatchProcessor({
			buttonId:       '#wrt-empty-trash-orders',
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
			responseKey:    'deleted',
		}).init();
	});

})(jQuery);
