<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Store_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mediline_store_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_mediline_store_sync', array( __CLASS__, 'sync' ) );
	}
	public static function menu() { add_menu_page( 'Mediline Store', 'Mediline Store', 'manage_options', 'mediline-store', array( __CLASS__, 'page' ), 'dashicons-store', 58 ); }
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$s = Mediline_Store_Client::settings();
		$count = 0; global $wpdb; $count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Mediline_Store_DB::products_table() );
		?>
		<div class="wrap"><h1>Mediline Store Core</h1>
		<?php if ( ! empty( $_GET['saved'] ) ) : ?><div class="notice notice-success"><p>Settings saved.</p></div><?php endif; ?>
		<?php if ( ! empty( $_GET['synced'] ) ) : ?><div class="notice notice-success"><p>Catalog synchronized.</p></div><?php endif; ?>
		<div style="display:flex;gap:16px;flex-wrap:wrap"><div class="card"><h2><?php echo esc_html( $count ); ?></h2><p>Local products</p></div><div class="card"><h2><?php echo esc_html( get_option( 'mediline_store_last_sync', 'Never' ) ); ?></h2><p>Last sync (UTC)</p></div></div>
		<?php if ( get_option( 'mediline_store_last_error' ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( get_option( 'mediline_store_last_error' ) ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mediline_store_save"><?php wp_nonce_field( 'mediline_store_save' ); ?><table class="form-table">
		<tr><th>Catalog API URL</th><td><input class="regular-text" name="api_url" value="<?php echo esc_attr( $s['api_url'] ); ?>" placeholder="https://mediline.io/wp-json/mediline/v1"></td></tr>
		<tr><th>Store ID</th><td><input class="regular-text" name="store_id" value="<?php echo esc_attr( $s['store_id'] ); ?>"></td></tr>
		<tr><th>Store secret</th><td><input class="regular-text" type="password" name="store_secret" value="<?php echo esc_attr( $s['store_secret'] ); ?>" autocomplete="new-password"></td></tr>
		<tr><th>Primary language</th><td><input name="primary_language" value="<?php echo esc_attr( $s['primary_language'] ); ?>" size="8"></td></tr>
		<tr><th>Languages</th><td><input class="regular-text" name="languages" value="<?php echo esc_attr( implode( ',', (array) $s['languages'] ) ); ?>"><p class="description">Comma-separated: en,fr,de,sp,it</p></td></tr>
		<tr><th>Currency</th><td><input name="currency" value="<?php echo esc_attr( $s['currency'] ); ?>" size="8" readonly></td></tr>
		</table><?php submit_button( 'Save connection' ); ?></form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px"><input type="hidden" name="action" value="mediline_store_sync"><?php wp_nonce_field( 'mediline_store_sync' ); ?><button class="button button-primary" name="mode" value="incremental">Sync changes now</button> <button class="button" name="mode" value="full">Full resync</button></form>
		<h2>Theme API</h2><p>Templates should use <code>mediline_store_get_products()</code>, <code>mediline_store_get_product()</code>, <code>mediline_store_get_categories()</code> and <code>mediline_store_checkout_url()</code>. They should not call the central API directly.</p>
		</div>
		<?php
	}
	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); } check_admin_referer( 'mediline_store_save' );
		$s = Mediline_Store_Client::settings();
		$s['api_url'] = esc_url_raw( wp_unslash( $_POST['api_url'] ?? '' ) );
		$s['store_id'] = sanitize_text_field( wp_unslash( $_POST['store_id'] ?? '' ) );
		$s['store_secret'] = sanitize_text_field( wp_unslash( $_POST['store_secret'] ?? '' ) );
		$s['primary_language'] = sanitize_key( wp_unslash( $_POST['primary_language'] ?? 'en' ) );
		$s['languages'] = array_values( array_filter( array_map( 'sanitize_key', explode( ',', wp_unslash( $_POST['languages'] ?? 'en' ) ) ) ) );
		$s['currency'] = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? $s['currency'] ?? 'EUR' ) ) );
		update_option( 'mediline_store_settings', $s, false );
		wp_safe_redirect( admin_url( 'admin.php?page=mediline-store&saved=1' ) ); exit;
	}
	public static function sync() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', 403 ); } check_admin_referer( 'mediline_store_sync' );
		$result = 'full' === sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ) ? Mediline_Store_Sync::full_sync() : Mediline_Store_Sync::incremental_sync();
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 500 ); }
		wp_safe_redirect( admin_url( 'admin.php?page=mediline-store&synced=1' ) ); exit;
	}
}
