<?php
/**
 * Encryption helpers for integration job payloads.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts and decrypts outbox payloads without exposing plaintext in logs.
 */
final class Mediline_Integrations_Crypto {
	const ENVELOPE_VERSION = 1;
	const CIPHER           = 'aes-256-gcm';
	const ALGORITHM_LABEL  = 'A256GCM';
	const IV_BYTES         = 12;
	const TAG_BYTES        = 16;
	const AAD              = 'mediline-integrations:payload:v1';

	/**
	 * Whether the required authenticated-encryption primitives are available.
	 *
	 * @return bool
	 */
	public static function is_supported() {
		if (
			! function_exists( 'openssl_encrypt' ) ||
			! function_exists( 'openssl_decrypt' ) ||
			! function_exists( 'openssl_get_cipher_methods' )
		) {
			return false;
		}

		$ciphers = openssl_get_cipher_methods( true );
		return is_array( $ciphers ) && in_array( self::CIPHER, array_map( 'strtolower', $ciphers ), true );
	}

	/**
	 * Encrypt an associative payload into a versioned JSON envelope.
	 *
	 * @param array $payload Payload containing data required by a background job.
	 * @return string|WP_Error Ciphertext envelope or an error with no plaintext data.
	 */
	public static function encrypt( array $payload ) {
		if ( ! self::is_supported() ) {
			return new WP_Error( 'mediline_crypto_unavailable', __( 'Secure payload encryption is unavailable.', 'mediline-integrations' ) );
		}

		$plaintext = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $plaintext ) {
			return new WP_Error( 'mediline_crypto_encode_failed', __( 'The integration payload could not be encoded.', 'mediline-integrations' ) );
		}

		try {
			$iv = random_bytes( self::IV_BYTES );
		} catch ( Throwable $exception ) {
			self::wipe( $plaintext );
			return new WP_Error( 'mediline_crypto_random_failed', __( 'Secure payload encryption could not be initialized.', 'mediline-integrations' ) );
		}

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::AAD,
			self::TAG_BYTES
		);
		self::wipe( $plaintext );

		if ( false === $ciphertext || self::TAG_BYTES !== strlen( $tag ) ) {
			return new WP_Error( 'mediline_crypto_encrypt_failed', __( 'The integration payload could not be encrypted.', 'mediline-integrations' ) );
		}

		$envelope = wp_json_encode(
			array(
				'v'   => self::ENVELOPE_VERSION,
				'alg' => self::ALGORITHM_LABEL,
				'iv'  => base64_encode( $iv ),
				'tag' => base64_encode( $tag ),
				'ct'  => base64_encode( $ciphertext ),
			),
			JSON_UNESCAPED_SLASHES
		);

		self::wipe( $ciphertext );
		self::wipe( $tag );

		if ( false === $envelope ) {
			return new WP_Error( 'mediline_crypto_envelope_failed', __( 'The encrypted integration payload could not be stored.', 'mediline-integrations' ) );
		}

		return $envelope;
	}

	/**
	 * Decrypt a versioned envelope for a worker.
	 *
	 * @param string $envelope Encrypted envelope produced by encrypt().
	 * @return array|WP_Error Decrypted payload or a generic error.
	 */
	public static function decrypt( $envelope ) {
		if ( ! self::is_supported() ) {
			return new WP_Error( 'mediline_crypto_unavailable', __( 'Secure payload decryption is unavailable.', 'mediline-integrations' ) );
		}

		if ( ! is_string( $envelope ) || '' === $envelope ) {
			return new WP_Error( 'mediline_crypto_invalid_envelope', __( 'The encrypted integration payload is invalid.', 'mediline-integrations' ) );
		}

		$data = json_decode( $envelope, true );
		if (
			! is_array( $data ) ||
			self::ENVELOPE_VERSION !== (int) ( $data['v'] ?? 0 ) ||
			self::ALGORITHM_LABEL !== ( $data['alg'] ?? '' ) ||
			! isset( $data['iv'], $data['tag'], $data['ct'] ) ||
			! is_string( $data['iv'] ) ||
			! is_string( $data['tag'] ) ||
			! is_string( $data['ct'] )
		) {
			return new WP_Error( 'mediline_crypto_invalid_envelope', __( 'The encrypted integration payload is invalid.', 'mediline-integrations' ) );
		}

		$iv         = base64_decode( $data['iv'], true );
		$tag        = base64_decode( $data['tag'], true );
		$ciphertext = base64_decode( $data['ct'], true );
		if (
			false === $iv || self::IV_BYTES !== strlen( $iv ) ||
			false === $tag || self::TAG_BYTES !== strlen( $tag ) ||
			false === $ciphertext
		) {
			return new WP_Error( 'mediline_crypto_invalid_envelope', __( 'The encrypted integration payload is invalid.', 'mediline-integrations' ) );
		}

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::CIPHER,
			self::key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::AAD
		);
		self::wipe( $ciphertext );
		self::wipe( $tag );

		if ( false === $plaintext ) {
			return new WP_Error( 'mediline_crypto_auth_failed', __( 'The encrypted integration payload could not be authenticated.', 'mediline-integrations' ) );
		}

		$payload = json_decode( $plaintext, true );
		$valid   = JSON_ERROR_NONE === json_last_error() && is_array( $payload );
		self::wipe( $plaintext );

		if ( ! $valid ) {
			return new WP_Error( 'mediline_crypto_decode_failed', __( 'The decrypted integration payload is invalid.', 'mediline-integrations' ) );
		}

		return $payload;
	}

	/**
	 * Derive a site-specific binary key from WordPress authentication salts.
	 *
	 * @return string 32-byte key.
	 */
	private static function key() {
		$material = implode(
			'|',
			array(
				wp_salt( 'auth' ),
				wp_salt( 'secure_auth' ),
				wp_salt( 'logged_in' ),
				wp_salt( 'nonce' ),
			)
		);

		if ( function_exists( 'hash_hkdf' ) ) {
			return hash_hkdf( 'sha256', $material, 32, 'mediline-integrations-payload', 'mediline-integrations-v1' );
		}

		return hash( 'sha256', "mediline-integrations-v1\0" . $material, true );
	}

	/**
	 * Best-effort removal of sensitive intermediate strings.
	 *
	 * @param string $value Sensitive intermediate value, passed by reference.
	 * @return void
	 */
	private static function wipe( &$value ) {
		if ( is_string( $value ) && function_exists( 'sodium_memzero' ) ) {
			sodium_memzero( $value );
		}
		$value = '';
	}
}
