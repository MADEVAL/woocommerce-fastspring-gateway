<?php
/**
 * Handle AJAX receipt requests after FastSpring popup payment.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use WC_Order;

final class AjaxHandler {

	public function __construct() {
		add_action( 'wc_ajax_wc_fastspring_get_receipt', array( $this, 'get_receipt' ) );
	}

	/**
	 * Process the receipt AJAX request.
	 *
	 * Called by frontend JS after the FastSpring popup closes with payment data.
	 * Verifies nonce, finds the WC order, optionally validates via API,
	 * updates order meta/status, and returns the thank-you page redirect URL.
	 */
	public function get_receipt(): void {
		$raw     = file_get_contents( 'php://input' );
		$payload = json_decode( false !== $raw ? $raw : '' );

		if ( ! is_object( $payload ) ) {
			wp_send_json_error( __( 'Invalid request.', 'woocommerce-fastspring-gateway' ), 400 );
			return;
		}

		$nonce = sanitize_text_field( $payload->security ?? '' );

		if ( ! wp_verify_nonce( $nonce, 'wc-fastspring-receipt' ) ) {
			wp_send_json_error( __( 'Access denied.', 'woocommerce-fastspring-gateway' ), 403 );
			return;
		}

		// Retrieve order from session.
		$order_id = absint( WC()->session->get( 'fastspring_order_id', 0 ) );
		if ( ! $order_id ) {
			$order_id = absint( WC()->session->get( 'order_awaiting_payment', 0 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			Plugin::log( sprintf( 'Receipt: order #%d not found.', $order_id ), 'error' );
			wp_send_json_error( __( 'Order not found.', 'woocommerce-fastspring-gateway' ), 404 );
			return;
		}

		$reference   = sanitize_text_field( $payload->reference ?? '' );
		$fs_order_id = sanitize_text_field( $payload->id ?? '' );

		if ( '' === $reference ) {
			Plugin::log( sprintf( 'Receipt: no reference for order #%d.', $order_id ), 'error' );
			wp_send_json_error( __( 'No payment reference received.', 'woocommerce-fastspring-gateway' ), 400 );
			return;
		}

		Plugin::log( sprintf( 'Receipt: order #%d (ref: %s, fs: %s).', $order_id, $reference, $fs_order_id ) );

		// Verify order via FastSpring API when credentials are available.
		$client = ApiClient::from_settings();
		$status = ( $client && '' !== $fs_order_id )
			? $client->get_order_status( $fs_order_id )
			: 'pending';

		// Clear the cart.
		WC()->cart->empty_cart();

		// Update order.
		$order->set_transaction_id( $reference );
		$order->update_meta_data( '_fs_order_id', $fs_order_id );

		if ( 'completed' === $status && ! $order->is_paid() ) {
			$order->payment_complete( $reference );
			/* translators: %s: FastSpring order ID */
			$order->add_order_note(
				sprintf( __( 'FastSpring payment approved (FS Order: %s).', 'woocommerce-fastspring-gateway' ), $fs_order_id )
			);
		} elseif ( ! $order->is_paid() && 'completed' !== $order->get_status() ) {
			$order->update_status( 'pending', __( 'Awaiting FastSpring payment confirmation via webhook.', 'woocommerce-fastspring-gateway' ) );
		}

		$order->save();

		wp_send_json(
			array(
				'redirect_url' => $this->get_return_url( $order ),
				'order_id'     => $order_id,
			)
		);
	}

	/**
	 * Get the checkout return/thank-you URL.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Return URL.
	 */
	private function get_return_url( WC_Order $order ): string {
		$url = $order->get_checkout_order_received_url();

		if ( is_ssl() || 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
			$url = str_replace( 'http:', 'https:', $url );
		}

		return apply_filters( 'woocommerce_get_return_url', $url, $order );
	}
}
