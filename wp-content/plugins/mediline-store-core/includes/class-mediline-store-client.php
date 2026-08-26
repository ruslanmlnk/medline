<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_Client {
	public static function settings() {
		return wp_parse_args( get_option( 'mediline_store_settings', array() ), array(
			'api_url'          => '',
			'store_id'         => '',
			'store_secret'     => '',
			'primary_language' => 'en',
			'languages'        => array( 'en' ),
			'currency'         => 'EUR',
			'sync_interval'    => 15,
			'pap_tracking_script_url' => '',
			'pap_account_id'          => 'default1',
		) );
	}

	public static function configured() {
		$s = self::settings();
		return ! empty( $s['api_url'] ) && ! empty( $s['store_id'] ) && ! empty( $s['store_secret'] );
	}

	public static function canonical( $timestamp, $nonce, $method, $route, $body ) {
		return $timestamp . "\n" . $nonce . "\n" . strtoupper( $method ) . "\n" . $route . "\n" . hash( 'sha256', $body );
	}

	public static function request( $method, $path, array $data = array(), array $query = array() ) {
		$settings = self::settings();
		if ( ! self::configured() ) { return new WP_Error( 'mediline_store_unconfigured', 'Mediline Store Core is not configured.' ); }
		$base = untrailingslashit( $settings['api_url'] );
		$path = '/' . ltrim( $path, '/' );
		$route = '/mediline/v1' . $path;
		$url = $base . $path;
		if ( $query ) { $url = add_query_arg( $query, $url ); }
		$body = 'GET' === strtoupper( $method ) ? '' : wp_json_encode( $data );
		$timestamp = (string) time();
		$nonce = bin2hex( random_bytes( 16 ) );
		$signature = hash_hmac( 'sha256', self::canonical( $timestamp, $nonce, $method, $route, $body ), $settings['store_secret'] );
		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 20,
			'headers' => array(
				'Accept'               => 'application/json',
				'Content-Type'         => 'application/json',
				'X-Mediline-Store'     => $settings['store_id'],
				'X-Mediline-Timestamp' => $timestamp,
				'X-Mediline-Nonce'     => $nonce,
				'X-Mediline-Signature' => $signature,
			),
		);
		if ( '' !== $body ) { $args['body'] = $body; }
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) { return $response; }
		$status = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $decoded ) && ! empty( $decoded['message'] ) ? $decoded['message'] : 'Mediline API request failed.';
			return new WP_Error( 'mediline_api_' . $status, $message, array( 'status' => $status, 'response' => $decoded ) );
		}
		return is_array( $decoded ) ? $decoded : array();
	}

	public static function heartbeat( $status = 'online' ) {
		return self::request( 'POST', '/store/heartbeat', array(
			'status' => sanitize_key( $status ?: 'online' ),
			'url' => home_url( '/' ),
			'wordpress_version' => get_bloginfo( 'version' ),
			'store_core_version' => defined( 'MEDILINE_STORE_VERSION' ) ? MEDILINE_STORE_VERSION : '',
		) );
	}

}
