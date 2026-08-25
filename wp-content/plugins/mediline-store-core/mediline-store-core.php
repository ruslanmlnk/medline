<?php
/**
 * Plugin Name: Mediline Store Core
 * Description: Storefront-side Mediline catalog mirror, secure API client, sync engine and central checkout bridge.
 * Version: 1.2.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Mediline
 * Text Domain: mediline-store-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'MEDILINE_STORE_VERSION', '1.2.1' );
define( 'MEDILINE_STORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDILINE_STORE_URL', plugin_dir_url( __FILE__ ) );

require_once MEDILINE_STORE_DIR . 'includes/class-mediline-store-db.php';
require_once MEDILINE_STORE_DIR . 'includes/class-mediline-store-client.php';
require_once MEDILINE_STORE_DIR . 'includes/class-mediline-store-sync.php';
require_once MEDILINE_STORE_DIR . 'includes/class-mediline-store-api.php';
require_once MEDILINE_STORE_DIR . 'includes/class-mediline-store-admin.php';

register_activation_hook( __FILE__, array( 'Mediline_Store_DB', 'activate' ) );
register_activation_hook( __FILE__, array( 'Mediline_Store_Sync', 'activate_cron' ) );
register_deactivation_hook( __FILE__, array( 'Mediline_Store_Sync', 'deactivate_cron' ) );

function mediline_store_boot() {
	Mediline_Store_DB::maybe_upgrade();
	Mediline_Store_Sync::init();
	Mediline_Store_API::init();
	Mediline_Store_Admin::init();
}
add_action( 'plugins_loaded', 'mediline_store_boot' );

function mediline_store_primary_language() {
	$settings = Mediline_Store_Client::settings();
	$primary = $settings['primary_language'] ?? '';
	return is_scalar( $primary ) && sanitize_key( (string) $primary ) ? sanitize_key( (string) $primary ) : 'en';
}

function mediline_store_languages() {
	$settings = Mediline_Store_Client::settings();
	$languages = array();
	foreach ( is_array( $settings['languages'] ) ? $settings['languages'] : array() as $language ) {
		if ( is_scalar( $language ) && sanitize_key( (string) $language ) ) { $languages[] = sanitize_key( (string) $language ); }
	}
	$primary = mediline_store_primary_language();
	if ( ! in_array( $primary, $languages, true ) ) { array_unshift( $languages, $primary ); }
	return array_values( array_unique( array_filter( $languages ) ) );
}

function mediline_store_current_language() {
	$requested = sanitize_key( (string) get_query_var( 'mediline_store_lang' ) );
	if ( $requested && in_array( $requested, mediline_store_languages(), true ) ) { return $requested; }
	return mediline_store_primary_language();
}

function mediline_store_valid_language( $requested = '' ) {
	$requested = is_scalar( $requested ) ? sanitize_key( (string) $requested ) : '';
	$languages = mediline_store_languages();
	if ( $requested && in_array( $requested, $languages, true ) ) { return $requested; }
	$current = mediline_store_current_language();
	return in_array( $current, $languages, true ) ? $current : mediline_store_primary_language();
}

function mediline_store_currency() {
	$settings = Mediline_Store_Client::settings();
	return strtoupper( sanitize_text_field( $settings['currency'] ?? 'EUR' ) );
}

function mediline_store_route_url( $path = '', $lang = '' ) {
	$lang = sanitize_key( $lang ?: mediline_store_current_language() );
	$primary = mediline_store_primary_language();
	$prefix = $lang && $lang !== $primary ? $lang . '/' : '';
	$path = trim( (string) $path, '/' );
	return home_url( '/' . $prefix . ( $path ? $path . '/' : '' ) );
}

/** Theme-facing stable API. */
function mediline_store_get_products( array $args = array() ) {
	return Mediline_Store_DB::products( $args );
}
function mediline_store_get_product( $id_or_slug, $lang = '' ) {
	return Mediline_Store_DB::product( $id_or_slug, $lang ?: mediline_store_current_language() );
}
function mediline_store_get_categories() {
	return Mediline_Store_DB::categories();
}
function mediline_store_checkout_url( array $items, $lang = '' ) {
	$result = Mediline_Store_Client::request( 'POST', '/checkout-sessions', array( 'items' => $items, 'lang' => mediline_store_valid_language( $lang ) ) );
	return is_wp_error( $result ) ? $result : ( $result['checkout_url'] ?? new WP_Error( 'mediline_checkout_url', 'Checkout URL was not returned.' ) );
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class Mediline_Store_CLI_Command {
		public function configure( $args, $assoc ) {
			$settings = Mediline_Store_Client::settings();
			foreach ( array( 'api_url', 'store_id', 'store_secret', 'primary_language' ) as $key ) {
				if ( isset( $assoc[ str_replace( '_', '-', $key ) ] ) ) { $settings[ $key ] = sanitize_text_field( $assoc[ str_replace( '_', '-', $key ) ] ); }
			}
			if ( isset( $assoc['currency'] ) ) { $settings['currency'] = strtoupper( sanitize_text_field( $assoc['currency'] ) ); }
			if ( isset( $assoc['languages'] ) ) { $settings['languages'] = array_values( array_filter( array_map( 'sanitize_key', explode( ',', $assoc['languages'] ) ) ) ); }
			update_option( 'mediline_store_settings', $settings, false );
			WP_CLI::success( 'Mediline Store Core configured.' );
		}
		public function sync( $args, $assoc ) {
			$result = ! empty( $assoc['full'] ) ? Mediline_Store_Sync::full_sync() : Mediline_Store_Sync::incremental_sync();
			if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
			WP_CLI::success( 'Catalog synchronized.' );
		}
		public function heartbeat( $args, $assoc ) {
			$result = Mediline_Store_Client::heartbeat( sanitize_key( $assoc['status'] ?? 'online' ) );
			if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
			WP_CLI::success( 'Store heartbeat sent.' );
		}
		public function status( $args, $assoc ) {
			$settings = Mediline_Store_Client::settings();
			global $wpdb;
			$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Mediline_Store_DB::products_table() );
			WP_CLI::line( 'Configured: ' . ( Mediline_Store_Client::configured() ? 'yes' : 'no' ) );
			WP_CLI::line( 'Store ID: ' . ( $settings['store_id'] ?: '-' ) );
			WP_CLI::line( 'Products: ' . $count );
			WP_CLI::line( 'Languages: ' . implode( ',', mediline_store_languages() ) );
			WP_CLI::line( 'Last sync: ' . get_option( 'mediline_store_last_sync', 'never' ) );
		}
	}
	WP_CLI::add_command( 'mediline-store', 'Mediline_Store_CLI_Command' );
}
