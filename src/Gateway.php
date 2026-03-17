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

class Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor — register gateway properties and hooks.
	 */
	public function __construct() {
		$this->id                 = Constants::PLUGIN_SLUG;
		$this->method_title       = __( 'FastSpring', 'woocommerce-fastspring-gateway' );
		$this->method_description = __( 'Accept payments via FastSpring popup or hosted checkout.', 'woocommerce-fastspring-gateway' );
		$this->has_fields         = true;
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

		return '' !== $this->get_option( 'access_key' )
			&& '' !== $this->get_option( 'private_key' )
			&& '' !== $this->get_option( 'storefront_path' );
	}

	/**
	 * Enqueue frontend checkout scripts.
	 */
	public function enqueue_scripts(): void {
		if ( ( ! is_checkout() && ! is_checkout_pay_page() ) || ! $this->is_available() ) {
			return;
		}

		wp_enqueue_script(
			'fastspring-sbl',
			Constants::SBL_SCRIPT_URL,
			array(),
			null, // External script — version managed by FastSpring.
			true
		);

		wp_enqueue_script(
			'wc-fastspring-checkout',
			plugins_url( 'assets/js/checkout.js', WC_FASTSPRING_MAIN_FILE ),
			array( 'jquery', 'fastspring-sbl' ),
			Constants::VERSION,
			true
		);

		wp_localize_script(
			'wc-fastspring-checkout',
			'wc_fastspring_params',
			array(
				'ajax_url' => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'nonce'    => array(
					'receipt' => wp_create_nonce( 'wc-fastspring-receipt' ),
				),
			)
		);

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
	 * Display payment fields on checkout.
	 */
	public function payment_fields(): void {
		$description = $this->get_description();
		if ( $description ) {
			echo wp_kses_post( wpautop( wptexturize( trim( $description ) ) ) );
		}
	}

	/**
	 * Process the payment — build the FastSpring session payload.
	 *
	 * Unlike a typical gateway that returns a redirect URL, this returns
	 * a "session" with encrypted payload data. The frontend JS intercepts
	 * the response, opens the FastSpring popup, and handles receipt flow.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array{result: string, session?: array}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wc_add_notice( __( 'Order not found.', 'woocommerce-fastspring-gateway' ), 'error' );
			return array( 'result' => 'failure' );
		}

		WC()->session->set( 'fastspring_order_id', $order_id );

		$is_test = 'yes' === $this->get_option( 'testmode' );
		$payload = PayloadBuilder::build_secure_payload( $order, $is_test );

		Plugin::log( sprintf( 'Processing payment for order #%d', $order_id ) );

		return array(
			'result'  => 'success',
			'session' => $payload,
		);
	}

	/**
	 * Process a refund via FastSpring API.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount   Refund amount (unused — FS processes full returns).
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
	 * Validate the access key field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string Sanitized value.
	 */
	public function validate_access_key_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' === $value ) {
			\WC_Admin_Settings::add_error(
				esc_html__( 'A FastSpring access key is required.', 'woocommerce-fastspring-gateway' )
			);
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Validate the RSA private key field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string The private key PEM or empty string.
	 */
	public function validate_private_key_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' !== $value ) {
			$pk = openssl_pkey_get_private( $value );

			if ( false === $pk ) {
				\WC_Admin_Settings::add_error(
					esc_html__( 'The RSA private key is invalid or cannot be parsed.', 'woocommerce-fastspring-gateway' )
				);
				return '';
			}

			$details = openssl_pkey_get_details( $pk );

			if ( $details && ( $details['bits'] ?? 0 ) < 2048 ) {
				\WC_Admin_Settings::add_error(
					esc_html__( 'The RSA key must be at least 2048 bits.', 'woocommerce-fastspring-gateway' )
				);
			}
		}

		return $value;
	}

	/**
	 * Validate the storefront path field.
	 *
	 * @param string      $key   Field key.
	 * @param string|null $value Field value.
	 * @return string Sanitized storefront path.
	 */
	public function validate_storefront_path_field( string $key, ?string $value ): string {
		$value = $value ?? '';

		if ( '' === $value ) {
			\WC_Admin_Settings::add_error(
				esc_html__( 'Enter a valid FastSpring storefront path.', 'woocommerce-fastspring-gateway' )
			);
			return '';
		}

		// Strip protocol prefix and trailing slash.
		$value = preg_replace( '#^https?://#', '', $value ) ?? $value;
		return rtrim( sanitize_text_field( $value ), '/' );
	}
}
