<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_API {
	const ATTRIBUTION_COOKIE = 'mediline_attribution';

	public static function init() {
		add_filter( 'script_loader_tag', array( __CLASS__, 'pap_script_tag' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_attribution' ), 1 );
	}

	public static function pap_script_tag( $tag, $handle ) {
		if ( 'mediline-store-pap-tracking' !== $handle ) { return $tag; }
		return preg_replace( '/\bid=([\x22\x27])[^\x22\x27]*\1/', 'id="pap_x2s6df8d"', $tag, 1 );
	}

	public static function enqueue_attribution() {
		if ( is_admin() ) { return; }
		$settings = Mediline_Store_Client::settings();
		$config = apply_filters( 'mediline_store_attribution_config', array(
			'cookieName'    => self::ATTRIBUTION_COOKIE,
			'storageKey'   => 'mediline_attribution_v1',
			'ttlSeconds'   => 90 * DAY_IN_SECONDS,
			'language'      => mediline_store_current_language(),
			'checkoutUrl'   => rest_url( 'mediline-store/v1/checkout' ),
			'papScriptUrl'  => $settings['pap_tracking_script_url'] ?? '',
			'papAccountId'  => $settings['pap_account_id'] ?? 'default1',
		) );

		$dependencies = array();
		$pap_script = esc_url_raw( (string) ( $config['papScriptUrl'] ?? '' ), array( 'https' ) );
		if ( $pap_script && wp_http_validate_url( $pap_script ) && 'https' === wp_parse_url( $pap_script, PHP_URL_SCHEME ) ) {
			wp_enqueue_script( 'mediline-store-pap-tracking', $pap_script, array(), null, true );
			$dependencies[] = 'mediline-store-pap-tracking';
		}

		$asset = MEDILINE_STORE_DIR . 'assets/js/attribution.js';
		$version = is_file( $asset ) ? (string) filemtime( $asset ) : MEDILINE_STORE_VERSION;
		wp_enqueue_script( 'mediline-store-attribution', MEDILINE_STORE_URL . 'assets/js/attribution.js', $dependencies, $version, true );
		wp_localize_script( 'mediline-store-attribution', 'MedilineStoreAttributionConfig', array(
			'cookieName'   => self::ATTRIBUTION_COOKIE,
			'storageKey'  => 'mediline_attribution_v1',
			'ttlSeconds'  => min( YEAR_IN_SECONDS, max( DAY_IN_SECONDS, absint( $config['ttlSeconds'] ?? 90 * DAY_IN_SECONDS ) ) ),
			'language'     => mediline_store_valid_language( $config['language'] ?? '' ),
			'checkoutUrl'  => esc_url_raw( (string) ( $config['checkoutUrl'] ?? rest_url( 'mediline-store/v1/checkout' ) ) ),
			'papEnabled'   => ! empty( $dependencies ),
			'papAccountId' => sanitize_text_field( (string) ( $config['papAccountId'] ?? 'default1' ) ),
		) );
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function text_value( $value, $length = 255 ) {
		if ( ! is_scalar( $value ) ) { return ''; }
		return self::truncate( sanitize_text_field( wp_unslash( (string) $value ) ), $length );
	}

	private static function safe_landing_url( $value ) {
		$url   = esc_url_raw( self::text_value( $value, 2048 ), array( 'http', 'https' ) );
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) { return ''; }
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) { return ''; }
		$clean = $scheme . '://' . strtolower( (string) $parts['host'] );
		if ( ! empty( $parts['port'] ) ) { $clean .= ':' . absint( $parts['port'] ); }
		$clean .= isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $raw_query );
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $key ) {
				$value = self::text_value( $raw_query[ $key ] ?? '', 200 );
				if ( '' !== $value ) { $query[ $key ] = $value; }
			}
			foreach ( array( 'gclid', 'fbclid' ) as $key ) {
				$value = preg_replace( '/[^A-Za-z0-9._~-]/', '', self::text_value( $raw_query[ $key ] ?? '', 160 ) );
				if ( '' !== $value ) { $query[ $key ] = $value; }
			}
		}
		return self::truncate( $query ? add_query_arg( $query, $clean ) : $clean, 700 );
	}

	private static function sanitize_attribution_touch( $raw, $language ) {
		if ( ! is_array( $raw ) ) { $raw = array(); }
		$touch = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $key ) {
			$value = self::text_value( $raw[ $key ] ?? '', 255 );
			if ( '' !== $value ) { $touch[ $key ] = $value; }
		}
		foreach ( array( 'gclid', 'fbclid' ) as $key ) {
			$value = preg_replace( '/[^A-Za-z0-9._~-]/', '', self::text_value( $raw[ $key ] ?? '', 255 ) );
			if ( '' !== $value ) { $touch[ $key ] = $value; }
		}
		$landing_url = self::safe_landing_url( $raw['landing_url'] ?? '' );
		if ( $landing_url ) { $touch['landing_url'] = $landing_url; }
		$touch['language'] = mediline_store_valid_language( $raw['language'] ?? $language );
		$captured_at = isset( $raw['captured_at'] ) && is_scalar( $raw['captured_at'] ) ? absint( $raw['captured_at'] ) : 0;
		if ( $captured_at ) { $touch['captured_at'] = $captured_at; }
		return $touch;
	}

	public static function sanitize_attribution( $raw, $language = '' ) {
		if ( is_string( $raw ) ) {
			$decoded = json_decode( wp_unslash( $raw ), true );
			$raw = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) { $raw = array(); }

		$touch_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'language', 'landing_url', 'captured_at' );
		$flattened = array_intersect_key( $raw, array_flip( $touch_keys ) );
		$current_raw = isset( $raw['current'] ) && is_array( $raw['current'] ) ? array_merge( $raw['current'], $flattened ) : $flattened;
		$first_raw = isset( $raw['first'] ) && is_array( $raw['first'] ) ? $raw['first'] : $current_raw;
		$current = self::sanitize_attribution_touch( $current_raw, $language );
		$first = self::sanitize_attribution_touch( $first_raw, $language );
		$clean = array(
			'version' => 1,
			'first'   => $first,
			'current' => $current,
		);
		foreach ( $current as $key => $value ) {
			if ( 'captured_at' !== $key ) { $clean[ $key ] = $value; }
		}

		$visitor = preg_replace( '/[^A-Za-z0-9_-]/', '', self::text_value( $raw['pap_visitor_id'] ?? '', 64 ) );
		if ( '' !== $visitor ) { $clean['pap_visitor_id'] = $visitor; }
		$affiliate = preg_replace( '/[^A-Za-z0-9._@:-]/', '', self::text_value( $raw['pap_affiliate_id'] ?? '', 191 ) );
		if ( '' !== $affiliate ) { $clean['pap_affiliate_id'] = $affiliate; }

		$submission = preg_replace( '/[^A-Za-z0-9._:-]/', '', self::text_value( $raw['submission_id'] ?? '', 64 ) );
		if ( '' !== $submission ) { $clean['submission_id'] = $submission; }
		$clean['updated_at'] = isset( $raw['updated_at'] ) && is_scalar( $raw['updated_at'] ) ? absint( $raw['updated_at'] ) : time();
		$clean['expires_at'] = isset( $raw['expires_at'] ) && is_scalar( $raw['expires_at'] ) ? absint( $raw['expires_at'] ) : 0;
		$clean['language'] = mediline_store_valid_language( $language );
		$clean['current']['language'] = $clean['language'];
		return $clean;
	}

	private static function attribution_cookie_data() {
		$raw = $_COOKIE[ self::ATTRIBUTION_COOKIE ] ?? '';
		if ( ! is_scalar( $raw ) ) { return array(); }
		$raw = wp_unslash( (string) $raw );
		if ( strlen( $raw ) > 8192 ) { return array(); }
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) && str_starts_with( $raw, '%' ) ) {
			$decoded = json_decode( rawurldecode( $raw ), true );
		}
		return is_array( $decoded ) ? $decoded : array();
	}

	private static function attribution_cookie_context() {
		$raw = self::attribution_cookie_data();
		$cookie_aliases = array(
			'pap_visitor_id'   => array( 'mediline_pap_visitor_id', 'pap_visitor_id', 'PAPVisitorId', 'visitorID', 'visitorId' ),
			'pap_affiliate_id' => array( 'mediline_pap_affiliate_id', 'pap_affiliate_id', 'affiliateID', 'affiliateId' ),
			'utm_source'       => array( 'utm_source' ),
			'utm_medium'       => array( 'utm_medium' ),
			'utm_campaign'     => array( 'utm_campaign' ),
			'utm_term'         => array( 'utm_term' ),
			'utm_content'      => array( 'utm_content' ),
			'gclid'            => array( 'gclid' ),
			'fbclid'           => array( 'fbclid' ),
		);
		foreach ( $cookie_aliases as $key => $aliases ) {
			foreach ( $aliases as $alias ) {
				if ( isset( $_COOKIE[ $alias ] ) && is_scalar( $_COOKIE[ $alias ] ) ) {
					$raw[ $key ] = wp_unslash( (string) $_COOKIE[ $alias ] );
					break;
				}
			}
		}
		return $raw;
	}

	public static function attribution_from_cookie( $language ) {
		$clean = self::sanitize_attribution( self::attribution_cookie_context(), $language );
		if ( empty( $clean['submission_id'] ) ) { $clean['submission_id'] = wp_generate_uuid4(); }
		return $clean;
	}

	public static function attribution_from_request( WP_REST_Request $request, $language ) {
		$raw = self::attribution_cookie_context();
		$provided = $request->get_param( 'attribution' );
		if ( is_string( $provided ) ) {
			$decoded = json_decode( $provided, true );
			$provided = is_array( $decoded ) ? $decoded : array();
		}
		if ( is_array( $provided ) ) { $raw = array_merge( $raw, $provided ); }
		$request_aliases = array(
			'visitorID'  => 'pap_visitor_id',
			'visitorId'  => 'pap_visitor_id',
			'affiliateID' => 'pap_affiliate_id',
			'affiliateId' => 'pap_affiliate_id',
		);
		foreach ( $request_aliases as $alias => $key ) {
			$value = $request->get_param( $alias );
			if ( null !== $value ) { $raw[ $key ] = $value; }
		}
		foreach ( array( 'pap_visitor_id', 'pap_affiliate_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'landing_url', 'submission_id' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) { $raw[ $key ] = $value; }
		}

		$clean = self::sanitize_attribution( $raw, $language );
		if ( empty( $clean['submission_id'] ) ) { $clean['submission_id'] = wp_generate_uuid4(); }
		return $clean;
	}
	public static function routes() {
		register_rest_route( 'mediline-store/v1', '/products', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'products' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mediline-store/v1', '/products/(?P<id>[A-Za-z0-9_-]+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'product' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mediline-store/v1', '/categories', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'categories' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mediline-store/v1', '/checkout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'checkout' ), 'permission_callback' => '__return_true' ) );
		register_rest_route( 'mediline-store/v1', '/status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ), 'permission_callback' => '__return_true' ) );
	}
	public static function products( WP_REST_Request $request ) {
		return rest_ensure_response( array( 'items' => mediline_store_get_products( array( 'lang' => sanitize_key( $request->get_param( 'lang' ) ?: mediline_store_current_language() ), 'limit' => min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 24 ) ) ), 'offset' => absint( $request->get_param( 'offset' ) ), 'featured' => rest_sanitize_boolean( $request->get_param( 'featured' ) ), 'category' => sanitize_title( $request->get_param( 'category' ) ), 'search' => sanitize_text_field( $request->get_param( 'search' ) ) ) ) ) );
	}
	public static function product( WP_REST_Request $request ) {
		$item = mediline_store_get_product( $request['id'], sanitize_key( $request->get_param( 'lang' ) ?: mediline_store_current_language() ) );
		return $item ? rest_ensure_response( array( 'item' => $item ) ) : new WP_Error( 'mediline_local_product', 'Product not found.', array( 'status' => 404 ) );
	}
	public static function categories( WP_REST_Request $request ) { return rest_ensure_response( array( 'items' => Mediline_Store_DB::categories( sanitize_key( $request->get_param( 'lang' ) ?: mediline_store_current_language() ) ) ) ); }
	public static function status() {
		global $wpdb;
		return rest_ensure_response( array(
			'configured' => Mediline_Store_Client::configured(),
			'products' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Mediline_Store_DB::products_table() ),
			'languages' => mediline_store_languages(),
			'last_sync' => get_option( 'mediline_store_last_sync', null ),
			'version' => MEDILINE_STORE_VERSION,
		) );
	}
	public static function checkout( WP_REST_Request $request ) {
		$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
		$rate_key = 'mediline_checkout_rate_' . hash( 'sha256', $ip );
		$rate = (int) get_transient( $rate_key );
		if ( $rate >= 30 ) { return new WP_Error( 'mediline_checkout_rate', 'Too many checkout requests. Try again shortly.', array( 'status' => 429 ) ); }
		set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );
		$items = $request->get_param( 'items' );
		if ( ! is_array( $items ) || ! $items ) { return new WP_Error( 'mediline_checkout_cart', 'Cart is empty.', array( 'status' => 400 ) ); }
		$lang = mediline_store_valid_language( $request->get_param( 'lang' ) );
		$attribution = self::attribution_from_request( $request, $lang );
		$result = Mediline_Store_Client::request( 'POST', '/checkout-sessions', array(
			'items'          => $items,
			'lang'           => $lang,
			'attribution'    => $attribution,
			'customer'       => $request->get_param( 'customer' ),
			'payment_method' => $request->get_param( 'payment_method' ),
			'order_notes'    => $request->get_param( 'order_notes' ),
			'privacy_consent'=> $request->get_param( 'privacy_consent' ),
			'website'        => $request->get_param( 'website' ),
		) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
