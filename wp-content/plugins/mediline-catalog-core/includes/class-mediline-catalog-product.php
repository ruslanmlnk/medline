<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Mediline_Catalog_Product {
	public static function languages() {
		if ( function_exists( 'mediline_partners_languages' ) ) {
			$result = array();
			foreach ( mediline_partners_languages() as $code => $language ) {
				$code = 'sp' === sanitize_key( $code ) ? 'es' : sanitize_key( $code );
				$result[ $code ] = sanitize_text_field( $language['name'] ?? $language['label'] ?? strtoupper( $code ) );
			}
			if ( $result ) { return $result; }
		}
		return array(
			'en' => 'English',
			'fr' => 'Français',
			'de' => 'Deutsch',
			'es' => 'Español',
			'it' => 'Italiano',
		);
	}

	public static function language_flags() {
		return array( 'en' => '🇬🇧', 'fr' => '🇫🇷', 'de' => '🇩🇪', 'es' => '🇪🇸', 'it' => '🇮🇹' );
	}

	private static function translation_data( $object_id, $code, $term = false ) {
		$prefix = $term ? '_mediline_i18n_' : '_mediline_translation_';
		$data   = $term ? get_term_meta( $object_id, $prefix . $code, true ) : get_post_meta( $object_id, $prefix . $code, true );
		if ( 'es' === $code && ! array_filter( (array) $data ) ) {
			$data = $term ? get_term_meta( $object_id, $prefix . 'sp', true ) : get_post_meta( $object_id, $prefix . 'sp', true );
		}
		return (array) $data;
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
		add_action( 'current_screen', array( __CLASS__, 'hide_default_translation_fields' ), 100 );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'remove_default_translation_boxes' ), 100 );
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

	/**
	 * Translation tabs are the only editing UI for customer-facing product copy.
	 * Product data (price, SKU, stock, shipping, attributes, etc.) stays native.
	 */
	public static function hide_default_translation_fields( $screen ) {
		if ( ! $screen || 'product' !== $screen->post_type || 'post' !== $screen->base ) { return; }
		remove_post_type_support( 'product', 'title' );
		remove_post_type_support( 'product', 'editor' );
	}

	public static function remove_default_translation_boxes() {
		remove_meta_box( 'postexcerpt', 'product', 'normal' );
	}

	public static function add_meta_box() {
		add_meta_box( 'mediline_catalog_product', 'Mediline Catalog', array( __CLASS__, 'render_meta_box' ), 'product', 'normal', 'default' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'mediline_catalog_product', 'mediline_catalog_nonce' );
		$allowed = (array) get_post_meta( $post->ID, '_mediline_markets', true );
		$flags   = self::language_flags();
		?>
		<style>
			.mc-box{border:1px solid #c3c4c7;border-radius:4px;padding:16px;background:#fff}.mc-box h3,.mc-box h4{margin:0 0 6px}.mc-grid,.mc-market-price{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mc-market-price label{display:block;font-weight:600}.mc-market-price input{box-sizing:border-box;width:100%;margin-top:5px}.mc-translations{padding:0;overflow:hidden}.mc-tabs{display:flex;margin:0;padding:0 16px;background:#f6f7f7;border-bottom:1px solid #c3c4c7;overflow-x:auto}.mc-tab{display:flex;align-items:center;gap:7px;min-width:112px;padding:13px 16px;margin:0 0 -1px;border:1px solid transparent;border-bottom-color:#c3c4c7;background:transparent;color:#50575e;font-weight:600;cursor:pointer;white-space:nowrap}.mc-tab:hover{color:#135e96;background:#fff}.mc-tab.is-active{color:#1d2327;background:#fff;border-color:#c3c4c7;border-bottom-color:#fff}.mc-flag{font-size:20px;line-height:1}.mc-code{padding:2px 6px;border-radius:10px;background:#dcdcde;font-size:10px;letter-spacing:.08em}.mc-tab.is-active .mc-code{color:#fff;background:#2271b1}.mc-status{width:7px;height:7px;margin-left:auto;border-radius:50%;background:#a7aaad}.mc-status.is-complete{background:#00a32a}.mc-panel{display:none;padding:20px}.mc-panel.is-active{display:block}.mc-panel-head{margin-bottom:18px}.mc-panel-head p{margin:4px 0 0}.mc-field{margin-bottom:18px}.mc-field>label{display:block;margin-bottom:7px;font-weight:600}.mc-field input[type=text],.mc-field textarea{box-sizing:border-box;width:100%}.mc-field input[type=text]{min-height:42px;padding:7px 10px;font-size:15px}.mc-help{display:flex;justify-content:space-between;gap:12px;margin-top:5px;color:#646970;font-size:12px}.mc-editor .wp-editor-wrap{border:1px solid #c3c4c7}.mc-editor .wp-editor-container{border:0}.mc-seo{padding:16px 16px 1px;border-left:4px solid #2271b1;background:#f6f7f7}.mc-seo h4{margin-bottom:12px}.mc-settings{margin-top:14px}.mc-settings summary{font-weight:600;cursor:pointer}.mc-settings[open] summary{margin-bottom:16px}@media(max-width:900px){.mc-grid,.mc-market-price{grid-template-columns:1fr}.mc-panel{padding:16px}.mc-tab{min-width:auto}.mc-tab-name{display:none}}
		</style>
		<div class="mc-box mc-translations">
			<div class="mc-tabs" role="tablist" aria-label="Product translation languages">
			<?php $first = true; foreach ( self::languages() as $code => $label ) :
				$translation = self::translation_data( $post->ID, $code );
				$complete = ! empty( $translation['name'] ) && ! empty( $translation['description'] ); ?>
				<button type="button" class="mc-tab<?php echo $first ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" aria-controls="mc-panel-<?php echo esc_attr( $code ); ?>" data-language="<?php echo esc_attr( $code ); ?>"><span class="mc-flag" aria-hidden="true"><?php echo esc_html( $flags[ $code ] ?? '🌐' ); ?></span><span class="mc-tab-name"><?php echo esc_html( $label ); ?></span><span class="mc-code"><?php echo esc_html( strtoupper( $code ) ); ?></span><span class="mc-status<?php echo $complete ? ' is-complete' : ''; ?>" title="<?php echo esc_attr( $complete ? 'Translation filled' : 'Translation incomplete' ); ?>"></span></button>
			<?php $first = false; endforeach; ?>
			</div>
			<?php $first = true; foreach ( self::languages() as $code => $label ) :
				$translation = self::translation_data( $post->ID, $code ); ?>
				<section id="mc-panel-<?php echo esc_attr( $code ); ?>" class="mc-panel<?php echo $first ? ' is-active' : ''; ?>" role="tabpanel" data-language="<?php echo esc_attr( $code ); ?>">
					<div class="mc-panel-head"><h3><?php echo esc_html( ( $flags[ $code ] ?? '🌐' ) . ' ' . $label ); ?> <code><?php echo esc_html( strtoupper( $code ) ); ?></code></h3><p class="description"><?php echo 'en' === $code ? 'Canonical language. Empty fields use the native WooCommerce product content.' : 'Empty fields fall back to English, then to the native WooCommerce content.'; ?></p></div>
					<div class="mc-field"><label for="mc-name-<?php echo esc_attr( $code ); ?>">Product name</label><input id="mc-name-<?php echo esc_attr( $code ); ?>" type="text" name="mediline_translation[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $translation['name'] ?? '' ); ?>" placeholder="<?php echo esc_attr( 'en' === $code ? $post->post_title : 'Translated product name' ); ?>"><div class="mc-help"><span>Customer-facing product title.</span><span class="mc-counter" data-max="70">0 / 70</span></div></div>
					<div class="mc-field mc-editor"><label>Short description</label><?php wp_editor( $translation['short_description'] ?? '', 'mc_short_' . $code, array( 'textarea_name' => 'mediline_translation[' . $code . '][short_description]', 'textarea_rows' => 5, 'media_buttons' => false, 'teeny' => true, 'quicktags' => true ) ); ?><div class="mc-help"><span>Used in product cards and beside the product image.</span></div></div>
					<div class="mc-field mc-editor"><label>Full description</label><?php wp_editor( $translation['description'] ?? '', 'mc_description_' . $code, array( 'textarea_name' => 'mediline_translation[' . $code . '][description]', 'textarea_rows' => 10, 'media_buttons' => true, 'teeny' => false, 'quicktags' => true ) ); ?><div class="mc-help"><span>Complete formatted product description.</span></div></div>
					<div class="mc-seo"><h4>Search snippet</h4><div class="mc-field"><label for="mc-seo-title-<?php echo esc_attr( $code ); ?>">SEO title</label><input id="mc-seo-title-<?php echo esc_attr( $code ); ?>" type="text" name="mediline_translation[<?php echo esc_attr( $code ); ?>][seo_title]" value="<?php echo esc_attr( $translation['seo_title'] ?? '' ); ?>" placeholder="Optional — product name is used by default"><div class="mc-help"><span>Recommended maximum: 60 characters.</span><span class="mc-counter" data-max="60">0 / 60</span></div></div><div class="mc-field"><label for="mc-seo-description-<?php echo esc_attr( $code ); ?>">Meta description</label><textarea id="mc-seo-description-<?php echo esc_attr( $code ); ?>" rows="3" name="mediline_translation[<?php echo esc_attr( $code ); ?>][seo_description]" placeholder="Short description for search and social previews"><?php echo esc_textarea( $translation['seo_description'] ?? '' ); ?></textarea><div class="mc-help"><span>Recommended maximum: 160 characters.</span><span class="mc-counter" data-max="160">0 / 160</span></div></div></div>
				</section>
			<?php $first = false; endforeach; ?>
		</div>
		<details class="mc-box mc-settings"><summary>Market availability and pricing</summary>
		<div>
			<h4>Availability by market</h4>
			<p class="description">If none are checked, the product is available in every registered market.</p>
			<div class="mc-grid">
			<?php foreach ( self::markets() as $code => $label ) : ?>
				<label><input type="checkbox" name="mediline_markets[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $allowed, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
			<?php endforeach; ?>
			</div>
		</div>
		<div style="margin-top:18px">
			<h4>Market price overrides</h4>
			<p class="description">Leave empty to use the WooCommerce product price. Checkout always revalidates the price on this central site.</p>
			<div class="mc-market-price">
			<?php foreach ( self::markets() as $code => $label ) : ?>
				<label><?php echo esc_html( $code . ' — ' . $label ); ?><input type="text" inputmode="decimal" name="mediline_prices[<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( get_post_meta( $post->ID, '_mediline_price_' . strtolower( $code ), true ) ); ?>" placeholder="Use Woo price"></label>
			<?php endforeach; ?>
			</div>
		</div>
		</div></details>
		<script>
		(function(){var root=document.getElementById('mediline_catalog_product');if(!root)return;root.querySelectorAll('.mc-tab').forEach(function(tab){tab.addEventListener('click',function(){var lang=tab.dataset.language;root.querySelectorAll('.mc-tab').forEach(function(item){var active=item===tab;item.classList.toggle('is-active',active);item.setAttribute('aria-selected',active?'true':'false');});root.querySelectorAll('.mc-panel').forEach(function(panel){panel.classList.toggle('is-active',panel.dataset.language===lang);});window.dispatchEvent(new Event('resize'));});});root.querySelectorAll('.mc-counter').forEach(function(counter){var field=counter.closest('.mc-field').querySelector('input,textarea');var update=function(){var length=field.value.length,max=parseInt(counter.dataset.max,10);counter.textContent=length+' / '+max;counter.style.color=length>max?'#b32d2e':'';};field.addEventListener('input',update);update();});})();
		</script>
		<?php
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
			if ( 'es' === $code ) { delete_post_meta( $post_id, '_mediline_translation_sp' ); }
		}

		self::sync_english_to_woocommerce( $post_id );
	}

	/**
	 * WooCommerce still needs its native title/content/excerpt internally even
	 * though editors manage those values in the English translation tab.
	 */
	private static function sync_english_to_woocommerce( $post_id ) {
		$english = self::translation_data( $post_id, 'en' );
		if ( ! array_filter( $english ) ) { return; }

		$current = get_post( $post_id );
		if ( ! $current ) { return; }

		$update = array( 'ID' => $post_id );
		if ( ! empty( $english['name'] ) ) {
			$update['post_title'] = $english['name'];
		}
		if ( isset( $english['description'] ) ) {
			$update['post_content'] = $english['description'];
		}
		if ( isset( $english['short_description'] ) ) {
			$update['post_excerpt'] = $english['short_description'];
		}

		if ( 1 === count( $update ) ) { return; }
		if ( isset( $update['post_title'], $update['post_content'], $update['post_excerpt'] ) && $update['post_title'] === $current->post_title && $update['post_content'] === $current->post_content && $update['post_excerpt'] === $current->post_excerpt ) { return; }

		remove_action( 'save_post_product', array( __CLASS__, 'save_meta' ), 30 );
		remove_action( 'save_post_product', array( __CLASS__, 'touch_product' ), 99 );
		wp_update_post( wp_slash( $update ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_meta' ), 30, 2 );
		add_action( 'save_post_product', array( __CLASS__, 'touch_product' ), 99, 2 );
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
			$data = self::translation_data( $term->term_id, $code, true );
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
			if ( 'es' === $code ) { delete_term_meta( $term_id, '_mediline_i18n_sp' ); }
		}
	}

	public static function translated_category( $term, $lang ) {
		$lang = 'sp' === sanitize_key( $lang ) ? 'es' : sanitize_key( $lang );
		$data = 'en' === $lang ? array() : self::translation_data( $term->term_id, $lang, true );
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
		$lang = 'sp' === sanitize_key( $lang ) ? 'es' : sanitize_key( $lang );
		$data = self::translation_data( $product->get_id(), $lang );
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
