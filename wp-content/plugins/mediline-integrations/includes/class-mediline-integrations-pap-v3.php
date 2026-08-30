<?php
/**
 * Read-only Post Affiliate Pro REST API v3 client.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mediline_Integrations_Pap_V3 {
	/** @var array<string,mixed> */
	private $settings;

	/** @var string */
	private $token;

	public function __construct( array $settings, $token ) {
		$this->settings = $settings;
		$this->token    = trim( (string) $token );
	}

	public static function configured() {
		$settings = function_exists( 'mediline_integrations_settings' ) ? mediline_integrations_settings() : array();
		$token    = function_exists( 'mediline_integrations_secret' ) ? mediline_integrations_secret( 'pap_api_v3_token' ) : '';
		return ! empty( $settings['pap_api_v3_enabled'] )
			&& self::normalize_api_base( $settings['pap_api_v3_base'] ?? '' )
			&& ! empty( $token );
	}

	/**
	 * Pin credentials to the official hosted PAP origin and the /api/v3 path.
	 */
	public static function normalize_api_base( $value ) {
		$url   = is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), array( 'https' ) ) : '';
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return '';
		}
		$host = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );
		if ( ! $host || ( 'postaffiliatepro.com' !== $host && ! str_ends_with( $host, '.postaffiliatepro.com' ) ) ) {
			return '';
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
			return '';
		}
		if ( isset( $parts['port'] ) && 443 !== absint( $parts['port'] ) ) {
			return '';
		}
		$path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
		if ( '/api/v3' !== strtolower( $path ) ) {
			return '';
		}
		return 'https://' . $host . '/api/v3';
	}

	/**
	 * Resolve one affiliate by PAP's internal user ID.
	 *
	 * @return array<string,string>|WP_Error
	 */
	public function get_affiliate( $user_id ) {
		$user_id = is_scalar( $user_id ) ? trim( (string) $user_id ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9_-]{1,100}$/D', $user_id ) ) {
			return new WP_Error( 'mediline_pap_v3_userid', 'PAP returned an invalid affiliate user ID.', array( 'status' => 502 ) );
		}

		// GET /affiliates is part of PAP's documented v3 contract. The search
		// narrows the response, but identity is accepted only after an exact
		// comparison with the userid obtained from the signed-in v1 session.
		$list = $this->request(
			'/affiliates',
			array(
				'q'      => 'id:' . $user_id,
				'fields' => 'referral_id,username,status',
			)
		);
		if ( is_wp_error( $list ) ) {
			return $list;
		}
		foreach ( self::records( $list ) as $record ) {
			$affiliate = self::normalize_affiliate( $record );
			if ( $affiliate && hash_equals( $user_id, $affiliate['userid'] ) ) {
				return $affiliate;
			}
		}
		return new WP_Error( 'mediline_pap_v3_not_found', 'The session-owned affiliate was not found in PAP API v3.', array( 'status' => 403 ) );
	}

	/** @return mixed|WP_Error */
	public function request( $path, array $query = array() ) {
		$base = self::normalize_api_base( $this->settings['pap_api_v3_base'] ?? '' );
		if ( ! $base || ! $this->token ) {
			return new WP_Error( 'mediline_pap_v3_configuration', 'PAP API v3 HTTPS base and token are required.', array( 'status' => 503 ) );
		}
		$path = '/' . ltrim( (string) $path, '/' );
		if ( ! preg_match( '#^/affiliates(?:/[A-Za-z0-9_%.-]+)?$#D', $path ) ) {
			return new WP_Error( 'mediline_pap_v3_path', 'The PAP API v3 path is not allowed.', array( 'status' => 500 ) );
		}
		$url = $base . $path;
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 8,
				'redirection'         => 0,
				'limit_response_size' => 1024 * 1024,
				'headers'             => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $this->token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mediline_pap_v3_network', 'PAP API v3 is temporarily unavailable.', array( 'status' => 502 ) );
		}
		$status  = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			return new WP_Error( 'mediline_pap_v3_http_' . $status, 'PAP API v3 rejected the affiliate lookup.', array( 'status' => $status, 'retryable' => 429 === $status || $status >= 500 ) );
		}
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'mediline_pap_v3_json', 'PAP API v3 returned invalid JSON.', array( 'status' => 502 ) );
		}
		return array_key_exists( 'data', $decoded ) ? $decoded['data'] : $decoded;
	}

	/** @return array<int,array<string,mixed>> */
	private static function records( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}
		foreach ( array( 'items', 'results', 'affiliates' ) as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				return array_values( array_filter( $data[ $key ], 'is_array' ) );
			}
		}
		return array_is_list( $data ) ? array_values( array_filter( $data, 'is_array' ) ) : array( $data );
	}

	/** @return array<string,string>|null */
	private static function normalize_affiliate( $record ) {
		if ( ! is_array( $record ) ) {
			return null;
		}
		if ( isset( $record['affiliate'] ) && is_array( $record['affiliate'] ) ) {
			$record = $record['affiliate'];
		}
		$userid = self::scalar( $record, array( 'userid', 'user_id', 'id' ) );
		$refid  = self::scalar( $record, array( 'refid', 'referral_id', 'referralId' ) );
		if ( ! $userid || ! $refid ) {
			return null;
		}
		$status = strtolower( self::scalar( $record, array( 'rstatus', 'status', 'account_status' ) ) );
		if ( in_array( $status, array( 'a', 'active', 'approved' ), true ) ) {
			$status = 'A';
		} elseif ( in_array( $status, array( 'p', 'pending' ), true ) ) {
			$status = 'P';
		} elseif ( $status ) {
			$status = 'D';
		}
		return array(
			'userid'   => sanitize_text_field( $userid ),
			'refid'    => sanitize_text_field( $refid ),
			'username' => sanitize_text_field( self::scalar( $record, array( 'username', 'email' ) ) ),
			'status'   => $status,
		);
	}

	private static function scalar( array $record, array $keys ) {
		foreach ( $keys as $key ) {
			if ( isset( $record[ $key ] ) && is_scalar( $record[ $key ] ) ) {
				return trim( (string) $record[ $key ] );
			}
		}
		return '';
	}
}
