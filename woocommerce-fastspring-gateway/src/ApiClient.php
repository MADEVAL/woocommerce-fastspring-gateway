<?php
/**
 * FastSpring REST API client.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

use WP_Error;

/**
 * FastSpring REST API client.
 */
final class ApiClient {

	/**
	 * API username.
	 *
	 * @var string
	 */
	private string $username;

	/**
	 * API password.
	 *
	 * @var string
	 */
	private string $password;

	/**
	 * Constructor.
	 *
	 * @param string $username API username.
	 * @param string $password API password.
	 */
	public function __construct( string $username, string $password ) {
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * Create an ApiClient instance from plugin settings.
	 *
	 * @return self|null Null if API credentials are not configured.
	 */
	public static function from_settings(): ?self {
		$username = Plugin::get_setting( 'api_username' );
		$password = Plugin::get_setting( 'api_password' );

		if ( ! is_string( $username ) || '' === $username || ! is_string( $password ) || '' === $password ) {
			return null;
		}

		return new self( $username, $password );
	}

	/**
	 * Make an HTTP request to the FastSpring API.
	 *
	 * @param string               $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string               $endpoint API endpoint path (e.g., 'orders/abc123').
	 * @param array<string, mixed> $body     Request body for POST/PUT.
	 * @return array<string, mixed>|WP_Error Response data or error.
	 */
	public function request( string $method, string $endpoint, array $body = array() ): array|WP_Error {
		$url = Constants::API_BASE_URL . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'    => $method,
			'timeout'   => 30,
			'sslverify' => true,
			'headers'   => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth.
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'WooCommerceFastSpring/' . Constants::VERSION,
			),
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Plugin::log( 'API request failed: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );
		$data     = json_decode( $body_raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = sprintf( 'API %s /%s returned HTTP %d', $method, $endpoint, $code );
			Plugin::log( $message, 'error' );
			return new WP_Error(
				'fastspring_api_error',
				$message,
				array(
					'status' => $code,
					'body'   => $data,
				)
			);
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Retrieve a FastSpring order.
	 *
	 * @param string $order_id FastSpring order ID.
	 * @return array<string, mixed>|null Order data or null on failure.
	 */
	public function get_order( string $order_id ): ?array {
		$result = $this->request( 'GET', 'orders/' . sanitize_text_field( $order_id ) );
		return is_wp_error( $result ) ? null : $result;
	}

	/**
	 * Check FastSpring order completion status.
	 *
	 * @param string $order_id FastSpring order ID.
	 * @return string 'completed' or 'pending'.
	 */
	public function get_order_status( string $order_id ): string {
		$order = $this->get_order( $order_id );

		if ( $order && ! empty( $order['completed'] ) ) {
			Plugin::log( sprintf( 'API: order %s is completed', $order_id ) );
			return 'completed';
		}

		Plugin::log( sprintf( 'API: order %s not completed or not found', $order_id ) );
		return 'pending';
	}

	/**
	 * Create a return (refund) via the FastSpring API.
	 *
	 * @param string $order_id FastSpring order ID.
	 * @param float  $amount   Refund amount.
	 * @param string $reason   Refund reason.
	 * @return bool True on success.
	 */
	public function create_return( string $order_id, float $amount, string $reason = '' ): bool {
		$body = array(
			'returns' => array(
				array(
					'order'  => sanitize_text_field( $order_id ),
					'reason' => '' !== $reason ? $reason : 'Refund from WooCommerce',
				),
			),
		);

		$result = $this->request( 'POST', 'returns', $body );
		return ! is_wp_error( $result );
	}

	/**
	 * Retrieve a FastSpring subscription.
	 *
	 * @param string $subscription_id FastSpring subscription ID.
	 * @return array<string, mixed>|null Subscription data or null on failure.
	 */
	public function get_subscription( string $subscription_id ): ?array {
		$result = $this->request( 'GET', 'subscriptions/' . sanitize_text_field( $subscription_id ) );
		return is_wp_error( $result ) ? null : $result;
	}

	/**
	 * Cancel a FastSpring subscription.
	 *
	 * @param string $subscription_id FastSpring subscription ID.
	 * @return bool True on success.
	 */
	public function cancel_subscription( string $subscription_id ): bool {
		$result = $this->request( 'DELETE', 'subscriptions/' . sanitize_text_field( $subscription_id ) );
		return ! is_wp_error( $result );
	}

	/**
	 * Update a FastSpring subscription.
	 *
	 * @param string               $subscription_id FastSpring subscription ID.
	 * @param array<string, mixed> $data            Fields to update.
	 * @return array<string, mixed>|null Updated subscription data or null.
	 */
	public function update_subscription( string $subscription_id, array $data ): ?array {
		$body   = array(
			'subscriptions' => array(
				array_merge( array( 'subscription' => sanitize_text_field( $subscription_id ) ), $data ),
			),
		);
		$result = $this->request( 'POST', 'subscriptions', $body );
		return is_wp_error( $result ) ? null : $result;
	}

	/**
	 * Pause a FastSpring subscription.
	 *
	 * @param string $subscription_id FastSpring subscription ID.
	 * @return bool True on success.
	 */
	public function pause_subscription( string $subscription_id ): bool {
		$result = $this->request(
			'POST',
			'subscriptions/pause',
			array(
				'subscriptions' => array( sanitize_text_field( $subscription_id ) ),
			)
		);
		return ! is_wp_error( $result );
	}

	/**
	 * Resume a FastSpring subscription.
	 *
	 * @param string $subscription_id FastSpring subscription ID.
	 * @return bool True on success.
	 */
	public function resume_subscription( string $subscription_id ): bool {
		$result = $this->request(
			'POST',
			'subscriptions/resume',
			array(
				'subscriptions' => array( sanitize_text_field( $subscription_id ) ),
			)
		);
		return ! is_wp_error( $result );
	}

	/**
	 * Create a checkout session.
	 *
	 * @param array<string, mixed> $data Session data (items, tags, contact, etc.).
	 * @return array<string, mixed>|WP_Error Session response with id, account, etc.
	 */
	public function create_session( array $data ): array|WP_Error {
		return $this->request( 'POST', 'sessions', $data );
	}

	/**
	 * Create a customer account.
	 *
	 * @param array<string, mixed> $data Account data (contact, language, country).
	 * @return array<string, mixed>|WP_Error Account response.
	 */
	public function create_account( array $data ): array|WP_Error {
		return $this->request( 'POST', 'accounts', $data );
	}

	/**
	 * List all product paths.
	 *
	 * @return array<string, mixed>|WP_Error Product list or error.
	 */
	public function get_products(): array|WP_Error {
		return $this->request( 'GET', 'products' );
	}

	/**
	 * Retrieve a single product.
	 *
	 * @param string $product_path FastSpring product path.
	 * @return array<string, mixed>|null Product data or null.
	 */
	public function get_product( string $product_path ): ?array {
		$result = $this->request( 'GET', 'products/' . sanitize_text_field( $product_path ) );
		return is_wp_error( $result ) ? null : $result;
	}
}
