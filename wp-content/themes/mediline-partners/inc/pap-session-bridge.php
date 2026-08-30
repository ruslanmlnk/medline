<?php
/**
 * Authenticated Post Affiliate Pro panel bridge for Store Builder.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public URL PAP should open inside its affiliate-panel iframe.
 */
function mediline_partners_pap_builder_bridge_url() {
	return home_url( '/pap-store-builder/' );
}

/**
 * Resolve the fixed PAP API server from the configured click-tracking URL.
 * User-controlled request values are never used to select the upstream host.
 */
function mediline_partners_pap_api_server_url() {
	$settings = function_exists( 'mediline_integrations_settings' )
		? mediline_integrations_settings()
		: get_option( 'mediline_integrations_settings', array() );
	$tracking = esc_url_raw( (string) ( $settings['pap_click_script_url'] ?? '' ), array( 'https' ) );
	$parts    = wp_parse_url( $tracking );
	$host     = is_array( $parts ) ? strtolower( (string) ( $parts['host'] ?? '' ) ) : '';
	$scheme   = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';

	if ( 'https' !== $scheme || ! $host || ( 'postaffiliatepro.com' !== $host && ! str_ends_with( $host, '.postaffiliatepro.com' ) ) ) {
		return '';
	}

	return 'https://' . $host . '/scripts/server.php';
}

/**
 * Read one scalar field from a PAP form response.
 */
function mediline_partners_pap_form_value( $form, array $names ) {
	foreach ( $names as $name ) {
		if ( $form->existsField( $name ) ) {
			$value = $form->getFieldValue( $name );
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}
	}
	return '';
}

/**
 * Return field names only for safe API-contract diagnostics.
 * Values are deliberately never logged or returned to the browser.
 */
function mediline_partners_pap_form_field_names( $form ) {
	$names = array();
	try {
		foreach ( $form->getFields() as $field ) {
			$name = sanitize_key( (string) $field->get( 'name' ) );
			if ( $name ) {
				$names[] = $name;
			}
		}
	} catch ( Throwable $error ) {
		return array();
	}
	return array_values( array_unique( $names ) );
}

/**
 * Validate an affiliate-panel session against PAP and return its owner.
 *
 * The low-level personal-details request intentionally uses the affiliate
 * session. PAP therefore returns only the profile bound to that session.
 */
function mediline_partners_pap_session_profile( $session_id ) {
	if ( ! is_string( $session_id ) || ! preg_match( '/^[A-Za-z0-9]{16,128}$/D', $session_id ) ) {
		return new WP_Error( 'pap_session_invalid', 'The PAP affiliate session is invalid.', array( 'status' => 401 ) );
	}

	$server_url = mediline_partners_pap_api_server_url();
	$api_file   = MEDILINE_PARTNERS_DIR . '/vendor/pap/PapApi.class.php';
	if ( ! $server_url || ! is_readable( $api_file ) ) {
		return new WP_Error( 'pap_bridge_not_configured', 'PAP session verification is not configured.', array( 'status' => 503 ) );
	}

	require_once $api_file;
	if ( ! class_exists( 'Pap_Api_Session' ) || ! class_exists( 'Gpf_Rpc_FormRequest' ) ) {
		return new WP_Error( 'pap_api_unavailable', 'The PAP API client is unavailable.', array( 'status' => 503 ) );
	}

	try {
		$session = new Pap_Api_Session( $server_url );
		$session->setSessionId( $session_id, Pap_Api_Session::AFFILIATE );
		$request = new Gpf_Rpc_FormRequest( 'Pap_Affiliates_Profile_PersonalDetailsForm', 'load', $session );
		$request->setMaxTimeout( 8 );
		// Avoid the vendor client's long automatic 429 retry loop. The browser can
		// safely retry by reopening Store Builder from the PAP panel.
		$request->send();
		$request->getMultiRequest()->send();
		$form = $request->getForm();
		if ( $form->isError() ) {
			return new WP_Error( 'pap_session_rejected', 'The PAP affiliate session is expired or invalid.', array( 'status' => 401 ) );
		}

		$profile = array(
			'affiliate_id' => sanitize_text_field( mediline_partners_pap_form_value( $form, array( 'userid', 'Id', 'id' ) ) ),
			'refid'        => sanitize_text_field( mediline_partners_pap_form_value( $form, array( 'refid' ) ) ),
			'username'     => sanitize_text_field( mediline_partners_pap_form_value( $form, array( 'username' ) ) ),
			'email'        => sanitize_email( mediline_partners_pap_form_value( $form, array( 'notificationemail', 'username' ) ) ),
			'status'       => strtoupper( sanitize_key( mediline_partners_pap_form_value( $form, array( 'rstatus', 'status' ) ) ) ),
		);
		if ( ! $profile['affiliate_id'] ) {
			return new WP_Error(
				'pap_profile_incomplete',
				'PAP returned an incomplete affiliate profile.',
				array(
					'status'           => 502,
					'missing'          => array( 'affiliate_id' ),
					'available_fields' => mediline_partners_pap_form_field_names( $form ),
				)
			);
		}
		if ( $profile['status'] && 'A' !== $profile['status'] ) {
			return new WP_Error( 'pap_affiliate_inactive', 'The PAP affiliate account is not active.', array( 'status' => 403 ) );
		}
		return $profile;
	} catch ( Throwable $error ) {
		if ( false !== stripos( $error->getMessage(), 'session_closed' ) ) {
			return new WP_Error( 'pap_session_rejected', 'The PAP affiliate session is expired or invalid.', array( 'status' => 401 ) );
		}
		return new WP_Error( 'pap_api_unavailable', 'PAP session verification is temporarily unavailable.', array( 'status' => 502 ) );
	}
}

/**
 * Resolve the authoritative affiliate record through read-only PAP API v3.
 */
function mediline_partners_pap_v3_affiliate( $affiliate_id ) {
	if ( ! class_exists( 'Mediline_Integrations_Pap_V3' ) || ! Mediline_Integrations_Pap_V3::configured() ) {
		return new WP_Error( 'pap_v3_not_configured', 'PAP API v3 identity verification is not configured.', array( 'status' => 503 ) );
	}
	$cache_key = 'mp_pap_v3_' . substr( mediline_partners_builder_hash( (string) $affiliate_id ), 0, 36 );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && ! empty( $cached['userid'] ) && ! empty( $cached['refid'] ) ) {
		return $cached;
	}
	$client = new Mediline_Integrations_Pap_V3( mediline_integrations_settings(), mediline_integrations_secret( 'pap_api_v3_token' ) );
	$result = $client->get_affiliate( $affiliate_id );
	if ( ! is_wp_error( $result ) ) {
		// PAP permits only one grid request per second for an API user. A short
		// success cache keeps iframe retries within that limit without extending
		// authorization materially after an account status change.
		set_transient( $cache_key, $result, MINUTE_IN_SECONDS );
	}
	return $result;
}

/**
 * Small per-IP throttle protecting the upstream hosted PAP API.
 */
function mediline_partners_pap_bridge_rate_limit() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$key = 'mp_pap_bridge_' . substr( mediline_partners_builder_hash( $ip ), 0, 36 );
	$hits = (int) get_transient( $key );
	if ( $hits >= 10 ) {
		return new WP_Error( 'pap_bridge_rate_limited', 'Too many Store Builder sign-in attempts.', array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
	return true;
}

/**
 * Exchange a verified PAP affiliate session for a one-time Builder URL.
 *
 * Optional loaders keep the security decision unit-testable without live PAP.
 * Production verifies the panel session with API v1 and resolves refid with
 * read-only API v3. No browser-supplied affiliate identity is trusted.
 */
function mediline_partners_builder_exchange_pap_session( $session_id, $profile_loader = null, $v3_loader = null ) {
	$session_id = is_scalar( $session_id ) ? trim( (string) $session_id ) : '';
	if ( ! preg_match( '/^[A-Za-z0-9]{16,128}$/D', $session_id ) ) {
		return new WP_Error( 'pap_bridge_request_invalid', 'The Store Builder sign-in request is invalid.', array( 'status' => 400 ) );
	}

	$profile_loader = is_callable( $profile_loader ) ? $profile_loader : 'mediline_partners_pap_session_profile';
	$profile        = call_user_func( $profile_loader, $session_id );
	if ( is_wp_error( $profile ) ) {
		return $profile;
	}
	if ( ! is_array( $profile ) || empty( $profile['affiliate_id'] ) ) {
		return new WP_Error( 'pap_profile_incomplete', 'PAP returned an incomplete affiliate profile.', array( 'status' => 502 ) );
	}
	if ( ! empty( $profile['status'] ) && 'A' !== strtoupper( (string) $profile['status'] ) ) {
		return new WP_Error( 'pap_affiliate_inactive', 'The PAP affiliate account is not active.', array( 'status' => 403 ) );
	}

	$v3_loader = is_callable( $v3_loader ) ? $v3_loader : 'mediline_partners_pap_v3_affiliate';
	$affiliate = call_user_func( $v3_loader, (string) $profile['affiliate_id'] );
	if ( is_wp_error( $affiliate ) ) {
		return $affiliate;
	}
	if ( ! is_array( $affiliate ) || empty( $affiliate['userid'] ) || empty( $affiliate['refid'] ) ) {
		return new WP_Error( 'pap_v3_profile_incomplete', 'PAP API v3 returned an incomplete affiliate.', array( 'status' => 502 ) );
	}
	if ( ! hash_equals( (string) $profile['affiliate_id'], (string) $affiliate['userid'] ) ) {
		return new WP_Error( 'pap_v3_identity_mismatch', 'PAP API v3 returned a different affiliate identity.', array( 'status' => 403 ) );
	}
	if ( ! empty( $affiliate['status'] ) && 'A' !== strtoupper( (string) $affiliate['status'] ) ) {
		return new WP_Error( 'pap_affiliate_inactive', 'The PAP affiliate account is not active.', array( 'status' => 403 ) );
	}
	$session_username = strtolower( trim( (string) ( $profile['username'] ?? '' ) ) );
	$v3_username      = strtolower( trim( (string) ( $affiliate['username'] ?? '' ) ) );
	if ( $session_username && $v3_username && ! hash_equals( $session_username, $v3_username ) ) {
		return new WP_Error( 'pap_v3_username_mismatch', 'PAP session and API v3 usernames do not match.', array( 'status' => 403 ) );
	}
	$profile['refid'] = sanitize_text_field( (string) $affiliate['refid'] );

	$token    = mediline_partners_builder_create_bootstrap( $profile );
	$response = new WP_REST_Response(
		array(
			'iframe_url' => add_query_arg( 'mbt', rawurlencode( $token ), mediline_partners_builder_url() ),
			'expires_in' => 60,
		),
		200
	);
	$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
	$response->header( 'Referrer-Policy', 'no-referrer' );
	return $response;
}

function mediline_partners_builder_rest_pap_session( WP_REST_Request $request ) {
	$limited = mediline_partners_pap_bridge_rate_limit();
	if ( is_wp_error( $limited ) ) {
		return $limited;
	}

	return mediline_partners_builder_exchange_pap_session(
		$request->get_param( 'session' )
	);
}

function mediline_partners_pap_bridge_register_rest_route() {
	register_rest_route(
		'mediline/v1',
		'/builder/pap-session',
		array(
			'methods'             => 'POST',
			'callback'            => 'mediline_partners_builder_rest_pap_session',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'mediline_partners_pap_bridge_register_rest_route' );
