<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_API {
	const CHECKOUT_COOKIE = 'mediline_checkout_source';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'claim_checkout_session' ), 1 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_prices' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_order_source' ), 20, 2 );
	}

	public static function register_routes() {
		$auth = array( __CLASS__, 'permission' );
		register_rest_route( 'mediline/v1', '/catalog/config', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'config' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/catalog/products', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'products' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/catalog/products/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'product' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/catalog/categories', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'categories' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/catalog/sync', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'sync' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/checkout-sessions', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'checkout_session' ), 'permission_callback' => $auth ) );
		register_rest_route( 'mediline/v1', '/store/heartbeat', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'heartbeat' ), 'permission_callback' => $auth ) );
	}

	public static function permission( WP_REST_Request $request ) {
		return Mediline_Catalog_Auth::verify_request( $request );
	}

	public static function store( WP_REST_Request $request ) {
		return Mediline_Catalog_Auth::get_store( sanitize_text_field( (string) $request->get_header( Mediline_Catalog_Auth::HEADER_STORE ) ) );
	}

	public static function language_for_store( $store, $requested ) {
		$configured = json_decode( (string) $store->languages, true );
		$languages = array();
		foreach ( is_array( $configured ) ? $configured : array() as $language ) {
			if ( is_scalar( $language ) && sanitize_key( (string) $language ) ) { $languages[] = sanitize_key( (string) $language ); }
		}
		$primary = sanitize_key( (string) $store->primary_language ) ?: 'en';
		if ( ! in_array( $primary, $languages, true ) ) { array_unshift( $languages, $primary ); }
		$lang = is_scalar( $requested ) ? sanitize_key( (string) $requested ) : '';
		return $lang && in_array( $lang, $languages, true ) ? $lang : $primary;
	}

	public static function config( WP_REST_Request $request ) {
		$store = self::store( $request );
		return rest_ensure_response( array(
			'store_id'        => $store->store_uuid,
			'market'          => $store->market,
			'currency'        => $store->currency,
			'primary_language'=> $store->primary_language,
			'languages'       => json_decode( $store->languages, true ),
			'catalog_version' => Mediline_Catalog_DB::current_version(),
			'checkout_mode'   => 'central_woocommerce',
		) );
	}

	public static function products( WP_REST_Request $request ) {
		$store = self::store( $request );
		$lang  = self::language_for_store( $store, $request->get_param( 'lang' ) );
		$page  = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per   = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$args  = array(
			'status'   => 'publish',
			'limit'    => $per,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'ID',
			'order'    => 'ASC',
		);
		$category = sanitize_title( (string) $request->get_param( 'category' ) );
		if ( $category ) { $args['category'] = array( $category ); }
		$featured = $request->get_param( 'featured' );
		if ( null !== $featured && '' !== $featured ) { $args['featured'] = rest_sanitize_boolean( $featured ); }
		$result = wc_get_products( $args );
		$items  = array();
		foreach ( $result->products as $product ) {
			$item = Mediline_Catalog_Product::serialize( $product, $store, $lang );
			if ( $item ) { $items[] = $item; }
		}
		return rest_ensure_response( array(
			'catalog_version' => Mediline_Catalog_DB::current_version(),
			'page'            => $page,
			'per_page'        => $per,
			'total'           => (int) $result->total,
			'total_pages'     => (int) $result->max_num_pages,
			'items'           => $items,
		) );
	}

	public static function product( WP_REST_Request $request ) {
		$store   = self::store( $request );
		$lang    = self::language_for_store( $store, $request->get_param( 'lang' ) );
		$product = wc_get_product( absint( $request['id'] ) );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return new WP_Error( 'mediline_product_missing', 'Product not found.', array( 'status' => 404 ) );
		}
		$item = Mediline_Catalog_Product::serialize( $product, $store, $lang );
		if ( ! $item ) { return new WP_Error( 'mediline_product_market', 'Product is unavailable for this market.', array( 'status' => 404 ) ); }
		return rest_ensure_response( array( 'catalog_version' => Mediline_Catalog_DB::current_version(), 'item' => $item ) );
	}

	public static function category_payload( $term, $lang = 'en' ) {
		$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
		$text = Mediline_Catalog_Product::translated_category( $term, $lang );
		return array(
			'id'          => (int) $term->term_id,
			'slug'        => $term->slug,
			'name'        => $text['name'],
			'description' => $text['description'],
			'parent'      => (int) $term->parent,
			'image'       => $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'full' ) : null,
		);
	}

	public static function categories( WP_REST_Request $request ) {
		$store = self::store( $request );
		$lang = self::language_for_store( $store, $request->get_param( 'lang' ) );
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) { return $terms; }
		$items = array(); foreach ( $terms as $term ) { $items[] = self::category_payload( $term, $lang ); }
		return rest_ensure_response( array( 'catalog_version' => Mediline_Catalog_DB::current_version(), 'items' => $items ) );
	}

	public static function sync( WP_REST_Request $request ) {
		global $wpdb;
		$store = self::store( $request );
		$lang  = self::language_for_store( $store, $request->get_param( 'lang' ) );
		$since = max( 0, (int) $request->get_param( 'since_version' ) );
		$limit = min( 500, max( 1, absint( $request->get_param( 'limit' ) ?: 200 ) ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT version,object_type,object_id,action FROM ' . Mediline_Catalog_DB::changes_table() . ' WHERE version > %d ORDER BY version ASC,id ASC LIMIT %d', $since, $limit ) );
		$updated = array();
		$deleted = array();
		$category_changed = false;
		$last = $since;
		foreach ( $rows as $row ) {
			$last = max( $last, (int) $row->version );
			if ( 'category' === $row->object_type ) { $category_changed = true; continue; }
			if ( 'product' !== $row->object_type ) { continue; }
			if ( 'delete' === $row->action ) {
				$deleted[] = (int) $row->object_id;
				continue;
			}
			$product = wc_get_product( (int) $row->object_id );
			$item = $product && 'publish' === $product->get_status() ? Mediline_Catalog_Product::serialize( $product, $store, $lang ) : null;
			if ( $item ) { $updated[ $item['id'] ] = $item; } else { $deleted[] = (int) $row->object_id; }
		}
		$categories = null;
		if ( $category_changed ) {
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
			$categories = array(); if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $categories[] = self::category_payload( $term, $lang ); } }
		}
		$current = Mediline_Catalog_DB::current_version();
		return rest_ensure_response( array(
			'from_version'    => $since,
			'to_version'      => $last,
			'catalog_version' => $current,
			'has_more'        => $last < $current && count( $rows ) >= $limit,
			'updated'         => array_values( $updated ),
			'deleted'         => array_values( array_unique( $deleted ) ),
			'categories'      => $categories,
		) );
	}

	public static function heartbeat( WP_REST_Request $request ) {
		global $wpdb;
		$store = self::store( $request );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( ! in_array( $status, array( 'online', 'installing', 'error' ), true ) ) { $status = 'online'; }
		$wpdb->update(
			Mediline_Catalog_DB::stores_table(),
			array( 'status' => 'active', 'last_seen' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $store->id ),
			array( '%s', '%s', '%s' ), array( '%d' )
		);
		return rest_ensure_response( array( 'ok' => true, 'store_id' => $store->store_uuid, 'status' => $status, 'server_time' => gmdate( 'c' ) ) );
	}

	public static function normalize_items( $raw, $store ) {
		if ( ! is_array( $raw ) || ! $raw ) { return new WP_Error( 'mediline_cart_empty', 'Cart is empty.', array( 'status' => 400 ) ); }
		$items = array();
		foreach ( $raw as $line ) {
			$product_id   = absint( $line['product_id'] ?? 0 );
			$variation_id = absint( $line['variation_id'] ?? 0 );
			$quantity     = min( 99, max( 1, absint( $line['quantity'] ?? 1 ) ) );
			$base = wc_get_product( $product_id );
			if ( ! $base || 'publish' !== $base->get_status() || ! Mediline_Catalog_Product::product_allowed( $base->get_id(), $store->market ) ) {
				return new WP_Error( 'mediline_cart_product', 'A cart item is unavailable.', array( 'status' => 409 ) );
			}
			$product = $variation_id ? wc_get_product( $variation_id ) : $base;
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				return new WP_Error( 'mediline_cart_stock', 'A cart item is not purchasable.', array( 'status' => 409 ) );
			}
			$items[] = array(
				'product_id'   => $base->get_id(),
				'variation_id' => $variation_id,
				'quantity'     => $quantity,
				'unit_price'   => Mediline_Catalog_Product::market_price( $product, $store->market ),
				'variation'    => $product instanceof WC_Product_Variation ? $product->get_variation_attributes() : array(),
			);
		}
		return $items;
	}

	public static function checkout_session( WP_REST_Request $request ) {
		$store = self::store( $request );
		$language = self::language_for_store( $store, $request->get_param( 'lang' ) );
		$rate_key = 'mediline_central_checkout_' . hash( 'sha256', $store->store_uuid );
		$rate = (int) get_transient( $rate_key );
		if ( $rate >= 120 ) { return new WP_Error( 'mediline_checkout_rate', 'Too many checkout sessions for this storefront.', array( 'status' => 429 ) ); }
		set_transient( $rate_key, $rate + 1, MINUTE_IN_SECONDS );
		$items = self::normalize_items( $request->get_param( 'items' ), $store );
		if ( is_wp_error( $items ) ) { return $items; }
		if ( strtoupper( (string) $store->currency ) !== strtoupper( get_woocommerce_currency() ) ) {
			return new WP_Error( 'mediline_currency_unconfigured', 'Central WooCommerce currency does not match this storefront. Configure a multi-currency integration before using this market.', array( 'status' => 409, 'store_currency' => $store->currency, 'central_currency' => get_woocommerce_currency() ) );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$payload = array(
			'store_id'       => $store->store_uuid,
			'affiliate_id'   => $store->affiliate_id,
			'affiliate_refid'=> $store->affiliate_refid,
			'market'         => $store->market,
			'currency'       => $store->currency,
			'language'       => $language,
			'items'          => $items,
			'created_at'     => time(),
		);
		set_transient( 'mediline_checkout_' . hash( 'sha256', $token ), $payload, 30 * MINUTE_IN_SECONDS );
		return rest_ensure_response( array(
			'checkout_url' => add_query_arg( array( 'mediline_checkout' => rawurlencode( $token ), 'lang' => $language ), home_url( '/' ) ),
			'language'     => $language,
			'expires_in'   => 30 * MINUTE_IN_SECONDS,
		) );
	}

	public static function claim_checkout_session() {
		if ( empty( $_GET['mediline_checkout'] ) || ! function_exists( 'WC' ) ) { return; }
		$token = sanitize_text_field( wp_unslash( $_GET['mediline_checkout'] ) );
		$key   = 'mediline_checkout_' . hash( 'sha256', $token );
		$data  = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $data ) || empty( $data['items'] ) ) {
			wp_die( esc_html__( 'This checkout session is invalid or expired.', 'mediline-catalog-core' ), '', array( 'response' => 410 ) );
		}
		if ( null === WC()->cart ) { wc_load_cart(); }
		WC()->cart->empty_cart();
		foreach ( $data['items'] as $line ) {
			$cart_data = array( 'mediline_unit_price' => (float) $line['unit_price'], 'mediline_source_store' => $data['store_id'] );
			$added = WC()->cart->add_to_cart( (int) $line['product_id'], (int) $line['quantity'], (int) $line['variation_id'], (array) ( $line['variation'] ?? array() ), $cart_data );
			if ( ! $added ) { wp_die( esc_html__( 'A product could not be added to the central checkout.', 'mediline-catalog-core' ), '', array( 'response' => 409 ) ); }
		}
		if ( WC()->session ) {
			$language = sanitize_key( (string) ( $data['language'] ?? '' ) );
			WC()->session->set( 'mediline_checkout_source', array(
				'store_id'        => $data['store_id'],
				'affiliate_id'    => $data['affiliate_id'],
				'affiliate_refid' => $data['affiliate_refid'],
				'market'          => $data['market'],
				'language'        => $language,
			) );
			WC()->session->set( 'mediline_language', $language );
		}
		$checkout_url = wc_get_checkout_url();
		if ( ! empty( $data['language'] ) ) { $checkout_url = add_query_arg( 'lang', sanitize_key( $data['language'] ), $checkout_url ); }
		wp_safe_redirect( $checkout_url );
		exit;
	}

	public static function apply_cart_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['mediline_unit_price'] ) && isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( (float) $item['mediline_unit_price'] );
			}
		}
	}

	public static function attach_order_source( $order, $data ) {
		$source = WC()->session ? WC()->session->get( 'mediline_checkout_source' ) : null;
		if ( ! is_array( $source ) ) { return; }
		$order->update_meta_data( '_mediline_source_store_id', sanitize_text_field( $source['store_id'] ?? '' ) );
		$order->update_meta_data( '_mediline_affiliate_id', sanitize_text_field( $source['affiliate_id'] ?? '' ) );
		$order->update_meta_data( '_mediline_affiliate_refid', sanitize_text_field( $source['affiliate_refid'] ?? '' ) );
		$order->update_meta_data( '_mediline_market', sanitize_text_field( $source['market'] ?? '' ) );
		$order->update_meta_data( '_mediline_language', sanitize_key( $source['language'] ?? '' ) );
		WC()->session->set( 'mediline_checkout_source', null );
		WC()->session->set( 'mediline_language', null );
	}
}
