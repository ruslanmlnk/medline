<?php
/** Workflow/webhook regression tests with an in-memory durable-outbox double. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$GLOBALS['test_integration_settings'] = array(
	'pipedrive_company_id'   => 'company-42',
	'pipedrive_webhook_id'   => 'webhook-99',
	'pipedrive_webhook_host' => 'company.pipedrive.com',
);
$GLOBALS['test_integration_secrets'] = array(
	'webhook_username' => 'mediline-hook',
	'webhook_password' => 'strong-webhook-password',
);

function mediline_integrations_settings(): array { return $GLOBALS['test_integration_settings']; }
function mediline_integrations_secret( $key ): string { return (string) ( $GLOBALS['test_integration_secrets'][ (string) $key ] ?? '' ); }

final class Mediline_Integrations_DB {
	public static array $jobs = array();
	public static array $woken = array();
	private static int $next_id = 1;

	public static function enqueue( $event_key, $job_type, array $payload, array $args = array() ) {
		foreach ( self::$jobs as $job ) {
			if ( $job['event_key'] === $event_key ) {
				return $job['id'];
			}
		}
		$id           = self::$next_id++;
		self::$jobs[] = array(
			'id'          => $id,
			'event_key'   => (string) $event_key,
			'job_type'    => (string) $job_type,
			'payload'     => $payload,
			'source_type' => (string) ( $args['source_type'] ?? '' ),
			'source_id'   => (string) ( $args['source_id'] ?? '' ),
		);
		return $id;
	}

	public static function reset(): void {
		self::$jobs    = array();
		self::$woken   = array();
		self::$next_id = 1;
	}

	public static function wake_pap_sale( $job_id ) {
		self::$woken[] = (int) $job_id;
		return true;
	}

	public static function find( string $event_key ): ?array {
		foreach ( self::$jobs as $job ) {
			if ( $job['event_key'] === $event_key ) {
				return $job;
			}
		}
		return null;
	}
}

require dirname( __DIR__ ) . '/includes/class-mediline-integrations-pipedrive.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-pap.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-attribution.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-workflow.php';

function valid_won_payload( int $deal_id = 501 ): array {
	return array(
		'meta' => array(
			'version'    => '2.0',
			'action'     => 'change',
			'entity'     => 'deal',
			'id'         => 'event-' . $deal_id,
			'company_id' => 'company-42',
			'webhook_id' => 'webhook-99',
			'host'       => 'company.pipedrive.com',
		),
		'data' => array( 'id' => $deal_id, 'status' => 'won' ),
		'previous' => array( 'status' => 'open' ),
	);
}

Test_Suite::run(
	'Pipedrive webhook rejects invalid Basic credentials',
	static function (): void {
		Mediline_Integrations_DB::reset();
		$_SERVER['PHP_AUTH_USER'] = '';
		$_SERVER['PHP_AUTH_PW']   = '';
		$request = new WP_REST_Request(
			valid_won_payload(),
			array( 'Authorization' => 'Basic ' . base64_encode( 'mediline-hook:wrong-password' ) )
		);
		$result = Mediline_Integrations_Workflow::receive_won_webhook( $request );
		Test_Suite::assert_true( is_wp_error( $result ) );
		Test_Suite::assert_same( 'mediline_webhook_auth', $result->get_error_code() );
		Test_Suite::assert_same( 401, $result->get_error_data()['status'] ?? 0 );
		Test_Suite::assert_same( 0, count( Mediline_Integrations_DB::$jobs ) );
	}
);

Test_Suite::run(
	'only a real Won transition is queued once per Deal',
	static function (): void {
		Mediline_Integrations_DB::reset();
		$GLOBALS['test_transients'] = array();
		$auth = array( 'Authorization' => 'Basic ' . base64_encode( 'mediline-hook:strong-webhook-password' ) );
		$first = Mediline_Integrations_Workflow::receive_won_webhook( new WP_REST_Request( valid_won_payload( 501 ), $auth ) );
		Test_Suite::assert_true( $first instanceof WP_REST_Response );
		Test_Suite::assert_same( 202, $first->get_status() );
		Test_Suite::assert_same( 501, $first->get_data()['deal_id'] ?? 0 );

		$repeat_payload               = valid_won_payload( 501 );
		$repeat_payload['meta']['id'] = 'a-different-delivery-id';
		$repeat = Mediline_Integrations_Workflow::receive_won_webhook( new WP_REST_Request( $repeat_payload, $auth ) );
		Test_Suite::assert_true( $repeat instanceof WP_REST_Response );
		Test_Suite::assert_same( 1, count( Mediline_Integrations_DB::$jobs ), 'Repeated webhook delivery must reuse the Deal-level outbox event.' );
		Test_Suite::assert_same( array( 1, 1 ), Mediline_Integrations_DB::$woken, 'Every fresh delivery must wake a safely retryable per-Deal job.' );
		$job = Mediline_Integrations_DB::$jobs[0];
		Test_Suite::assert_same( 'pap-sale-deal:501', $job['event_key'] );
		Test_Suite::assert_same( Mediline_Integrations_Workflow::JOB_PAP_SALE, $job['job_type'] );
		Test_Suite::assert_same( 501, $job['payload']['deal_id'] ?? 0 );

		$already_won                 = valid_won_payload( 502 );
		$already_won['previous']['status'] = 'won';
		$ignored = Mediline_Integrations_Workflow::receive_won_webhook( new WP_REST_Request( $already_won, $auth ) );
		Test_Suite::assert_same( true, $ignored->get_data()['ignored'] ?? false );
		Test_Suite::assert_same( 1, count( Mediline_Integrations_DB::$jobs ) );

		$not_won                  = valid_won_payload( 503 );
		$not_won['data']['status'] = 'open';
		$ignored = Mediline_Integrations_Workflow::receive_won_webhook( new WP_REST_Request( $not_won, $auth ) );
		Test_Suite::assert_same( true, $ignored->get_data()['ignored'] ?? false );
		Test_Suite::assert_same( 1, count( Mediline_Integrations_DB::$jobs ) );
	}
);

Test_Suite::run(
	'public lead endpoint allowlists data before it reaches the durable queue',
	static function (): void {
		Mediline_Integrations_DB::reset();
		$GLOBALS['test_transients'] = array();
		$payload = array(
			'form_type'        => 'partner_application',
			'firstname'        => 'Lead',
			'lastname'         => 'Owner',
			'email'            => 'lead@example.test',
			'data26'           => '@lead',
			'language'         => 'uk',
			'utm_source'       => 'telegram',
			'landing_url'      => 'https://mediline.test/register/?utm_source=telegram&gclid=G-123&password=landing-secret#private',
			'pap_affiliate_id' => 'partner-7',
			'submission_id'    => '12345678-1234-4123-8123-123456789abc',
			'password'         => 'login-password-must-not-leak',
			'admin_password'   => 'generated-store-secret',
			'credit_card'      => '4111111111111111',
		);
		$request = new WP_REST_Request(
			$payload,
			array( 'X-Mediline-Lead-Nonce' => 'valid-test-nonce', 'Origin' => 'https://mediline.test' )
		);
		$result = Mediline_Integrations_Workflow::receive_lead( $request );
		Test_Suite::assert_true( $result instanceof WP_REST_Response );
		Test_Suite::assert_same( 202, $result->get_status() );
		$job  = Mediline_Integrations_DB::find( 'lead:12345678-1234-4123-8123-123456789abc' );
		$json = (string) json_encode( $job['payload'] ?? array() );
		Test_Suite::assert_true( is_array( $job ) );
		Test_Suite::assert_same( 'lead@example.test', $job['payload']['email'] ?? '' );
		Test_Suite::assert_same( 'telegram', $job['payload']['utm_source'] ?? '' );
		Test_Suite::assert_contains( 'utm_source=telegram', $job['payload']['landing_url'] ?? '' );
		Test_Suite::assert_contains( 'gclid=G-123', $job['payload']['landing_url'] ?? '' );
		Test_Suite::assert_not_contains( 'landing-secret', $job['payload']['landing_url'] ?? '' );
		Test_Suite::assert_not_contains( '#private', $job['payload']['landing_url'] ?? '' );
		Test_Suite::assert_not_contains( 'password', strtolower( $json ) );
		Test_Suite::assert_not_contains( 'login-password-must-not-leak', $json );
		Test_Suite::assert_not_contains( 'generated-store-secret', $json );
		Test_Suite::assert_not_contains( '4111111111111111', $json );

		Mediline_Integrations_DB::reset();
		$GLOBALS['test_transients'] = array();
		$bot_payload = $payload;
		$bot_payload['company_website'] = 'https://spam.example.test';
		$bot = Mediline_Integrations_Workflow::receive_lead(
			new WP_REST_Request( $bot_payload, array( 'X-Mediline-Lead-Nonce' => 'valid-test-nonce', 'Origin' => 'https://mediline.test' ) )
		);
		Test_Suite::assert_true( $bot instanceof WP_REST_Response );
		Test_Suite::assert_same( true, $bot->get_data()['ignored'] ?? false );
		Test_Suite::assert_same( 0, count( Mediline_Integrations_DB::$jobs ) );

		$wrong_scheme = new WP_REST_Request(
			$payload,
			array( 'X-Mediline-Lead-Nonce' => 'valid-test-nonce', 'Origin' => 'http://mediline.test' )
		);
		$rejected = Mediline_Integrations_Workflow::receive_lead( $wrong_scheme );
		Test_Suite::assert_true( is_wp_error( $rejected ) );
		Test_Suite::assert_same( 'mediline_lead_origin', $rejected->get_error_code() );

		$missing_origin = new WP_REST_Request( $payload, array( 'X-Mediline-Lead-Nonce' => 'valid-test-nonce' ) );
		$rejected = Mediline_Integrations_Workflow::receive_lead( $missing_origin );
		Test_Suite::assert_true( is_wp_error( $rejected ) );
		Test_Suite::assert_same( 'mediline_lead_origin', $rejected->get_error_code() );
	}
);

Test_Suite::run(
	'checkout queue keeps direct and generated-store affiliates isolated and idempotent',
	static function (): void {
		Mediline_Integrations_DB::reset();
		$GLOBALS['test_transients'] = array();
		$direct = new WC_Order(
			801,
			array( '_mediline_pap_affiliate_id' => 'direct-browser-partner', '_mediline_language' => 'en' ),
			array( 'email' => 'direct@example.test' ),
			49.95,
			'EUR'
		);
		$store = new WC_Order(
			802,
			array(
				'_mediline_affiliate_id'     => 'generated-store-owner',
				'_mediline_pap_affiliate_id' => 'untrusted-browser-partner',
				'_mediline_pap_visitor_id'   => '0123456789abcdef0123456789abcdef',
				'_mediline_language'         => 'de',
			),
			array( 'email' => 'store@example.test' ),
			99.50,
			'USD'
		);

		Mediline_Integrations_Workflow::queue_order( $direct );
		Mediline_Integrations_Workflow::queue_order( $store );
		Mediline_Integrations_Workflow::queue_order( $store );

		$direct_job = Mediline_Integrations_DB::find( 'pipedrive-order:801' );
		$store_job  = Mediline_Integrations_DB::find( 'pipedrive-order:802' );
		Test_Suite::assert_same( 'direct-browser-partner', $direct_job['payload']['pap_affiliate_id'] ?? '' );
		Test_Suite::assert_same( false, $direct_job['payload']['pap_affiliate_fixed'] ?? null );
		Test_Suite::assert_same( 'generated-store-owner', $store_job['payload']['pap_affiliate_id'] ?? '' );
		Test_Suite::assert_same( true, $store_job['payload']['pap_affiliate_fixed'] ?? false );
		Test_Suite::assert_same( 'de', $store_job['payload']['language'] ?? '' );
		Test_Suite::assert_same( 2, count( Mediline_Integrations_DB::$jobs ), 'Repeating the same order must not enqueue a duplicate.' );
	}
);

Test_Suite::finish();
