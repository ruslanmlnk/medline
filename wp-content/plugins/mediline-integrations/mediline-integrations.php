<?php
/**
 * Plugin Name: Mediline PAP & CRM Integrations
 * Description: PAP attribution, durable Pipedrive Contact/Deal delivery, and idempotent Won-sale commission tracking.
 * Version: 1.1.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Mediline
 * Text Domain: mediline-integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDILINE_INTEGRATIONS_VERSION', '1.1.1' );
define( 'MEDILINE_INTEGRATIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEDILINE_INTEGRATIONS_URL', plugin_dir_url( __FILE__ ) );

require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-crypto.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-db.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-pipedrive.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-pap.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-pap-v3.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-attribution.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-workflow.php';
require_once MEDILINE_INTEGRATIONS_DIR . 'includes/class-mediline-integrations-admin.php';

/** @return array<string,mixed> */
function mediline_integrations_default_settings() {
	$fields = array();
	foreach ( Mediline_Integrations_Pipedrive::field_definitions() as $suffix => $definition ) {
		$fields[ 'pipedrive_field_' . $suffix ] = '';
	}
	return array_merge(
		array(
			'pap_click_enabled'        => 1,
			'pap_click_script_url'     => 'https://mediline.postaffiliatepro.com/scripts/trackjs.js',
			'pap_account_id'           => 'default1',
			'pap_api_v3_enabled'       => 0,
			'pap_api_v3_base'          => 'https://mediline.postaffiliatepro.com/api/v3',
			'pap_sale_enabled'         => 1,
			'pap_sale_endpoint'        => 'https://mediline.postaffiliatepro.com/scripts/sale.php',
			'pap_sale_status'          => '',
			'pap_duplicate_protection_confirmed' => 0,
			'pap_fraud_protection_enabled'       => 1,
			'pap_fraud_data_field'                => 5,
			'pipedrive_enabled'        => 0,
			'pipedrive_api_base'       => '',
			'pipedrive_pipeline_id'    => '',
			'pipedrive_stage_id'       => '',
			'pipedrive_company_id'     => '',
			'pipedrive_webhook_id'     => '',
			'pipedrive_webhook_host'   => '',
		),
		$fields
	);
}

/** @return array<string,mixed> */
function mediline_integrations_settings() {
	$settings = wp_parse_args( get_option( 'mediline_integrations_settings', array() ), mediline_integrations_default_settings() );
	$constant_map = array(
		'MEDILINE_PAP_CLICK_SCRIPT_URL' => 'pap_click_script_url',
		'MEDILINE_PAP_ACCOUNT_ID'       => 'pap_account_id',
		'MEDILINE_PAP_SALE_ENDPOINT'    => 'pap_sale_endpoint',
		'MEDILINE_PAP_API_V3_BASE'      => 'pap_api_v3_base',
		'MEDILINE_PIPEDRIVE_API_BASE'   => 'pipedrive_api_base',
	);
	foreach ( $constant_map as $constant => $key ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			$settings[ $key ] = constant( $constant );
		}
	}
	return apply_filters( 'mediline_integrations_settings', $settings );
}

/** @return array<string,string> */
function mediline_integrations_secrets() {
	global $mediline_integrations_secret_cache;
	if ( is_array( $mediline_integrations_secret_cache ) ) {
		return $mediline_integrations_secret_cache;
	}
	$envelope = get_option( 'mediline_integrations_secrets', '' );
	$decoded  = $envelope ? Mediline_Integrations_Crypto::decrypt( $envelope ) : array();
	$mediline_integrations_secret_cache = is_array( $decoded ) && ! is_wp_error( $decoded ) ? $decoded : array();
	return $mediline_integrations_secret_cache;
}

function mediline_integrations_secret( $key ) {
	$constant_map = array(
		'pipedrive_api_token' => 'MEDILINE_PIPEDRIVE_API_TOKEN',
		'pap_fraud_secret'    => 'MEDILINE_PAP_FRAUD_SECRET',
		'pap_api_v3_token'    => 'MEDILINE_PAP_API_V3_TOKEN',
		'webhook_username'    => 'MEDILINE_PIPEDRIVE_WEBHOOK_USER',
		'webhook_password'    => 'MEDILINE_PIPEDRIVE_WEBHOOK_PASSWORD',
	);
	$key = sanitize_key( (string) $key );
	if ( isset( $constant_map[ $key ] ) && defined( $constant_map[ $key ] ) ) {
		return (string) constant( $constant_map[ $key ] );
	}
	$secrets = mediline_integrations_secrets();
	return isset( $secrets[ $key ] ) && is_scalar( $secrets[ $key ] ) ? (string) $secrets[ $key ] : '';
}

/** @return true|WP_Error */
function mediline_integrations_save_secrets( array $changes ) {
	global $mediline_integrations_secret_cache;
	$secrets = mediline_integrations_secrets();
	foreach ( array( 'pipedrive_api_token', 'pap_fraud_secret', 'pap_api_v3_token', 'webhook_username', 'webhook_password' ) as $key ) {
		if ( array_key_exists( $key, $changes ) ) {
			$secrets[ $key ] = is_scalar( $changes[ $key ] ) ? trim( (string) $changes[ $key ] ) : '';
		}
	}
	$encrypted = Mediline_Integrations_Crypto::encrypt( $secrets );
	if ( is_wp_error( $encrypted ) ) {
		return $encrypted;
	}
	update_option( 'mediline_integrations_secrets', $encrypted, false );
	$mediline_integrations_secret_cache = $secrets;
	return true;
}

function mediline_integrations_activate() {
	Mediline_Integrations_DB::activate();
	if ( false === get_option( 'mediline_integrations_settings', false ) ) {
		add_option( 'mediline_integrations_settings', mediline_integrations_default_settings(), '', false );
	}
	if ( ! mediline_integrations_secret( 'webhook_password' ) ) {
		mediline_integrations_save_secrets(
			array(
				'webhook_username' => 'mediline-pap',
				'webhook_password' => wp_generate_password( 40, true, true ),
			)
		);
	}
	Mediline_Integrations_Workflow::ensure_schedule();
}

function mediline_integrations_boot() {
	load_plugin_textdomain( 'mediline-integrations', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	Mediline_Integrations_DB::maybe_upgrade();
	Mediline_Integrations_Attribution::init();
	Mediline_Integrations_Workflow::init();
	Mediline_Integrations_Admin::init();
}

register_activation_hook( __FILE__, 'mediline_integrations_activate' );
register_deactivation_hook( __FILE__, array( 'Mediline_Integrations_Workflow', 'deactivate' ) );
add_action( 'plugins_loaded', 'mediline_integrations_boot', 30 );
