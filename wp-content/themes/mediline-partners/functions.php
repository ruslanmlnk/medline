<?php
/**
 * Mediline Partners theme bootstrap.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MEDILINE_PARTNERS_VERSION', '1.8.4' );
define( 'MEDILINE_PARTNERS_DIR', get_template_directory() );
define( 'MEDILINE_PARTNERS_URI', get_template_directory_uri() );

require_once MEDILINE_PARTNERS_DIR . '/inc/languages.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/default-translations.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/theme-options.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/content-types.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/template-tags.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/store-builder.php';
require_once MEDILINE_PARTNERS_DIR . '/inc/pap-session-bridge.php';

/**
 * Theme supports and menus.
 */
function mediline_partners_setup() {
	load_theme_textdomain( 'mediline-partners', MEDILINE_PARTNERS_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'mediline-partners' ),
			'footer'  => __( 'Footer navigation', 'mediline-partners' ),
		)
	);
}
add_action( 'after_setup_theme', 'mediline_partners_setup' );

/**
 * Front-end assets. The stylesheet is intentionally framework-free and the
 * JavaScript is deferred, keeping the first render small and predictable.
 */
function mediline_partners_assets() {
	$is_builder = (bool) get_query_var( 'mediline_builder' );
	wp_enqueue_style(
		'mediline-partners-site',
		MEDILINE_PARTNERS_URI . '/assets/css/site.css',
		array(),
		MEDILINE_PARTNERS_VERSION
	);

	if ( ! $is_builder ) {
		wp_enqueue_script(
			'mediline-partners-site',
			MEDILINE_PARTNERS_URI . '/assets/js/theme.js',
			array(),
			MEDILINE_PARTNERS_VERSION,
			true
		);
		wp_script_add_data( 'mediline-partners-site', 'strategy', 'defer' );
		wp_localize_script(
			'mediline-partners-site',
			'medilinePartners',
			array(
				'reducedMotion' => false,
				'currentLanguage' => mediline_partners_current_language(),
				'i18n'          => array(
					'previousScreenshot' => mediline_partners_t( 'previous_screenshot', 'Previous screenshot' ),
					'nextScreenshot'     => mediline_partners_t( 'next_screenshot', 'Next screenshot' ),
					'chooseScreenshot'   => mediline_partners_t( 'choose_screenshot', 'Choose screenshot' ),
					'openScreenshot'     => mediline_partners_t( 'open_screenshot', 'Open screenshot' ),
					'storefrontScreen'   => mediline_partners_t( 'storefront_screen', 'Storefront screen' ),
					'screenshots'        => mediline_partners_t( 'screenshots', 'screenshots' ),
					'templatePreview'    => mediline_partners_t( 'template_preview', 'Template preview' ),
					'show'               => mediline_partners_t( 'show', 'Show' ),
					'hide'               => mediline_partners_t( 'hide', 'Hide' ),
				),
			)
		);
	}

	if ( $is_builder ) {
		wp_enqueue_style(
			'mediline-store-builder',
			MEDILINE_PARTNERS_URI . '/assets/css/store-builder.css',
			array( 'mediline-partners-site' ),
			MEDILINE_PARTNERS_VERSION
		);
		wp_enqueue_script(
			'mediline-store-builder',
			MEDILINE_PARTNERS_URI . '/assets/js/store-builder.js',
			array(),
			(string) filemtime( MEDILINE_PARTNERS_DIR . '/assets/js/store-builder.js' ),
			true
		);
		wp_script_add_data( 'mediline-store-builder', 'strategy', 'defer' );
		$builder_session = mediline_partners_builder_session();
		wp_localize_script(
			'mediline-store-builder',
			'medilineBuilder',
			array(
				'packageEndpoint' => rest_url( 'mediline/v1/builder/package' ),
				'csrf'            => is_array( $builder_session ) ? ( $builder_session['csrf'] ?? '' ) : '',
				'restNonce'       => is_user_logged_in() && current_user_can( 'edit_theme_options' ) ? wp_create_nonce( 'wp_rest' ) : '',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mediline_partners_assets' );

/**
 * Remove assets unused by the bespoke landing and partner-access templates.
 */
function mediline_partners_trim_frontend_assets() {
	if ( mediline_partners_is_landing_view() || get_query_var( 'mediline_builder' ) || get_query_var( 'mediline_legal' ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	}
}
add_action( 'wp_enqueue_scripts', 'mediline_partners_trim_frontend_assets', 100 );

/**
 * Native /login/ and /register/ routes without requiring page creation.
 */
function mediline_partners_rewrite_rules() {
	add_rewrite_rule( '^store-builder/?$', 'index.php?mediline_builder=1', 'top' );
	add_rewrite_rule( '^pap-store-builder/?$', 'index.php?mediline_pap_builder_bridge=1', 'top' );
	add_rewrite_rule( '^terms-conditions/?$', 'index.php?mediline_legal=terms-conditions', 'top' );

	$codes = array_diff( array_keys( mediline_partners_languages() ), array( mediline_partners_default_language() ) );
	if ( $codes ) {
		$pattern = implode( '|', array_map( static function ( $code ) { return preg_quote( $code, '/' ); }, $codes ) );
		add_rewrite_rule( '^(' . $pattern . ')/?$', 'index.php?mediline_lang=$matches[1]', 'top' );
		add_rewrite_rule( '^(' . $pattern . ')/terms-conditions/?$', 'index.php?mediline_lang=$matches[1]&mediline_legal=terms-conditions', 'top' );
	}
}
add_action( 'init', 'mediline_partners_rewrite_rules' );

function mediline_partners_query_vars( $vars ) {
	$vars[] = 'mediline_lang';
	$vars[] = 'mediline_builder';
	$vars[] = 'mediline_pap_builder_bridge';
	$vars[] = 'mediline_legal';
	return $vars;
}
add_filter( 'query_vars', 'mediline_partners_query_vars' );

function mediline_partners_access_template( $template ) {
	if ( get_query_var( 'mediline_pap_builder_bridge' ) ) {
		return MEDILINE_PARTNERS_DIR . '/page-pap-store-builder.php';
	}
	if ( get_query_var( 'mediline_builder' ) ) {
		return MEDILINE_PARTNERS_DIR . '/page-store-builder.php';
	}
	if ( 'terms-conditions' === get_query_var( 'mediline_legal' ) ) {
		return MEDILINE_PARTNERS_DIR . '/page-terms.php';
	}
	if ( get_query_var( 'mediline_lang' ) ) {
		return MEDILINE_PARTNERS_DIR . '/front-page.php';
	}
	return $template;
}
add_filter( 'template_include', 'mediline_partners_access_template' );

/**
 * Keep the virtual access routes out of WordPress' 404 state and prevent
 * browsers/proxies from caching pages that contain authentication forms.
 */
function mediline_partners_access_status() {
	$is_builder = get_query_var( 'mediline_builder' );
	$is_pap_bridge = get_query_var( 'mediline_pap_builder_bridge' );
	$is_legal = 'terms-conditions' === get_query_var( 'mediline_legal' );
	$is_localized_landing = get_query_var( 'mediline_lang' ) && ! $is_legal;
	if ( $is_localized_landing || $is_builder || $is_pap_bridge || $is_legal ) {
		global $wp_query;
		if ( $wp_query ) {
			$wp_query->is_404 = false;
		}
		status_header( 200 );
		if ( $is_builder || $is_pap_bridge ) {
			nocache_headers();
		}
	}
}
add_action( 'template_redirect', 'mediline_partners_access_status' );

/**
 * Flush routes and seed editable production content when the theme is activated.
 */
function mediline_partners_activate_theme() {
	mediline_partners_register_content_types();
	mediline_partners_rewrite_rules();
	mediline_partners_seed_content();
	mediline_partners_sync_storefront_catalog();
	if ( false === get_option( 'mediline_partner_options', false ) ) {
		add_option( 'mediline_partner_options', mediline_partners_default_options() );
	}
	if ( false === get_option( 'mediline_partner_languages', false ) ) {
		add_option( 'mediline_partner_languages', mediline_partners_default_languages(), '', false );
	}
	mediline_partners_builder_bridge_secret();
	update_option( 'mediline_partners_theme_version', MEDILINE_PARTNERS_VERSION, false );
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'mediline_partners_activate_theme' );

/**
 * Refresh rewrite rules once after an in-dashboard theme update.
 */
function mediline_partners_maybe_upgrade() {
	if ( MEDILINE_PARTNERS_VERSION === get_option( 'mediline_partners_theme_version' ) ) {
		return;
	}
	if ( false === get_option( 'mediline_partner_languages', false ) ) {
		add_option( 'mediline_partner_languages', mediline_partners_default_languages(), '', false );
	}
	mediline_partners_register_content_types();
	mediline_partners_seed_content();
	mediline_partners_sync_storefront_catalog();
	mediline_partners_rewrite_rules();
	mediline_partners_builder_bridge_secret();
	flush_rewrite_rules( false );
	update_option( 'mediline_partners_theme_version', MEDILINE_PARTNERS_VERSION, false );
}
add_action( 'admin_init', 'mediline_partners_maybe_upgrade', 30 );

/**
 * Preload the only large above-the-fold image.
 */
function mediline_partners_preload_hero() {
	if ( mediline_partners_is_landing_view() ) {
		echo '<link rel="preload" as="image" href="' . esc_url( MEDILINE_PARTNERS_URI . '/assets/images/mediline-affiliate-money-art.webp' ) . '" type="image/webp" media="(min-width: 601px)" fetchpriority="high">' . "\n";
		echo '<link rel="preload" as="image" href="' . esc_url( MEDILINE_PARTNERS_URI . '/assets/images/mediline-affiliate-money-art-768.webp' ) . '" type="image/webp" media="(max-width: 600px)" fetchpriority="high">' . "\n";
	}
}
add_action( 'wp_head', 'mediline_partners_preload_hero', 2 );

/**
 * Keep archive titles out of custom front-end views.
 */
function mediline_partners_document_title( $parts ) {
	if ( 'terms-conditions' === get_query_var( 'mediline_legal' ) ) {
		$parts['title'] = wp_strip_all_tags( str_replace( '<br>', ' ', mediline_partners_option( 'terms_heading', 'Terms & Conditions' ) ) );
	}
	return $parts;
}
add_filter( 'document_title_parts', 'mediline_partners_document_title' );
