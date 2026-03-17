<?php
/**
 * Gateway settings field definitions.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring\Admin;

final class Settings {

	private function __construct() {}

	/**
	 * Get all gateway settings fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields(): array {
		return apply_filters(
			'wc_fastspring_gateway_settings',
			array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-fastspring-gateway' ),
					'label'   => __( 'Enable FastSpring payment gateway', 'woocommerce-fastspring-gateway' ),
					'type'    => 'checkbox',
					'default' => 'no',
				),
				'title'              => array(
					'title'       => __( 'Title', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'The title displayed during checkout.', 'woocommerce-fastspring-gateway' ),
					'default'     => __( 'Pay with FastSpring', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
				'description'        => array(
					'title'       => __( 'Description', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'The description displayed when selecting this payment method.', 'woocommerce-fastspring-gateway' ),
					'default'     => __( 'Pay with credit card, PayPal, Amazon Pay and more.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
				'icons'              => array(
					'title'       => __( 'Payment Icons', 'woocommerce-fastspring-gateway' ),
					'type'        => 'multiselect',
					'description' => __( 'Select payment icons to display at checkout.', 'woocommerce-fastspring-gateway' ),
					'default'     => array( 'paypal', 'visa', 'mastercard', 'amex' ),
					'desc_tip'    => true,
					'class'       => 'wc-enhanced-select',
					'options'     => array(
						'paypal'     => 'PayPal',
						'visa'       => 'Visa',
						'mastercard' => 'Mastercard',
						'amex'       => 'American Express',
						'discover'   => 'Discover',
						'jcb'        => 'JCB',
						'diners'     => 'Diners Club',
						'ideal'      => 'iDEAL',
						'unionpay'   => 'UnionPay',
						'sofort'     => 'SOFORT',
					),
				),
				'testmode'           => array(
					'title'       => __( 'Test Mode', 'woocommerce-fastspring-gateway' ),
					'label'       => __( 'Enable test mode', 'woocommerce-fastspring-gateway' ),
					'type'        => 'checkbox',
					'description' => sprintf(
						/* translators: %s: FastSpring test orders documentation URL */
						__( 'Place the gateway in test mode. See <a href="%s" target="_blank">Testing Orders</a> for details.', 'woocommerce-fastspring-gateway' ),
						'https://developer.fastspring.com/docs/testing-overview'
					),
					'default'     => 'no',
					'desc_tip'    => false,
				),
				'logging'            => array(
					'title'       => __( 'Logging', 'woocommerce-fastspring-gateway' ),
					'label'       => __( 'Enable debug logging', 'woocommerce-fastspring-gateway' ),
					'type'        => 'checkbox',
					'description' => __( 'Log debug messages to WooCommerce > Status > Logs.', 'woocommerce-fastspring-gateway' ),
					'default'     => 'no',
				),
				'storefront_path'    => array(
					'title'       => __( 'Storefront Path', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'Your FastSpring storefront URL (e.g., yourstore.onfastspring.com/popup-checkout).', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => false,
				),
				'billing_address'    => array(
					'title'       => __( 'Billing Address', 'woocommerce-fastspring-gateway' ),
					'label'       => __( 'Remove billing address fields from checkout', 'woocommerce-fastspring-gateway' ),
					'type'        => 'checkbox',
					'description' => __( 'FastSpring collects billing information. Remove duplicate fields if no other gateway requires them.', 'woocommerce-fastspring-gateway' ),
					'default'     => 'yes',
				),
				'api_details'        => array(
					'title'       => __( 'Access Credentials', 'woocommerce-fastspring-gateway' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: %s: FastSpring secure payloads documentation URL */
						__( 'Your Access Key and Private Key encrypt order data sent to FastSpring. See the <a href="%s" target="_blank">Secure Payloads</a> documentation for setup instructions.', 'woocommerce-fastspring-gateway' ),
						'https://developer.fastspring.com/reference/pass-a-secure-request'
					),
				),
				'access_key'         => array(
					'title'       => __( 'Access Key', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'From Developer Tools > Store Builder Library in your FastSpring dashboard.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
				'private_key'        => array(
					'title'       => __( 'Private Key (RSA 2048-bit)', 'woocommerce-fastspring-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Your RSA private key in PEM format. Upload the corresponding public key to FastSpring.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
				'order_verification' => array(
					'title'       => __( 'Order Verification', 'woocommerce-fastspring-gateway' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: %s: webhook endpoint URL */
						__( 'Configure webhook or API verification. Your webhook URL: %s', 'woocommerce-fastspring-gateway' ),
						'<code>' . esc_url( site_url( '?wc-api=wc_gateway_fastspring', 'https' ) ) . '</code>'
					),
				),
				'webhook_secret'     => array(
					'title'       => __( 'Webhook Secret', 'woocommerce-fastspring-gateway' ),
					'type'        => 'password',
					'description' => __( 'HMAC SHA256 secret for webhook signature verification. Must match FastSpring > Developer Tools > Webhooks.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => false,
					'default'     => wp_generate_password( 40, false ),
				),
				'webhook_ip_filter'  => array(
					'title'       => __( 'Webhook IP Filter', 'woocommerce-fastspring-gateway' ),
					'label'       => __( 'Only accept webhooks from FastSpring IP', 'woocommerce-fastspring-gateway' ),
					'type'        => 'checkbox',
					'description' => __( 'Additional security: restrict webhooks to FastSpring server IP address.', 'woocommerce-fastspring-gateway' ),
					'default'     => 'no',
				),
				'api_username'       => array(
					'title'       => __( 'API Username', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'From Developer Tools > API Credentials in your FastSpring dashboard.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
				'api_password'       => array(
					'title'       => __( 'API Password', 'woocommerce-fastspring-gateway' ),
					'type'        => 'password',
					'description' => __( 'Your FastSpring API password.', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => true,
				),
			)
		);
	}
}
