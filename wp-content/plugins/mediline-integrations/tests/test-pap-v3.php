<?php
/** Dependency-free security and contract tests for the PAP API v3 client. */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public function __construct( private string $code, private string $message = '', private $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
function esc_url_raw( $value, $protocols = null ): string { return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : ''; }
function wp_parse_url( $value ) { return parse_url( (string) $value ); }
function absint( $value ): int { return abs( (int) $value ); }
function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
function add_query_arg( array $query, string $url ): string { return $url . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ); }
function wp_remote_get( $url, $args ) { return call_user_func( $GLOBALS['pap_v3_remote'], $url, $args ); }
function wp_remote_retrieve_response_code( $response ): int { return (int) ( $response['response']['code'] ?? 0 ); }
function wp_remote_retrieve_body( $response ): string { return (string) ( $response['body'] ?? '' ); }

require dirname( __DIR__ ) . '/includes/class-mediline-integrations-pap-v3.php';

$assertions = 0;
$failures   = 0;

function pap_v3_test( string $name, callable $test ): void {
	global $failures;
	try {
		$test();
		echo "[PASS] {$name}\n";
	} catch ( Throwable $error ) {
		$failures++;
		echo "[FAIL] {$name}\n       {$error->getMessage()}\n";
	}
}

function pap_v3_same( $expected, $actual, string $message = '' ): void {
	global $assertions;
	$assertions++;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message ?: 'Expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) );
	}
}

function pap_v3_client( string $base = 'https://mediline.postaffiliatepro.com/api/v3', string $token = 'test-secret' ): Mediline_Integrations_Pap_V3 {
	return new Mediline_Integrations_Pap_V3( array( 'pap_api_v3_base' => $base ), $token );
}

pap_v3_test(
	'API credentials are pinned to the hosted PAP HTTPS origin',
	function (): void {
		pap_v3_same( 'https://mediline.postaffiliatepro.com/api/v3', Mediline_Integrations_Pap_V3::normalize_api_base( 'https://MEDILINE.postaffiliatepro.com/api/v3/' ) );
		pap_v3_same( '', Mediline_Integrations_Pap_V3::normalize_api_base( 'http://mediline.postaffiliatepro.com/api/v3' ) );
		pap_v3_same( '', Mediline_Integrations_Pap_V3::normalize_api_base( 'https://mediline.postaffiliatepro.com.evil.test/api/v3' ) );
		pap_v3_same( '', Mediline_Integrations_Pap_V3::normalize_api_base( 'https://mediline.postaffiliatepro.com/api/v3?redirect=https://evil.test' ) );
		pap_v3_same( '', Mediline_Integrations_Pap_V3::normalize_api_base( 'https://mediline.postaffiliatepro.com:444/api/v3' ) );
	}
);

pap_v3_test(
	'lookup uses Bearer auth, no redirects, and exact userid matching',
	function (): void {
		$GLOBALS['pap_v3_remote'] = function ( $url, $args ) {
			pap_v3_same( 'https://mediline.postaffiliatepro.com/api/v3/affiliates?q=id%3Aabc12345&fields=referral_id%2Cusername%2Cstatus', $url );
			pap_v3_same( 'Bearer test-secret', $args['headers']['Authorization'] );
			pap_v3_same( 0, $args['redirection'] );
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'data' => array(
							'items' => array(
								array( 'id' => 'other', 'refid' => 'wrong', 'username' => 'wrong@example.test', 'status' => 'approved' ),
								array( 'id' => 'abc12345', 'refid' => 'owner-ref', 'username' => 'owner@example.test', 'status' => 'approved' ),
							),
						),
					)
				),
			);
		};
		$result = pap_v3_client()->get_affiliate( 'abc12345' );
		pap_v3_same( false, is_wp_error( $result ) );
		pap_v3_same( 'abc12345', $result['userid'] );
		pap_v3_same( 'owner-ref', $result['refid'] );
		pap_v3_same( 'A', $result['status'] );
	}
);

pap_v3_test(
	'incomplete and mismatched PAP records fail closed',
	function (): void {
		$GLOBALS['pap_v3_remote'] = fn() => array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"data":{"items":[{"id":"attacker","refid":"wrong"},{"id":"abc12345","refid":""}]}}',
		);
		$result = pap_v3_client()->get_affiliate( 'abc12345' );
		pap_v3_same( true, is_wp_error( $result ) );
		pap_v3_same( 'mediline_pap_v3_not_found', $result->get_error_code() );
	}
);

pap_v3_test(
	'invalid configuration and paths never send the token',
	function (): void {
		$calls = 0;
		$GLOBALS['pap_v3_remote'] = function () use ( &$calls ) { $calls++; return array(); };
		$result = pap_v3_client( 'https://evil.test/api/v3' )->get_affiliate( 'abc12345' );
		pap_v3_same( true, is_wp_error( $result ) );
		pap_v3_same( 'mediline_pap_v3_configuration', $result->get_error_code() );
		$path_result = pap_v3_client()->request( '/transactions' );
		pap_v3_same( 'mediline_pap_v3_path', $path_result->get_error_code() );
		pap_v3_same( 0, $calls );
	}
);

echo "\nAssertions: {$assertions}; failures: {$failures}\n";
exit( $failures ? 1 : 0 );
