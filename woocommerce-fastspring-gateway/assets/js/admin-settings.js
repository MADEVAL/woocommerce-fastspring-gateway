/**
 * WooCommerce FastSpring Gateway: admin settings interactive sidebar.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */
(function ($) {
	'use strict';

	var fieldCardMap = {
		'woocommerce_fastspring_storefront_path': 'help-storefront',
		'woocommerce_fastspring_access_key': 'help-access-key',
		'woocommerce_fastspring_private_key': 'help-private-key',
		'woocommerce_fastspring_webhook_secret': 'help-webhook',
		'woocommerce_fastspring_webhook_ip_filter': 'help-webhook',
		'woocommerce_fastspring_api_username': 'help-api',
		'woocommerce_fastspring_api_password': 'help-api',
		'woocommerce_fastspring_enabled': 'help-display',
		'woocommerce_fastspring_title': 'help-display',
		'woocommerce_fastspring_description': 'help-display',
		'woocommerce_fastspring_icons': 'help-display',
		'woocommerce_fastspring_billing_address': 'help-display',
		'woocommerce_fastspring_testmode': 'help-testing',
		'woocommerce_fastspring_logging': 'help-testing'
	};

	var requiredFields = [
		'woocommerce_fastspring_storefront_path',
		'woocommerce_fastspring_access_key',
		'woocommerce_fastspring_private_key',
		'woocommerce_fastspring_webhook_secret'
	];

	var recommendedFields = [
		'woocommerce_fastspring_api_username',
		'woocommerce_fastspring_api_password'
	];

	function addFieldBadges() {
		var l10n = window.wcFsAdminL10n || {};
		var i, $field, $label;

		for (i = 0; i < requiredFields.length; i++) {
			$field = $('#' + requiredFields[i]);
			if ($field.length) {
				$label = $field.closest('tr').find('th label');
				if ($label.length && !$label.find('.wc-fs-field-badge').length) {
					$label.append(
						'<span class="wc-fs-field-badge wc-fs-field-badge-required">' +
						(l10n.required || 'Required') +
						'</span>'
					);
				}
			}
		}

		for (i = 0; i < recommendedFields.length; i++) {
			$field = $('#' + recommendedFields[i]);
			if ($field.length) {
				$label = $field.closest('tr').find('th label');
				if ($label.length && !$label.find('.wc-fs-field-badge').length) {
					$label.append(
						'<span class="wc-fs-field-badge wc-fs-field-badge-recommended">' +
						(l10n.recommended || 'Recommended') +
						'</span>'
					);
				}
			}
		}
	}

	function initGenerateKeyPair() {
		var l10n = window.wcFsAdminL10n || {};
		var $textarea = $('#woocommerce_fastspring_private_key');
		if (!$textarea.length) {
			return;
		}

		var $wrap = $('<div class="wc-fs-keygen-wrap"></div>');
		var $btn = $('<button type="button" class="button wc-fs-generate-keys-btn">' +
			'<span class="dashicons dashicons-admin-network"></span> ' +
			(l10n.generateBtn || 'Generate Key Pair') +
			'</button>');
		var $status = $('<div class="wc-fs-keygen-status"></div>');

		$wrap.append($btn).append($status);
		$textarea.after($wrap);

		$btn.on('click', function () {
			var hasKey = $.trim($textarea.val()).length > 0;
			if (hasKey && !window.confirm(l10n.confirmOverwrite || 'This will replace your current private key. Continue?')) {
				return;
			}

			$btn.prop('disabled', true).text(l10n.generating || 'Generating...');
			$status.empty();

			$.post(l10n.ajaxUrl, {
				action: 'wc_fastspring_generate_rsa_keys',
				nonce: l10n.generateNonce
			}, function (response) {
				$btn.prop('disabled', false).html(
					'<span class="dashicons dashicons-admin-network"></span> ' +
					(l10n.generateBtn || 'Generate Key Pair')
				);

				if (response.success) {
					$textarea.val(response.data.private_key).trigger('change');

					$status.html(
						'<div class="wc-fs-keygen-success">' +
						'<span class="dashicons dashicons-yes-alt"></span> ' +
						(l10n.generateSuccess || 'Keys generated!') +
						'</div>' +
						'<a href="#" class="button button-secondary wc-fs-download-cert-btn">' +
						'<span class="dashicons dashicons-download"></span> ' +
						(l10n.downloadCert || 'Download Public Certificate') +
						'</a>'
					);

					$status.find('.wc-fs-download-cert-btn').on('click', function (e) {
						e.preventDefault();
						var blob = new Blob([response.data.certificate], { type: 'application/x-pem-file' });
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'fastspring-publiccert.pem';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
					});
				} else {
					$status.html(
						'<div class="wc-fs-keygen-error">' +
						'<span class="dashicons dashicons-warning"></span> ' +
						(response.data && response.data.message ? response.data.message : (l10n.generateError || 'Key generation failed.')) +
						'</div>'
					);
				}
			}).fail(function () {
				$btn.prop('disabled', false).html(
					'<span class="dashicons dashicons-admin-network"></span> ' +
					(l10n.generateBtn || 'Generate Key Pair')
				);
				$status.html(
					'<div class="wc-fs-keygen-error">' +
					'<span class="dashicons dashicons-warning"></span> ' +
					(l10n.generateError || 'Key generation failed.') +
					'</div>'
				);
			});
		});
	}

	function highlightCard(cardId) {
		var $card = $('[data-help-id="' + cardId + '"]');
		if (!$card.length) {
			return;
		}

		$('.wc-fs-help-card').removeClass('is-active');
		$card.addClass('is-active');

		var $sidebar = $('.wc-fs-settings-sidebar');
		if ($sidebar.length) {
			var cardOffset = $card[0].offsetTop;
			var sidebarScroll = $sidebar.scrollTop();
			var sidebarHeight = $sidebar.innerHeight();
			var cardHeight = $card.outerHeight();

			if (cardOffset < sidebarScroll || cardOffset + cardHeight > sidebarScroll + sidebarHeight) {
				$sidebar.animate({ scrollTop: cardOffset - 10 }, 250);
			}
		}
	}

	function init() {
		addFieldBadges();
		initGenerateKeyPair();

		$('.wc-fs-settings-main').on('focusin click', 'input, textarea, select', function () {
			var id = $(this).attr('id') || '';
			if (fieldCardMap[id]) {
				highlightCard(fieldCardMap[id]);
			}
		});

		$('.wc-fs-webhook-url-box').on('click', function () {
			var url = $(this).attr('data-url');
			if (url && navigator.clipboard) {
				var $hint = $(this).find('.wc-fs-copy-hint');
				var original = $hint.text();
				var l10n = window.wcFsAdminL10n || {};
				navigator.clipboard.writeText(url).then(function () {
					$hint.text(l10n.copied || 'Copied!');
					setTimeout(function () { $hint.text(original); }, 2000);
				});
			}
		});
	}

	$(init);
})(jQuery);
