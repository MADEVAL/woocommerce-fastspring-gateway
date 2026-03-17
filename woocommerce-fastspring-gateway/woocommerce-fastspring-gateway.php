<?php
/**
 * Plugin Name: WooCommerce FastSpring Gateway
 * Plugin URI:  https://globus.studio
 * Description: Accept credit card, PayPal, Amazon Pay and other payments via FastSpring.
 * Version:     2.0.0
 * Author:      Yevhen Leonidov
 * Author URI:  https://leonidov.dev
 * Text Domain: woocommerce-fastspring-gateway
 * Domain Path: /languages
 * Requires at least: 6.4
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'WC_FASTSPRING_MAIN_FILE', __FILE__ );
define( 'WC_FASTSPRING_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WC_FASTSPRING_PLUGIN_DIR', __DIR__ );

/**
 * PSR-4 autoloader for the GlobusStudio\WooCommerceFastSpring namespace.
 *
 * Maps namespace prefixes to the src/ directory so the plugin works
 * without requiring Composer in production (ZIP install).
 */
spl_autoload_register( static function ( string $class ): void {
	$prefix = 'GlobusStudio\\WooCommerceFastSpring\\';

	if ( ! str_starts_with( $class, $prefix ) ) {
		return;
	}

	$relative = substr( $class, strlen( $prefix ) );
	$file     = WC_FASTSPRING_PLUGIN_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

	if ( is_file( $file ) ) {
		require_once $file;
	}
} );

// Bootstrap the plugin.
GlobusStudio\WooCommerceFastSpring\Plugin::instance();
