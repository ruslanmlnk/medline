<?php
/**
 * Minimal WordPress/WooCommerce test doubles for dependency-free regression tests.
 *
 * This file intentionally implements only the contracts exercised by this test
 * suite. It is not a WordPress replacement.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 5 ) . DIRECTORY_SEPARATOR );
}
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
defined( 'ARRAY_A' ) || define( 'ARRAY_A', 'ARRAY_A' );

final class Test_Suite {
	private static int $assertions = 0;
	private static int $failures   = 0;

	public static function run( string $name, callable $test ): void {
		try {
			$test();
			echo "[PASS] {$name}\n";
		} catch ( Throwable $error ) {
			self::$failures++;
			echo "[FAIL] {$name}\n       {$error->getMessage()}\n";
		}
	}

	public static function assert_true( $actual, string $message = 'Expected a truthy value.' ): void {
		self::$assertions++;
		if ( ! $actual ) {
			throw new RuntimeException( $message );
		}
	}

	public static function assert_false( $actual, string $message = 'Expected a falsy value.' ): void {
		self::$assertions++;
		if ( $actual ) {
			throw new RuntimeException( $message );
		}
	}

	public static function assert_same( $expected, $actual, string $message = '' ): void {
		self::$assertions++;
		if ( $expected !== $actual ) {
			$details = 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . '.';
			throw new RuntimeException( $message ? $message . ' ' . $details : $details );
		}
	}

	public static function assert_has_key( string $key, array $actual, string $message = '' ): void {
		self::$assertions++;
		if ( ! array_key_exists( $key, $actual ) ) {
			throw new RuntimeException( $message ?: "Expected array key {$key}." );
		}
	}

	public static function assert_not_has_key( string $key, array $actual, string $message = '' ): void {
		self::$assertions++;
		if ( array_key_exists( $key, $actual ) ) {
			throw new RuntimeException( $message ?: "Unexpected array key {$key}." );
		}
	}

	public static function assert_contains( string $needle, string $haystack, string $message = '' ): void {
		self::$assertions++;
		if ( false === strpos( $haystack, $needle ) ) {
			throw new RuntimeException( $message ?: "Expected to find {$needle}." );
		}
	}

	public static function assert_not_contains( string $needle, string $haystack, string $message = '' ): void {
		self::$assertions++;
		if ( false !== strpos( $haystack, $needle ) ) {
			throw new RuntimeException( $message ?: "Unexpectedly found {$needle}." );
		}
	}

	public static function finish(): void {
		echo sprintf( "\nAssertions: %d; failures: %d\n", self::$assertions, self::$failures );
		exit( self::$failures ? 1 : 0 );
	}
}

class WP_Error {
	private string $code;
	private string $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
		$this->data    = $data;
	}

	public function get_error_code(): string {
		return $this->code;
	}

	public function get_error_message(): string {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Request {
	private array $headers;
	private array $params;
	private string $body;

	public function __construct( array $params = array(), array $headers = array(), ?string $body = null ) {
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->body    = null === $body ? (string) json_encode( $params ) : $body;
	}

	public function get_body(): string {
		return $this->body;
	}

	public function get_json_params(): array {
		return $this->params;
	}

	public function get_header( $name ): string {
		return (string) ( $this->headers[ strtolower( (string) $name ) ] ?? '' );
	}
}

class WP_REST_Response {
	private $data;
	private int $status;

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = (int) $status;
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status(): int {
		return $this->status;
	}
}

class WC_Order {
	private int $id;
	private array $meta;
	private array $billing;
	private float $total;
	private string $currency;
	public int $save_count = 0;

	public function __construct( int $id, array $meta = array(), array $billing = array(), float $total = 0.0, string $currency = 'EUR' ) {
		$this->id       = $id;
		$this->meta     = $meta;
		$this->billing  = array_merge(
			array( 'first_name' => 'Test', 'last_name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '+420 123 456' ),
			$billing
		);
		$this->total    = $total;
		$this->currency = $currency;
	}

	public function get_id(): int { return $this->id; }
	public function get_meta( $key, $single = true ) { return $this->meta[ (string) $key ] ?? ''; }
	public function update_meta_data( $key, $value ): void { $this->meta[ (string) $key ] = $value; }
	public function get_billing_first_name(): string { return (string) $this->billing['first_name']; }
	public function get_billing_last_name(): string { return (string) $this->billing['last_name']; }
	public function get_billing_email(): string { return (string) $this->billing['email']; }
	public function get_billing_phone(): string { return (string) $this->billing['phone']; }
	public function get_total(): float { return $this->total; }
	public function get_currency(): string { return $this->currency; }
	public function save(): void { $this->save_count++; }
	public function all_meta(): array { return $this->meta; }
}

$GLOBALS['test_http_requests']  = array();
$GLOBALS['test_http_responses'] = array();
$GLOBALS['test_options']        = array();
$GLOBALS['test_transients']     = array();
$GLOBALS['test_orders']         = array();
$GLOBALS['test_async_actions']  = array();

function test_http_reset(): void {
	$GLOBALS['test_http_requests']  = array();
	$GLOBALS['test_http_responses'] = array();
}

function test_http_queue_json( array $data, int $status = 200, array $headers = array() ): void {
	$GLOBALS['test_http_responses'][] = array(
		'response' => array( 'code' => $status ),
		'headers'  => $headers,
		'body'     => (string) json_encode( $data, JSON_UNESCAPED_SLASHES ),
	);
}

function test_http_queue_text( string $body, int $status = 200, array $headers = array() ): void {
	$GLOBALS['test_http_responses'][] = array(
		'response' => array( 'code' => $status ),
		'headers'  => $headers,
		'body'     => $body,
	);
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function __( $text, $domain = null ): string { return (string) $text; }
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_key( $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_text_field( $text ): string {
	$text = is_scalar( $text ) ? (string) $text : '';
	$text = strip_tags( $text );
	return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', $text ) );
}
function sanitize_email( $email ): string { return (string) filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function is_email( $email ): bool { return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $value ) {
	if ( is_array( $value ) ) {
		return array_map( 'wp_unslash', $value );
	}
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, (int) $flags, (int) $depth ); }
function wp_salt( $scheme = 'auth' ): string { return 'test-only-static-salt:' . (string) $scheme . ':ed2ff8232e5d'; }
function determine_locale(): string { return 'en_US'; }
function get_locale(): string { return determine_locale(); }
function get_current_blog_id(): int { return 1; }
function rest_sanitize_boolean( $value ): bool { return filter_var( $value, FILTER_VALIDATE_BOOLEAN ); }
function wp_parse_url( $url, $component = -1 ) { return -1 === $component ? parse_url( (string) $url ) : parse_url( (string) $url, $component ); }
function esc_url_raw( $url, $protocols = null ): string {
	$url    = trim( (string) $url );
	$parts  = parse_url( $url );
	$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
	$allow  = is_array( $protocols ) ? $protocols : array( 'http', 'https' );
	return $scheme && in_array( $scheme, $allow, true ) && ! empty( $parts['host'] ) ? $url : '';
}
function add_query_arg( array $args, $url ): string {
	$parts = parse_url( (string) $url );
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	$query = array_merge( $query, $args );
	$base  = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' );
	if ( isset( $parts['port'] ) ) {
		$base .= ':' . $parts['port'];
	}
	$base .= $parts['path'] ?? '/';
	return $base . ( $query ? '?' . http_build_query( $query ) : '' );
}
function untrailingslashit( $value ): string { return rtrim( (string) $value, "/\\" ); }
function apply_filters( $hook, $value ) { return $value; }
function do_action( $hook, ...$args ): void {}
function add_action( ...$args ): void {}
function add_filter( ...$args ): void {}
function register_rest_route( ...$args ): void {}
function wp_verify_nonce( $nonce, $action ): bool { return 'valid-test-nonce' === $nonce && 'mediline_public_lead' === $action; }
function home_url( $path = '/' ): string { return 'https://mediline.test' . ( '/' === $path ? '/' : $path ); }
function get_option( $key, $default = false ) { return $GLOBALS['test_options'][ (string) $key ] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( (string) $key, $GLOBALS['test_options'] ) ) {
		return false;
	}
	$GLOBALS['test_options'][ (string) $key ] = $value;
	return true;
}
function update_option( $key, $value, $autoload = null ): bool { $GLOBALS['test_options'][ (string) $key ] = $value; return true; }
function delete_option( $key ): bool { unset( $GLOBALS['test_options'][ (string) $key ] ); return true; }
function get_transient( $key ) { return $GLOBALS['test_transients'][ (string) $key ] ?? false; }
function set_transient( $key, $value, $expiration ): bool { $GLOBALS['test_transients'][ (string) $key ] = $value; return true; }
function wp_generate_uuid4(): string {
	static $sequence = 0;
	$sequence++;
	return sprintf( '00000000-0000-4000-8000-%012d', $sequence );
}
function wp_rand( $min = 0, $max = 0 ): int { return (int) $min; }
function wp_next_scheduled( $hook ) { return false; }
function wp_schedule_event( ...$args ): bool { return true; }
function wp_schedule_single_event( ...$args ): bool { return true; }
function wp_unschedule_event( ...$args ): bool { return true; }
function as_enqueue_async_action( $hook, $args = array(), $group = '', $unique = false ): int {
	$GLOBALS['test_async_actions'][] = compact( 'hook', 'args', 'group', 'unique' );
	return count( $GLOBALS['test_async_actions'] );
}
function wc_get_order( $id ) { return $GLOBALS['test_orders'][ (int) $id ] ?? false; }

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['test_http_requests'][] = array( 'transport' => 'request', 'url' => (string) $url, 'args' => $args );
	return array_shift( $GLOBALS['test_http_responses'] ) ?: new WP_Error( 'test_http_empty', 'No mocked HTTP response was queued.' );
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['test_http_requests'][] = array( 'transport' => 'post', 'url' => (string) $url, 'args' => $args );
	return array_shift( $GLOBALS['test_http_responses'] ) ?: new WP_Error( 'test_http_empty', 'No mocked HTTP response was queued.' );
}
function wp_remote_retrieve_response_code( $response ): int { return (int) ( $response['response']['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ): string { return (string) ( $response['body'] ?? '' ); }
function wp_remote_retrieve_header( $response, $header ): string {
	$headers = array_change_key_case( (array) ( $response['headers'] ?? array() ), CASE_LOWER );
	return (string) ( $headers[ strtolower( (string) $header ) ] ?? '' );
}

