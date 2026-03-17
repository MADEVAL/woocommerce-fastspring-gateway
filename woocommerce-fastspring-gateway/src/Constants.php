<?php
/**
 * Plugin constants.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

final class Constants {

	public const VERSION         = '2.0.0';
	public const API_BASE_URL    = 'https://api.fastspring.com';
	public const MIN_PHP_VERSION = '8.1.0';
	public const MIN_WC_VERSION  = '8.0.0';
	public const MIN_WP_VERSION  = '6.4';
	public const PLUGIN_SLUG     = 'fastspring';
	public const LOG_SOURCE      = 'fastspring-gateway';
	public const TEXT_DOMAIN     = 'woocommerce-fastspring-gateway';
	public const SETTINGS_KEY    = 'woocommerce_fastspring_settings';
	public const WEBHOOK_IP      = '107.23.30.83';

	private function __construct() {}
}
