/**
 * WooCommerce FastSpring Gateway: admin settings interactive sidebar.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */
(function ($) {
	'use strict';

	var fieldCardMap = {
		'woocommerce_fastspring_storefront_path': 'help-storefront',
		'woocommerce_fastspring_api_username': 'help-api',
		'woocommerce_fastspring_api_password': 'help-api',
		'woocommerce_fastspring_webhook_secret': 'help-webhook',
		'woocommerce_fastspring_webhook_ip_filter': 'help-webhook',
		'woocommerce_fastspring_enabled': 'help-display',
		'woocommerce_fastspring_title': 'help-display',
		'woocommerce_fastspring_description': 'help-display',
		'woocommerce_fastspring_icons': 'help-display',
		'woocommerce_fastspring_testmode': 'help-testing',
		'woocommerce_fastspring_logging': 'help-testing'
	};

	var requiredFields = [
		'woocommerce_fastspring_storefront_path',
		'woocommerce_fastspring_api_username',
		'woocommerce_fastspring_api_password',
		'woocommerce_fastspring_webhook_secret'
	];

	var recommendedFields = [];

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
