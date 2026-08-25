<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_API {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
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
		$result = Mediline_Store_Client::request( 'POST', '/checkout-sessions', array( 'items' => $items, 'lang' => $lang ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
