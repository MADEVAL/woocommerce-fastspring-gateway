<?php
/**
 * Handle incoming FastSpring webhook events.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use WC_Order;

/**
 * Handle incoming FastSpring webhook events.
 */
final class WebhookHandler {

	/** Register the webhook listener. */
	public function __construct() {
		add_action( 'woocommerce_api_wc_gateway_fastspring', array( $this, 'handle_request' ) );
	}

	/**
	 * Process an incoming webhook request.
	 */
	public function handle_request(): void {
		$raw_body = file_get_contents( 'php://input' );

		if ( false === $raw_body || '' === $raw_body ) {
			Plugin::log( 'Webhook: empty request body.', 'error' );
			wp_send_json_error( 'Empty body', 400 );
			return;
		}

		// Mandatory HMAC signature validation.
		if ( ! $this->is_valid_signature( $raw_body ) ) {
			Plugin::log( 'Webhook: HMAC signature validation failed.', 'error' );
			wp_send_json_error( 'Invalid signature', 403 );
			return;
		}

		// Optional IP address filtering.
		if ( 'yes' === Plugin::get_setting( 'webhook_ip_filter' ) ) {
			$remote_ip = isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
				: '';
			if ( Constants::WEBHOOK_IP !== $remote_ip ) {
				Plugin::log( sprintf( 'Webhook: rejected IP %s.', $remote_ip ), 'error' );
				wp_send_json_error( 'Forbidden', 403 );
				return;
			}
		}

		$data = json_decode( $raw_body );

		if ( ! is_object( $data ) ) {
			Plugin::log( 'Webhook: malformed JSON payload.', 'error' );
			wp_send_json_error( 'Invalid JSON', 400 );
			return;
		}

		// FastSpring wraps events inside an "events" array.
		$events = $data->events ?? array( $data );
		if ( ! is_array( $events ) ) {
			$events = array( $data );
		}

		foreach ( $events as $event ) {
			if ( ! is_object( $event ) || ! isset( $event->type ) ) {
				Plugin::log( 'Webhook: event missing type field.', 'warning' );
				continue;
			}

			Plugin::log( sprintf( 'Webhook: processing "%s".', $event->type ) );
			$this->route_event( $event );
		}

		wp_send_json_success();
	}

	/**
	 * Validate the X-FS-Signature HMAC header.
	 *
	 * Both the webhook secret and the signature header MUST be present.
	 * This is the primary security fix vs. the old plugin which accepted
	 * requests with missing signatures.
	 *
	 * @param string $raw_body Raw request body.
	 * @return bool Whether the signature is valid.
	 */
	private function is_valid_signature( string $raw_body ): bool {
		$secret = Plugin::get_setting( 'webhook_secret' );

		if ( ! is_string( $secret ) || '' === $secret ) {
			Plugin::log( 'Webhook: no webhook secret configured, rejecting.', 'error' );
			return false;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw base64 signature for byte-for-byte HMAC comparison.
		$signature = isset( $_SERVER['HTTP_X_FS_SIGNATURE'] )
			? trim( (string) wp_unslash( $_SERVER['HTTP_X_FS_SIGNATURE'] ) )
			: '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $signature ) {
			Plugin::log( 'Webhook: missing X-FS-Signature header, rejecting.', 'error' );
			return false;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HMAC signature encoding.
		$expected = base64_encode( hash_hmac( 'sha256', $raw_body, $secret, true ) );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Route an event to the correct handler.
	 *
	 * @param object $event FastSpring event object.
	 */
	private function route_event( object $event ): void {
		try {
			match ( $event->type ) {
				'order.completed'               => $this->on_order_completed( $event ),
				'order.failed'                  => $this->on_order_failed( $event ),
				'order.canceled'                => $this->on_order_canceled( $event ),
				'return.created'                => $this->on_return_created( $event ),
				'subscription.activated'        => $this->on_subscription_activated( $event ),
				'subscription.canceled'         => $this->on_subscription_canceled( $event ),
				'subscription.deactivated'      => $this->on_subscription_deactivated( $event ),
				'subscription.uncanceled'       => $this->on_subscription_uncanceled( $event ),
				'subscription.paused'           => $this->on_subscription_paused( $event ),
				'subscription.resumed'          => $this->on_subscription_resumed( $event ),
				'subscription.charge.completed' => $this->on_subscription_charge_completed( $event ),
				'subscription.charge.failed'    => $this->on_subscription_charge_failed( $event ),
				default                         => Plugin::log( sprintf( 'Webhook: unhandled event "%s".', $event->type ) ),
			};
		} catch ( \Throwable $e ) {
			Plugin::log(
				sprintf( 'Webhook: error handling "%s": %s', $event->type, $e->getMessage() ),
				'error'
			);
		}
	}

	/**
	 * Find the WC order associated with an event via tags.store_order_id.
	 *
	 * @param object $event FastSpring event.
	 * @return WC_Order|null
	 */
	private function find_order( object $event ): ?WC_Order {
		$order_id = $event->data->tags->store_order_id ?? null;

		if ( null === $order_id ) {
			Plugin::log( 'Webhook: no store_order_id in event tags.', 'error' );
			return null;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof WC_Order ) {
			Plugin::log( sprintf( 'Webhook: WC order #%s not found.', (string) $order_id ), 'error' );
			return null;
		}

		if ( Constants::PLUGIN_SLUG !== $order->get_payment_method() ) {
			Plugin::log( sprintf( 'Webhook: order #%s uses a different payment method, ignoring.', (string) $order_id ), 'warning' );
			return null;
		}

		return $order;
	}

	// -------------------------------------------------------------------------
	// Event handlers.
	// -------------------------------------------------------------------------

	/**
	 * Handle order.completed event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_order_completed( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$reference = sanitize_text_field( $event->data->reference ?? '' );

		if ( $order->is_paid() ) {
			Plugin::log( sprintf( 'Webhook: order #%d already paid, skipping.', $order->get_id() ) );
			return;
		}

		$order->payment_complete( $reference );

		$fs_order_id = sanitize_text_field( $event->data->id ?? '' );
		if ( '' !== $fs_order_id ) {
			$order->update_meta_data( '_fs_order_id', $fs_order_id );
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: FastSpring reference ID */
				__( 'FastSpring payment completed (Ref: %s).', 'woocommerce-fastspring-gateway' ),
				$reference
			)
		);
		$order->save();

		Plugin::log( sprintf( 'Webhook: order #%d completed.', $order->get_id() ) );
	}

	/**
	 * Handle order.failed event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_order_failed( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'failed', __( 'FastSpring payment failed.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: order #%d failed.', $order->get_id() ) );
	}

	/**
	 * Handle order.canceled event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_order_canceled( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'cancelled', __( 'Order cancelled by FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: order #%d cancelled.', $order->get_id() ) );
	}

	/**
	 * Handle return.created event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_return_created( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'refunded', __( 'Refund processed by FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: order #%d refunded.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.activated event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_activated( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$fs_sub_id = sanitize_text_field( $event->data->id ?? '' );
		if ( '' !== $fs_sub_id ) {
			$order->update_meta_data( '_fs_subscription_id', $fs_sub_id );
			$order->save();
		}

		$order->add_order_note( __( 'Subscription activated by FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d activated.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.canceled event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_canceled( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'cancelled', __( 'Subscription cancelled via FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d cancelled.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.deactivated event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_deactivated( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'on-hold', __( 'Subscription deactivated by FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d deactivated.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.uncanceled event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_uncanceled( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'processing', __( 'Subscription cancellation reversed by FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d uncanceled.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.paused event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_paused( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'on-hold', __( 'Subscription paused via FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d paused.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.resumed event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_resumed( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->update_status( 'processing', __( 'Subscription resumed via FastSpring.', 'woocommerce-fastspring-gateway' ) );
		Plugin::log( sprintf( 'Webhook: subscription for order #%d resumed.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.charge.completed event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_charge_completed( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: charge reference */
				__( 'FastSpring subscription charge completed (Ref: %s).', 'woocommerce-fastspring-gateway' ),
				sanitize_text_field( $event->data->reference ?? 'N/A' )
			)
		);
		$order->save();

		Plugin::log( sprintf( 'Webhook: subscription charge completed for order #%d.', $order->get_id() ) );
	}

	/**
	 * Handle subscription.charge.failed event.
	 *
	 * @param object $event FastSpring event.
	 */
	private function on_subscription_charge_failed( object $event ): void {
		$order = $this->find_order( $event );
		if ( ! $order ) {
			return;
		}

		$order->add_order_note( __( 'FastSpring subscription charge failed.', 'woocommerce-fastspring-gateway' ) );
		$order->save();

		Plugin::log( sprintf( 'Webhook: subscription charge failed for order #%d.', $order->get_id() ), 'error' );
	}
}
