<?php
/**
 * AES + RSA encryption for FastSpring secure payloads.
 *
 * @package GlobusStudio\WooCommerceFastSpring
 */

declare(strict_types=1);

namespace GlobusStudio\WooCommerceFastSpring;

final class Encryption {

	private function __construct() {}

	/**
	 * Generate a random 16-byte AES key.
	 */
	public static function generate_aes_key(): string {
		return random_bytes( 16 );
	}

	/**
	 * Encrypt payload string with AES-128-ECB.
	 *
	 * Note: ECB mode is required by FastSpring's Secure Payload specification.
	 *
	 * @param string $json    JSON string to encrypt.
	 * @param string $aes_key 16-byte AES key.
	 * @return string Base64-encoded ciphertext.
	 *
	 * @throws \RuntimeException If encryption fails.
	 */
	public static function encrypt_payload( string $json, string $aes_key ): string {
		$cipher = openssl_encrypt( $json, 'AES-128-ECB', $aes_key, OPENSSL_RAW_DATA );

		if ( false === $cipher ) {
			throw new \RuntimeException( 'AES payload encryption failed.' );
		}

		return base64_encode( $cipher );
	}

	/**
	 * Encrypt AES key with RSA private key.
	 *
	 * @param string $aes_key         16-byte AES key.
	 * @param string $private_key_pem RSA private key in PEM format.
	 * @return string Base64-encoded encrypted key.
	 *
	 * @throws \RuntimeException If the private key is invalid or encryption fails.
	 */
	public static function encrypt_key( string $aes_key, string $private_key_pem ): string {
		$private_key = openssl_pkey_get_private( $private_key_pem );

		if ( false === $private_key ) {
			throw new \RuntimeException( 'Invalid RSA private key.' );
		}

		$result = openssl_private_encrypt( $aes_key, $encrypted, $private_key );

		if ( ! $result ) {
			throw new \RuntimeException( 'RSA key encryption failed.' );
		}

		return base64_encode( $encrypted );
	}

	/**
	 * Build the secure data array for FastSpring Store Builder.
	 *
	 * In test mode, returns unencrypted payload with empty key
	 * per FastSpring specification.
	 *
	 * @param string $json            JSON payload string.
	 * @param string $private_key_pem RSA private key in PEM format.
	 * @param bool   $is_test_mode    Whether test mode is active.
	 * @return array{payload: string|array, key: string}
	 */
	public static function build_secure_data( string $json, string $private_key_pem, bool $is_test_mode ): array {
		if ( $is_test_mode ) {
			// Per FS docs: secure(nonEncryptedJSON, "") — pass JSON string, not decoded.
			return array(
				'payload' => $json,
				'key'     => '',
			);
		}

		$aes_key = self::generate_aes_key();

		return array(
			'payload' => self::encrypt_payload( $json, $aes_key ),
			'key'     => self::encrypt_key( $aes_key, $private_key_pem ),
		);
	}
}
