<?php
/**
 * Plugin bootstrap and core functionality.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

final class Plugin {

	private static ?self $instance = null;

	/** @var array<string, mixed>|null */
	private static ?array $settings_cache = null;

	/** @var array<string, array{class: string, message: string}> */
	private array $notices = array();

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ), 15 );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Declare WooCommerce feature compatibility.
	 */
	public function declare_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_FASTSPRING_MAIN_FILE );
			FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WC_FASTSPRING_MAIN_FILE );
		}
	}

	/**
	 * Initialize the plugin on plugins_loaded.
	 */
	public function init(): void {
		$warning = self::get_environment_warning();

		if ( '' !== $warning ) {
			return;
		}

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		load_plugin_textdomain(
			Constants::TEXT_DOMAIN,
			false,
			dirname( plugin_basename( WC_FASTSPRING_MAIN_FILE ) ) . '/languages'
		);

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'script_loader_tag', array( $this, 'modify_sbl_script_tag' ), 20, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( WC_FASTSPRING_MAIN_FILE ), array( $this, 'add_action_links' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'maybe_remove_billing_fields' ), 20 );
		add_filter( 'woocommerce_endpoint_order-pay_title', array( $this, 'order_pay_title' ), 10, 2 );

		new WebhookHandler();
		new AjaxHandler();

		add_action( 'wp_ajax_wc_fastspring_generate_rsa_keys', array( $this, 'ajax_generate_rsa_keys' ) );
	}

	/**
	 * Register the gateway class with WooCommerce.
	 *
	 * @param array<int, string> $methods Registered gateway classes.
	 * @return array<int, string>
	 */
	public function register_gateway( array $methods ): array {
		$methods[] = Gateway::class;
		return $methods;
	}

	/**
	 * Add FastSpring SBL data attributes to the script tag.
	 *
	 * @param string $tag    HTML script tag.
	 * @param string $handle Script handle.
	 * @return string Modified script tag.
	 */
	public function modify_sbl_script_tag( string $tag, string $handle ): string {
		if ( 'fastspring-sbl' !== $handle ) {
			return $tag;
		}

		// Remove WP-generated id to avoid duplicate; SBL requires id="fsc-api".
		$tag = str_replace( ' id="fastspring-sbl-js"', '', $tag );

		$storefront = esc_attr( self::get_storefront_path() );
		$access_key = esc_attr( (string) self::get_setting( 'access_key' ) );
		$is_debug   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;

		$attributes = sprintf(
			'id="fsc-api" data-storefront="%s" data-access-key="%s" data-popup-closed="fastspringPopupCloseHandler" data-before-requests-callback="fastspringBeforeRequestHandler"',
			$storefront,
			$access_key
		);

		if ( $is_debug ) {
			$is_test     = 'yes' === self::get_setting( 'testmode' );
			$attributes .= sprintf(
				' data-debug="true" data-data-callback="fastspringDataCallback" data-error-callback="fastspringErrorCallback" data-test="%s"',
				$is_test ? 'yes' : 'no'
			);
		}

		return str_replace( ' src', ' ' . $attributes . ' src', $tag );
	}

	/**
	 * Add plugin action links in admin.
	 *
	 * @param array<string, string> $links Existing links.
	 * @return array<string, string>
	 */
	public function add_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fastspring' );
		$plugin_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'woocommerce-fastspring-gateway' ) . '</a>',
			'<a href="https://developer.fastspring.com/docs" target="_blank">' . esc_html__( 'Docs', 'woocommerce-fastspring-gateway' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

	/**
	 * Remove billing address fields when FastSpring handles billing.
	 *
	 * @param array<string, array<string, mixed>> $fields Checkout fields.
	 * @return array<string, array<string, mixed>>
	 */
	public function maybe_remove_billing_fields( array $fields ): array {
		if ( 'yes' === self::get_setting( 'billing_address' ) ) {
			$remove = array(
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_postcode',
				'billing_country',
				'billing_state',
				'billing_company',
			);
			foreach ( $remove as $field ) {
				unset( $fields['billing'][ $field ] );
			}
		}
		return $fields;
	}

	/**
	 * Override the order-pay page title.
	 *
	 * @param string $title    Current title.
	 * @param string $endpoint Endpoint name.
	 * @return string
	 */
	public function order_pay_title( string $title, string $endpoint ): string {
		$order_id = absint( get_query_var( 'order-pay' ) );
		$order    = wc_get_order( $order_id );

		if ( $order instanceof \WC_Order && Constants::PLUGIN_SLUG === $order->get_payment_method() ) {
			return __( 'Enter Payment Info on Next Page', 'woocommerce-fastspring-gateway' );
		}

		return $title;
	}

	// -------------------------------------------------------------------------
	// Environment checks
	// -------------------------------------------------------------------------

	/**
	 * Run environment checks and display admin notices.
	 */
	public function check_environment(): void {
		if ( ! defined( 'IFRAME_REQUEST' ) && Constants::VERSION !== get_option( 'wc_fastspring_version' ) ) {
			update_option( 'wc_fastspring_version', Constants::VERSION );
			do_action( 'wc_fastspring_updated' );
		}

		$warning = self::get_environment_warning();

		if ( '' !== $warning && is_plugin_active( plugin_basename( WC_FASTSPRING_MAIN_FILE ) ) ) {
			$this->notices['bad_environment'] = array(
				'class'   => 'error',
				'message' => $warning,
			);
		}

		$access_key  = self::get_setting( 'access_key' );
		$storefront  = self::get_setting( 'storefront_path' );
		$private_key = self::get_setting( 'private_key' );
		$missing     = empty( $access_key ) || empty( $storefront ) || empty( $private_key );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable

		$on_settings_page = ( 'wc-settings' === $page && 'fastspring' === $section );

		if ( $missing && ! $on_settings_page ) {
			$url                            = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fastspring' );
			$this->notices['setup_required'] = array(
				'class'   => 'notice notice-warning',
				'message' => sprintf(
					/* translators: %s: settings page URL */
					__( 'FastSpring is almost ready. <a href="%s">Set your credentials</a> to start accepting payments.', 'woocommerce-fastspring-gateway' ),
					esc_url( $url )
				),
			);
		}
	}

	/**
	 * Display admin notices.
	 */
	public function display_admin_notices(): void {
		foreach ( $this->notices as $notice ) {
			printf(
				'<div class="%s"><p>%s</p></div>',
				esc_attr( $notice['class'] ),
				wp_kses(
					$notice['message'],
					array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					)
				)
			);
		}
	}

	/**
	 * Check for environment incompatibilities.
	 *
	 * @return string Warning message or empty string.
	 */
	public static function get_environment_warning(): string {
		if ( version_compare( PHP_VERSION, Constants::MIN_PHP_VERSION, '<' ) ) {
			return sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				__( 'WooCommerce FastSpring requires PHP %1$s or higher. You are running %2$s.', 'woocommerce-fastspring-gateway' ),
				Constants::MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		if ( ! defined( 'WC_VERSION' ) ) {
			return __( 'WooCommerce FastSpring requires WooCommerce to be activated.', 'woocommerce-fastspring-gateway' );
		}

		if ( version_compare( WC_VERSION, Constants::MIN_WC_VERSION, '<' ) ) {
			return sprintf(
				/* translators: 1: required WC version, 2: current WC version */
				__( 'WooCommerce FastSpring requires WooCommerce %1$s or higher. You are running %2$s.', 'woocommerce-fastspring-gateway' ),
				Constants::MIN_WC_VERSION,
				WC_VERSION
			);
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// RSA key generation
	// -------------------------------------------------------------------------

	/**
	 * AJAX: generate an RSA 2048-bit key pair and self-signed X.509 certificate.
	 */
	public function ajax_generate_rsa_keys(): void {
		check_ajax_referer( 'wc_fastspring_generate_keys', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'woocommerce-fastspring-gateway' ) ) );
		}

		if ( ! function_exists( 'openssl_pkey_new' ) ) {
			wp_send_json_error( array( 'message' => __( 'The OpenSSL PHP extension is not available on your server. Contact your hosting provider.', 'woocommerce-fastspring-gateway' ) ) );
		}

		$private_key = openssl_pkey_new( array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		) );

		if ( false === $private_key ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate RSA key pair.', 'woocommerce-fastspring-gateway' ) ) );
		}

		$dn  = array( 'commonName' => wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost' );
		$csr = openssl_csr_new( $dn, $private_key );

		if ( false === $csr ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create certificate request.', 'woocommerce-fastspring-gateway' ) ) );
		}

		$cert = openssl_csr_sign( $csr, null, $private_key, 3650, array( 'digest_alg' => 'sha256' ) );

		if ( false === $cert ) {
			wp_send_json_error( array( 'message' => __( 'Failed to generate certificate.', 'woocommerce-fastspring-gateway' ) ) );
		}

		openssl_pkey_export( $private_key, $private_key_pem );
		openssl_x509_export( $cert, $cert_pem );

		wp_send_json_success( array(
			'private_key'  => $private_key_pem,
			'certificate'  => $cert_pem,
		) );
	}

	// -------------------------------------------------------------------------
	// Settings helpers
	// -------------------------------------------------------------------------

	/**
	 * Get a plugin setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed Setting value or null.
	 */
	public static function get_setting( string $key ): mixed {
		if ( null === self::$settings_cache ) {
			$settings = get_option( Constants::SETTINGS_KEY, array() );
			self::$settings_cache = is_array( $settings ) ? $settings : array();
		}
		return self::$settings_cache[ $key ] ?? null;
	}

	/**
	 * Flush the settings cache (call after saving settings).
	 */
	public static function flush_settings_cache(): void {
		self::$settings_cache = null;
	}

	/**
	 * Get the storefront path, adjusted for test/live mode.
	 *
	 * @return string Storefront path.
	 */
	public static function get_storefront_path(): string {
		$path = self::get_setting( 'storefront_path' );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$is_test = 'yes' === self::get_setting( 'testmode' );

		if ( $is_test && ! str_contains( $path, 'test.onfastspring.com' ) ) {
			$path = str_replace( 'onfastspring.com', 'test.onfastspring.com', $path );
		} elseif ( ! $is_test ) {
			$path = str_replace( 'test.onfastspring.com', 'onfastspring.com', $path );
		}

		return $path;
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (debug, info, warning, error).
	 */
	public static function log( string $message, string $level = 'debug' ): void {
		$should_log = 'yes' === self::get_setting( 'logging' )
			|| ( defined( 'WP_DEBUG' ) && WP_DEBUG );

		if ( ! $should_log ) {
			return;
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => Constants::LOG_SOURCE ) );
		}
	}
}
