/**
 * WooCommerce FastSpring Gateway: checkout frontend logic.
 *
 * Intercepts WC checkout form submission, builds the FastSpring popup session,
 * and handles post-payment receipt flow.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */
(function ($) {
	'use strict';

	var checkoutForm = $('form.checkout');
	var params       = window.wc_fastspring_params || {};

	/**
	 * Build the WC AJAX endpoint URL for a given action.
	 *
	 * @param {string} endpoint Action suffix (e.g. "get_receipt").
	 * @return {string} Full AJAX URL.
	 */
	function getAjaxURL(endpoint) {
		return params.ajax_url.toString().replace('%%endpoint%%', 'wc_fastspring_' + endpoint);
	}

	/**
	 * Toggle the loading/processing overlay on the checkout form.
	 *
	 * @param {boolean} on Whether to show loading state.
	 */
	function setLoading(on) {
		if (on) {
			checkoutForm.addClass('processing').block({
				message: null,
				overlayCSS: { background: '#fff', opacity: 0.6 }
			});
		} else {
			checkoutForm.removeClass('processing').unblock();
		}
	}

	/**
	 * Check if FastSpring is the selected payment method.
	 *
	 * @return {boolean}
	 */
	function isFastSpringSelected() {
		return $('#payment_method_fastspring').is(':checked');
	}

	/**
	 * Display a checkout error and scroll to it.
	 *
	 * @param {string} message HTML error message.
	 */
	function submitError(message) {
		setLoading(false);

		$('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();
		checkoutForm.prepend(
			'<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + message + '</div>'
		);
		checkoutForm.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur');

		$('html, body').animate({ scrollTop: checkoutForm.offset().top - 100 }, 1000);
		$(document.body).trigger('checkout_error');
	}

	/**
	 * Submit the WC checkout form and retrieve the FastSpring session data.
	 *
	 * @param {Function} callback (err, result) callback.
	 */
	function createOrder(callback) {
		$.ajax({
			type:     'POST',
			url:      window.wc_checkout_params.checkout_url,
			data:     checkoutForm.serialize(),
			dataType: 'json',
			success: function (result) {
				if (result.result === 'success') {
					callback(null, result);
					return;
				}

				if (result.reload === true) {
					window.location.reload();
					return;
				}

				if (result.refresh === true) {
					$(document.body).trigger('update_checkout');
				}

				submitError(
					result.messages ||
					'<div class="woocommerce-error">' + window.wc_checkout_params.i18n_checkout_error + '</div>'
				);
			},
			error: function (jqXHR, textStatus, errorThrown) {
				submitError('<div class="woocommerce-error">' + errorThrown + '</div>');
			}
		});
	}

	/**
	 * Launch the FastSpring popup with the encrypted session.
	 *
	 * @param {Object} session Session object with payload + key.
	 */
	function launchFastSpring(session) {
		if (window.fastspring && window.fastspring.builder) {
			window.fastspring.builder.secure(session.payload, session.key);
			window.fastspring.builder.checkout();
		}
	}

	/**
	 * Request the receipt/redirect URL from the server after popup payment.
	 *
	 * @param {Object}   data     FastSpring popup close data.
	 * @param {Function} callback (err, response) callback.
	 */
	function requestReceipt(data, callback) {
		var payload = $.extend({}, data, { security: params.nonce.receipt });

		$.ajax({
			type:        'POST',
			dataType:    'json',
			contentType: 'application/json; charset=utf-8',
			data:        JSON.stringify(payload),
			url:         getAjaxURL('get_receipt'),
			success: function (response) {
				callback(null, response);
			},
			error: function (xhr) {
				callback(xhr.responseText);
			}
		});
	}

	/**
	 * Full checkout flow: create WC order, then launch FS popup.
	 */
	function doSubmit() {
		setLoading(true);

		createOrder(function (err, result) {
			if (!err && result.session) {
				launchFastSpring(result.session);
			}
		});
	}

	// -------------------------------------------------------------------------
	// Global callbacks required by FastSpring Store Builder Library.
	// These names are referenced in the SBL script tag data-* attributes.
	// -------------------------------------------------------------------------

	/**
	 * Called before FastSpring sends requests. Hides the loading overlay.
	 */
	window.fastspringBeforeRequestHandler = function () {
		setLoading(false);
	};

	/**
	 * Called when the FastSpring popup closes.
	 *
	 * If payment data is present (data.reference), request the receipt URL
	 * from the server and redirect the customer to the thank-you page.
	 *
	 * @param {Object|null} data FastSpring popup close data.
	 */
	window.fastspringPopupCloseHandler = function (data) {
		if (data && data.reference) {
			setLoading(true);
			requestReceipt(data, function (err, res) {
				if (!err && res.redirect_url) {
					window.location.href = res.redirect_url;
				} else {
					submitError('<div class="woocommerce-error">' + (err || 'Payment received but redirect failed. Please check your orders.') + '</div>');
				}
			});
		}
	};

	/**
	 * Debug callback: logs SBL data events to the console.
	 *
	 * @param {Object} data SBL data object.
	 */
	window.fastspringDataCallback = function (data) {
		if (window.console && window.console.log) {
			window.console.log('[FastSpring Data]', data);
		}
	};

	/**
	 * Debug callback: logs SBL errors to the console.
	 *
	 * @param {string} code    Error code.
	 * @param {string} message Error message.
	 */
	window.fastspringErrorCallback = function (code, message) {
		if (window.console && window.console.error) {
			window.console.error('[FastSpring Error]', code, message);
		}
	};

	// -------------------------------------------------------------------------
	// Bind to WooCommerce checkout form submission.
	// -------------------------------------------------------------------------

	checkoutForm.on('checkout_place_order', function () {
		if (isFastSpringSelected()) {
			doSubmit();
			return false;
		}
	});

})(jQuery);
