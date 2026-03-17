<?php
/**
 * Plugin bootstrap and core functionality.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Plugin bootstrap and core functionality.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $settings_cache = null;

	/**
	 * Admin notices.
	 *
	 * @var array<string, array{class: string, message: string}>
	 */
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

	/** Register core hooks. */
	private function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'admin_init', array( $this, 'check_environment' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ), 15 );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/** Prevent cloning. */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \RuntimeException Always.
	 */
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
		add_filter( 'plugin_action_links_' . plugin_basename( WC_FASTSPRING_MAIN_FILE ), array( $this, 'add_action_links' ) );

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_product_fastspring_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );

		new WebhookHandler();
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
	 * Render FastSpring Product Path field on the product edit screen.
	 */
	public function render_product_fastspring_field(): void {
		woocommerce_wp_text_input(
			array(
				'id'          => '_fastspring_product_path',
				'label'       => __( 'FastSpring Product Path', 'woocommerce-fastspring-gateway' ),
				'description' => __( 'The product path from your FastSpring dashboard (e.g. "my-software"). Falls back to SKU if empty.', 'woocommerce-fastspring-gateway' ),
				'desc_tip'    => true,
			)
		);
	}

	/**
	 * Save FastSpring Product Path meta.
	 *
	 * @param int $post_id Product post ID.
	 */
	public function save_product_meta( int $post_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified immediately after.
		if ( ! isset( $_POST['_fastspring_product_path'] ) ) {
			return;
		}

		check_admin_referer( 'woocommerce-save-product', 'woocommerce-save-product-nonce' );

		$path = sanitize_text_field( wp_unslash( $_POST['_fastspring_product_path'] ) );
		update_post_meta( $post_id, '_fastspring_product_path', $path );
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

		$api_username = self::get_setting( 'api_username' );
		$api_password = self::get_setting( 'api_password' );
		$storefront   = self::get_setting( 'storefront_path' );
		$missing      = empty( $api_username ) || empty( $api_password ) || empty( $storefront );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		// phpcs:enable

		$on_settings_page = ( 'wc-settings' === $page && 'fastspring' === $section );

		if ( $missing && ! $on_settings_page ) {
			$url                             = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fastspring' );
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
			$settings             = get_option( Constants::SETTINGS_KEY, array() );
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
