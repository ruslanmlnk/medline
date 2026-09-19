<?php
/**
 * Browser attribution, Post Affiliate Pro click tracking and WooCommerce order metadata.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mediline_Integrations_Attribution {
	const COOKIE_NAME = 'mediline_attribution';
	const STORAGE_KEY = 'mediline_attribution_v1';
	const OPTION_NAME = 'mediline_integrations_settings';
	const SCRIPT_HANDLE = 'mediline-integrations-attribution';
	const PAP_SCRIPT_HANDLE = 'mediline-integrations-pap-click';

	/**
	 * Register frontend and checkout hooks.
	 */
	public static function init() {
		add_filter( 'script_loader_tag', array( __CLASS__, 'pap_script_tag' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_woocommerce_order' ), 50, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'attach_store_api_order' ), 50, 2 );
	}

	public static function pap_script_tag( $tag, $handle ) {
		if ( self::PAP_SCRIPT_HANDLE !== $handle ) { return $tag; }
		return preg_replace( '/\bid=([\x22\x27])[^\x22\x27]*\1/', 'id="pap_x2s6df8d"', $tag, 1 );
	}

	/**
	 * Enqueue the first-party module and, when configured, the PAP click tracker.
	 */
	public static function enqueue_assets() {
		if ( is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) ) {
			return;
		}

		$settings = self::settings();
		$pap      = isset( $settings['pap'] ) && is_array( $settings['pap'] ) ? $settings['pap'] : array();
		$pap_url  = self::validated_pap_script_url(
			self::first_setting( $settings, $pap, array( 'pap_click_script_url', 'click_script_url', 'tracking_script_url' ) )
		);
		$account  = self::sanitize_account_id(
			self::first_setting( $settings, $pap, array( 'pap_account_id', 'account_id' ), 'default1' )
		);
		$enabled  = self::pap_enabled( $settings, $pap, $pap_url, $account );

		$dependencies = array();
		if ( $enabled ) {
			wp_enqueue_script( self::PAP_SCRIPT_HANDLE, $pap_url, array(), null, true );
			wp_script_add_data( self::PAP_SCRIPT_HANDLE, 'strategy', 'defer' );
			$dependencies[] = self::PAP_SCRIPT_HANDLE;
		}

		$plugin_file = dirname( __DIR__ ) . '/mediline-integrations.php';
		$script_path = dirname( __DIR__ ) . '/assets/js/attribution.js';
		$script_url  = plugin_dir_url( $plugin_file ) . 'assets/js/attribution.js';
		$version     = defined( 'MEDILINE_INTEGRATIONS_VERSION' ) ? MEDILINE_INTEGRATIONS_VERSION : ( file_exists( $script_path ) ? (string) filemtime( $script_path ) : '1.0.0' );

		wp_enqueue_script( self::SCRIPT_HANDLE, $script_url, $dependencies, $version, true );
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );

		$ttl_days = absint( isset( $settings['attribution_ttl_days'] ) ? $settings['attribution_ttl_days'] : 90 );
		$ttl_days = min( 365, max( 1, $ttl_days ) );
		$selector = isset( $settings['attribution_form_selector'] ) ? (string) $settings['attribution_form_selector'] : 'form[data-mediline-crm-form]';
		$selector = self::sanitize_selector( apply_filters( 'mediline_integrations_attribution_form_selector', $selector ) );

		$lead_endpoint = apply_filters(
			'mediline_integrations_lead_endpoint',
			isset( $settings['lead_endpoint'] ) ? (string) $settings['lead_endpoint'] : rest_url( 'mediline-integrations/v1/lead' )
		);
		$lead_nonce = apply_filters( 'mediline_integrations_lead_nonce', wp_create_nonce( 'mediline_public_lead' ) );

		$config = array(
			'cookieName'       => self::COOKIE_NAME,
			'storageKey'       => self::STORAGE_KEY,
			'ttlSeconds'       => $ttl_days * DAY_IN_SECONDS,
			'formSelector'     => $selector,
			'requiredMarker'   => 'data-mediline-crm-form',
			'language'         => self::current_language(),
			'submitWaitMs'     => min( 900, max( 100, absint( isset( $settings['attribution_submit_wait_ms'] ) ? $settings['attribution_submit_wait_ms'] : 900 ) ) ),
			'leadEndpoint'     => esc_url_raw( (string) $lead_endpoint ),
			'leadNonce'        => self::bounded_text( $lead_nonce, 160 ),
			'leadNonceHeader'  => 'X-Mediline-Lead-Nonce',
			'pap'              => array(
				'enabled'   => $enabled,
				'scriptUrl' => $enabled ? $pap_url : '',
				'accountId' => $enabled ? $account : '',
				'waitMs'    => min( 700, max( 50, absint( isset( $settings['pap_submit_wait_ms'] ) ? $settings['pap_submit_wait_ms'] : 450 ) ) ),
			),
		);

		$config = apply_filters( 'mediline_integrations_attribution_config', $config, $settings );
		wp_localize_script( self::SCRIPT_HANDLE, 'medilineIntegrationsAttribution', $config );
	}

	/**
	 * Get the plugin option without assuming that the admin/settings class exists.
	 */
	private static function settings() {
		if ( function_exists( 'mediline_integrations_settings' ) ) {
			$settings = mediline_integrations_settings();
			return is_array( $settings ) ? $settings : array();
		}
		$settings = get_option( self::OPTION_NAME, array() );
		return is_array( $settings ) ? $settings : array();
	}

	private static function first_setting( $settings, $nested, $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $settings ) && is_scalar( $settings[ $key ] ) ) {
				return $settings[ $key ];
			}
			if ( array_key_exists( $key, $nested ) && is_scalar( $nested[ $key ] ) ) {
				return $nested[ $key ];
			}
		}
		return $default;
	}

	private static function pap_enabled( $settings, $pap, $url, $account ) {
		foreach ( array( 'pap_click_enabled', 'pap_enabled' ) as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				return rest_sanitize_boolean( $settings[ $key ] ) && $url && $account;
			}
			if ( array_key_exists( $key, $pap ) ) {
				return rest_sanitize_boolean( $pap[ $key ] ) && $url && $account;
			}
		}
		return (bool) ( $url && $account );
	}

	private static function sanitize_account_id( $account ) {
		$account = is_scalar( $account ) ? (string) $account : '';
		$account = preg_replace( '/[^A-Za-z0-9_-]/', '', $account );
		return substr( $account, 0, 64 );
	}

	private static function validated_pap_script_url( $url ) {
		$url = is_scalar( $url ) ? esc_url_raw( (string) $url, array( 'https' ) ) : '';
		if ( ! $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || 'https' !== strtolower( isset( $parts['scheme'] ) ? $parts['scheme'] : '' ) || empty( $parts['host'] ) ) {
			return '';
		}
		return $url;
	}

	private static function sanitize_selector( $selector ) {
		$selector = is_scalar( $selector ) ? (string) $selector : '';
		$selector = preg_replace( '/[\x00-\x1F\x7F]/', '', $selector );
		$selector = substr( trim( $selector ), 0, 240 );
		return $selector ? $selector : 'form[data-mediline-crm-form]';
	}

	/**
	 * Current UI language, preferring the theme/checkout language over the WP locale.
	 */
	public static function current_language() {
		$language = '';
		if ( function_exists( 'mediline_partners_current_language' ) ) {
			$language = mediline_partners_current_language();
		}
		if ( ! $language && function_exists( 'WC' ) && WC() && WC()->session ) {
			$language = WC()->session->get( 'mediline_language' );
		}
		if ( ! $language && isset( $_GET['lang'] ) && is_scalar( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$language = wp_unslash( $_GET['lang'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! $language ) {
			$language = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		}
		return self::sanitize_language( $language );
	}

	/**
	 * Read and validate the first-party attribution cookie.
	 */
	public static function read_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || ! is_scalar( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return array();
		}
		$raw = (string) wp_unslash( $_COOKIE[ self::COOKIE_NAME ] );
		if ( strlen( $raw ) > 8192 ) {
			return array();
		}
		// PHP commonly URL-decodes cookie values before populating $_COOKIE. Try
		// that canonical value first so a literal "%xx" inside a UTM value is not
		// decoded a second time and cannot invalidate the JSON string.
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = json_decode( rawurldecode( $raw ), true );
		}
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$expires_at = isset( $decoded['expires_at'] ) ? absint( $decoded['expires_at'] ) : 0;
		if ( $expires_at && $expires_at < time() ) {
			return array();
		}
		return self::sanitize_state( $decoded );
	}

	/**
	 * Build a canonical attribution object from cookie data and allowlisted input.
	 * No arbitrary form values are inspected or copied.
	 */
	public static function request_attribution( $source = null ) {
		$state = self::read_cookie();
		if ( null === $source ) {
			$source = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		if ( ! is_array( $source ) ) {
			$source = array();
		}
		$source = wp_unslash( $source );

		$nested = array();
		if ( isset( $source['attribution'] ) ) {
			$nested = $source['attribution'];
			if ( is_string( $nested ) && strlen( $nested ) <= 8192 ) {
				$nested = json_decode( $nested, true );
			}
			if ( ! is_array( $nested ) ) {
				$nested = array();
			}
		}

		$submitted_state = self::sanitize_state( $nested );
		if ( ! empty( $submitted_state['first'] ) ) {
			$state['first'] = $submitted_state['first'];
		}
		if ( ! empty( $submitted_state['current'] ) ) {
			$state['current'] = $submitted_state['current'];
		}

		$current = isset( $state['current'] ) && is_array( $state['current'] ) ? $state['current'] : array();
		foreach ( self::touch_keys() as $key => $limit ) {
			if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ) {
				$current[ $key ] = self::sanitize_touch_value( $key, $source[ $key ], $limit );
			} elseif ( isset( $nested[ $key ] ) && is_scalar( $nested[ $key ] ) ) {
				$current[ $key ] = self::sanitize_touch_value( $key, $nested[ $key ], $limit );
			}
		}
		$state['current'] = array_filter( $current, static function ( $value ) { return '' !== $value && null !== $value; } );

		foreach ( array( 'pap_visitor_id', 'pap_affiliate_id', 'submission_id', 'form_type' ) as $key ) {
			$value = '';
			if ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ) {
				$value = $source[ $key ];
			} elseif ( isset( $nested[ $key ] ) && is_scalar( $nested[ $key ] ) ) {
				$value = $nested[ $key ];
			} elseif ( isset( $state[ $key ] ) ) {
				$value = $state[ $key ];
			}
			if ( 'pap_visitor_id' === $key ) {
				$state[ $key ] = self::normalize_visitor_id( $value );
			} elseif ( 'pap_affiliate_id' === $key ) {
				$state[ $key ] = self::sanitize_affiliate_id( $value );
			} elseif ( 'submission_id' === $key ) {
				$state[ $key ] = self::sanitize_submission_id( $value );
			} else {
				$state[ $key ] = substr( sanitize_key( (string) $value ), 0, 64 );
			}
		}

		if ( empty( $state['current']['language'] ) ) {
			$state['current']['language'] = self::current_language();
		}
		$state['language'] = $state['current']['language'];
		foreach ( array_keys( self::touch_keys() ) as $key ) {
			if ( isset( $state['current'][ $key ] ) ) {
				$state[ $key ] = $state['current'][ $key ];
			}
		}
		return apply_filters( 'mediline_integrations_request_attribution', $state, $source );
	}

	/**
	 * Attach sanitized attribution metadata to classic WooCommerce checkout orders.
	 */
	public static function attach_woocommerce_order( $order, $data = array() ) {
		self::attach_to_order( $order, is_array( $data ) ? array_merge( $_POST, $data ) : $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Attach attribution for Checkout Block / Store API orders.
	 */
	public static function attach_store_api_order( $order, $request ) {
		$data = array();
		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$params = $request->get_json_params();
			if ( is_array( $params ) ) {
				$data = $params;
				if ( isset( $params['extensions']['mediline-integrations'] ) && is_array( $params['extensions']['mediline-integrations'] ) ) {
					$data = array_merge( $data, $params['extensions']['mediline-integrations'] );
				}
			}
		}
		self::attach_to_order( $order, $data );
	}

	/**
	 * Save a compact, allowlisted set of order meta. Existing authoritative store
	 * attribution is preserved when the catalog bridge has already supplied it.
	 */
	public static function attach_to_order( $order, $source = array() ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		$attribution = self::request_attribution( $source );
		$attribution = apply_filters( 'mediline_integrations_order_attribution', $attribution, $order );
		if ( ! is_array( $attribution ) ) {
			return;
		}

		$meta = array(
			'_mediline_pap_visitor_id'      => isset( $attribution['pap_visitor_id'] ) ? $attribution['pap_visitor_id'] : '',
			'_mediline_pap_affiliate_id'    => isset( $attribution['pap_affiliate_id'] ) ? $attribution['pap_affiliate_id'] : '',
			'_mediline_attribution_language' => isset( $attribution['language'] ) ? $attribution['language'] : '',
			'_mediline_submission_id'        => isset( $attribution['submission_id'] ) ? $attribution['submission_id'] : '',
		);
		$current = isset( $attribution['current'] ) && is_array( $attribution['current'] ) ? $attribution['current'] : array();
		$first   = isset( $attribution['first'] ) && is_array( $attribution['first'] ) ? $attribution['first'] : array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'landing_url' ) as $key ) {
			$meta[ '_mediline_' . $key ] = isset( $current[ $key ] ) ? $current[ $key ] : '';
			$meta[ '_mediline_first_' . $key ] = isset( $first[ $key ] ) ? $first[ $key ] : '';
		}
		foreach ( $meta as $key => $value ) {
			if ( '' !== $value && null !== $value && '' === (string) $order->get_meta( $key, true ) ) {
				$order->update_meta_data( $key, $value );
			}
		}
		if ( ! empty( $attribution['language'] ) && '' === (string) $order->get_meta( '_mediline_language', true ) ) {
			$order->update_meta_data( '_mediline_language', $attribution['language'] );
		}
		do_action( 'mediline_integrations_attribution_attached_to_order', $order, $attribution );
	}

	/**
	 * PAP S2S expects the account prefix removed, leaving the final 32 characters.
	 */
	public static function normalize_visitor_id( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^A-Za-z0-9]/', '', $value );
		if ( strlen( $value ) > 32 ) {
			$value = substr( $value, -32 );
		}
		return 32 === strlen( $value ) ? $value : '';
	}

	public static function sanitize_affiliate_id( $value ) {
		$value = self::bounded_text( $value, 100 );
		return preg_replace( '/[^\p{L}\p{N}_.@-]/u', '', $value );
	}

	private static function sanitize_submission_id( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
		$value = substr( $value, 0, 64 );
		return strlen( $value ) >= 16 ? $value : '';
	}

	private static function sanitize_state( $state ) {
		if ( ! is_array( $state ) ) {
			return array();
		}
		$clean = array(
			'version'          => 1,
			'first'            => self::sanitize_touch( isset( $state['first'] ) ? $state['first'] : array() ),
			'current'          => self::sanitize_touch( isset( $state['current'] ) ? $state['current'] : array() ),
			'pap_visitor_id'   => self::normalize_visitor_id( isset( $state['pap_visitor_id'] ) ? $state['pap_visitor_id'] : '' ),
			'pap_affiliate_id' => self::sanitize_affiliate_id( isset( $state['pap_affiliate_id'] ) ? $state['pap_affiliate_id'] : '' ),
			'updated_at'       => isset( $state['updated_at'] ) ? absint( $state['updated_at'] ) : 0,
			'expires_at'       => isset( $state['expires_at'] ) ? absint( $state['expires_at'] ) : 0,
		);
		return array_filter( $clean, static function ( $value ) { return array() !== $value && '' !== $value && 0 !== $value; } );
	}

	private static function sanitize_touch( $touch ) {
		if ( ! is_array( $touch ) ) {
			return array();
		}
		$clean = array();
		foreach ( self::touch_keys() as $key => $limit ) {
			if ( isset( $touch[ $key ] ) && is_scalar( $touch[ $key ] ) ) {
				$value = self::sanitize_touch_value( $key, $touch[ $key ], $limit );
				if ( '' !== $value ) {
					$clean[ $key ] = $value;
				}
			}
		}
		if ( isset( $touch['captured_at'] ) ) {
			$clean['captured_at'] = absint( $touch['captured_at'] );
		}
		return $clean;
	}

	private static function touch_keys() {
		return array(
			'utm_source'   => 200,
			'utm_medium'   => 200,
			'utm_campaign' => 200,
			'utm_term'     => 200,
			'utm_content'  => 200,
			'gclid'        => 160,
			'fbclid'       => 160,
			'language'     => 16,
			'landing_url'  => 700,
		);
	}

	private static function sanitize_touch_value( $key, $value, $limit ) {
		if ( 'landing_url' === $key ) {
			return self::sanitize_landing_url( $value );
		}
		if ( 'language' === $key ) {
			return self::sanitize_language( $value );
		}
		return self::bounded_text( $value, $limit );
	}

	private static function sanitize_language( $value ) {
		$value = strtolower( str_replace( '_', '-', self::bounded_text( $value, 16 ) ) );
		$value = preg_replace( '/[^a-z0-9-]/', '', $value );
		return substr( trim( $value, '-' ), 0, 16 );
	}

	public static function sanitize_landing_url( $value ) {
		$url = is_scalar( $value ) ? esc_url_raw( (string) $value, array( 'http', 'https' ) ) : '';
		if ( ! $url ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$clean = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$clean .= ':' . absint( $parts['port'] );
		}
		$clean .= isset( $parts['path'] ) ? $parts['path'] : '/';
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $raw_query );
			foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'fbclid' ) as $key ) {
				if ( isset( $raw_query[ $key ] ) && is_scalar( $raw_query[ $key ] ) ) {
					$query[ $key ] = self::bounded_text( $raw_query[ $key ], 200 );
				}
			}
		}
		if ( $query ) {
			$clean = add_query_arg( $query, $clean );
		}
		return substr( $clean, 0, 700 );
	}

	private static function bounded_text( $value, $limit ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit, 'UTF-8' );
		}
		return substr( $value, 0, $limit );
	}
}
