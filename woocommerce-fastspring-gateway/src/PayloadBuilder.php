<?php
/**
 * Build encrypted session payloads for FastSpring Store Builder.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

final class PayloadBuilder {

	private function __construct() {}

	/**
	 * Build a secure (encrypted) payload for the FastSpring checkout session.
	 *
	 * @param WC_Order $order        WooCommerce order.
	 * @param bool     $is_test_mode Whether test mode is active.
	 * @return array{payload: string|array, key: string}
	 */
	public static function build_secure_payload( WC_Order $order, bool $is_test_mode ): array {
		$private_key   = Plugin::get_setting( 'private_key' );
		$payload_array = self::build_payload( $order );
		$json          = wp_json_encode( $payload_array );

		if ( false === $json ) {
			Plugin::log( 'Failed to encode payload to JSON.', 'error' );
			return array(
				'payload' => '',
				'key'     => '',
			);
		}

		try {
			return Encryption::build_secure_data(
				$json,
				is_string( $private_key ) ? $private_key : '',
				$is_test_mode
			);
		} catch ( \RuntimeException $e ) {
			Plugin::log( 'Encryption failed: ' . $e->getMessage(), 'error' );
			return array(
				'payload' => '',
				'key'     => '',
			);
		}
	}

	/**
	 * Build the unencrypted payload array.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, mixed>
	 */
	public static function build_payload( WC_Order $order ): array {
		$payload = array(
			'tags'    => self::build_tags( $order ),
			'contact' => self::build_contact( $order ),
			'items'   => self::build_items( $order ),
		);

		return apply_filters( 'wc_fastspring_payload', $payload, $order );
	}

	/**
	 * Build order-level tags for FastSpring.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, string>
	 */
	public static function build_tags( WC_Order $order ): array {
		return array(
			'store_order_id' => (string) $order->get_id(),
		);
	}

	/**
	 * Build contact information from order billing data.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, string>
	 */
	public static function build_contact( WC_Order $order ): array {
		return array_filter(
			array(
				'email'        => $order->get_billing_email(),
				'firstName'    => $order->get_billing_first_name(),
				'lastName'     => $order->get_billing_last_name(),
				'company'      => $order->get_billing_company(),
				'addressLine1' => $order->get_billing_address_1(),
				'addressLine2' => $order->get_billing_address_2(),
				'city'         => $order->get_billing_city(),
				'region'       => $order->get_billing_state(),
				'postalCode'   => $order->get_billing_postcode(),
				'country'      => $order->get_billing_country(),
				'phoneNumber'  => $order->get_billing_phone(),
			)
		);
	}

	/**
	 * Build the items array from order line items.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_items( WC_Order $order ): array {
		$items              = array();
		$has_subscriptions  = class_exists( 'WC_Subscriptions_Product' );
		$currency           = $order->get_currency();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$quantity   = $item->get_quantity();
			$line_total = (float) $item->get_total();
			$unit_price = $quantity > 0 ? round( $line_total / $quantity, 2 ) : 0.0;

			$fs_item = self::build_single_item( $product, $quantity, $unit_price, $currency );

			// Subscription support.
			$signup_fee = 0.0;
			if ( $has_subscriptions && \WC_Subscriptions_Product::is_subscription( $product ) ) {
				$fs_item    = self::apply_subscription_pricing( $fs_item, $product, $currency );
				$signup_fee = (float) \WC_Subscriptions_Product::get_sign_up_fee( $product->get_id() );
			}

			$items[] = $fs_item;

			// FastSpring cannot combine signup fee with trial in a single item.
			if ( $signup_fee > 0.0 ) {
				$items[] = self::build_signup_fee_item( $product, $quantity, $signup_fee, $currency );
			}
		}

		return $items;
	}

	/**
	 * Build a single product item for the payload.
	 *
	 * @param WC_Product $product    WooCommerce product.
	 * @param int        $quantity   Item quantity.
	 * @param float      $unit_price Per-unit price (after discounts).
	 * @param string     $currency   Currency code.
	 * @return array<string, mixed>
	 */
	private static function build_single_item( WC_Product $product, int $quantity, float $unit_price, string $currency ): array {
		$item = array(
			'product'   => $product->get_slug(),
			'pricing'   => array(
				'quantityBehavior' => 'lock',
				'quantityDefault'  => $quantity,
				'price'            => array(
					$currency => $unit_price,
				),
			),
			'display'   => array(
				'en' => $product->get_name(),
			),
			'image'     => self::get_product_image_url( $product ),
			'removable' => false,
		);

		$sku = $product->get_sku();
		if ( '' !== $sku ) {
			$item['sku'] = $sku;
		}

		$short_desc = $product->get_short_description();
		if ( '' !== $short_desc ) {
			$item['description'] = array(
				'values' => array(
					'full' => array(
						'values' => array(
							'en' => wp_strip_all_tags( $short_desc ),
						),
					),
				),
			);
		}

		return $item;
	}

	/**
	 * Apply subscription pricing fields to an item.
	 *
	 * @param array<string, mixed> $fs_item  FastSpring item array.
	 * @param WC_Product           $product  WooCommerce product.
	 * @param string               $currency Currency code.
	 * @return array<string, mixed>
	 */
	private static function apply_subscription_pricing( array $fs_item, WC_Product $product, string $currency ): array {
		$product_id = $product->get_id();
		$period     = \WC_Subscriptions_Product::get_period( $product_id );

		if ( empty( $period ) ) {
			return $fs_item;
		}

		$interval  = (int) \WC_Subscriptions_Product::get_interval( $product_id );
		$length    = (int) \WC_Subscriptions_Product::get_length( $product_id );
		$sub_price = (float) \WC_Subscriptions_Product::get_price( $product_id );

		$fs_item['pricing']['price']          = array( $currency => round( $sub_price, 2 ) );
		$fs_item['pricing']['interval']       = $period;
		$fs_item['pricing']['intervalLength']  = $interval;
		$fs_item['pricing']['intervalCount']   = $length > 0 ? $length : null;

		// Trial period.
		$trial_length = (int) \WC_Subscriptions_Product::get_trial_length( $product_id );
		if ( $trial_length > 0 ) {
			$trial_period = \WC_Subscriptions_Product::get_trial_period( $product_id );
			$fs_item['pricing']['trial'] = self::period_to_days( $trial_length, $trial_period );
		}

		return $fs_item;
	}

	/**
	 * Build a separate signup fee item (required by FastSpring when trial is present).
	 *
	 * @param WC_Product $product    WooCommerce product.
	 * @param int        $quantity   Item quantity.
	 * @param float      $signup_fee Signup fee amount.
	 * @param string     $currency   Currency code.
	 * @return array<string, mixed>
	 */
	private static function build_signup_fee_item( WC_Product $product, int $quantity, float $signup_fee, string $currency ): array {
		return array(
			'product'   => $product->get_slug() . '-signup-fee',
			'pricing'   => array(
				'quantityBehavior' => 'lock',
				'quantityDefault'  => $quantity,
				'price'            => array(
					$currency => round( $signup_fee, 2 ),
				),
			),
			'display'   => array(
				/* translators: %s: product name */
				'en' => sprintf( __( '%s - Signup Fee', 'woocommerce-fastspring-gateway' ), $product->get_name() ),
			),
			'removable' => false,
		);
	}

	/**
	 * Convert a period length to days.
	 *
	 * @param int    $length Number of period units.
	 * @param string $period Period type (day, week, month, year).
	 * @return int Number of days.
	 */
	private static function period_to_days( int $length, string $period ): int {
		return match ( $period ) {
			'day'   => $length,
			'week'  => $length * 7,
			'month' => $length * 30,
			'year'  => $length * 365,
			default => $length,
		};
	}

	/**
	 * Get a protocol-relative product image URL.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return string Image URL or empty string.
	 */
	private static function get_product_image_url( WC_Product $product ): string {
		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return '';
		}

		$data = wp_get_attachment_image_src( (int) $image_id, 'woocommerce_thumbnail' );

		if ( ! $data || empty( $data[0] ) ) {
			return '';
		}

		return set_url_scheme( $data[0], 'https' );
	}
}
