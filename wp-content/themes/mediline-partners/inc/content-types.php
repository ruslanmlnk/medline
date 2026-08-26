<?php
/**
 * Editable Store Templates and FAQ entries.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mediline_partners_register_content_types() {
	register_post_type(
		'mp_store_template',
		array(
			'labels' => array(
				'name'          => __( 'Store Templates', 'mediline-partners' ),
				'singular_name' => __( 'Store Template', 'mediline-partners' ),
				'add_new_item'  => __( 'Add Store Template', 'mediline-partners' ),
				'edit_item'     => __( 'Edit Store Template', 'mediline-partners' ),
				'menu_name'     => __( 'Store Templates', 'mediline-partners' ),
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-layout',
			'menu_position'      => 26,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
			'exclude_from_search'=> true,
		)
	);

	register_post_type(
		'mp_faq',
		array(
			'labels' => array(
				'name'          => __( 'Partner FAQ', 'mediline-partners' ),
				'singular_name' => __( 'FAQ item', 'mediline-partners' ),
				'add_new_item'  => __( 'Add FAQ item', 'mediline-partners' ),
				'edit_item'     => __( 'Edit FAQ item', 'mediline-partners' ),
				'menu_name'     => __( 'Partner FAQ', 'mediline-partners' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-editor-help',
			'menu_position'       => 27,
			'supports'            => array( 'title', 'editor', 'page-attributes' ),
			'exclude_from_search' => true,
		)
	);
}
add_action( 'init', 'mediline_partners_register_content_types' );

function mediline_partners_template_meta_fields() {
	return array(
		'_mp_category'        => array( 'Category', 'select', array( 'Conversion', 'Performance', 'Global', 'Content' ) ),
		'_mp_variant'         => array( 'Visual direction', 'select', array( 'atelier', 'motion', 'nordic', 'family', 'mono', 'terra' ) ),
		'_mp_tagline'         => array( 'Modal tagline', 'text' ),
		'_mp_tone'            => array( 'Visual tone', 'text' ),
		'_mp_audience'        => array( 'Best for', 'text' ),
		'_mp_preview_eyebrow' => array( 'Preview label', 'text' ),
		'_mp_preview_heading' => array( 'Preview headline', 'textarea' ),
		'_mp_preview_text'    => array( 'Preview description', 'textarea' ),
		'_mp_preview_cta'     => array( 'Preview button', 'text' ),
		'_mp_product_1'       => array( 'Product label 1', 'text' ),
		'_mp_product_2'       => array( 'Product label 2', 'text' ),
	);
}

/**
 * Production storefronts shipped through Store Builder.
 *
 * The package fragment links a database entry to its uploaded private ZIP,
 * while preview_asset points at the matching, versioned theme screenshot.
 */
function mediline_partners_storefront_catalog() {
	return array(
		'aeris'     => array(
			'package_fragment' => 'mediline-aeris',
			'preview_asset'    => 'aeris.png',
			'legacy_title'     => 'Mediline Core',
			'title'            => 'Mediline Aeris',
			'excerpt'          => 'Clinical Air — a calm, high-trust pharmacy storefront.',
			'content'          => 'A light clinical storefront with focused search, generous spacing and clear product discovery. Built for trust-led health and wellness campaigns.',
			'category'         => 'Conversion',
			'variant'          => 'atelier',
			'tone'             => 'Clinical / airy',
			'audience'         => 'Trust-led health stores',
			'preview_eyebrow'  => 'Clinical Air',
			'preview_heading'  => "Breathe easier.\nShop clearly.",
			'preview_text'     => 'A calm pharmacy experience with fast product discovery.',
			'preview_cta'      => 'Browse products',
			'product_1'        => 'Daily care',
			'product_2'        => 'Wellness',
		),
		'nova24'    => array(
			'package_fragment' => 'mediline-nova24',
			'preview_asset'    => 'nova24.png',
			'legacy_title'     => 'Performance OS',
			'title'            => 'Mediline Nova/24',
			'excerpt'          => 'Dark Med-Tech — a high-contrast storefront for always-on performance.',
			'content'          => 'A dark, technical commerce direction with sharp hierarchy, vivid accents and a direct route from product discovery to secure checkout.',
			'category'         => 'Performance',
			'variant'          => 'motion',
			'tone'             => 'Dark / med-tech',
			'audience'         => 'Performance affiliates',
			'preview_eyebrow'  => 'Dark Med-Tech',
			'preview_heading'  => "Your pharmacy.\nNow.",
			'preview_text'     => 'High-contrast shopping designed for decisive traffic.',
			'preview_cta'      => 'Shop now',
			'product_1'        => 'Performance',
			'product_2'        => 'Care',
		),
		'pulse'     => array(
			'package_fragment' => 'mediline-pulse',
			'preview_asset'    => 'pulse.png',
			'legacy_title'     => 'Market Light',
			'title'            => 'Mediline Pulse',
			'excerpt'          => 'Express Mobile — a fast storefront for mobile-first campaigns.',
			'content'          => 'A bright, energetic storefront with bold navigation, compact decision paths and responsive product discovery for high-volume mobile traffic.',
			'category'         => 'Performance',
			'variant'          => 'nordic',
			'tone'             => 'Bright / mobile-first',
			'audience'         => 'Mobile-first campaigns',
			'preview_eyebrow'  => 'Express Mobile',
			'preview_heading'  => "Health at\nyour door.",
			'preview_text'     => 'Fast product discovery for customers shopping on the move.',
			'preview_cta'      => 'Explore products',
			'product_1'        => 'Express',
			'product_2'        => 'Essentials',
		),
		'bloom'     => array(
			'package_fragment' => 'mediline-bloom',
			'preview_asset'    => 'bloom.png',
			'legacy_title'     => 'Creator Shop',
			'title'            => 'Mediline Bloom',
			'excerpt'          => 'Family First — a warm, approachable wellness storefront.',
			'content'          => 'A friendly commerce direction with softer color, accessible hierarchy and clear product journeys for family, wellness and creator-led audiences.',
			'category'         => 'Content',
			'variant'          => 'family',
			'tone'             => 'Warm / approachable',
			'audience'         => 'Family and creator audiences',
			'preview_eyebrow'  => 'Family First',
			'preview_heading'  => "Care that is\nalways close.",
			'preview_text'     => 'Helpful shopping journeys for everyday family wellness.',
			'preview_cta'      => 'Browse care',
			'product_1'        => 'Family',
			'product_2'        => 'Daily care',
		),
		'apotheke'  => array(
			'package_fragment' => 'mediline-apotheke',
			'preview_asset'    => 'apotheke.png',
			'legacy_title'     => 'Conversion Mono',
			'title'            => 'Mediline Apotheke',
			'excerpt'          => 'Swiss Grid — precise pharmacy retail with uncompromised clarity.',
			'content'          => 'A disciplined grid-led storefront with strong typography, direct calls to action and a precise visual system suited to German-speaking markets.',
			'category'         => 'Conversion',
			'variant'          => 'mono',
			'tone'             => 'Swiss / precise',
			'audience'         => 'German-speaking markets',
			'preview_eyebrow'  => 'Swiss Grid',
			'preview_heading'  => "Gesundheit.\nKlar gedacht.",
			'preview_text'     => 'Precise pharmacy commerce with a clear path to purchase.',
			'preview_cta'      => 'Produkte ansehen',
			'product_1'        => 'Apotheke',
			'product_2'        => 'Gesundheit',
		),
		'verde'     => array(
			'package_fragment' => 'mediline-verde',
			'preview_asset'    => 'verde.png',
			'legacy_title'     => 'Trust Retail',
			'title'            => 'Mediline Verde',
			'excerpt'          => 'Natural Care — an organic storefront for wellness and lifestyle.',
			'content'          => 'A grounded retail direction with natural color, editorial breathing room and reassuring product discovery for care-focused audiences.',
			'category'         => 'Global',
			'variant'          => 'terra',
			'tone'             => 'Natural / grounded',
			'audience'         => 'Wellness and lifestyle audiences',
			'preview_eyebrow'  => 'Natural Care',
			'preview_heading'  => "Care rooted\nin nature.",
			'preview_text'     => 'A warmer product experience inspired by everyday wellbeing.',
			'preview_cta'      => 'Discover care',
			'product_1'        => 'Natural care',
			'product_2'        => 'Wellness',
		),
	);
}

/**
 * Resolve a storefront entry without exposing the private package path.
 */
function mediline_partners_template_storefront_key( $post_id ) {
	$catalog = mediline_partners_storefront_catalog();
	$key     = sanitize_key( get_post_meta( $post_id, '_mp_storefront_key', true ) );
	if ( isset( $catalog[ $key ] ) ) {
		return $key;
	}

	$package = strtolower(
		(string) get_post_meta( $post_id, '_mp_package_name', true ) . ' ' .
		(string) get_post_meta( $post_id, '_mp_package_path', true )
	);
	$title = get_the_title( $post_id );
	foreach ( $catalog as $catalog_key => $entry ) {
		if ( false !== strpos( $package, $entry['package_fragment'] ) || $title === $entry['legacy_title'] || $title === $entry['title'] ) {
			return $catalog_key;
		}
	}

	return '';
}

/**
 * Replace the original design-direction demo records with their real themes.
 * Existing custom storefront records and already-renamed entries are preserved.
 */
function mediline_partners_sync_storefront_catalog() {
	if ( '1' === (string) get_option( 'mediline_partners_storefront_catalog_version', '' ) ) {
		return;
	}

	$catalog = mediline_partners_storefront_catalog();
	$posts   = get_posts(
		array(
			'post_type'      => 'mp_store_template',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
		)
	);

	foreach ( $posts as $post ) {
		$key = mediline_partners_template_storefront_key( $post->ID );
		if ( ! $key || ! isset( $catalog[ $key ] ) ) {
			continue;
		}

		$entry = $catalog[ $key ];
		update_post_meta( $post->ID, '_mp_storefront_key', $key );

		if ( $post->post_title !== $entry['legacy_title'] ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $post->ID,
				'post_title'   => $entry['title'],
				'post_excerpt' => $entry['excerpt'],
				'post_content' => $entry['content'],
			)
		);

		$meta = array(
			'_mp_category'        => $entry['category'],
			'_mp_variant'         => $entry['variant'],
			'_mp_tagline'         => $entry['excerpt'],
			'_mp_tone'            => $entry['tone'],
			'_mp_audience'        => $entry['audience'],
			'_mp_preview_eyebrow' => $entry['preview_eyebrow'],
			'_mp_preview_heading' => $entry['preview_heading'],
			'_mp_preview_text'    => $entry['preview_text'],
			'_mp_preview_cta'     => $entry['preview_cta'],
			'_mp_product_1'       => $entry['product_1'],
			'_mp_product_2'       => $entry['product_2'],
		);
		foreach ( $meta as $meta_key => $value ) {
			update_post_meta( $post->ID, $meta_key, $value );
		}
	}

	update_option( 'mediline_partners_storefront_catalog_version', '1', false );
}
add_action( 'admin_init', 'mediline_partners_sync_storefront_catalog', 20 );

function mediline_partners_protect_package_storage_dir( $dir ) {
	if ( ! $dir || ! is_dir( $dir ) ) {
		return;
	}
	@file_put_contents( trailingslashit( $dir ) . '.htaccess', "Options -Indexes\nDeny from all\n" );
	@file_put_contents( trailingslashit( $dir ) . 'web.config', '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>' );
	@file_put_contents( trailingslashit( $dir ) . 'index.php', "<?php http_response_code(404); exit;\n" );
}

function mediline_partners_package_storage_dir() {
	$candidates = array();
	if ( defined( 'MEDILINE_PRIVATE_PACKAGE_DIR' ) && MEDILINE_PRIVATE_PACKAGE_DIR ) {
		$candidates[] = untrailingslashit( MEDILINE_PRIVATE_PACKAGE_DIR );
	}

	/*
	 * Prefer wp-content: it is normally persistent/writable on managed hosts and
	 * container deployments. The old implementation preferred the directory
	 * above ABSPATH, which may be ephemeral or blocked by open_basedir.
	 */
	$candidates[] = trailingslashit( WP_CONTENT_DIR ) . 'mediline-private-packages';
	$candidates[] = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'mediline-private-packages';

	foreach ( array_unique( $candidates ) as $dir ) {
		if ( wp_mkdir_p( $dir ) && is_dir( $dir ) && is_writable( $dir ) ) {
			mediline_partners_protect_package_storage_dir( $dir );
			return untrailingslashit( $dir );
		}
	}

	return '';
}

function mediline_partners_template_package( $post_id ) {
	$file    = (string) get_post_meta( $post_id, '_mp_package_path', true );
	$name    = (string) get_post_meta( $post_id, '_mp_package_name', true );
	$version = sanitize_text_field( get_post_meta( $post_id, '_mp_package_version', true ) );

	/* Recover packages after a document-root/container path change. */
	if ( $file && ! file_exists( $file ) ) {
		$storage = mediline_partners_package_storage_dir();
		$recovered = $storage ? trailingslashit( $storage ) . basename( $file ) : '';
		if ( $recovered && file_exists( $recovered ) ) {
			$file = $recovered;
			update_post_meta( $post_id, '_mp_package_path', $file );
		}
	}

	$exists = $file && is_file( $file );
	return array(
		'id'      => 0,
		'file'    => $exists ? $file : '',
		'name'    => $name ?: ( $file ? basename( $file ) : '' ),
		'version' => $version ?: '1.0.0',
		'size'    => $exists ? (int) filesize( $file ) : 0,
	);
}

function mediline_partners_add_template_meta_box() {
	add_meta_box(
		'mediline-template-details',
		__( 'Storefront preview details', 'mediline-partners' ),
		'mediline_partners_render_template_meta_box',
		'mp_store_template',
		'normal',
		'high'
	);
	add_meta_box(
		'mediline-template-translations',
		__( 'Storefront translations', 'mediline-partners' ),
		'mediline_partners_render_template_translations',
		'mp_store_template',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'mediline_partners_add_template_meta_box' );

function mediline_partners_add_faq_translation_meta_box() {
	add_meta_box(
		'mediline-faq-translations',
		__( 'FAQ translations', 'mediline-partners' ),
		'mediline_partners_render_faq_translations',
		'mp_faq',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'mediline_partners_add_faq_translation_meta_box' );

/**
 * Load WordPress' native media picker only while editing a Store Template.
 */
function mediline_partners_template_editor_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'mp_store_template', 'mp_faq' ), true ) || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_style(
		'mediline-partners-translations',
		MEDILINE_PARTNERS_URI . '/assets/css/admin-translations.css',
		array(),
		MEDILINE_PARTNERS_VERSION
	);
	wp_enqueue_style(
		'mediline-partners-translations-layout',
		MEDILINE_PARTNERS_URI . '/assets/css/admin-translations-layout.css',
		array( 'mediline-partners-translations' ),
		MEDILINE_PARTNERS_VERSION
	);
	if ( 'mp_store_template' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style(
		'mediline-partners-template-gallery',
		MEDILINE_PARTNERS_URI . '/assets/css/admin-template-gallery.css',
		array(),
		MEDILINE_PARTNERS_VERSION
	);
	wp_enqueue_script(
		'mediline-partners-template-gallery',
		MEDILINE_PARTNERS_URI . '/assets/js/admin-template-gallery.js',
		array( 'media-editor' ),
		MEDILINE_PARTNERS_VERSION,
		true
	);
	wp_localize_script(
		'mediline-partners-template-gallery',
		'medilineTemplateGallery',
		array(
			'title'  => __( 'Choose storefront screenshots', 'mediline-partners' ),
			'button' => __( 'Use selected screenshots', 'mediline-partners' ),
			'empty'  => __( 'No screenshots selected yet.', 'mediline-partners' ),
			'remove' => __( 'Remove screenshot', 'mediline-partners' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'mediline_partners_template_editor_assets' );

function mediline_partners_template_form_uploads( $post ) {
	if ( $post && 'mp_store_template' === $post->post_type ) {
		echo ' enctype="multipart/form-data"';
	}
}
add_action( 'post_edit_form_tag', 'mediline_partners_template_form_uploads' );

function mediline_partners_nondefault_languages() {
	$languages = mediline_partners_languages();
	unset( $languages[ mediline_partners_default_language() ] );
	return $languages;
}

function mediline_partners_template_translation_fields() {
	return array(
		'name'            => array( 'Template name', 'text' ),
		'tagline'         => array( 'Modal tagline', 'text' ),
		'description'     => array( 'Modal description', 'textarea' ),
		'tone'            => array( 'Visual tone', 'text' ),
		'audience'        => array( 'Best for', 'text' ),
		'preview_eyebrow' => array( 'Preview label', 'text' ),
		'preview_heading' => array( 'Preview headline', 'textarea' ),
		'preview_text'    => array( 'Preview description', 'textarea' ),
		'preview_cta'     => array( 'Preview button', 'text' ),
		'product_1'       => array( 'Product label 1', 'text' ),
		'product_2'       => array( 'Product label 2', 'text' ),
	);
}

function mediline_partners_render_template_translations( $post ) {
	wp_nonce_field( 'mediline_template_translations', 'mediline_template_translations_nonce' );
	$translations = get_post_meta( $post->ID, '_mp_translations', true );
	$translations = is_array( $translations ) ? $translations : array();
	$languages    = mediline_partners_nondefault_languages();
	?>
	<div class="mp-translation-box">
		<p class="mp-translation-intro"><?php esc_html_e( 'English uses the standard title, excerpt, editor and preview fields above. Add language-specific versions here; empty fields fall back to English.', 'mediline-partners' ); ?></p>
		<?php if ( ! $languages ) : ?><p><?php esc_html_e( 'Add another language under Appearance → Languages first.', 'mediline-partners' ); ?></p><?php endif; ?>
		<?php foreach ( $languages as $code => $language ) : $values = isset( $translations[ $code ] ) && is_array( $translations[ $code ] ) ? $translations[ $code ] : array(); ?>
			<details class="mp-translation-language" <?php echo 'fr' === $code ? 'open' : ''; ?>><summary><b><?php echo esc_html( $language['label'] ); ?></b><span><?php echo esc_html( $language['name'] ); ?></span><em><?php echo ! empty( array_filter( $values ) ) ? esc_html__( 'Translated', 'mediline-partners' ) : esc_html__( 'Uses EN fallback', 'mediline-partners' ); ?></em></summary>
				<div class="mp-translation-grid">
					<?php foreach ( mediline_partners_template_translation_fields() as $key => $field ) : $wide = 'textarea' === $field[1]; ?>
						<label class="<?php echo $wide ? 'wide' : ''; ?>"><span><?php echo esc_html( $field[0] ); ?></span><?php if ( $wide ) : ?><textarea name="mp_translations[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( $values[ $key ] ?? '' ); ?></textarea><?php else : ?><input type="text" name="mp_translations[<?php echo esc_attr( $code ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[ $key ] ?? '' ); ?>"><?php endif; ?></label>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

function mediline_partners_render_faq_translations( $post ) {
	wp_nonce_field( 'mediline_faq_translations', 'mediline_faq_translations_nonce' );
	$translations = get_post_meta( $post->ID, '_mp_faq_translations', true );
	$translations = is_array( $translations ) ? $translations : array();
	$languages    = mediline_partners_nondefault_languages();
	?>
	<div class="mp-translation-box">
		<p class="mp-translation-intro"><?php esc_html_e( 'The standard title and editor are the English question and answer. Empty translations automatically use English.', 'mediline-partners' ); ?></p>
		<?php if ( ! $languages ) : ?><p><?php esc_html_e( 'Add another language under Appearance → Languages first.', 'mediline-partners' ); ?></p><?php endif; ?>
		<?php foreach ( $languages as $code => $language ) : $values = isset( $translations[ $code ] ) && is_array( $translations[ $code ] ) ? $translations[ $code ] : array(); ?>
			<details class="mp-translation-language" <?php echo 'fr' === $code ? 'open' : ''; ?>><summary><b><?php echo esc_html( $language['label'] ); ?></b><span><?php echo esc_html( $language['name'] ); ?></span><em><?php echo ! empty( array_filter( $values ) ) ? esc_html__( 'Translated', 'mediline-partners' ) : esc_html__( 'Uses EN fallback', 'mediline-partners' ); ?></em></summary>
				<div class="mp-translation-grid"><label class="wide"><span><?php esc_html_e( 'Question', 'mediline-partners' ); ?></span><input type="text" name="mp_faq_translations[<?php echo esc_attr( $code ); ?>][question]" value="<?php echo esc_attr( $values['question'] ?? '' ); ?>"></label><label class="wide"><span><?php esc_html_e( 'Answer', 'mediline-partners' ); ?></span><textarea name="mp_faq_translations[<?php echo esc_attr( $code ); ?>][answer]" rows="5"><?php echo esc_textarea( $values['answer'] ?? '' ); ?></textarea></label></div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Return valid image attachment IDs in their saved display order.
 */
function mediline_partners_template_gallery_ids( $post_id ) {
	$ids = get_post_meta( $post_id, '_mp_gallery_ids', true );
	if ( ! is_array( $ids ) ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $ids ) ) );
	}

	return array_values(
		array_filter(
			array_unique( array_map( 'absint', $ids ) ),
			'wp_attachment_is_image'
		)
	);
}

function mediline_partners_render_template_meta_box( $post ) {
	wp_nonce_field( 'mediline_template_meta', 'mediline_template_nonce' );
	$gallery_ids = mediline_partners_template_gallery_ids( $post->ID );
	$package     = mediline_partners_template_package( $post->ID );
	?>
	<style>
		.mp-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.mp-meta-field{display:flex;flex-direction:column;gap:7px}.mp-meta-field.wide{grid-column:1/-1}.mp-meta-field span{font-weight:650}.mp-meta-field input,.mp-meta-field select,.mp-meta-field textarea{width:100%;max-width:none}.mp-meta-help{grid-column:1/-1;margin:0;padding:12px 14px;background:#f2f5ee;border-left:4px solid #90b900}@media(max-width:782px){.mp-meta-grid{grid-template-columns:1fr}.mp-meta-field.wide{grid-column:auto}}
	</style>
	<div class="mp-meta-grid">
		<p class="mp-meta-help"><?php esc_html_e( 'The title, excerpt and main editor control the template name, short tagline and modal description. The fields below control its visual preview.', 'mediline-partners' ); ?></p>
		<div class="mp-package-field">
			<div class="mp-package-copy"><strong><?php esc_html_e( 'WordPress storefront theme ZIP', 'mediline-partners' ); ?></strong><p><?php esc_html_e( 'Upload only the WordPress theme ZIP for this storefront design. Store Builder automatically wraps it with install.sh, Docker Compose, Caddy HTTPS, Mediline Store Core, one-time provisioning and the initial catalog sync.', 'mediline-partners' ); ?></p></div>
			<div class="mp-package-current" <?php echo $package['file'] ? '' : 'hidden'; ?>>
				<span class="dashicons dashicons-media-archive"></span><div><b class="mp-package-name"><?php echo esc_html( $package['name'] ); ?></b><small class="mp-package-size"><?php echo $package['size'] ? esc_html( size_format( $package['size'] ) ) : ''; ?></small></div><label class="mp-package-delete"><input type="checkbox" name="_mp_package_remove" value="1"> <?php esc_html_e( 'Remove on save', 'mediline-partners' ); ?></label>
			</div>
			<input type="hidden" name="_mp_package_upload_expected" class="mp-package-upload-expected" value="0"><label class="mp-package-upload"><span><?php echo $package['file'] ? esc_html__( 'Replace ZIP', 'mediline-partners' ) : esc_html__( 'Upload ZIP', 'mediline-partners' ); ?></span><input type="file" name="_mp_package_upload" accept=".zip,application/zip"></label>
			<label class="mp-package-version"><span><?php esc_html_e( 'Theme version', 'mediline-partners' ); ?></span><input type="text" name="_mp_package_version" value="<?php echo esc_attr( $package['version'] ); ?>" placeholder="1.0.0"></label>
		</div>
		<div class="mp-gallery-field">
			<div class="mp-gallery-heading">
				<div><strong><?php esc_html_e( 'Storefront screenshots', 'mediline-partners' ); ?></strong><p><?php esc_html_e( 'Add one or more images. The first screenshot becomes the public card cover; all screenshots appear in the detailed slider. Drag cards to change their order.', 'mediline-partners' ); ?></p></div>
				<button type="button" class="button button-primary mp-gallery-add"><?php esc_html_e( 'Add screenshots', 'mediline-partners' ); ?></button>
			</div>
			<input type="hidden" class="mp-gallery-ids" name="_mp_gallery_ids" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">
			<ul class="mp-gallery-list">
				<?php foreach ( $gallery_ids as $attachment_id ) :
					$image = wp_get_attachment_image( $attachment_id, 'medium', false, array( 'draggable' => 'false' ) );
					$title = get_the_title( $attachment_id ) ?: __( 'Storefront screenshot', 'mediline-partners' );
					?>
					<li class="mp-gallery-item" data-id="<?php echo esc_attr( $attachment_id ); ?>" draggable="true">
						<span class="mp-gallery-drag" aria-hidden="true">⋮⋮</span>
						<div class="mp-gallery-thumb"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<div class="mp-gallery-item-meta"><b><?php echo esc_html( $title ); ?></b><small><?php esc_html_e( 'Drag to reorder', 'mediline-partners' ); ?></small></div>
						<button type="button" class="mp-gallery-remove" aria-label="<?php esc_attr_e( 'Remove screenshot', 'mediline-partners' ); ?>">×</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="mp-gallery-empty" <?php echo $gallery_ids ? 'hidden' : ''; ?>><?php esc_html_e( 'No screenshots selected yet. The generated visual preview will remain active.', 'mediline-partners' ); ?></p>
		</div>
		<?php foreach ( mediline_partners_template_meta_fields() as $key => $field ) :
			$value = get_post_meta( $post->ID, $key, true );
			$type  = $field[1];
			$wide  = 'textarea' === $type;
			?>
			<label class="mp-meta-field <?php echo $wide ? 'wide' : ''; ?>">
				<span><?php echo esc_html( $field[0] ); ?></span>
				<?php if ( 'select' === $type ) : ?>
					<select name="<?php echo esc_attr( $key ); ?>">
						<?php foreach ( $field[2] as $choice ) : ?><option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $value, $choice ); ?>><?php echo esc_html( ucfirst( $choice ) ); ?></option><?php endforeach; ?>
					</select>
				<?php elseif ( 'textarea' === $type ) : ?>
					<textarea name="<?php echo esc_attr( $key ); ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
				<?php else : ?>
					<input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</div>
	<?php
}

function mediline_partners_validate_storefront_theme_zip( $file ) {
	$files = array();

	if ( class_exists( 'ZipArchive' ) ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return new WP_Error( 'theme_zip_invalid', __( 'The uploaded file is not a readable ZIP archive.', 'mediline-partners' ) );
		}
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = ltrim( str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) ), '/' );
			if ( $name && false === strpos( $name, '../' ) ) {
				$files[] = $name;
			}
		}
		$zip->close();
	} else {
		/* WordPress itself falls back to PclZip on hosts without ext-zip. */
		if ( ! class_exists( 'PclZip' ) ) {
			$pclzip = ABSPATH . 'wp-admin/includes/class-pclzip.php';
			if ( file_exists( $pclzip ) ) {
				require_once $pclzip;
			}
		}
		if ( ! class_exists( 'PclZip' ) ) {
			return new WP_Error( 'zip_support_missing', __( 'The server has no ZIP reader available. Enable PHP ZipArchive or the WordPress PclZip library.', 'mediline-partners' ) );
		}
		$archive = new PclZip( $file );
		$list    = $archive->listContent();
		if ( ! is_array( $list ) ) {
			return new WP_Error( 'theme_zip_invalid', __( 'The uploaded file is not a readable ZIP archive.', 'mediline-partners' ) );
		}
		foreach ( $list as $entry ) {
			$name = isset( $entry['filename'] ) ? ltrim( str_replace( '\\', '/', (string) $entry['filename'] ), '/' ) : '';
			if ( $name && false === strpos( $name, '../' ) ) {
				$files[] = $name;
			}
		}
	}

	$roots = array( '' );
	foreach ( $files as $name ) {
		$parts = explode( '/', $name );
		if ( count( $parts ) > 1 && $parts[0] ) {
			$roots[] = $parts[0] . '/';
		}
	}
	foreach ( array_unique( $roots ) as $root ) {
		$has_style = in_array( $root . 'style.css', $files, true );
		$has_entry = in_array( $root . 'index.php', $files, true ) || in_array( $root . 'templates/index.html', $files, true );
		if ( $has_style && $has_entry ) {
			return true;
		}
	}

	return new WP_Error( 'theme_zip_structure', __( 'This ZIP does not look like a WordPress theme: style.css plus index.php/templates/index.html were not found.', 'mediline-partners' ) );
}

function mediline_partners_template_upload_notice() {
	if ( ! current_user_can( 'edit_theme_options' ) ) { return; }
	$user_id   = get_current_user_id();
	$error_key = 'mediline_template_upload_error_' . $user_id;
	$ok_key    = 'mediline_template_upload_success_' . $user_id;
	$error     = get_transient( $error_key );
	$success   = get_transient( $ok_key );
	if ( $error ) {
		delete_transient( $error_key );
		echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Storefront ZIP was not saved.', 'mediline-partners' ) . '</strong> ' . esc_html( $error ) . '</p></div>';
	}
	if ( $success ) {
		delete_transient( $ok_key );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $success ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'mediline_partners_template_upload_notice' );

function mediline_partners_set_template_upload_error( $message ) {
	set_transient( 'mediline_template_upload_error_' . get_current_user_id(), (string) $message, MINUTE_IN_SECONDS );
}

function mediline_partners_upload_error_message( $error_code ) {
	$messages = array(
		UPLOAD_ERR_INI_SIZE   => __( 'The ZIP is larger than the server upload_max_filesize limit.', 'mediline-partners' ),
		UPLOAD_ERR_FORM_SIZE  => __( 'The ZIP is larger than the allowed form upload limit.', 'mediline-partners' ),
		UPLOAD_ERR_PARTIAL    => __( 'The ZIP upload was interrupted before it completed.', 'mediline-partners' ),
		UPLOAD_ERR_NO_TMP_DIR => __( 'The server has no temporary upload directory configured.', 'mediline-partners' ),
		UPLOAD_ERR_CANT_WRITE => __( 'The server could not write the uploaded ZIP to disk.', 'mediline-partners' ),
		UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the ZIP upload.', 'mediline-partners' ),
	);
	return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : __( 'The ZIP upload failed before WordPress could save it.', 'mediline-partners' );
}

function mediline_partners_save_template_meta( $post_id ) {
	if ( ! isset( $_POST['mediline_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mediline_template_nonce'] ) ), 'mediline_template_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( mediline_partners_template_meta_fields() as $key => $field ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$value = wp_unslash( $_POST[ $key ] );
		$value = 'textarea' === $field[1] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		update_post_meta( $post_id, $key, $value );
	}

	$raw_gallery = isset( $_POST['_mp_gallery_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['_mp_gallery_ids'] ) ) : '';
	$gallery_ids = array_values(
		array_filter(
			array_unique( array_map( 'absint', explode( ',', $raw_gallery ) ) ),
			'wp_attachment_is_image'
		)
	);
	if ( $gallery_ids ) {
		update_post_meta( $post_id, '_mp_gallery_ids', $gallery_ids );
	} else {
		delete_post_meta( $post_id, '_mp_gallery_ids' );
	}

	$current_package = mediline_partners_template_package( $post_id );
	if ( ! empty( $_POST['_mp_package_remove'] ) && $current_package['file'] ) {
		@unlink( $current_package['file'] );
		delete_post_meta( $post_id, '_mp_package_path' );
		delete_post_meta( $post_id, '_mp_package_name' );
		$current_package = array( 'file' => '' );
	}

	$upload_expected = ! empty( $_POST['_mp_package_upload_expected'] );
	if ( $upload_expected && ! isset( $_FILES['_mp_package_upload'] ) ) {
		mediline_partners_set_template_upload_error( __( 'A ZIP was selected in the browser, but PHP received no file. Check post_max_size, upload_max_filesize and multipart/form-data handling on the server.', 'mediline-partners' ) );
	}

	if ( isset( $_FILES['_mp_package_upload'] ) && is_array( $_FILES['_mp_package_upload'] ) ) {
		$upload_error = isset( $_FILES['_mp_package_upload']['error'] ) ? (int) $_FILES['_mp_package_upload']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_NO_FILE !== $upload_error && UPLOAD_ERR_OK !== $upload_error ) {
			mediline_partners_set_template_upload_error( mediline_partners_upload_error_message( $upload_error ) );
		} elseif ( UPLOAD_ERR_OK === $upload_error ) {
			$original_name = sanitize_file_name( wp_unslash( $_FILES['_mp_package_upload']['name'] ) );
			$tmp_name      = isset( $_FILES['_mp_package_upload']['tmp_name'] ) ? $_FILES['_mp_package_upload']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$size          = isset( $_FILES['_mp_package_upload']['size'] ) ? (int) $_FILES['_mp_package_upload']['size'] : 0;

			if ( 'zip' !== strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) ) ) {
				mediline_partners_set_template_upload_error( __( 'Only .zip WordPress theme packages are accepted.', 'mediline-partners' ) );
			} elseif ( $size <= 0 ) {
				mediline_partners_set_template_upload_error( __( 'The uploaded ZIP is empty.', 'mediline-partners' ) );
			} elseif ( $size > 512 * MB_IN_BYTES ) {
				mediline_partners_set_template_upload_error( __( 'The uploaded ZIP is larger than the 512 MB Store Builder limit.', 'mediline-partners' ) );
			} elseif ( ! $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
				mediline_partners_set_template_upload_error( __( 'WordPress did not receive a valid uploaded file. Check the server upload configuration and try again.', 'mediline-partners' ) );
			} else {
				$valid_theme = mediline_partners_validate_storefront_theme_zip( $tmp_name );
				if ( is_wp_error( $valid_theme ) ) {
					mediline_partners_set_template_upload_error( $valid_theme->get_error_message() );
				} else {
					$storage_dir = mediline_partners_package_storage_dir();
					if ( ! $storage_dir ) {
						mediline_partners_set_template_upload_error( __( 'No writable private package directory is available. Make wp-content writable or define MEDILINE_PRIVATE_PACKAGE_DIR.', 'mediline-partners' ) );
					} else {
						$destination = trailingslashit( $storage_dir ) . 'template-' . $post_id . '-' . wp_generate_password( 18, false, false ) . '.zip';
						$moved = @move_uploaded_file( $tmp_name, $destination );
						if ( ! $moved ) {
							$moved = @copy( $tmp_name, $destination );
						}
						if ( ! $moved || ! is_file( $destination ) || filesize( $destination ) <= 0 ) {
							@unlink( $destination );
							mediline_partners_set_template_upload_error( __( 'The ZIP reached WordPress but could not be persisted in private Store Builder storage.', 'mediline-partners' ) );
						} else {
							@chmod( $destination, 0640 );
							if ( ! empty( $current_package['file'] ) && $current_package['file'] !== $destination ) { @unlink( $current_package['file'] ); }
							update_post_meta( $post_id, '_mp_package_path', $destination );
							update_post_meta( $post_id, '_mp_package_name', $original_name );
							set_transient(
								'mediline_template_upload_success_' . get_current_user_id(),
								sprintf( __( 'Storefront theme ZIP saved: %s', 'mediline-partners' ), $original_name ),
								MINUTE_IN_SECONDS
							);
						}
					}
				}
			}
		}
	}
	$package_version = isset( $_POST['_mp_package_version'] ) ? sanitize_text_field( wp_unslash( $_POST['_mp_package_version'] ) ) : '1.0.0';
	update_post_meta( $post_id, '_mp_package_version', $package_version ?: '1.0.0' );

	if ( isset( $_POST['mediline_template_translations_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mediline_template_translations_nonce'] ) ), 'mediline_template_translations' ) ) {
		$saved = get_post_meta( $post_id, '_mp_translations', true );
		$saved = is_array( $saved ) ? $saved : array();
		$input = isset( $_POST['mp_translations'] ) && is_array( $_POST['mp_translations'] ) ? wp_unslash( $_POST['mp_translations'] ) : array();
		foreach ( mediline_partners_nondefault_languages() as $code => $language ) {
			$clean = array();
			$rows  = isset( $input[ $code ] ) && is_array( $input[ $code ] ) ? $input[ $code ] : array();
			foreach ( mediline_partners_template_translation_fields() as $key => $field ) {
				$value = isset( $rows[ $key ] ) ? $rows[ $key ] : '';
				$value = 'textarea' === $field[1] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
				if ( '' !== trim( $value ) ) {
					$clean[ $key ] = $value;
				}
			}
			if ( $clean ) {
				$saved[ $code ] = $clean;
			} else {
				unset( $saved[ $code ] );
			}
		}
		if ( $saved ) {
			update_post_meta( $post_id, '_mp_translations', $saved );
		} else {
			delete_post_meta( $post_id, '_mp_translations' );
		}
	}
}
add_action( 'save_post_mp_store_template', 'mediline_partners_save_template_meta' );

function mediline_partners_save_faq_translations( $post_id ) {
	if ( ! isset( $_POST['mediline_faq_translations_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mediline_faq_translations_nonce'] ) ), 'mediline_faq_translations' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$saved = get_post_meta( $post_id, '_mp_faq_translations', true );
	$saved = is_array( $saved ) ? $saved : array();
	$input = isset( $_POST['mp_faq_translations'] ) && is_array( $_POST['mp_faq_translations'] ) ? wp_unslash( $_POST['mp_faq_translations'] ) : array();
	foreach ( mediline_partners_nondefault_languages() as $code => $language ) {
		$rows  = isset( $input[ $code ] ) && is_array( $input[ $code ] ) ? $input[ $code ] : array();
		$clean = array(
			'question' => sanitize_text_field( $rows['question'] ?? '' ),
			'answer'   => sanitize_textarea_field( $rows['answer'] ?? '' ),
		);
		$clean = array_filter( $clean, static function ( $value ) { return '' !== trim( $value ); } );
		if ( $clean ) {
			$saved[ $code ] = $clean;
		} else {
			unset( $saved[ $code ] );
		}
	}
	if ( $saved ) {
		update_post_meta( $post_id, '_mp_faq_translations', $saved );
	} else {
		delete_post_meta( $post_id, '_mp_faq_translations' );
	}
}
add_action( 'save_post_mp_faq', 'mediline_partners_save_faq_translations' );

function mediline_partners_template_columns( $columns ) {
	$columns['mp_category'] = __( 'Category', 'mediline-partners' );
	$columns['mp_variant']  = __( 'Visual', 'mediline-partners' );
	$columns['mp_gallery']  = __( 'Screenshots', 'mediline-partners' );
	$columns['mp_package']  = __( 'Package', 'mediline-partners' );
	$columns['menu_order']  = __( 'Order', 'mediline-partners' );
	return $columns;
}
add_filter( 'manage_mp_store_template_posts_columns', 'mediline_partners_template_columns' );

function mediline_partners_template_column_value( $column, $post_id ) {
	if ( 'mp_category' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_mp_category', true ) );
	} elseif ( 'mp_variant' === $column ) {
		echo esc_html( ucfirst( get_post_meta( $post_id, '_mp_variant', true ) ) );
	} elseif ( 'mp_gallery' === $column ) {
		echo esc_html( count( mediline_partners_template_gallery_ids( $post_id ) ) );
	} elseif ( 'mp_package' === $column ) {
		$package = mediline_partners_template_package( $post_id );
		echo $package['file'] ? '<strong style="color:#628000">ZIP ' . esc_html( $package['version'] ) . '</strong>' : '<span style="color:#a33">Missing</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} elseif ( 'menu_order' === $column ) {
		echo esc_html( get_post_field( 'menu_order', $post_id ) );
	}
}
add_action( 'manage_mp_store_template_posts_custom_column', 'mediline_partners_template_column_value', 10, 2 );

/**
 * Seed editable production content once. Existing content is never overwritten.
 */
function mediline_partners_seed_content() {
	$template_counts = wp_count_posts( 'mp_store_template' );
	if ( empty( $template_counts->publish ) ) {
		$index = 0;
		foreach ( mediline_partners_storefront_catalog() as $key => $template ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'mp_store_template',
					'post_status'  => 'publish',
					'post_title'   => $template['title'],
					'post_excerpt' => $template['excerpt'],
					'post_content' => $template['content'],
					'menu_order'   => $index + 1,
				)
			);
			if ( ! is_wp_error( $post_id ) ) {
				$meta = array(
					'_mp_storefront_key'  => $key,
					'_mp_category'        => $template['category'],
					'_mp_variant'         => $template['variant'],
					'_mp_tagline'         => $template['excerpt'],
					'_mp_tone'            => $template['tone'],
					'_mp_audience'        => $template['audience'],
					'_mp_preview_eyebrow' => $template['preview_eyebrow'],
					'_mp_preview_heading' => $template['preview_heading'],
					'_mp_preview_text'    => $template['preview_text'],
					'_mp_preview_cta'     => $template['preview_cta'],
					'_mp_product_1'       => $template['product_1'],
					'_mp_product_2'       => $template['product_2'],
				);
				foreach ( $meta as $meta_key => $value ) {
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
			$index++;
		}
	}

	$faq_counts = wp_count_posts( 'mp_faq' );
	if ( empty( $faq_counts->publish ) ) {
		$faqs = array(
			array( 'Who can join the Mediline Partner Program?', 'The program accepts affiliates with proven experience in affiliate marketing. Every application is reviewed, and the program administration may decline cooperation.' ),
			array( 'Can I download a storefront template from this page?', 'No. This public website only demonstrates the available directions. Approved partners access templates, resources and launch materials after signing in to the Post Affiliate Pro Affiliate Panel.' ),
			array( 'Which traffic geographies are accepted?', 'Traffic is accepted exclusively from European countries and the USA. Traffic from other countries is not serviced and is not eligible for payment.' ),
			array( 'How much is the commission?', 'Commission ranges from 40% to 50% depending on the number of sales during the reporting period. The percentage is determined weekly and fixed by the program administration.' ),
			array( 'How do payouts work?', 'Payments are made once a week in USDT. The minimum payout is 100 USDT, and payment-system fees are covered by the affiliate. Other methods or schedules may be agreed individually.' ),
			array( 'What is the attribution window?', 'The cookie storage period is 90 days. A qualifying sale made within that period is attributed to the affiliate.' ),
			array( 'Which traffic methods are prohibited?', 'Fraud, incentivized traffic, spam, click fraud, bot traffic and use of the Mediline brand in contextual advertising without prior approval are prohibited.' ),
		);

		foreach ( $faqs as $index => $faq ) {
			wp_insert_post(
				array(
					'post_type'    => 'mp_faq',
					'post_status'  => 'publish',
					'post_title'   => $faq[0],
					'post_content' => $faq[1],
					'menu_order'   => $index + 1,
				)
			);
		}
	}
}
