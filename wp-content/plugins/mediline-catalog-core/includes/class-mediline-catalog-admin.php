<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mediline_catalog_create_store', array( __CLASS__, 'create_store' ) );
		add_action( 'admin_post_mediline_catalog_toggle_store', array( __CLASS__, 'toggle_store' ) );
	}

	public static function menu() {
		add_menu_page( 'Mediline Catalog', 'Mediline Catalog', 'manage_woocommerce', 'mediline-catalog', array( __CLASS__, 'overview' ), 'dashicons-database-view', 56 );
		add_submenu_page( 'mediline-catalog', 'Stores', 'Stores', 'manage_woocommerce', 'mediline-catalog-stores', array( __CLASS__, 'stores' ) );
	}

	public static function overview() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$count = wp_count_posts( 'product' );
		global $wpdb;
		$stores = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Mediline_Catalog_DB::stores_table() );
		?>
		<div class="wrap"><h1>Mediline Catalog</h1>
		<p>WooCommerce is the source of truth. Mediline Catalog Core adds translations, market visibility, storefront credentials, incremental sync and central checkout.</p>
		<div style="display:flex;gap:16px;flex-wrap:wrap;margin:20px 0">
			<div class="card"><h2><?php echo esc_html( (int) ( $count->publish ?? 0 ) ); ?></h2><p>Published products</p></div>
			<div class="card"><h2><?php echo esc_html( $stores ); ?></h2><p>Registered stores</p></div>
			<div class="card"><h2><?php echo esc_html( Mediline_Catalog_DB::current_version() ); ?></h2><p>Catalog version</p></div>
		</div>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Manage products</a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mediline-catalog-stores' ) ); ?>">Manage stores</a></p>
		<h2>Store API</h2><p><code><?php echo esc_html( untrailingslashit( rest_url( 'mediline/v1' ) ) ); ?></code></p>
		<ul style="line-height:1.8"><li><code>GET /catalog/config</code></li><li><code>GET /catalog/products</code></li><li><code>GET /catalog/categories</code></li><li><code>GET /catalog/sync</code></li><li><code>POST /checkout-sessions</code></li><li><code>POST /store/heartbeat</code></li></ul>
		</div>
		<?php
	}

	public static function stores() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . Mediline_Catalog_DB::stores_table() . ' ORDER BY id DESC LIMIT 200' );
		?>
		<div class="wrap"><h1>Mediline Stores</h1>
		<?php if ( ! empty( $_GET['created'] ) ) : ?><div class="notice notice-success"><p>Store created. Copy the secret now; it is intentionally shown only once.</p></div><?php endif; ?>
		<?php if ( ! empty( $_GET['store_secret'] ) ) : ?><div class="notice notice-warning"><p><strong>Store ID:</strong> <code><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['store_id'] ?? '' ) ) ); ?></code><br><strong>Store secret:</strong> <code style="word-break:break-all"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['store_secret'] ) ) ); ?></code></p></div><?php endif; ?>
		<h2>Register storefront manually</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:900px;background:#fff;padding:18px;border:1px solid #dcdcde">
			<input type="hidden" name="action" value="mediline_catalog_create_store"><?php wp_nonce_field( 'mediline_catalog_create_store' ); ?>
			<table class="form-table"><tr><th>Affiliate ID</th><td><input class="regular-text" name="affiliate_id"></td></tr><tr><th>Domain</th><td><input class="regular-text" name="domain" placeholder="shop.example.com"></td></tr><tr><th>Market</th><td><select name="market"><?php foreach ( Mediline_Catalog_Product::markets() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr><tr><th>Currency</th><td><input name="currency" value="<?php echo esc_attr( get_woocommerce_currency() ); ?>" size="8"></td></tr><tr><th>Primary language</th><td><select name="primary_language"><?php foreach ( Mediline_Catalog_Product::languages() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr></table>
			<?php submit_button( 'Register store' ); ?>
		</form>
		<h2 style="margin-top:28px">Registered stores</h2>
		<table class="widefat striped"><thead><tr><th>Store</th><th>Affiliate</th><th>Template</th><th>Domain</th><th>Market</th><th>Language</th><th>Status</th><th>Last seen</th><th></th></tr></thead><tbody>
		<?php if ( ! $rows ) : ?><tr><td colspan="9">No stores yet.</td></tr><?php endif; foreach ( $rows as $row ) : ?>
		<tr><td><code><?php echo esc_html( $row->store_uuid ); ?></code></td><td><?php echo esc_html( $row->affiliate_id ); ?></td><td><?php echo esc_html( trim( $row->template_key . ' ' . $row->template_version ) ); ?></td><td><?php echo esc_html( $row->domain ); ?></td><td><?php echo esc_html( $row->market . ' / ' . $row->currency ); ?></td><td><?php echo esc_html( $row->primary_language ); ?></td><td><?php echo esc_html( $row->status ); ?></td><td><?php echo esc_html( $row->last_seen ?: 'Never' ); ?></td><td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=mediline_catalog_toggle_store&id=' . (int) $row->id ), 'mediline_catalog_toggle_store_' . (int) $row->id ) ); ?>"><?php echo 'active' === $row->status ? 'Disable' : 'Enable'; ?></a></td></tr>
		<?php endforeach; ?></tbody></table></div>
		<?php
	}

	public static function create_store() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Forbidden', 403 ); }
		check_admin_referer( 'mediline_catalog_create_store' );
		$result = Mediline_Catalog_Auth::register_store( array(
			'affiliate_id'     => sanitize_text_field( wp_unslash( $_POST['affiliate_id'] ?? '' ) ),
			'domain'           => sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) ),
			'market'           => sanitize_text_field( wp_unslash( $_POST['market'] ?? 'EU' ) ),
			'currency'         => sanitize_text_field( wp_unslash( $_POST['currency'] ?? get_woocommerce_currency() ) ),
			'primary_language' => sanitize_key( wp_unslash( $_POST['primary_language'] ?? 'en' ) ),
			'languages'        => array( sanitize_key( wp_unslash( $_POST['primary_language'] ?? 'en' ) ) ),
		) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), 500 ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'mediline-catalog-stores', 'created' => 1, 'store_id' => rawurlencode( $result['store_id'] ), 'store_secret' => rawurlencode( $result['store_secret'] ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function toggle_store() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( 'Forbidden', 403 ); }
		$id = absint( $_GET['id'] ?? 0 );
		check_admin_referer( 'mediline_catalog_toggle_store_' . $id );
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status FROM ' . Mediline_Catalog_DB::stores_table() . ' WHERE id=%d', $id ) );
		if ( $row ) { $wpdb->update( Mediline_Catalog_DB::stores_table(), array( 'status' => 'active' === $row->status ? 'disabled' : 'active', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) ); }
		wp_safe_redirect( admin_url( 'admin.php?page=mediline-catalog-stores' ) ); exit;
	}
}
