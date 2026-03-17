<?php
/**
 * WooCommerce payment gateway integration for FastSpring.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use GlobusStudio\WooCommerceFastSpring\Admin\Settings;
use WC_Order;
use WC_Payment_Gateway;

/**
 * WooCommerce payment gateway for FastSpring.
 */
class Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor: registers gateway properties and hooks.
	 */
	public function __construct() {
		$this->id                 = Constants::PLUGIN_SLUG;
		$this->method_title       = __( 'FastSpring', 'woocommerce-fastspring-gateway' );
		$this->method_description = __( 'Accept payments via FastSpring checkout. Customers are redirected to FastSpring to complete payment.', 'woocommerce-fastspring-gateway' );
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'multiple_subscriptions',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Pay with FastSpring', 'woocommerce-fastspring-gateway' ) );
		$this->description = $this->get_option( 'description', '' );

		if ( 'yes' === $this->get_option( 'testmode' ) ) {
			$this->description .= "\n" . sprintf(
				/* translators: %s: FastSpring testing documentation URL */
				__( 'TEST MODE ENABLED. Use test card numbers from the <a target="_blank" href="%s">FastSpring test panel</a>.', 'woocommerce-fastspring-gateway' ),
				'https://developer.fastspring.com/docs/testing-overview'
			);
			$this->description = trim( $this->description );
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( Plugin::class, 'flush_settings_cache' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Load settings field definitions.
	 */
	public function init_form_fields(): void {
		$this->form_fields = Settings::get_fields();
	}

	/**
	 * Check if the gateway is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( 'yes' !== $this->get_option( 'enabled' ) ) {
			return false;
		}

		return '' !== $this->get_option( 'api_username' )
			&& '' !== $this->get_option( 'api_password' )
			&& '' !== $this->get_option( 'storefront_path' );
	}

	/**
	 * Enqueue minimal frontend styles for payment icons.
	 */
	public function enqueue_scripts(): void {
		if ( ( ! is_checkout() && ! is_checkout_pay_page() ) || ! $this->is_available() ) {
			return;
		}

		$css = '.woocommerce-checkout #payment ul.payment_methods li img.fastspring-icon{max-width:40px;padding-left:3px;margin:0}'
			. '.woocommerce-checkout #payment ul.payment_methods li img.fastspring-icon-ideal{max-height:26px}'
			. '.woocommerce-checkout #payment ul.payment_methods li img.fastspring-icon-sofort{max-width:55px}';
		wp_add_inline_style( 'woocommerce-inline', $css );
	}

	/**
	 * Render payment method icon(s).
	 *
	 * @return string HTML for the icons.
	 */
	public function get_icon(): string {
		$icons_html    = '';
		$all_icons     = $this->get_payment_icons();
		$enabled_icons = (array) $this->get_option( 'icons', array() );

		foreach ( $enabled_icons as $icon_key ) {
			if ( isset( $all_icons[ $icon_key ] ) ) {
				$icons_html .= $all_icons[ $icon_key ];
			}
		}

		return apply_filters( 'woocommerce_gateway_icon', $icons_html, $this->id );
	}

	/**
	 * Get all available payment icon markup.
	 *
	 * @return array<string, string>
	 */
	private function get_payment_icons(): array {
		$url        = plugins_url( 'assets/img/', WC_FASTSPRING_MAIN_FILE );
		$icons      = array();
		$icon_names = array( 'paypal', 'visa', 'amex', 'mastercard', 'discover', 'diners', 'jcb', 'ideal', 'unionpay', 'sofort' );

		foreach ( $icon_names as $name ) {
			$label          = ucfirst( $name );
			$icons[ $name ] = '<img src="' . esc_url( $url . $name . '.svg' ) . '" class="fastspring-icon fastspring-icon-' . esc_attr( $name ) . '" alt="' . esc_attr( $label ) . '" />';
		}

		return apply_filters( 'wc_fastspring_payment_icons', $icons );
	}

	/**
	 * Process the payment via FastSpring Sessions API.
	 *
	 * Creates a checkout session and redirects the customer to the FastSpring
	 * hosted checkout page. Order completion is handled asynchronously by webhooks.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array{result: string, redirect?: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wc_add_notice( __( 'Order not found.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$client = ApiClient::from_settings();

		if ( ! $client ) {
			wc_add_notice( __( 'FastSpring API credentials are not configured.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		// Build items from order line items.
		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$fs_path = $product->get_meta( '_fastspring_product_path' );
			if ( empty( $fs_path ) ) {
				$fs_path = $product->get_sku();
			}

			if ( empty( $fs_path ) ) {
				wc_add_notice(
					sprintf(
						/* translators: %s: product name */
						__( 'Product "%s" is not linked to a FastSpring product. Set its FastSpring Product Path or SKU.', 'woocommerce-fastspring-gateway' ),
						$product->get_name()
					),
					'error'
				);
				return array( 'result' => 'failure' );
			}

			$items[] = array(
				'product'  => sanitize_text_field( $fs_path ),
				'quantity' => $item->get_quantity(),
			);
		}

		if ( empty( $items ) ) {
			wc_add_notice( __( 'No items to process.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		// Build session request.
		$session_data = array(
			'items' => $items,
			'tags'  => array(
				'store_order_id'  => (string) $order->get_id(),
				'store_order_key' => $order->get_order_key(),
			),
		);

		// Add customer contact info.
		$email = $order->get_billing_email();
		if ( $email ) {
			$session_data['contact'] = array_filter(
				array(
					'email'   => $email,
					'first'   => $order->get_billing_first_name(),
					'last'    => $order->get_billing_last_name(),
					'company' => $order->get_billing_company(),
				)
			);
			$country                 = $order->get_billing_country();
			if ( $country ) {
				$session_data['country'] = $country;
			}
		}

		$session_data = apply_filters( 'wc_fastspring_session_data', $session_data, $order );

		$session = $client->create_session( $session_data );

		if ( is_wp_error( $session ) ) {
			Plugin::log( 'Session creation failed: ' . $session->get_error_message(), 'error' );
			wc_add_notice( __( 'Unable to initiate FastSpring checkout. Please try again.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		if ( empty( $session['id'] ) ) {
			Plugin::log( 'Session response missing id.', 'error' );
			wc_add_notice( __( 'Invalid FastSpring checkout response. Please try again.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_fs_session_id', sanitize_text_field( $session['id'] ) );
		$order->save();

		// Build checkout URL: https://{storefront}/session/{session_id}.
		$storefront   = Plugin::get_storefront_path();
		$checkout_url = 'https://' . $storefront . '/session/' . rawurlencode( $session['id'] );

		Plugin::log( sprintf( 'Session %s created for order #%d, redirecting to %s', $session['id'], $order_id, $checkout_url ) );

		return array(
			'result'   => 'success',
			'redirect' => $checkout_url,
		);
	}

	/**
	 * Process a refund via FastSpring API.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount   Refund amount.
	 * @param string     $reason   Refund reason.
	 * @return bool|\WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool|\WP_Error {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return new \WP_Error( 'invalid_order', __( 'Order not found.', 'woocommerce-fastspring-gateway' ) );
		}

		$fs_order_id = $order->get_meta( '_fs_order_id' );

		if ( empty( $fs_order_id ) ) {
			return new \WP_Error( 'no_fs_order', __( 'No FastSpring order ID found for this order.', 'woocommerce-fastspring-gateway' ) );
		}

		$client = ApiClient::from_settings();

		if ( ! $client ) {
			return new \WP_Error( 'no_api_credentials', __( 'FastSpring API credentials are not configured.', 'woocommerce-fastspring-gateway' ) );
		}

		$result = $client->create_return( $fs_order_id, (float) ( $amount ?? 0 ), $reason );

		if ( ! $result ) {
			return new \WP_Error( 'refund_failed', __( 'FastSpring refund request failed.', 'woocommerce-fastspring-gateway' ) );
		}

		Plugin::log( sprintf( 'Refund of %s processed for order #%d', (string) $amount, $order_id ) );
		return true;
	}

	/**
	 * Get a link to the FastSpring transaction.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Transaction URL.
	 */
	public function get_transaction_url( $order ): string {
		if ( $order instanceof WC_Order && $order->meta_exists( '_fs_order_id' ) ) {
			$fs_id = $order->get_meta( '_fs_order_id' );
			return 'https://dashboard.fastspring.com/order/home.xml?mRef=AcquisitionTransaction:' . rawurlencode( $fs_id );
		}
		return '';
	}

	// -------------------------------------------------------------------------
	// Settings field validators
	// -------------------------------------------------------------------------

	/**
	 * Validate the API username field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string Sanitized value.
	 */
	public function validate_api_username_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' === $value ) {
			\WC_Admin_Settings::add_error(
				esc_html__( 'FastSpring API username is required.', 'woocommerce-fastspring-gateway' )
			);
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Validate the API password field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string Sanitized value.
	 */
	public function validate_api_password_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' === $value ) {
			\WC_Admin_Settings::add_error(
				esc_html__( 'FastSpring API password is required.', 'woocommerce-fastspring-gateway' )
			);
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Validate the storefront path field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string Sanitized storefront domain.
	 */
	public function validate_storefront_path_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' === $value ) {
			\WC_Admin_Settings::add_error(
				esc_html__( 'Enter your FastSpring storefront URL.', 'woocommerce-fastspring-gateway' )
			);
			return '';
		}

		// Strip protocol prefix, path, and trailing slash to keep only the domain.
		$value = preg_replace( '#^https?://#', '', $value ) ?? $value;
		$value = explode( '/', $value )[0];
		return rtrim( sanitize_text_field( $value ), '/' );
	}

	// -------------------------------------------------------------------------
	// Admin settings page
	// -------------------------------------------------------------------------

	/**
	 * Render the gateway settings page with a contextual help sidebar.
	 */
	public function admin_options(): void {
		echo '<h2>' . esc_html( $this->get_method_title() );
		wc_back_link( __( 'Return to payments', 'woocommerce-fastspring-gateway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
		echo '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );

		echo '<div class="wc-fs-settings-wrap">';
		echo '<div class="wc-fs-settings-main">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WC generates settings HTML.
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';
		echo '</div>';
		echo '<div class="wc-fs-settings-sidebar">';
		Settings::render_help_sidebar();
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Enqueue admin CSS and JS on the gateway settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable

		if ( Constants::PLUGIN_SLUG !== $section ) {
			return;
		}

		wp_enqueue_style(
			'wc-fastspring-admin',
			plugins_url( 'assets/css/admin-settings.css', WC_FASTSPRING_MAIN_FILE ),
			array(),
			Constants::VERSION
		);

		wp_enqueue_script(
			'wc-fastspring-admin',
			plugins_url( 'assets/js/admin-settings.js', WC_FASTSPRING_MAIN_FILE ),
			array( 'jquery' ),
			Constants::VERSION,
			true
		);

		wp_localize_script(
			'wc-fastspring-admin',
			'wcFsAdminL10n',
			array(
				'required'    => __( 'Required', 'woocommerce-fastspring-gateway' ),
				'recommended' => __( 'Recommended', 'woocommerce-fastspring-gateway' ),
				'copied'      => __( 'Copied!', 'woocommerce-fastspring-gateway' ),
			)
		);
	}
}
