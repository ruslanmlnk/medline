<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_Sync {
	const CRON_HOOK = 'mediline_store_catalog_sync';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'incremental_sync' ) );
	}
	public static function schedule( $schedules ) {
		$schedules['mediline_15_minutes'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Every 15 minutes' );
		return $schedules;
	}
	public static function activate_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_event( time() + 120, 'mediline_15_minutes', self::CRON_HOOK ); }
	}
	public static function deactivate_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK ); if ( $timestamp ) { wp_unschedule_event( $timestamp, self::CRON_HOOK ); }
	}

	public static function languages() {
		$s = Mediline_Store_Client::settings();
		$languages = is_array( $s['languages'] ) ? array_map( 'sanitize_key', $s['languages'] ) : array( sanitize_key( $s['primary_language'] ) );
		if ( ! in_array( sanitize_key( $s['primary_language'] ), $languages, true ) ) { array_unshift( $languages, sanitize_key( $s['primary_language'] ) ); }
		return array_values( array_unique( array_filter( $languages ) ) );
	}

	public static function full_sync() {
		if ( ! Mediline_Store_Client::configured() ) { return new WP_Error( 'mediline_sync_config', 'Store is not configured.' ); }
		$seen = array();
		foreach ( self::languages() as $lang ) {
			$page = 1;
			do {
				$result = Mediline_Store_Client::request( 'GET', '/catalog/products', array(), array( 'lang' => $lang, 'page' => $page, 'per_page' => 100 ) );
				if ( is_wp_error( $result ) ) { self::record_error( $result ); return $result; }
				foreach ( (array) ( $result['items'] ?? array() ) as $item ) { Mediline_Store_DB::upsert_product( $item, $lang ); $seen[ absint( $item['id'] ?? 0 ) ] = true; }
				$total_pages = max( 1, absint( $result['total_pages'] ?? 1 ) );
				$page++;
			} while ( $page <= $total_pages );
			update_option( 'mediline_store_version_' . $lang, absint( $result['catalog_version'] ?? 0 ), false );
		}
		global $wpdb;
		$all_ids = $wpdb->get_col( 'SELECT remote_id FROM ' . Mediline_Store_DB::products_table() );
		$delete = array_diff( array_map( 'intval', $all_ids ), array_keys( $seen ) );
		Mediline_Store_DB::delete_products( $delete );
		foreach ( self::languages() as $lang ) {
			$categories = Mediline_Store_Client::request( 'GET', '/catalog/categories', array(), array( 'lang' => $lang ) );
			if ( ! is_wp_error( $categories ) ) { Mediline_Store_DB::replace_categories( (array) ( $categories['items'] ?? array() ), $lang, $lang === mediline_store_primary_language() ); }
		}
		self::record_success();
		return array( 'products' => count( $seen ), 'languages' => self::languages() );
	}

	public static function incremental_sync() {
		if ( ! Mediline_Store_Client::configured() ) { return new WP_Error( 'mediline_sync_config', 'Store is not configured.' ); }
		foreach ( self::languages() as $lang ) {
			$version = absint( get_option( 'mediline_store_version_' . $lang, 0 ) );
			$loops = 0;
			do {
				$result = Mediline_Store_Client::request( 'GET', '/catalog/sync', array(), array( 'lang' => $lang, 'since_version' => $version, 'limit' => 200 ) );
				if ( is_wp_error( $result ) ) { self::record_error( $result ); return $result; }
				foreach ( (array) ( $result['updated'] ?? array() ) as $item ) { Mediline_Store_DB::upsert_product( $item, $lang ); }
				Mediline_Store_DB::delete_products( (array) ( $result['deleted'] ?? array() ) );
				if ( is_array( $result['categories'] ?? null ) ) { Mediline_Store_DB::replace_categories( $result['categories'], $lang, $lang === mediline_store_primary_language() ); }
				$version = max( $version, absint( $result['to_version'] ?? $version ) );
				update_option( 'mediline_store_version_' . $lang, $version, false );
				$loops++;
			} while ( ! empty( $result['has_more'] ) && $loops < 20 );
		}
		self::record_success();
		return true;
	}

	public static function record_success() { update_option( 'mediline_store_last_sync', current_time( 'mysql', true ), false ); delete_option( 'mediline_store_last_error' ); Mediline_Store_Client::heartbeat( 'online' ); }
	public static function record_error( $error ) { update_option( 'mediline_store_last_error', is_wp_error( $error ) ? $error->get_error_message() : (string) $error, false ); }
}
