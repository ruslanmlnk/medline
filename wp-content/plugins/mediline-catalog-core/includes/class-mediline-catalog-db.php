<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_DB {
	const DB_VERSION = '1.1.0';

	public static function stores_table() {
		global $wpdb;
		return $wpdb->prefix . 'mediline_stores';
	}

	public static function changes_table() {
		global $wpdb;
		return $wpdb->prefix . 'mediline_catalog_changes';
	}

	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$stores  = self::stores_table();
		$changes = self::changes_table();

		$sql_stores = "CREATE TABLE {$stores} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			store_uuid varchar(64) NOT NULL,
			installation_id varchar(64) NOT NULL DEFAULT '',
			template_key varchar(191) NOT NULL DEFAULT '',
			template_version varchar(32) NOT NULL DEFAULT '',
			affiliate_id varchar(191) NOT NULL DEFAULT '',
			affiliate_refid varchar(191) NOT NULL DEFAULT '',
			domain varchar(191) NOT NULL DEFAULT '',
			market varchar(16) NOT NULL DEFAULT 'EU',
			currency varchar(8) NOT NULL DEFAULT 'EUR',
			primary_language varchar(12) NOT NULL DEFAULT 'en',
			languages longtext NULL,
			secret_payload longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			last_seen datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY store_uuid (store_uuid),
			KEY installation_id (installation_id),
			KEY affiliate_id (affiliate_id),
			KEY domain (domain),
			KEY status (status)
		) {$charset};";

		$sql_changes = "CREATE TABLE {$changes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			version bigint(20) unsigned NOT NULL,
			object_type varchar(32) NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			action varchar(16) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY version_object (version,object_type,object_id),
			KEY version (version),
			KEY object_lookup (object_type,object_id)
		) {$charset};";

		dbDelta( $sql_stores );
		dbDelta( $sql_changes );

		if ( false === get_option( 'mediline_catalog_version_counter', false ) ) {
			add_option( 'mediline_catalog_version_counter', 1, '', false );
		}
		update_option( 'mediline_catalog_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'mediline_catalog_db_version' ) ) {
			self::activate();
		}
	}

	public static function next_version() {
		global $wpdb;
		$option = 'mediline_catalog_version_counter';
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s", $option ) );
		$value = (int) get_option( $option, 1 );
		wp_cache_delete( $option, 'options' );
		$value = (int) get_option( $option, $value );
		return max( 1, $value );
	}

	public static function current_version() {
		return max( 1, (int) get_option( 'mediline_catalog_version_counter', 1 ) );
	}

	public static function log_change( $object_type, $object_id, $action, $version = 0 ) {
		global $wpdb;
		$version = $version ? absint( $version ) : self::next_version();
		$wpdb->insert(
			self::changes_table(),
			array(
				'version'     => $version,
				'object_type' => sanitize_key( $object_type ),
				'object_id'   => absint( $object_id ),
				'action'      => sanitize_key( $action ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);
		return $version;
	}
}
