<?php
/**
 * Plugin Name: Mediline Catalog Core
 * Description: Central WooCommerce catalog, storefront authentication, market/language data, sync API and checkout bridge for Mediline partner stores.
 * Version: 1.2.3
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * Author: Mediline
 * Text Domain: mediline-catalog-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDILINE_CATALOG_VERSION', '1.2.3' );
define( 'MEDILINE_CATALOG_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDILINE_CATALOG_URL', plugin_dir_url( __FILE__ ) );

require_once MEDILINE_CATALOG_DIR . 'includes/class-mediline-catalog-db.php';
require_once MEDILINE_CATALOG_DIR . 'includes/class-mediline-catalog-auth.php';
require_once MEDILINE_CATALOG_DIR . 'includes/class-mediline-catalog-product.php';
require_once MEDILINE_CATALOG_DIR . 'includes/class-mediline-catalog-api.php';
require_once MEDILINE_CATALOG_DIR . 'includes/class-mediline-catalog-admin.php';

register_activation_hook( __FILE__, array( 'Mediline_Catalog_DB', 'activate' ) );

function mediline_catalog_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>Mediline Catalog Core</strong> requires WooCommerce to be installed and active.</p></div>';
			}
		}
		);
		return;
	}

	Mediline_Catalog_DB::maybe_upgrade();
	Mediline_Catalog_Product::init();
	Mediline_Catalog_API::init();
	Mediline_Catalog_Admin::init();
}
add_action( 'plugins_loaded', 'mediline_catalog_boot', 20 );

/**
 * Public bridge used by the Mediline Partner Store Builder when both live on
 * the same WordPress installation. Returns credentials once; the raw secret is
 * not persisted unencrypted.
 */
function mediline_catalog_register_store( array $args ) {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return new WP_Error( 'woocommerce_missing', 'WooCommerce must be active before registering storefronts.' );
	}
	return Mediline_Catalog_Auth::register_store( $args );
}

function mediline_catalog_supported_currencies() {
	$base = class_exists( 'WooCommerce' ) ? get_woocommerce_currency() : 'EUR';
	$currencies = apply_filters( 'mediline_catalog_supported_currencies', array( strtoupper( $base ) ) );
	return array_values( array_unique( array_filter( array_map( static function ( $currency ) { return strtoupper( sanitize_key( $currency ) ); }, (array) $currencies ) ) ) );
}

function mediline_catalog_supported_markets() {
	$markets = apply_filters( 'mediline_catalog_supported_markets', array( 'EU', 'FR', 'DE', 'IT', 'ES', 'US' ) );
	return array_values( array_unique( array_filter( array_map( static function ( $market ) { return strtoupper( sanitize_key( $market ) ); }, (array) $markets ) ) ) );
}
