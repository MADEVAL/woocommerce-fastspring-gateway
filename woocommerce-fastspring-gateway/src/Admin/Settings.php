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
				'api_details'        => array(
					'title'       => __( 'FastSpring API', 'woocommerce-fastspring-gateway' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: %s: FastSpring API documentation URL */
						__( 'Enter your FastSpring API credentials and storefront URL. See the <a href="%s" target="_blank">API documentation</a> for details.', 'woocommerce-fastspring-gateway' ),
						'https://developer.fastspring.com/reference/getting-started-with-your-api'
					),
				),
				'storefront_path'    => array(
					'title'       => __( 'Storefront URL', 'woocommerce-fastspring-gateway' ),
					'type'        => 'text',
					'description' => __( 'Your FastSpring storefront domain (e.g., yourstore.onfastspring.com).', 'woocommerce-fastspring-gateway' ),
					'desc_tip'    => false,
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
				'order_verification' => array(
					'title'       => __( 'Webhooks', 'woocommerce-fastspring-gateway' ),
					'type'        => 'title',
					'description' => sprintf(
						/* translators: %s: webhook endpoint URL */
						__( 'Configure webhook notifications from FastSpring. Your webhook URL: %s', 'woocommerce-fastspring-gateway' ),
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
			)
		);
	}

	/**
	 * Render the contextual help sidebar for the settings page.
	 */
	public static function render_help_sidebar(): void {
		$webhook_url = site_url( '?wc-api=wc_gateway_fastspring', 'https' );
		$allowed     = array(
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'strong' => array(),
			'code'   => array(),
		);
		?>
		<div class="wc-fs-help-card wc-fs-help-card-overview" data-help-id="help-overview">
			<h4><?php esc_html_e( 'Quick Setup Guide', 'woocommerce-fastspring-gateway' ); ?></h4>
			<ol>
				<li><?php esc_html_e( 'Enter your Storefront URL', 'woocommerce-fastspring-gateway' ); ?></li>
				<li><?php esc_html_e( 'Create API credentials and paste them here', 'woocommerce-fastspring-gateway' ); ?></li>
				<li><?php esc_html_e( 'Set up the webhook URL and HMAC secret in FastSpring', 'woocommerce-fastspring-gateway' ); ?></li>
				<li><?php esc_html_e( 'Link WooCommerce products to FastSpring product paths', 'woocommerce-fastspring-gateway' ); ?></li>
				<li><?php esc_html_e( 'Enable the gateway and test a payment', 'woocommerce-fastspring-gateway' ); ?></li>
			</ol>
			<div class="wc-fs-webhook-url-box" data-url="<?php echo esc_attr( $webhook_url ); ?>">
				<?php echo esc_html( $webhook_url ); ?>
				<span class="wc-fs-copy-hint"><?php esc_html_e( 'Your webhook URL (click to copy)', 'woocommerce-fastspring-gateway' ); ?></span>
			</div>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-required" data-help-id="help-storefront">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-required"><?php esc_html_e( 'Required', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'Storefront URL', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ol>
				<li>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: opening link tag, 2: closing link tag */
							__( 'Log in to %1$sapp.fastspring.com%2$s.', 'woocommerce-fastspring-gateway' ),
							'<a href="https://app.fastspring.com" target="_blank" rel="noopener noreferrer">',
							'</a>'
						),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Your storefront URL is visible in any product or storefront link. It follows the format: <code>yourstore.onfastspring.com</code>', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Paste it here. The <code>https://</code> prefix and any trail path are stripped automatically.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ol>
			<p class="wc-fs-help-doc">
				<span class="dashicons dashicons-book"></span>
				<a href="https://developer.fastspring.com/reference/sessions" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Sessions API documentation', 'woocommerce-fastspring-gateway' ); ?>
				</a>
			</p>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-required" data-help-id="help-api">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-required"><?php esc_html_e( 'Required', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'API Credentials', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ol>
				<li>
					<?php
					echo wp_kses(
						__( 'In the FastSpring App, go to <strong>Developer Tools > API Credentials</strong>.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Click <strong>Create</strong> to generate a new username and password.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Important:</strong> the password is only shown once during creation. Save it immediately.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ol>
			<p class="wc-fs-help-note">
				<?php esc_html_e( 'API credentials are used for everything: creating checkout sessions, verifying orders, managing subscriptions and processing refunds.', 'woocommerce-fastspring-gateway' ); ?>
			</p>
			<p class="wc-fs-help-doc">
				<span class="dashicons dashicons-book"></span>
				<a href="https://developer.fastspring.com/reference/getting-started-with-your-api" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'API overview documentation', 'woocommerce-fastspring-gateway' ); ?>
				</a>
			</p>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-required" data-help-id="help-webhook">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-required"><?php esc_html_e( 'Required', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'Webhook Configuration', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ol>
				<li>
					<?php
					echo wp_kses(
						__( 'In the FastSpring App, go to <strong>Developer Tools > Webhooks</strong>.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li><?php esc_html_e( 'Add a new webhook endpoint using the URL shown above.', 'woocommerce-fastspring-gateway' ); ?></li>
				<li>
					<?php
					echo wp_kses(
						__( 'Set the <strong>HMAC SHA256 Secret</strong> to the same value as the Webhook Secret field.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'Select events: <code>order.completed</code>, <code>order.failed</code>, <code>order.canceled</code>, <code>return.created</code>, and all subscription events.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'HMAC signature validation is <strong>mandatory</strong>. Unsigned requests are rejected.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ol>
			<p class="wc-fs-help-doc">
				<span class="dashicons dashicons-book"></span>
				<a href="https://developer.fastspring.com/reference/message-security" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Webhook security documentation', 'woocommerce-fastspring-gateway' ); ?>
				</a>
			</p>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-required" data-help-id="help-products">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-required"><?php esc_html_e( 'Required', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'Product Setup', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ol>
				<li>
					<?php
					echo wp_kses(
						__( 'Create your products in the <strong>FastSpring dashboard</strong> (Products section).', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'In WooCommerce, edit each product and enter its <strong>FastSpring Product Path</strong> in the Product Data section.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( 'If the FastSpring path is not set, the product <strong>SKU</strong> is used as fallback.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ol>
			<p class="wc-fs-help-note">
				<?php esc_html_e( 'The product path is the identifier from the URL in your FastSpring product page, for example "my-software" from app.fastspring.com/products/my-software.', 'woocommerce-fastspring-gateway' ); ?>
			</p>
			<p class="wc-fs-help-doc">
				<span class="dashicons dashicons-book"></span>
				<a href="https://developer.fastspring.com/reference/products" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Products API documentation', 'woocommerce-fastspring-gateway' ); ?>
				</a>
			</p>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-optional" data-help-id="help-display">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-optional"><?php esc_html_e( 'Optional', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'Display & Checkout', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Title</strong> and <strong>Description</strong> appear on the checkout page when the customer selects this payment method.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Payment Icons</strong> control which card and wallet logos appear next to the payment method name.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ul>
		</div>

		<div class="wc-fs-help-card wc-fs-help-card-optional" data-help-id="help-testing">
			<h4>
				<span class="wc-fs-badge wc-fs-badge-optional"><?php esc_html_e( 'Optional', 'woocommerce-fastspring-gateway' ); ?></span>
				<?php esc_html_e( 'Testing & Debug', 'woocommerce-fastspring-gateway' ); ?>
			</h4>
			<ul>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Test Mode</strong> switches the storefront to <code>test.onfastspring.com</code> so you can place test orders without real charges.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
				<li>
					<?php
					echo wp_kses(
						__( '<strong>Logging</strong> writes debug messages to <strong>WooCommerce > Status > Logs</strong>. Enable when troubleshooting payment or webhook issues.', 'woocommerce-fastspring-gateway' ),
						$allowed
					);
					?>
				</li>
			</ul>
			<p class="wc-fs-help-doc">
				<span class="dashicons dashicons-book"></span>
				<a href="https://developer.fastspring.com/docs/testing-overview" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Testing overview', 'woocommerce-fastspring-gateway' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
