<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_Auth {
	const HEADER_STORE = 'x-mediline-store';
	const HEADER_TIME  = 'x-mediline-timestamp';
	const HEADER_NONCE = 'x-mediline-nonce';
	const HEADER_SIG   = 'x-mediline-signature';

	public static function encryption_key() {
		return hash( 'sha256', wp_salt( 'secure_auth' ) . 'mediline-catalog-store-secrets', true );
	}

	public static function encrypt_secret( $secret ) {
		$key = self::encryption_key();
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = sodium_crypto_secretbox( $secret, $nonce, $key );
			return 's1:' . base64_encode( $nonce . $box );
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$box = openssl_encrypt( $secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return 'o1:' . base64_encode( $iv . $tag . $box );
		}
		return new WP_Error( 'encryption_unavailable', 'Sodium or OpenSSL is required.' );
	}

	public static function decrypt_secret( $payload ) {
		$key = self::encryption_key();
		if ( 0 === strpos( (string) $payload, 's1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw   = base64_decode( substr( $payload, 3 ), true );
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$box   = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return sodium_crypto_secretbox_open( $box, $nonce, $key );
		}
		if ( 0 === strpos( (string) $payload, 'o1:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $payload, 3 ), true );
			if ( ! $raw || strlen( $raw ) < 29 ) { return false; }
			$iv  = substr( $raw, 0, 12 );
			$tag = substr( $raw, 12, 16 );
			$box = substr( $raw, 28 );
			return openssl_decrypt( $box, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		}
		return false;
	}

	public static function register_store( array $args ) {
		global $wpdb;
		$currency = strtoupper( sanitize_key( $args['currency'] ?? get_woocommerce_currency() ) );
		if ( function_exists( 'mediline_catalog_supported_currencies' ) && ! in_array( $currency, mediline_catalog_supported_currencies(), true ) ) {
			return new WP_Error( 'currency_unsupported', 'This storefront currency is not configured on the central WooCommerce checkout.' );
		}
		$market = strtoupper( sanitize_key( $args['market'] ?? 'EU' ) );
		if ( function_exists( 'mediline_catalog_supported_markets' ) && ! in_array( $market, mediline_catalog_supported_markets(), true ) ) {
			return new WP_Error( 'market_unsupported', 'This storefront market is not supported.' );
		}
		$secret = bin2hex( random_bytes( 32 ) );
		$encrypted = self::encrypt_secret( $secret );
		if ( is_wp_error( $encrypted ) ) { return $encrypted; }

		$uuid = 'store_' . strtolower( wp_generate_password( 20, false, false ) );
		$languages = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) ( $args['languages'] ?? array( 'en' ) ) ) ) ) );
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			Mediline_Catalog_DB::stores_table(),
			array(
				'store_uuid'       => $uuid,
				'installation_id'  => sanitize_text_field( $args['installation_id'] ?? '' ),
				'template_key'     => sanitize_text_field( $args['template_key'] ?? '' ),
				'template_version' => sanitize_text_field( $args['template_version'] ?? '' ),
				'affiliate_id'     => sanitize_text_field( $args['affiliate_id'] ?? '' ),
				'affiliate_refid'  => sanitize_text_field( $args['affiliate_refid'] ?? '' ),
				'domain'           => sanitize_text_field( $args['domain'] ?? '' ),
				'market'           => $market,
				'currency'         => $currency,
				'primary_language' => sanitize_key( $args['primary_language'] ?? 'en' ),
				'languages'        => wp_json_encode( $languages ),
				'secret_payload'   => $encrypted,
				'status'           => 'active',
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'store_create_failed', 'Could not register storefront.' );
		}
		return array(
			'store_id'   => $uuid,
			'store_secret' => $secret,
			'api_url'    => untrailingslashit( rest_url( 'mediline/v1' ) ),
			'market'     => $market,
			'currency'   => $currency,
			'languages'  => $languages,
		);
	}

	public static function get_store( $store_uuid ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Mediline_Catalog_DB::stores_table() . ' WHERE store_uuid = %s AND status = %s LIMIT 1', $store_uuid, 'active' ) );
	}

	public static function canonical_request( WP_REST_Request $request, $timestamp, $nonce ) {
		$method = strtoupper( $request->get_method() );
		$route  = $request->get_route();
		$body   = (string) $request->get_body();
		return $timestamp . "\n" . $nonce . "\n" . $method . "\n" . $route . "\n" . hash( 'sha256', $body );
	}

	public static function verify_request( WP_REST_Request $request ) {
		$store_id  = sanitize_text_field( (string) $request->get_header( self::HEADER_STORE ) );
		$timestamp = (string) $request->get_header( self::HEADER_TIME );
		$nonce     = sanitize_text_field( (string) $request->get_header( self::HEADER_NONCE ) );
		$signature = strtolower( sanitize_text_field( (string) $request->get_header( self::HEADER_SIG ) ) );
		if ( ! $store_id || ! ctype_digit( $timestamp ) || ! $nonce || ! preg_match( '/^[a-f0-9]{64}$/', $signature ) ) {
			return new WP_Error( 'mediline_auth_missing', 'Missing storefront authentication.', array( 'status' => 401 ) );
		}
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return new WP_Error( 'mediline_auth_expired', 'Request timestamp expired.', array( 'status' => 401 ) );
		}
		$nonce_key = 'mediline_nonce_' . hash( 'sha256', $store_id . '|' . $nonce );
		if ( get_transient( $nonce_key ) ) {
			return new WP_Error( 'mediline_auth_replay', 'Request nonce has already been used.', array( 'status' => 401 ) );
		}
		$store = self::get_store( $store_id );
		if ( ! $store ) {
			return new WP_Error( 'mediline_store_unknown', 'Unknown storefront.', array( 'status' => 401 ) );
		}
		$secret = self::decrypt_secret( $store->secret_payload );
		if ( ! $secret ) {
			return new WP_Error( 'mediline_store_secret', 'Storefront secret is unavailable.', array( 'status' => 500 ) );
		}
		$expected = hash_hmac( 'sha256', self::canonical_request( $request, $timestamp, $nonce ), $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'mediline_auth_invalid', 'Invalid storefront signature.', array( 'status' => 401 ) );
		}
		set_transient( $nonce_key, 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}
}
