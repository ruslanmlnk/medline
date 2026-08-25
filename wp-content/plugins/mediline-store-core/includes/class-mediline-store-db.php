<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_DB {
	const DB_VERSION = '1.1.0';
	public static function products_table() { global $wpdb; return $wpdb->prefix . 'mediline_products'; }
	public static function i18n_table() { global $wpdb; return $wpdb->prefix . 'mediline_product_i18n'; }
	public static function categories_table() { global $wpdb; return $wpdb->prefix . 'mediline_categories'; }
	public static function category_i18n_table() { global $wpdb; return $wpdb->prefix . 'mediline_category_i18n'; }

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$products = self::products_table();
		$i18n = self::i18n_table();
		$categories = self::categories_table();
		$category_i18n = self::category_i18n_table();
		dbDelta( "CREATE TABLE {$products} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remote_id bigint(20) unsigned NOT NULL,
			sku varchar(191) NOT NULL DEFAULT '',
			slug varchar(191) NOT NULL,
			type varchar(32) NOT NULL DEFAULT 'simple',
			status varchar(24) NOT NULL DEFAULT 'publish',
			featured tinyint(1) NOT NULL DEFAULT 0,
			price decimal(18,4) NOT NULL DEFAULT 0,
			currency varchar(8) NOT NULL DEFAULT 'EUR',
			stock_status varchar(32) NOT NULL DEFAULT 'instock',
			stock_quantity int NULL,
			images longtext NULL,
			categories longtext NULL,
			attributes longtext NULL,
			variations longtext NULL,
			catalog_version bigint(20) unsigned NOT NULL DEFAULT 0,
			remote_updated_at varchar(40) NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY remote_id (remote_id),
			KEY slug (slug),
			KEY featured (featured),
			KEY catalog_version (catalog_version)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$i18n} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remote_id bigint(20) unsigned NOT NULL,
			language varchar(12) NOT NULL,
			name text NOT NULL,
			short_description longtext NULL,
			description longtext NULL,
			seo longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY product_language (remote_id,language),
			KEY language (language)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$categories} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remote_id bigint(20) unsigned NOT NULL,
			slug varchar(191) NOT NULL,
			name text NOT NULL,
			description longtext NULL,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			image text NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY remote_id (remote_id),
			KEY slug (slug)
		) {$charset};" );
		dbDelta( "CREATE TABLE {$category_i18n} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remote_id bigint(20) unsigned NOT NULL,
			language varchar(12) NOT NULL,
			name text NOT NULL,
			description longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY category_language (remote_id,language),
			KEY language (language)
		) {$charset};" );
		update_option( 'mediline_store_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() { if ( self::DB_VERSION !== get_option( 'mediline_store_db_version' ) ) { self::activate(); } }

	public static function upsert_product( array $item, $lang ) {
		global $wpdb;
		$remote_id = absint( $item['id'] ?? 0 );
		if ( ! $remote_id ) { return; }
		$price = is_array( $item['price'] ?? null ) ? (float) ( $item['price']['amount'] ?? 0 ) : 0;
		$currency = is_array( $item['price'] ?? null ) ? sanitize_text_field( $item['price']['currency'] ?? 'EUR' ) : 'EUR';
		$row = array(
			'remote_id'         => $remote_id,
			'sku'               => sanitize_text_field( $item['sku'] ?? '' ),
			'slug'              => sanitize_title( $item['slug'] ?? ( 'product-' . $remote_id ) ),
			'type'              => sanitize_key( $item['type'] ?? 'simple' ),
			'status'            => sanitize_key( $item['status'] ?? 'publish' ),
			'featured'          => ! empty( $item['featured'] ) ? 1 : 0,
			'price'             => $price,
			'currency'          => strtoupper( sanitize_text_field( $currency ) ),
			'stock_status'      => sanitize_key( $item['stock_status'] ?? 'instock' ),
			'stock_quantity'    => null === ( $item['stock_quantity'] ?? null ) ? null : (int) $item['stock_quantity'],
			'images'            => wp_json_encode( $item['images'] ?? array() ),
			'categories'        => wp_json_encode( $item['categories'] ?? array() ),
			'attributes'        => wp_json_encode( $item['attributes'] ?? array() ),
			'variations'        => wp_json_encode( $item['variations'] ?? array() ),
			'catalog_version'   => absint( $item['version'] ?? 0 ),
			'remote_updated_at' => sanitize_text_field( $item['updated_at'] ?? '' ),
			'updated_at'        => current_time( 'mysql', true ),
		);
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::products_table() . ' WHERE remote_id=%d', $remote_id ) );
		$existing ? $wpdb->update( self::products_table(), $row, array( 'remote_id' => $remote_id ) ) : $wpdb->insert( self::products_table(), $row );
		$i18n = array(
			'remote_id'         => $remote_id,
			'language'          => sanitize_key( $lang ),
			'name'              => sanitize_text_field( $item['name'] ?? '' ),
			'short_description' => wp_kses_post( $item['short_description'] ?? '' ),
			'description'       => wp_kses_post( $item['description'] ?? '' ),
			'seo'               => wp_json_encode( $item['seo'] ?? array() ),
		);
		$translation_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::i18n_table() . ' WHERE remote_id=%d AND language=%s', $remote_id, sanitize_key( $lang ) ) );
		$translation_id ? $wpdb->update( self::i18n_table(), $i18n, array( 'id' => $translation_id ) ) : $wpdb->insert( self::i18n_table(), $i18n );
	}

	public static function delete_products( array $ids ) {
		global $wpdb;
		foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
			$wpdb->delete( self::products_table(), array( 'remote_id' => $id ), array( '%d' ) );
			$wpdb->delete( self::i18n_table(), array( 'remote_id' => $id ), array( '%d' ) );
		}
	}

	public static function replace_categories( array $categories, $lang = 'en', $prune = false ) {
		global $wpdb;
		$lang = sanitize_key( $lang ?: 'en' );
		$seen = array();
		foreach ( $categories as $item ) {
			$remote_id = absint( $item['id'] ?? 0 ); if ( ! $remote_id ) { continue; } $seen[] = $remote_id;
			$base = array( 'remote_id' => $remote_id, 'slug' => sanitize_title( $item['slug'] ?? '' ), 'name' => sanitize_text_field( $item['name'] ?? '' ), 'description' => wp_kses_post( $item['description'] ?? '' ), 'parent_id' => absint( $item['parent'] ?? 0 ), 'image' => esc_url_raw( $item['image'] ?? '' ), 'updated_at' => current_time( 'mysql', true ) );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::categories_table() . ' WHERE remote_id=%d', $remote_id ) );
			$exists ? $wpdb->update( self::categories_table(), $base, array( 'remote_id' => $remote_id ) ) : $wpdb->insert( self::categories_table(), $base );
			$i18n = array( 'remote_id' => $remote_id, 'language' => $lang, 'name' => sanitize_text_field( $item['name'] ?? '' ), 'description' => wp_kses_post( $item['description'] ?? '' ) );
			$i18n_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::category_i18n_table() . ' WHERE remote_id=%d AND language=%s', $remote_id, $lang ) );
			$i18n_id ? $wpdb->update( self::category_i18n_table(), $i18n, array( 'id' => $i18n_id ) ) : $wpdb->insert( self::category_i18n_table(), $i18n );
		}
		if ( $prune ) {
			$existing = array_map( 'intval', $wpdb->get_col( 'SELECT remote_id FROM ' . self::categories_table() ) );
			foreach ( array_diff( $existing, $seen ) as $id ) { $wpdb->delete( self::categories_table(), array( 'remote_id' => $id ), array( '%d' ) ); $wpdb->delete( self::category_i18n_table(), array( 'remote_id' => $id ), array( '%d' ) ); }
		}
	}

	public static function hydrate_product_row( $row, $lang ) {
		if ( ! $row ) { return null; }
		global $wpdb;
		$text = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::i18n_table() . ' WHERE remote_id=%d AND language=%s', $row->remote_id, sanitize_key( $lang ) ), ARRAY_A );
		if ( ! $text && 'en' !== $lang ) { $text = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::i18n_table() . ' WHERE remote_id=%d AND language=%s', $row->remote_id, 'en' ), ARRAY_A ); }
		return (object) array(
			'id'                => (int) $row->remote_id,
			'sku'               => $row->sku,
			'slug'              => $row->slug,
			'type'              => $row->type,
			'featured'          => (bool) $row->featured,
			'name'              => $text['name'] ?? '',
			'short_description' => $text['short_description'] ?? '',
			'description'       => $text['description'] ?? '',
			'seo'               => json_decode( $text['seo'] ?? '[]', true ) ?: array(),
			'price'             => (float) $row->price,
			'currency'          => $row->currency,
			'stock_status'      => $row->stock_status,
			'stock_quantity'    => null === $row->stock_quantity ? null : (int) $row->stock_quantity,
			'images'            => json_decode( $row->images ?: '[]', true ) ?: array(),
			'categories'        => json_decode( $row->categories ?: '[]', true ) ?: array(),
			'attributes'        => json_decode( $row->attributes ?: '[]', true ) ?: array(),
			'variations'        => json_decode( $row->variations ?: '[]', true ) ?: array(),
			'version'           => (int) $row->catalog_version,
		);
	}

	public static function products( array $args = array() ) {
		global $wpdb;
		$lang = sanitize_key( $args['lang'] ?? mediline_store_current_language() );
		$limit = min( 100, max( 1, absint( $args['limit'] ?? 24 ) ) );
		$offset = max( 0, absint( $args['offset'] ?? 0 ) );
		$where = array( '1=1' ); $params = array();
		if ( ! empty( $args['featured'] ) ) { $where[] = 'featured=1'; }
		if ( ! empty( $args['category'] ) ) {
			$category = sanitize_title( $args['category'] );
			$where[] = $wpdb->prepare( 'categories LIKE %s', '%' . $wpdb->esc_like( '"slug":"' . $category . '"' ) . '%' );
		}
		if ( ! empty( $args['search'] ) ) {
			$search_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT remote_id FROM ' . self::i18n_table() . ' WHERE language=%s AND name LIKE %s', $lang, '%' . $wpdb->esc_like( $args['search'] ) . '%' ) );
			if ( ! $search_ids ) { return array(); }
			$where[] = 'remote_id IN (' . implode( ',', array_map( 'absint', $search_ids ) ) . ')';
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::products_table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY featured DESC,id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset );
		$items = array(); foreach ( $rows as $row ) { $items[] = self::hydrate_product_row( $row, $lang ); }
		return $items;
	}

	public static function product( $id_or_slug, $lang ) {
		global $wpdb;
		$row = is_numeric( $id_or_slug ) ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::products_table() . ' WHERE remote_id=%d', absint( $id_or_slug ) ) ) : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::products_table() . ' WHERE slug=%s', sanitize_title( $id_or_slug ) ) );
		return self::hydrate_product_row( $row, $lang );
	}

	public static function categories( $lang = '' ) {
		global $wpdb;
		$lang = sanitize_key( $lang ?: mediline_store_current_language() );
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::categories_table() . ' ORDER BY name ASC' );
		$out = array();
		foreach ( $rows as $row ) {
			$text = $wpdb->get_row( $wpdb->prepare( 'SELECT name,description FROM ' . self::category_i18n_table() . ' WHERE remote_id=%d AND language=%s', $row->remote_id, $lang ) );
			if ( ! $text && 'en' !== $lang ) { $text = $wpdb->get_row( $wpdb->prepare( 'SELECT name,description FROM ' . self::category_i18n_table() . ' WHERE remote_id=%d AND language=%s', $row->remote_id, 'en' ) ); }
			$out[] = (object) array( 'id' => (int) $row->remote_id, 'slug' => $row->slug, 'name' => $text ? $text->name : $row->name, 'description' => $text ? $text->description : $row->description, 'parent' => (int) $row->parent_id, 'image' => $row->image );
		}
		return $out;
	}
}
