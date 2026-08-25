<?php
/**
 * Front-end rendering helpers.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mediline_partners_brand( $inverse = false, $href = '' ) {
	$href     = $href ? $href : home_url( '/' );
	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : MEDILINE_PARTNERS_URI . '/assets/images/mediline-sphere.png';
	?>
	<a class="brand <?php echo $inverse ? 'inverse' : ''; ?>" href="<?php echo esc_url( $href ); ?>" aria-label="<?php esc_attr_e( 'Mediline Partners home', 'mediline-partners' ); ?>">
		<span class="brand-symbol official" aria-hidden="true"><img src="<?php echo esc_url( $logo_url ); ?>" alt="" width="36" height="37"></span>
		<span class="brand-type"><strong>MEDILINE</strong><small>PARTNERS</small></span>
	</a>
	<?php
}

function mediline_partners_arrow( $diagonal = false ) {
	?><span aria-hidden="true" class="arrow <?php echo $diagonal ? 'diagonal' : ''; ?>"><span></span></span><?php
}

function mediline_partners_login_arrow() {
	?><span class="login-arrow" aria-hidden="true"></span><?php
}

function mediline_partners_product_pack( $label, $kind = 'box' ) {
	?>
	<div class="product-pack <?php echo esc_attr( $kind ); ?>"><span class="pack-mark">M+</span><span class="pack-line"></span><b><?php echo esc_html( $label ); ?></b></div>
	<?php
}

function mediline_partners_get_templates() {
	return get_posts(
		array(
			'post_type'      => 'mp_store_template',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
			'order'          => 'ASC',
		)
	);
}

function mediline_partners_get_faqs() {
	return get_posts(
		array(
			'post_type'      => 'mp_faq',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'ASC' ),
			'order'          => 'ASC',
		)
	);
}

/**
 * Prepare responsive screenshot data for a Store Template card and modal.
 */
function mediline_partners_template_gallery( $post_id ) {
	$gallery = array();
	$ids     = function_exists( 'mediline_partners_template_gallery_ids' ) ? mediline_partners_template_gallery_ids( $post_id ) : array();

	foreach ( $ids as $attachment_id ) {
		$large = wp_get_attachment_image_src( $attachment_id, 'large' );
		$cover = wp_get_attachment_image_src( $attachment_id, 'medium_large' );
		$full  = wp_get_attachment_image_src( $attachment_id, 'full' );
		$image = $large ?: $full;
		$cover = $cover ?: $image;
		if ( ! $image || ! $cover ) {
			continue;
		}

		$gallery[] = array(
			'id'           => (string) $attachment_id,
			'src'          => $image[0],
			'srcset'       => wp_get_attachment_image_srcset( $attachment_id, 'large' ) ?: '',
			'sizes'        => '(max-width: 780px) 100vw, 72vw',
			'width'        => (int) $image[1],
			'height'       => (int) $image[2],
			'cover'        => $cover[0],
			'cover_width'  => (int) $cover[1],
			'cover_height' => (int) $cover[2],
			'alt'          => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $attachment_id ),
			'caption'      => wp_strip_all_tags( wp_get_attachment_caption( $attachment_id ) ),
		);
	}

	return $gallery;
}

function mediline_partners_template_data( $post, $index = 0 ) {
	$variant = get_post_meta( $post->ID, '_mp_variant', true );
	if ( ! in_array( $variant, array( 'atelier', 'motion', 'nordic', 'family', 'mono', 'terra' ), true ) ) {
		$variant = 'atelier';
	}
	$language     = mediline_partners_current_language();
	$translations = get_post_meta( $post->ID, '_mp_translations', true );
	$translated   = mediline_partners_default_language() !== $language && is_array( $translations ) && isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array();
	$localized    = static function ( $key, $english ) use ( $translated ) {
		return ! empty( $translated[ $key ] ) ? $translated[ $key ] : $english;
	};
	$category = get_post_meta( $post->ID, '_mp_category', true ) ?: 'Conversion';
	return array(
		'id'              => (string) $post->ID,
		'number'          => str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ),
		'name'            => $localized( 'name', get_the_title( $post ) ),
		'category'        => $category,
		'category_label'  => mediline_partners_category_label( $category ),
		'tagline'         => $localized( 'tagline', get_post_meta( $post->ID, '_mp_tagline', true ) ?: get_the_excerpt( $post ) ),
		'description'     => $localized( 'description', wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ) ),
		'tone'            => $localized( 'tone', get_post_meta( $post->ID, '_mp_tone', true ) ),
		'audience'        => $localized( 'audience', get_post_meta( $post->ID, '_mp_audience', true ) ),
		'variant'         => $variant,
		'preview_eyebrow' => $localized( 'preview_eyebrow', get_post_meta( $post->ID, '_mp_preview_eyebrow', true ) ),
		'preview_heading' => $localized( 'preview_heading', get_post_meta( $post->ID, '_mp_preview_heading', true ) ),
		'preview_text'    => $localized( 'preview_text', get_post_meta( $post->ID, '_mp_preview_text', true ) ),
		'preview_cta'     => $localized( 'preview_cta', get_post_meta( $post->ID, '_mp_preview_cta', true ) ),
		'product_1'       => $localized( 'product_1', get_post_meta( $post->ID, '_mp_product_1', true ) ),
		'product_2'       => $localized( 'product_2', get_post_meta( $post->ID, '_mp_product_2', true ) ),
		'gallery'         => mediline_partners_template_gallery( $post->ID ),
	);
}

function mediline_partners_faq_data( $post ) {
	$language     = mediline_partners_current_language();
	$translations = get_post_meta( $post->ID, '_mp_faq_translations', true );
	$translated   = mediline_partners_default_language() !== $language && is_array( $translations ) && isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array();
	return array(
		'question' => ! empty( $translated['question'] ) ? $translated['question'] : get_the_title( $post ),
		'answer'   => ! empty( $translated['answer'] ) ? $translated['answer'] : wp_strip_all_tags( apply_filters( 'the_content', $post->post_content ) ),
	);
}

function mediline_partners_preview_heading( $value ) {
	return nl2br( esc_html( $value ) );
}

/**
 * Render the first uploaded screenshot as the card cover.
 */
function mediline_partners_store_cover( $template ) {
	if ( empty( $template['gallery'][0] ) ) {
		mediline_partners_store_preview( $template );
		return;
	}

	$cover = $template['gallery'][0];
	$count = count( $template['gallery'] );
	?>
	<div class="template-cover">
		<img src="<?php echo esc_url( $cover['cover'] ); ?>" alt="<?php echo esc_attr( $cover['alt'] ); ?>" width="<?php echo esc_attr( $cover['cover_width'] ); ?>" height="<?php echo esc_attr( $cover['cover_height'] ); ?>" loading="lazy" decoding="async">
		<div class="template-cover-chrome"><span><?php echo esc_html( mediline_partners_t( 'live_preview', 'Live storefront preview' ) ); ?></span><b><?php echo esc_html( number_format_i18n( $count ) . ' ' . mediline_partners_t( 1 === $count ? 'screen' : 'screens', 1 === $count ? 'screen' : 'screens' ) ); ?></b></div>
	</div>
	<?php
}

/**
 * Render one CSS-built storefront preview. All visible text comes from the
 * corresponding Store Template entry while the visual direction remains fixed.
 */
function mediline_partners_store_preview( $template, $expanded = false ) {
	$variant = $template['variant'];
	?>
	<div class="store-preview preview-<?php echo esc_attr( $variant ); ?> <?php echo $expanded ? 'expanded' : ''; ?>">
		<div class="store-browser-bar">
			<span class="browser-dots"><i></i><i></i><i></i></span>
			<span class="browser-url">store.mediline.health</span>
			<span class="browser-actions"><?php echo esc_html( strtoupper( mediline_partners_current_language() ) ); ?>&nbsp;&nbsp; Cart 02</span>
		</div>
		<div class="store-header">
			<b><?php echo esc_html( $template['name'] ); ?></b>
			<nav aria-label="<?php echo esc_attr( $template['name'] . ' preview navigation' ); ?>"><span><?php echo esc_html( mediline_partners_t( 'shop', 'Shop' ) ); ?></span><span><?php echo esc_html( mediline_partners_t( 'guides', 'Guides' ) ); ?></span><span><?php echo esc_html( mediline_partners_t( 'about', 'About' ) ); ?></span></nav>
			<span class="store-menu"><?php echo esc_html( mediline_partners_t( 'menu', 'Menu' ) ); ?></span>
		</div>

		<?php if ( 'atelier' === $variant ) : ?>
			<div class="store-scene atelier-scene">
				<div class="scene-copy"><em><?php echo esc_html( $template['preview_eyebrow'] ); ?></em><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><p><?php echo esc_html( $template['preview_text'] ); ?></p><span class="mini-button"><?php echo esc_html( $template['preview_cta'] ); ?></span></div>
				<div class="atelier-stage"><span class="stage-disc"></span><?php mediline_partners_product_pack( $template['product_1'] ?: 'Value', 'tube' ); ?><?php mediline_partners_product_pack( $template['product_2'] ?: 'Core' ); ?></div>
			</div>
		<?php elseif ( 'motion' === $variant ) : ?>
			<div class="store-scene motion-scene">
				<div class="motion-grid"></div><span class="motion-index">/ 01</span>
				<div class="scene-copy"><em><?php echo esc_html( $template['preview_eyebrow'] ); ?></em><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><span class="mini-button"><?php echo esc_html( $template['preview_cta'] ); ?></span></div>
				<div class="motion-product"><span class="orbit">LIVE</span><?php mediline_partners_product_pack( $template['product_1'] ?: 'Track', 'tall' ); ?></div>
				<div class="motion-ticker">TRAFFIC · ATTRIBUTION · PERFORMANCE ·</div>
			</div>
		<?php elseif ( 'nordic' === $variant ) : ?>
			<div class="store-scene nordic-scene">
				<div class="nordic-nav">01 &nbsp; Offers&nbsp;&nbsp;&nbsp; 02 &nbsp; Stories&nbsp;&nbsp;&nbsp; 03 &nbsp; Support</div>
				<div class="scene-copy"><em><?php echo esc_html( $template['preview_eyebrow'] ); ?></em><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><p><?php echo esc_html( $template['preview_text'] ); ?></p></div>
				<div class="nordic-shelf"><?php mediline_partners_product_pack( $template['product_1'] ?: 'Select', 'bottle' ); ?><?php mediline_partners_product_pack( $template['product_2'] ?: 'Value', 'tube' ); ?><span class="shelf-shadow"></span></div>
				<span class="nordic-seal">MEDILINE<br>PARTNER</span>
			</div>
		<?php elseif ( 'family' === $variant ) : ?>
			<div class="store-scene family-scene">
				<div class="scene-copy"><span class="friendly-pill"><?php echo esc_html( $template['preview_eyebrow'] ); ?></span><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><p><?php echo esc_html( $template['preview_text'] ); ?></p><span class="mini-button"><?php echo esc_html( $template['preview_cta'] ); ?></span></div>
				<div class="family-cards"><span class="family-card peach"><b>Discover</b><small>Start with context</small></span><span class="family-card lilac"><b>Compare</b><small>See the value</small></span><span class="family-card yellow"><b>Choose</b><small>Take action</small></span></div>
			</div>
		<?php elseif ( 'mono' === $variant ) : ?>
			<div class="store-scene mono-scene">
				<span class="mono-issue">ISSUE 05 — MEASURED GROWTH</span>
				<div class="scene-copy"><em><?php echo esc_html( $template['preview_eyebrow'] ); ?></em><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><span class="mini-link"><?php echo esc_html( strtoupper( $template['preview_cta'] ) ); ?> <b>↗</b></span></div>
				<div class="mono-object"><span class="mono-ring"></span><?php mediline_partners_product_pack( $template['product_1'] ?: 'Proof', 'tall' ); ?></div><span class="mono-vertical">DESIGNED FOR CONVERSION</span>
			</div>
		<?php else : ?>
			<div class="store-scene terra-scene">
				<div class="terra-sun"></div><div class="scene-copy"><em><?php echo esc_html( $template['preview_eyebrow'] ); ?></em><h3><?php echo mediline_partners_preview_heading( $template['preview_heading'] ); ?></h3><p><?php echo esc_html( $template['preview_text'] ); ?></p><span class="mini-button"><?php echo esc_html( $template['preview_cta'] ); ?></span></div>
				<div class="terra-table"><?php mediline_partners_product_pack( $template['product_1'] ?: 'Trust', 'bottle' ); ?><span class="terra-leaf"></span><?php mediline_partners_product_pack( $template['product_2'] ?: 'Value' ); ?></div>
			</div>
		<?php endif; ?>
		<div class="store-footer-line"><span><?php echo esc_html( strtoupper( mediline_partners_t( 'partner_storefront', 'Mediline partner storefront' ) ) ); ?></span><span><?php echo esc_html( mediline_partners_t( 'tracked_pap', 'Tracked with Post Affiliate Pro' ) ); ?></span></div>
	</div>
	<?php
}
