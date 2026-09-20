<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_API {
	const CHECKOUT_COOKIE = 'mediline_checkout_source';

	public static function init() {
		add_filter( 'woocommerce_defer_transactional_emails', '__return_true' );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'template_redirect', array( __CLASS__, 'claim_checkout_session' ), 1 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_cart_prices' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_order_source' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'attach_store_api_order_source' ), 20, 2 );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_payment_instructions' ), 15, 4 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou_payment_instructions' ), 15 );
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

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	private static function attribution_text( $value, $length = 255 ) {
		if ( ! is_scalar( $value ) ) { return ''; }
		return self::truncate( sanitize_text_field( wp_unslash( (string) $value ) ), $length );
	}

	private static function safe_landing_url( $value ) {
		$url   = esc_url_raw( self::attribution_text( $value, 2048 ), array( 'http', 'https' ) );
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
				$value = self::attribution_text( $raw_query[ $key ] ?? '', 200 );
				if ( '' !== $value ) { $query[ $key ] = $value; }
			}
			foreach ( array( 'gclid', 'fbclid' ) as $key ) {
				$value = preg_replace( '/[^A-Za-z0-9._~-]/', '', self::attribution_text( $raw_query[ $key ] ?? '', 160 ) );
				if ( '' !== $value ) { $query[ $key ] = $value; }
			}
		}
		return self::truncate( $query ? add_query_arg( $query, $clean ) : $clean, 700 );
	}

	private static function sanitize_attribution_touch( $raw, $language ) {
		if ( ! is_array( $raw ) ) { $raw = array(); }
		$touch = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $key ) {
			$value = self::attribution_text( $raw[ $key ] ?? '', 255 );
			if ( '' !== $value ) { $touch[ $key ] = $value; }
		}
		foreach ( array( 'gclid', 'fbclid' ) as $key ) {
			$value = preg_replace( '/[^A-Za-z0-9._~-]/', '', self::attribution_text( $raw[ $key ] ?? '', 255 ) );
			if ( '' !== $value ) { $touch[ $key ] = $value; }
		}
		$landing_url = self::safe_landing_url( $raw['landing_url'] ?? '' );
		if ( $landing_url ) { $touch['landing_url'] = $landing_url; }
		$touch['language'] = substr( sanitize_key( (string) ( $raw['language'] ?? $language ) ), 0, 16 ) ?: sanitize_key( (string) $language );
		$captured_at = isset( $raw['captured_at'] ) && is_scalar( $raw['captured_at'] ) ? absint( $raw['captured_at'] ) : 0;
		if ( $captured_at ) { $touch['captured_at'] = $captured_at; }
		return $touch;
	}

	public static function sanitize_attribution( $raw, $language ) {
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

		$visitor = preg_replace( '/[^A-Za-z0-9_-]/', '', self::attribution_text( $raw['pap_visitor_id'] ?? '', 64 ) );
		if ( '' !== $visitor ) { $clean['pap_visitor_id'] = $visitor; }
		$affiliate = preg_replace( '/[^A-Za-z0-9._@:-]/', '', self::attribution_text( $raw['pap_affiliate_id'] ?? '', 191 ) );
		if ( '' !== $affiliate ) { $clean['pap_affiliate_id'] = $affiliate; }

		$submission = preg_replace( '/[^A-Za-z0-9._:-]/', '', self::attribution_text( $raw['submission_id'] ?? '', 64 ) );
		$clean['submission_id'] = $submission ?: wp_generate_uuid4();
		$clean['language'] = sanitize_key( (string) $language );
		$clean['current']['language'] = $clean['language'];
		$clean['updated_at'] = isset( $raw['updated_at'] ) && is_scalar( $raw['updated_at'] ) ? absint( $raw['updated_at'] ) : time();
		$clean['expires_at'] = isset( $raw['expires_at'] ) && is_scalar( $raw['expires_at'] ) ? absint( $raw['expires_at'] ) : 0;
		return $clean;
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
		return rest_ensure_response( array( 'ok' => true, 'store_id' => $store->store_uuid, 'status' => $status, 'server_time' => gmdate( 'c' ), 'online_crypto' => Mediline_Catalog_Payments::enabled() ) );
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
		$attribution = self::sanitize_attribution( $request->get_param( 'attribution' ), self::language_for_store( $store, $request->get_param( 'lang' ) ) );
		$request->set_param( 'attribution', $attribution );
		global $wpdb;
		$lock = 'ml_order_' . substr( hash( 'sha256', $store->store_uuid . ':' . $attribution['submission_id'] ), 0, 50 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return new WP_Error( 'mediline_checkout_busy', 'This order is being processed. Retry shortly.', array( 'status' => 409 ) );
		}
		try { return self::checkout_session_locked( $request ); }
		finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}

	private static function checkout_session_locked( WP_REST_Request $request ) {
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
		$customer = self::normalize_customer( $request->get_param( 'customer' ) );
		if ( is_wp_error( $customer ) ) { return $customer; }
		$payment_method = sanitize_key( (string) $request->get_param( 'payment_method' ) );
		$payment_methods = self::payment_methods();
		if ( ! isset( $payment_methods[ $payment_method ] ) ) {
			return new WP_Error( 'mediline_payment_method', 'Choose a valid payment method.', array( 'status' => 400 ) );
		}
		if ( ! rest_sanitize_boolean( $request->get_param( 'privacy_consent' ) ) ) {
			return new WP_Error( 'mediline_privacy_consent', 'Privacy consent is required to place the order.', array( 'status' => 400 ) );
		}
		if ( trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_Error( 'mediline_checkout_rejected', 'Order request rejected.', array( 'status' => 400 ) );
		}

		$attribution = self::sanitize_attribution( $request->get_param( 'attribution' ), $language );
		$submission_id = sanitize_text_field( (string) ( $attribution['submission_id'] ?? '' ) );
		if ( $submission_id ) {
			$existing = wc_get_orders( array( 'limit' => 1, 'meta_query' => array(
				array( 'key' => '_mediline_submission_id', 'value' => $submission_id ),
				array( 'key' => '_mediline_source_store_id', 'value' => $store->store_uuid ),
			) ) );
			if ( $existing ) {
				do_action( 'mediline_catalog_invoice_created', $existing[0] );
				return rest_ensure_response( self::invoice_response( $existing[0], $language ) );
			}
		}

		if ( Mediline_Catalog_Payments::enabled() && in_array( $payment_method, array( 'bitcoin', 'usdt_trc20' ), true ) ) {
			$health = Mediline_Catalog_Payments::request( 'GET', '/v1/status' );
			$asset = 'bitcoin' === $payment_method ? 'BTC' : 'USDT_TRC20';
			if ( is_wp_error( $health ) || ( $health['mode'] ?? '' ) !== Mediline_Catalog_Payments::mode() || ! in_array( $asset, (array) ( $health['assets'] ?? array() ), true ) || ( $health['last_sweep'] ?? 0 ) < ( time() - 180 ) * 1000 ) {
				return new WP_Error( 'mediline_crypto_unavailable', 'This crypto payment method is temporarily unavailable. Please choose another method or retry later.', array( 'status' => 503 ) );
			}
		}
		$order = wc_create_order( array( 'status' => 'pending', 'customer_id' => 0, 'created_via' => 'mediline_storefront' ) );
		if ( is_wp_error( $order ) ) { return $order; }
		try {
			foreach ( $items as $line ) {
				$product = wc_get_product( $line['variation_id'] ?: $line['product_id'] );
				$line_total = (float) $line['unit_price'] * (int) $line['quantity'];
				$order->add_product( $product, (int) $line['quantity'], array( 'subtotal' => $line_total, 'total' => $line_total ) );
			}
			$order->set_address( $customer['billing'], 'billing' );
			$order->set_address( $customer['shipping'], 'shipping' );
			$order->set_payment_method( 'mediline_' . $payment_method );
			$order->set_payment_method_title( $payment_methods[ $payment_method ] );
			$order->set_customer_note( sanitize_textarea_field( (string) $request->get_param( 'order_notes' ) ) );
			$order->update_meta_data( '_mediline_source_store_id', $store->store_uuid );
			$order->update_meta_data( '_mediline_affiliate_id', $store->affiliate_id );
			$order->update_meta_data( '_mediline_affiliate_refid', $store->affiliate_refid );
			$order->update_meta_data( '_mediline_market', $store->market );
			$order->update_meta_data( '_mediline_language', $language );
			$order->update_meta_data( '_mediline_payment_choice', $payment_method );
			$order->update_meta_data( '_mediline_privacy_consent', current_time( 'mysql', true ) );
			$order->update_meta_data( '_mediline_submission_id', $submission_id );
			self::attach_attribution_meta( $order, $attribution );
			$order->calculate_totals();
			$order->save();
			$order->update_status( 'on-hold', 'Invoice created by partner storefront. Awaiting payment instructions or confirmation.', true );
		} catch ( Throwable $error ) {
			$order->delete( true );
			return new WP_Error( 'mediline_invoice_create', 'The invoice could not be created.', array( 'status' => 500 ) );
		}
		do_action( 'mediline_catalog_invoice_created', $order );
		return rest_ensure_response( self::invoice_response( $order, $language ) );

		/* Legacy checkout hand-off retained below for older Store Core clients. */
		/*
		$token = bin2hex( random_bytes( 32 ) );
		$payload = array(
			'store_id'       => $store->store_uuid,
			'affiliate_id'   => $store->affiliate_id,
			'affiliate_refid'=> $store->affiliate_refid,
			'market'         => $store->market,
			'currency'       => $store->currency,
			'language'       => $language,
			'attribution'    => $attribution,
			'items'          => $items,
			'created_at'     => time(),
		);
		set_transient( 'mediline_checkout_' . hash( 'sha256', $token ), $payload, 30 * MINUTE_IN_SECONDS );
		return rest_ensure_response( array(
			'checkout_url' => add_query_arg( array( 'mediline_checkout' => rawurlencode( $token ), 'lang' => $language ), home_url( '/' ) ),
			'language'     => $language,
			'expires_in'   => 30 * MINUTE_IN_SECONDS,
		) );
		*/
	}

	private static function payment_methods() {
		return array(
			'card'       => 'Credit Card — payment link by email',
			'bank_wire'  => 'Bank Wire',
			'bitcoin'    => 'Bitcoin',
			'usdt_trc20' => 'USDT (TRC-20)',
		);
	}

	private static function normalize_customer( $raw ) {
		if ( ! is_array( $raw ) ) { return new WP_Error( 'mediline_customer', 'Customer details are required.', array( 'status' => 400 ) ); }
		$sanitize_address = static function ( $address ) {
			$address = is_array( $address ) ? $address : array();
			$data = array();
			foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ) as $key ) {
				$data[ $key ] = sanitize_text_field( (string) ( $address[ $key ] ?? '' ) );
			}
			$data['country'] = strtoupper( substr( $data['country'], 0, 2 ) );
			return $data;
		};
		$billing = $sanitize_address( $raw['billing'] ?? array() );
		$billing['email'] = sanitize_email( (string) ( $raw['billing']['email'] ?? '' ) );
		foreach ( array( 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country', 'phone', 'email' ) as $required ) {
			if ( empty( $billing[ $required ] ) ) { return new WP_Error( 'mediline_customer_field', 'Complete all required customer fields.', array( 'status' => 400, 'field' => $required ) ); }
		}
		if ( ! is_email( $billing['email'] ) ) { return new WP_Error( 'mediline_customer_email', 'Enter a valid email address.', array( 'status' => 400 ) ); }
		$shipping = ! empty( $raw['ship_to_different'] ) ? $sanitize_address( $raw['shipping'] ?? array() ) : $billing;
		if ( ! empty( $raw['ship_to_different'] ) ) {
			foreach ( array( 'first_name', 'last_name', 'address_1', 'city', 'postcode', 'country' ) as $required ) {
				if ( empty( $shipping[ $required ] ) ) { return new WP_Error( 'mediline_shipping_field', 'Complete all required shipping fields.', array( 'status' => 400, 'field' => $required ) ); }
			}
		}
		return array( 'billing' => $billing, 'shipping' => $shipping );
	}

	private static function invoice_response( WC_Order $order, $language ) {
		return Mediline_Catalog_Payments::checkout_response( array(
			'invoice_id'   => $order->get_order_number(),
			'order_id'     => $order->get_id(),
			'status'       => $order->get_status(),
			'payment_method'=> $order->get_meta( '_mediline_payment_choice', true ),
			'checkout_url' => $order->get_checkout_order_received_url(),
			'invoice_url'  => $order->get_checkout_order_received_url(),
			'language'     => $language,
		), $order );
	}

	public static function payment_instruction( WC_Order $order ) {
		$method = sanitize_key( (string) $order->get_meta( '_mediline_payment_choice', true ) );
		$configured = (array) get_option( 'mediline_catalog_payment_instructions', array() );
		if ( ! empty( $configured[ $method ] ) ) { return wp_kses_post( $configured[ $method ] ); }
		$defaults = array(
			'card'       => 'No card details are collected here. A secure card payment link will be sent to your billing email after the invoice is reviewed.',
			'bank_wire'  => 'Bank transfer instructions and account details will be sent to your billing email.',
			'bitcoin'    => 'The Bitcoin wallet address and exact payment amount will be sent to your billing email.',
			'usdt_trc20' => 'The USDT TRC-20 wallet address and exact payment amount will be sent to your billing email. Use the TRC-20 network only.',
		);
		return $defaults[ $method ] ?? '';
	}

	public static function email_payment_instructions( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $order instanceof WC_Order && Mediline_Catalog_Payments::enabled() && Mediline_Catalog_Payments::asset( $order ) ) { return; }
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $order->get_meta( '_mediline_payment_choice', true ) ) { return; }
		$instruction = self::payment_instruction( $order );
		if ( $plain_text ) { echo "\nPayment instructions\n" . wp_strip_all_tags( $instruction ) . "\n"; }
		else { echo '<h2>Payment instructions</h2><div>' . wp_kses_post( wpautop( $instruction ) ) . '</div>'; }
	}

	public static function thankyou_payment_instructions( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && Mediline_Catalog_Payments::enabled() && Mediline_Catalog_Payments::asset( $order ) ) { return; }
		if ( ! $order || ! $order->get_meta( '_mediline_payment_choice', true ) ) { return; }
		echo '<section class="woocommerce-order-details"><h2>Payment instructions</h2>' . wp_kses_post( wpautop( self::payment_instruction( $order ) ) ) . '</section>';
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
			$attribution = self::sanitize_attribution( $data['attribution'] ?? array(), $language );
			WC()->session->set( 'mediline_checkout_source', array(
				'store_id'        => $data['store_id'],
				'affiliate_id'    => $data['affiliate_id'],
				'affiliate_refid' => $data['affiliate_refid'],
				'market'          => $data['market'],
				'language'        => $language,
				'attribution'     => $attribution,
			) );
			WC()->session->set( 'mediline_language', $language );
			WC()->session->set( 'mediline_attribution', $attribution );
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
		$attribution = self::sanitize_attribution( $source['attribution'] ?? array(), $source['language'] ?? '' );
		self::attach_attribution_meta( $order, $attribution );
		WC()->session->set( 'mediline_checkout_source', null );
		WC()->session->set( 'mediline_language', null );
		WC()->session->set( 'mediline_attribution', null );
	}

	/** Persist sanitized attribution for both hosted checkout and API invoices. */
	public static function attach_attribution_meta( $order, array $attribution ) {
		$meta_keys = array(
			'pap_visitor_id'   => '_mediline_pap_visitor_id',
			'pap_affiliate_id' => '_mediline_pap_affiliate_id',
			'utm_source'       => '_mediline_utm_source',
			'utm_medium'       => '_mediline_utm_medium',
			'utm_campaign'     => '_mediline_utm_campaign',
			'utm_term'         => '_mediline_utm_term',
			'utm_content'      => '_mediline_utm_content',
			'gclid'            => '_mediline_gclid',
			'fbclid'           => '_mediline_fbclid',
			'landing_url'      => '_mediline_landing_url',
			'submission_id'    => '_mediline_submission_id',
		);
		foreach ( $meta_keys as $key => $meta_key ) {
			if ( isset( $attribution[ $key ] ) && '' !== $attribution[ $key ] ) {
				$order->update_meta_data( $meta_key, $attribution[ $key ] );
			}
		}
		$first = isset( $attribution['first'] ) && is_array( $attribution['first'] ) ? $attribution['first'] : array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'landing_url' ) as $key ) {
			if ( isset( $first[ $key ] ) && '' !== $first[ $key ] ) {
				$order->update_meta_data( '_mediline_first_' . $key, $first[ $key ] );
			}
		}
		if ( ! empty( $attribution['submission_id'] ) ) {
			$order->update_meta_data( '_mediline_attribution_submission_id', $attribution['submission_id'] );
		}
	}

	public static function attach_store_api_order_source( $order, $request ) {
		self::attach_order_source( $order, array() );
	}
}
