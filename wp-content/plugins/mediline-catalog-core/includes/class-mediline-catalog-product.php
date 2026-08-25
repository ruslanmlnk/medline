<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_Product {
	public static function languages() {
		if ( function_exists( 'mediline_partners_languages' ) ) {
			$result = array();
			foreach ( mediline_partners_languages() as $code => $language ) {
				$result[ sanitize_key( $code ) ] = sanitize_text_field( $language['name'] ?? $language['label'] ?? strtoupper( $code ) );
			}
			if ( $result ) { return $result; }
		}
		return array(
			'en' => 'English',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'sp' => 'Español',
			'it' => 'Italiano',
		);
	}

	public static function markets() {
		return array(
			'EU' => 'Europe / default',
			'FR' => 'France',
			'DE' => 'Germany',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'US' => 'United States',
		);
	}

	public static function init() {
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_meta' ), 30, 2 );
		add_action( 'save_post_product', array( __CLASS__, 'touch_product' ), 99, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'deleted_product' ) );
		add_action( 'created_product_cat', array( __CLASS__, 'touch_category' ) );
		add_action( 'edited_product_cat', array( __CLASS__, 'touch_category' ) );
		add_action( 'delete_product_cat', array( __CLASS__, 'delete_category' ) );
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'category_add_fields' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'category_edit_fields' ) );
		add_action( 'created_product_cat', array( __CLASS__, 'save_category_translations' ), 20 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_category_translations' ), 20 );
	}

	public static function add_meta_box() {
		add_meta_box( 'mediline_catalog_product', 'Mediline Catalog', array( __CLASS__, 'render_meta_box' ), 'product', 'normal', 'default' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'mediline_catalog_product', 'mediline_catalog_nonce' );
		$allowed = (array) get_post_meta( $post->ID, '_mediline_markets', true );
		?>
		<style>
			.mc-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mc-box{border:1px solid #dcdcde;padding:14px;background:#fff}.mc-box h4{margin:0 0 10px}.mc-i18n{display:grid;grid-template-columns:1fr 1fr;gap:12px}.mc-i18n .wide{grid-column:1/-1}.mc-i18n label,.mc-market-price label{display:block;font-weight:600}.mc-i18n input,.mc-i18n textarea,.mc-market-price input{width:100%;margin-top:5px}.mc-market-price{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}@media(max-width:900px){.mc-grid,.mc-market-price,.mc-i18n{grid-template-columns:1fr}}
		</style>
		<div class="mc-box">
			<h4>Availability by market</h4>
			<p class="description">If none are checked, the product is available in every registered market.</p>
			<div class="mc-grid">
			<?php foreach ( self::markets() as $code => $label ) : ?>
				<label><input type="checkbox" name="mediline_markets[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $allowed, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			</div>
		</div>
		<div class="mc-box" style="margin-top:12px">
			<h4>Market price overrides</h4>
			<p class="description">Leave empty to use the WooCommerce product price. Checkout always revalidates the price on this central site.</p>
			<div class="mc-market-price">
			<?php foreach ( self::markets() as $code => $label ) : ?>
				<label><?php echo esc_html( $code . ' — ' . $label ); ?><input type="text" inputmode="decimal" name="mediline_prices[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_mediline_price_' . strtolower( $code ), true ) ); ?>" placeholder="Use Woo price"></label>
			<?php endforeach; ?>
			</div>
		</div>
		<?php foreach ( self::languages() as $code => $label ) :
			$translation = (array) get_post_meta( $post->ID, '_mediline_translation_' . $code, true );
			?>
			<div class="mc-box" style="margin-top:12px">
				<h4><?php echo esc_html( $label . ' (' . strtoupper( $code ) . ')' ); ?></h4>
				<p class="description"><?php echo 'en' === $code ? 'English can be left empty to use the native WooCommerce title/descriptions.' : 'Empty fields fall back to English / WooCommerce.'; ?></p>
				<div class="mc-i18n">
					<label>Name<input type="text" name="mediline_translation[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $translation['name'] ?? '' ); ?>"></label>
					<label>SEO title<input type="text" name="mediline_translation[<?php echo esc_attr( $code ); ?>][seo_title]" value="<?php echo esc_attr( $translation['seo_title'] ?? '' ); ?>"></label>
					<label class="wide">Short description<textarea rows="3" name="mediline_translation[<?php echo esc_attr( $code ); ?>][short_description]"><?php echo esc_textarea( $translation['short_description'] ?? '' ); ?></textarea></label>
					<label class="wide">Description<textarea rows="6" name="mediline_translation[<?php echo esc_attr( $code ); ?>][description]"><?php echo esc_textarea( $translation['description'] ?? '' ); ?></textarea></label>
					<label class="wide">SEO description<textarea rows="2" name="mediline_translation[<?php echo esc_attr( $code ); ?>][seo_description]"><?php echo esc_textarea( $translation['seo_description'] ?? '' ); ?></textarea></label>
				</div>
			</div>
		<?php endforeach;
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['mediline_catalog_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mediline_catalog_nonce'] ) ), 'mediline_catalog_product' ) ) { return; }
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$markets = isset( $_POST['mediline_markets'] ) ? array_values( array_intersect( array_keys( self::markets() ), array_map( 'sanitize_key', (array) wp_unslash( $_POST['mediline_markets'] ) ) ) ) : array();
		update_post_meta( $post_id, '_mediline_markets', $markets );

		$prices = isset( $_POST['mediline_prices'] ) && is_array( $_POST['mediline_prices'] ) ? wp_unslash( $_POST['mediline_prices'] ) : array();
		foreach ( self::markets() as $code => $label ) {
			$value = isset( $prices[ $code ] ) ? wc_format_decimal( $prices[ $code ] ) : '';
			$key   = '_mediline_price_' . strtolower( $code );
			'' === $value ? delete_post_meta( $post_id, $key ) : update_post_meta( $post_id, $key, $value );
		}

		$translations = isset( $_POST['mediline_translation'] ) && is_array( $_POST['mediline_translation'] ) ? wp_unslash( $_POST['mediline_translation'] ) : array();
		foreach ( self::languages() as $code => $label ) {
			$raw = isset( $translations[ $code ] ) && is_array( $translations[ $code ] ) ? $translations[ $code ] : array();
			$data = array(
				'name'              => sanitize_text_field( $raw['name'] ?? '' ),
				'short_description' => wp_kses_post( $raw['short_description'] ?? '' ),
				'description'       => wp_kses_post( $raw['description'] ?? '' ),
				'seo_title'         => sanitize_text_field( $raw['seo_title'] ?? '' ),
				'seo_description'   => sanitize_textarea_field( $raw['seo_description'] ?? '' ),
			);
			update_post_meta( $post_id, '_mediline_translation_' . $code, $data );
		}
	}

	public static function touch_product( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'product' !== $post->post_type ) { return; }
		$version = Mediline_Catalog_DB::log_change( 'product', $post_id, 'upsert' );
		update_post_meta( $post_id, '_mediline_catalog_version', $version );
	}

	public static function deleted_product( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			Mediline_Catalog_DB::log_change( 'product', $post_id, 'delete' );
		}
	}

	public static function category_add_fields() {
		wp_nonce_field( 'mediline_category_i18n', 'mediline_category_i18n_nonce' );
		foreach ( self::languages() as $code => $label ) {
			if ( 'en' === $code ) { continue; }
			?><div class="form-field"><label><?php echo esc_html( $label . ' (' . strtoupper( $code ) . ')' ); ?> name</label><input type="text" name="mediline_category_i18n[<?php echo esc_attr( $code ); ?>][name]"><p class="description">Optional. Empty values fall back to the WooCommerce category name.</p></div><?php
		}
	}

	public static function category_edit_fields( $term ) {
		wp_nonce_field( 'mediline_category_i18n', 'mediline_category_i18n_nonce' );
		foreach ( self::languages() as $code => $label ) {
			if ( 'en' === $code ) { continue; }
			$data = (array) get_term_meta( $term->term_id, '_mediline_i18n_' . $code, true );
			?><tr class="form-field"><th><label><?php echo esc_html( $label . ' (' . strtoupper( $code ) . ')' ); ?></label></th><td><input type="text" name="mediline_category_i18n[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $data['name'] ?? '' ); ?>"><textarea rows="3" name="mediline_category_i18n[<?php echo esc_attr( $code ); ?>][description]" placeholder="Translated description"><?php echo esc_textarea( $data['description'] ?? '' ); ?></textarea><p class="description">Empty values fall back to the WooCommerce category.</p></td></tr><?php
		}
	}

	public static function save_category_translations( $term_id ) {
		if ( ! isset( $_POST['mediline_category_i18n_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mediline_category_i18n_nonce'] ) ), 'mediline_category_i18n' ) ) { return; }
		$input = isset( $_POST['mediline_category_i18n'] ) && is_array( $_POST['mediline_category_i18n'] ) ? wp_unslash( $_POST['mediline_category_i18n'] ) : array();
		foreach ( self::languages() as $code => $label ) {
			if ( 'en' === $code ) { continue; }
			$raw = isset( $input[ $code ] ) && is_array( $input[ $code ] ) ? $input[ $code ] : array();
			$data = array( 'name' => sanitize_text_field( $raw['name'] ?? '' ), 'description' => wp_kses_post( $raw['description'] ?? '' ) );
			if ( array_filter( $data ) ) { update_term_meta( $term_id, '_mediline_i18n_' . $code, $data ); } else { delete_term_meta( $term_id, '_mediline_i18n_' . $code ); }
		}
	}

	public static function translated_category( $term, $lang ) {
		$lang = sanitize_key( $lang );
		$data = 'en' === $lang ? array() : (array) get_term_meta( $term->term_id, '_mediline_i18n_' . $lang, true );
		return array(
			'name' => ! empty( $data['name'] ) ? $data['name'] : $term->name,
			'description' => ! empty( $data['description'] ) ? $data['description'] : $term->description,
		);
	}

	public static function touch_category( $term_id ) {
		Mediline_Catalog_DB::log_change( 'category', $term_id, 'upsert' );
	}

	public static function delete_category( $term_id ) {
		Mediline_Catalog_DB::log_change( 'category', $term_id, 'delete' );
	}

	public static function product_allowed( $product_id, $market ) {
		$raw = get_post_meta( $product_id, '_mediline_markets', true );
		if ( '' === $raw || null === $raw || false === $raw ) { return true; }

		$allowed = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY );
		$normalized = array();
		foreach ( $allowed as $value ) {
			if ( ! is_scalar( $value ) ) { continue; }
			$value = strtoupper( sanitize_text_field( (string) $value ) );
			if ( '' !== $value ) { $normalized[] = $value; }
		}
		$allowed = array_values( array_unique( $normalized ) );
		$market = strtoupper( sanitize_text_field( (string) $market ) );

		return ! $allowed || in_array( $market, $allowed, true ) || ( 'EU' !== $market && in_array( 'EU', $allowed, true ) );
	}

	public static function translated( WC_Product $product, $lang ) {
		$lang = sanitize_key( $lang );
		$data = (array) get_post_meta( $product->get_id(), '_mediline_translation_' . $lang, true );
		$en   = (array) get_post_meta( $product->get_id(), '_mediline_translation_en', true );
		$pick = static function ( $key, $fallback = '' ) use ( $data, $en ) {
			if ( isset( $data[ $key ] ) && '' !== trim( wp_strip_all_tags( (string) $data[ $key ] ) ) ) { return $data[ $key ]; }
			if ( isset( $en[ $key ] ) && '' !== trim( wp_strip_all_tags( (string) $en[ $key ] ) ) ) { return $en[ $key ]; }
			return $fallback;
		};
		return array(
			'name'              => $pick( 'name', $product->get_name() ),
			'short_description' => $pick( 'short_description', $product->get_short_description() ),
			'description'       => $pick( 'description', $product->get_description() ),
			'seo_title'         => $pick( 'seo_title', '' ),
			'seo_description'   => $pick( 'seo_description', '' ),
		);
	}

	public static function market_price( WC_Product $product, $market ) {
		$override = get_post_meta( $product->get_id(), '_mediline_price_' . strtolower( $market ), true );
		if ( '' === $override && 'EU' !== strtoupper( $market ) ) {
			$override = get_post_meta( $product->get_id(), '_mediline_price_eu', true );
		}
		$price = '' !== $override ? (float) $override : (float) $product->get_price();
		return max( 0, $price );
	}

	public static function image_data( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $url ) { return null; }
		return array(
			'id'  => (int) $attachment_id,
			'url' => $url,
			'alt' => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	public static function serialize( WC_Product $product, $store, $lang ) {
		$market = strtoupper( (string) $store->market );
		if ( ! self::product_allowed( $product->get_id(), $market ) ) { return null; }
		$text = self::translated( $product, $lang );
		$images = array();
		if ( $product->get_image_id() ) { $images[] = self::image_data( $product->get_image_id() ); }
		foreach ( $product->get_gallery_image_ids() as $image_id ) { $images[] = self::image_data( $image_id ); }
		$images = array_values( array_filter( $images ) );

		$categories = array();
		foreach ( $product->get_category_ids() as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$category_text = self::translated_category( $term, $lang );
				$categories[] = array( 'id' => (int) $term->term_id, 'slug' => $term->slug, 'name' => $category_text['name'] );
			}
		}
		$attributes = array();
		foreach ( $product->get_attributes() as $attribute ) {
			$attributes[] = array(
				'name'      => wc_attribute_label( $attribute->get_name() ),
				'slug'      => $attribute->get_name(),
				'options'   => $attribute->is_taxonomy() ? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) ) : $attribute->get_options(),
				'variation' => $attribute->get_variation(),
			);
		}
		$variations = array();
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation || ! $variation->exists() || ! $variation->is_purchasable() ) { continue; }
				$variations[] = array(
					'id'           => $variation->get_id(),
					'sku'          => $variation->get_sku(),
					'attributes'   => $variation->get_attributes(),
					'price'        => self::market_price( $variation, $market ),
					'stock_status' => $variation->get_stock_status(),
					'image'        => $variation->get_image_id() ? self::image_data( $variation->get_image_id() ) : null,
				);
			}
		}

		return array(
			'id'                => $product->get_id(),
			'sku'               => $product->get_sku(),
			'type'              => $product->get_type(),
			'slug'              => $product->get_slug(),
			'status'            => $product->get_status(),
			'featured'          => $product->is_featured(),
			'name'              => $text['name'],
			'short_description' => $text['short_description'],
			'description'       => $text['description'],
			'seo'               => array( 'title' => $text['seo_title'], 'description' => $text['seo_description'] ),
			'images'            => $images,
			'categories'        => $categories,
			'attributes'        => $attributes,
			'variations'        => $variations,
			'price'             => array(
				'amount'   => self::market_price( $product, $market ),
				'currency' => strtoupper( (string) $store->currency ),
			),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_manage_stock() ? $product->get_stock_quantity() : null,
			'purchasable'       => $product->is_purchasable(),
			'version'           => (int) get_post_meta( $product->get_id(), '_mediline_catalog_version', true ),
			'updated_at'        => $product->get_date_modified() ? $product->get_date_modified()->date( DATE_ATOM ) : null,
		);
	}
}
