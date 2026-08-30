<?php
/** Dependency-free regression tests for the PAP Store Builder bridge. */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'MEDILINE_PARTNERS_DIR', dirname( __DIR__ ) );

$GLOBALS['bridge_settings'] = array(
	'pap_click_script_url' => 'https://mediline.postaffiliatepro.com/scripts/xzqfnqhkjlk',
);
$GLOBALS['bridge_transients'] = array();
$GLOBALS['bridge_options'] = array();

class WP_Error {
	public function __construct( private string $code, private string $message = '', private $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Response {
	private array $headers = array();
	public function __construct( private $data = null, private int $status = 200 ) {}
	public function header( $name, $value ): void { $this->headers[ strtolower( (string) $name ) ] = (string) $value; }
	public function get_data() { return $this->data; }
	public function get_status(): int { return $this->status; }
	public function get_headers(): array { return $this->headers; }
}

class WP_REST_Request {}

function add_action() {}
function register_rest_route() {}
function __return_true(): bool { return true; }
function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function mediline_integrations_settings(): array { return $GLOBALS['bridge_settings']; }
function get_option( $name, $fallback = array() ) { return array_key_exists( $name, $GLOBALS['bridge_options'] ) ? $GLOBALS['bridge_options'][ $name ] : $fallback; }
function add_option( $name, $value, $deprecated = '', $autoload = true ): bool {
	if ( array_key_exists( $name, $GLOBALS['bridge_options'] ) ) return false;
	$GLOBALS['bridge_options'][ $name ] = $value;
	return true;
}
function esc_url_raw( $url, $protocols = null ): string { return (string) $url; }
function wp_parse_url( $url ) { return parse_url( (string) $url ); }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ): string { return (string) filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) ); }
function home_url( $path = '' ): string { return 'https://partners.example' . (string) $path; }
function get_transient( $key ) { return $GLOBALS['bridge_transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ): bool { $GLOBALS['bridge_transients'][ $key ] = $value; return true; }
function mediline_partners_builder_hash( $value ): string { return hash( 'sha256', 'test:' . (string) $value ); }
function mediline_partners_builder_create_bootstrap( $profile ): string { return 'one-time-token'; }
function mediline_partners_builder_url(): string { return 'https://partners.example/store-builder/'; }
function add_query_arg( $key, $value, $url ): string { return (string) $url . '?' . rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value ); }

require dirname( __DIR__ ) . '/inc/pap-session-bridge.php';

$assertions = 0;
$failures   = 0;

function bridge_test( string $name, callable $test ): void {
	global $failures;
	try {
		$test();
		echo "[PASS] {$name}\n";
	} catch ( Throwable $error ) {
		$failures++;
		echo "[FAIL] {$name}\n       {$error->getMessage()}\n";
	}
}

function bridge_same( $expected, $actual, string $message = '' ): void {
	global $assertions;
	$assertions++;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message ?: 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

bridge_test(
	'API server is pinned to the configured hosted PAP account',
	function (): void {
		bridge_same( 'https://mediline.postaffiliatepro.com/scripts/server.php', mediline_partners_pap_api_server_url() );
		$GLOBALS['bridge_settings']['pap_click_script_url'] = 'https://127.0.0.1/scripts/tracker';
		bridge_same( '', mediline_partners_pap_api_server_url() );
		$GLOBALS['bridge_settings']['pap_click_script_url'] = 'https://mediline.postaffiliatepro.com/scripts/xzqfnqhkjlk';
	}
);

bridge_test(
	'invalid input is rejected before the PAP loader runs',
	function (): void {
		$called = false;
		$result = mediline_partners_builder_exchange_pap_session(
			'short',
			function () use ( &$called ) { $called = true; return array(); }
		);
		bridge_same( false, $called );
		bridge_same( 'pap_bridge_request_invalid', $result->get_error_code() );
	}
);

bridge_test(
	'API v3 identity mismatch and inactive affiliates are rejected',
	function (): void {
		$session = '1234567890abcdef1234567890abcdef';
		$mismatch = mediline_partners_builder_exchange_pap_session(
			$session,
			fn() => array( 'affiliate_id' => 'abc12345', 'username' => 'owner@example.test', 'email' => 'owner@example.test', 'status' => 'A' ),
			fn() => array( 'userid' => 'attacker1', 'refid' => 'attacker', 'username' => 'attacker@example.test', 'status' => 'A' )
		);
		bridge_same( 'pap_v3_identity_mismatch', $mismatch->get_error_code() );
		$inactive = mediline_partners_builder_exchange_pap_session(
			$session,
			fn() => array( 'affiliate_id' => 'abc12345', 'username' => 'owner@example.test', 'email' => 'owner@example.test', 'status' => 'D' ),
			fn() => array( 'userid' => 'abc12345', 'refid' => 'owner', 'username' => 'owner@example.test', 'status' => 'A' )
		);
		bridge_same( 'pap_affiliate_inactive', $inactive->get_error_code() );
	}
);

bridge_test(
	'verified PAP owner receives a no-store one-time Builder URL',
	function (): void {
		$result = mediline_partners_builder_exchange_pap_session(
			'1234567890abcdef1234567890abcdef',
			fn() => array( 'affiliate_id' => 'abc12345', 'username' => 'owner@example.test', 'email' => 'owner@example.test', 'status' => 'A' ),
			fn() => array( 'userid' => 'abc12345', 'refid' => 'owner', 'username' => 'owner@example.test', 'status' => 'A' )
		);
		bridge_same( 200, $result->get_status() );
		bridge_same( 'https://partners.example/store-builder/?mbt=one-time-token', $result->get_data()['iframe_url'] );
		bridge_same( 'no-store, private, max-age=0', $result->get_headers()['cache-control'] );
	}
);

bridge_test(
	'API v3 username and status must match the session-owned affiliate',
	function (): void {
		$session = 'abcdef1234567890abcdef1234567890';
		$profile_loader = fn() => array(
			'affiliate_id' => 'affiliate-42',
			'username'     => 'owner@example.test',
			'email'        => 'owner@example.test',
			'status'       => 'A',
		);
		$wrong_username = mediline_partners_builder_exchange_pap_session(
			$session,
			$profile_loader,
			fn() => array( 'userid' => 'affiliate-42', 'refid' => 'owner-ref', 'username' => 'attacker@example.test', 'status' => 'A' )
		);
		bridge_same( 'pap_v3_username_mismatch', $wrong_username->get_error_code() );
		$inactive = mediline_partners_builder_exchange_pap_session(
			$session,
			$profile_loader,
			fn() => array( 'userid' => 'affiliate-42', 'refid' => 'owner-ref', 'username' => 'owner@example.test', 'status' => 'D' )
		);
		bridge_same( 'pap_affiliate_inactive', $inactive->get_error_code() );
	}
);

echo "\nAssertions: {$assertions}; failures: {$failures}\n";
exit( $failures ? 1 : 0 );
