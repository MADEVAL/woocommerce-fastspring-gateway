<?php
/**
 * Uninstall — clean up plugin options.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_fastspring_settings' );
delete_option( 'wc_fastspring_version' );
