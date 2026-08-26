<?php
/** Core attribution, Pipedrive, PAP and encryption regression tests. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-crypto.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-db.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-attribution.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-pipedrive.php';
require dirname( __DIR__ ) . '/includes/class-mediline-integrations-pap.php';

$GLOBALS['test_core_secrets'] = array( 'pap_fraud_secret' => 'unit-test-fraud-secret' );
function mediline_integrations_secret( $key ): string {
	return (string) ( $GLOBALS['test_core_secrets'][ (string) $key ] ?? '' );
}

Test_Suite::run(
	'attribution is normalized, allowlisted, and never carries passwords',
	static function (): void {
		$_COOKIE = array();
		$visitor = 'ACCOUNT1' . '0123456789abcdef0123456789abcdef';
		$input   = array(
			'utm_source'       => 'newsletter',
			'language'         => 'UK_ua',
			'landing_url'      => 'https://Example.test/apply?utm_source=mail&gclid=G-123&password=do-not-copy#private',
			'pap_visitor_id'   => $visitor,
			'pap_affiliate_id' => 'partner-42<script>@#',
			'submission_id'    => '12345678-1234-4123-8123-123456789abc',
			'form_type'        => 'partner_application',
			'password'         => 'top-secret-password',
			'admin_password'   => 'another-secret',
			'credit_card'      => '4111111111111111',
			'attribution'      => array(
				'first'    => array( 'utm_medium' => 'cpc', 'password' => 'nested-secret', 'evil' => 'drop-me' ),
				'current'  => array( 'utm_campaign' => 'launch', 'unknown' => 'drop-me-too' ),
				'password' => 'nested-state-secret',
			),
		);

		$result = Mediline_Integrations_Attribution::request_attribution( $input );
		$json   = (string) json_encode( $result );

		Test_Suite::assert_same( '0123456789abcdef0123456789abcdef', $result['pap_visitor_id'] ?? '' );
		Test_Suite::assert_same( 'partner-42@', $result['pap_affiliate_id'] ?? '' );
		Test_Suite::assert_same( 'uk-ua', $result['language'] ?? '' );
		Test_Suite::assert_same( 'newsletter', $result['utm_source'] ?? '' );
		Test_Suite::assert_same( 'cpc', $result['first']['utm_medium'] ?? '' );
		Test_Suite::assert_same( 'launch', $result['current']['utm_campaign'] ?? '' );
		Test_Suite::assert_not_contains( 'password', strtolower( $json ) );
		Test_Suite::assert_not_contains( 'top-secret', $json );
		Test_Suite::assert_not_contains( '4111111111111111', $json );
		Test_Suite::assert_not_contains( 'do-not-copy', $result['landing_url'] ?? '' );
		Test_Suite::assert_not_contains( '#private', $result['landing_url'] ?? '' );
		Test_Suite::assert_contains( 'utm_source=mail', $result['landing_url'] ?? '' );
		Test_Suite::assert_contains( 'gclid=G-123', $result['landing_url'] ?? '' );
	}
);

Test_Suite::run(
	'browser attribution stays non-fixed and cannot replace a generated store affiliate',
	static function (): void {
		$_COOKIE = array();
		$direct  = new WC_Order( 101 );
		Mediline_Integrations_Attribution::attach_to_order(
			$direct,
			array(
				'pap_affiliate_id' => 'direct-partner',
				'language'         => 'en',
				'submission_id'    => '12345678-1234-4123-8123-123456789abc',
			)
		);
		Test_Suite::assert_same( '', $direct->get_meta( '_mediline_affiliate_id', true ) );
		Test_Suite::assert_same( 'direct-partner', $direct->get_meta( '_mediline_pap_affiliate_id', true ) );

		$store = new WC_Order(
			102,
			array(
				'_mediline_affiliate_id' => 'generated-store-owner',
				'_mediline_language'     => 'de',
			)
		);
		Mediline_Integrations_Attribution::attach_to_order(
			$store,
			array(
				'pap_affiliate_id' => 'browser-partner',
				'language'         => 'uk',
				'submission_id'    => 'abcdefab-1234-4123-8123-123456789abc',
			)
		);
		Test_Suite::assert_same( 'generated-store-owner', $store->get_meta( '_mediline_affiliate_id', true ) );
		Test_Suite::assert_same( 'de', $store->get_meta( '_mediline_language', true ) );
		Test_Suite::assert_same( 'browser-partner', $store->get_meta( '_mediline_pap_affiliate_id', true ) );
	}
);

function pipedrive_settings(): array {
	return array(
		'pipedrive_api_base'             => 'https://company.pipedrive.com',
		'pipedrive_pipeline_id'          => '7',
		'pipedrive_stage_id'             => '9',
		'pipedrive_field_language'       => 'fld_language',
		'pipedrive_field_utm_source'     => 'fld_utm_source',
		'pipedrive_field_utm_campaign'   => 'fld_utm_campaign',
		'pipedrive_field_pap_visitor_id' => 'fld_visitor',
		'pipedrive_field_pap_affiliate_id'=> 'fld_affiliate',
		'pipedrive_field_submission_id'  => 'fld_submission',
		'pipedrive_field_form_id'        => 'fld_form',
		'pipedrive_field_source_id'      => 'fld_source',
	);
}

Test_Suite::run(
	'Pipedrive API base is restricted to an official HTTPS origin',
	static function (): void {
		Test_Suite::assert_same( 'https://company.pipedrive.com', Mediline_Integrations_Pipedrive::normalize_api_base( 'https://Company.Pipedrive.com/' ) );
		Test_Suite::assert_same( 'https://api.pipedrive.com', Mediline_Integrations_Pipedrive::normalize_api_base( 'https://api.pipedrive.com:443' ) );
		Test_Suite::assert_same( '', Mediline_Integrations_Pipedrive::normalize_api_base( 'https://pipedrive.com.evil.test' ) );
		Test_Suite::assert_same( '', Mediline_Integrations_Pipedrive::normalize_api_base( 'https://pipedrive.com@evil.test' ) );
		Test_Suite::assert_same( '', Mediline_Integrations_Pipedrive::normalize_api_base( 'https://company.pipedrive.com/api/v2' ) );
	}
);

Test_Suite::run(
	'Pipedrive runtime readiness requires every attribution field mapping',
	static function (): void {
		$settings = pipedrive_settings();
		Test_Suite::assert_false( Mediline_Integrations_Pipedrive::required_fields_configured( $settings ) );
		foreach ( Mediline_Integrations_Pipedrive::required_field_suffixes() as $suffix ) {
			$settings[ 'pipedrive_field_' . $suffix ] = $settings[ 'pipedrive_field_' . $suffix ] ?? 'fld_' . $suffix;
		}
		Test_Suite::assert_true( Mediline_Integrations_Pipedrive::required_fields_configured( $settings ) );
	}
);

Test_Suite::run(
	'Pipedrive accepts a successful empty DELETE response',
	static function (): void {
		test_http_reset();
		test_http_queue_text( '', 204 );
		$client = new Mediline_Integrations_Pipedrive( array( 'pipedrive_api_base' => 'https://company.pipedrive.com' ), 'api-token' );
		$result = $client->request( 'DELETE', '/api/v1/webhooks/99' );
		Test_Suite::assert_same( array(), $result );
	}
);

Test_Suite::run(
	'Pipedrive Person lookup requires an exact email and avoids duplicate creation',
	static function (): void {
		test_http_reset();
		test_http_queue_json(
			array(
				'data' => array(
					'items' => array(
						array( 'item' => array( 'id' => 10, 'emails' => array( array( 'value' => 'alice+other@example.test' ) ) ) ),
						array( 'item' => array( 'id' => 22, 'emails' => array( array( 'value' => 'ALICE@example.test' ) ) ) ),
					),
				),
			)
		);
		$client = new Mediline_Integrations_Pipedrive( pipedrive_settings(), 'api-token' );
		$id     = $client->ensure_person( array( 'email' => 'Alice@example.test', 'first_name' => 'Alice' ) );
		Test_Suite::assert_same( 22, $id );
		Test_Suite::assert_same( 1, count( $GLOBALS['test_http_requests'] ) );
		$request = $GLOBALS['test_http_requests'][0];
		Test_Suite::assert_contains( '/api/v2/persons/search?', $request['url'] );
		Test_Suite::assert_contains( 'exact_match=true', $request['url'] );
		Test_Suite::assert_contains( 'fields=email', $request['url'] );
		Test_Suite::assert_contains( 'term=alice%40example.test', $request['url'] );
	}
);

Test_Suite::run(
	'Pipedrive Person creation sends only the contact payload',
	static function (): void {
		test_http_reset();
		test_http_queue_json( array( 'data' => array( 'items' => array() ) ) );
		test_http_queue_json( array( 'data' => array( 'id' => 33 ) ) );
		$client = new Mediline_Integrations_Pipedrive( pipedrive_settings(), 'api-token' );
		$id     = $client->ensure_person(
			array(
				'email'          => 'NEW@example.test',
				'phone'          => '+420 555 123',
				'first_name'     => 'New',
				'last_name'      => 'Person',
				'password'       => 'must-never-leave',
				'admin_password' => 'also-private',
			)
		);
		Test_Suite::assert_same( 33, $id );
		Test_Suite::assert_same( 2, count( $GLOBALS['test_http_requests'] ) );
		$post = $GLOBALS['test_http_requests'][1];
		$body = json_decode( (string) $post['args']['body'], true );
		Test_Suite::assert_same( 'POST', $post['args']['method'] );
		Test_Suite::assert_same( 'New Person', $body['name'] ?? '' );
		Test_Suite::assert_same( 'new@example.test', $body['emails'][0]['value'] ?? '' );
		Test_Suite::assert_not_has_key( 'password', $body );
		Test_Suite::assert_not_contains( 'must-never-leave', (string) $post['args']['body'] );
		Test_Suite::assert_same( 'api-token', $post['args']['headers']['x-api-token'] ?? '' );
	}
);

Test_Suite::run(
	'Pipedrive Deal lookup is exact and Deal payload carries required CRM fields',
	static function (): void {
		$settings = pipedrive_settings();
		$client   = new Mediline_Integrations_Pipedrive( $settings, 'api-token' );

		test_http_reset();
		test_http_queue_json(
			array(
				'data' => array(
					'items' => array(
						array( 'item' => array( 'id' => 77, 'custom_fields' => array( 'fld_submission' => 'submission-exact-001' ) ) ),
					),
				),
			)
		);
		$existing = $client->ensure_deal( array( 'submission_id' => 'submission-exact-001' ), 22 );
		Test_Suite::assert_same( 77, $existing );
		Test_Suite::assert_same( 1, count( $GLOBALS['test_http_requests'] ), 'An exact lookup must not create another Deal.' );

		test_http_reset();
		test_http_queue_json( array( 'data' => array( 'items' => array( array( 'item' => array( 'id' => 79 ) ) ) ) ) );
		test_http_queue_json( array( 'data' => array( 'id' => 79, 'custom_fields' => array( 'fld_submission' => 'submission-exact-001' ) ) ) );
		$expanded = $client->ensure_deal( array( 'submission_id' => 'submission-exact-001' ), 22 );
		Test_Suite::assert_same( 79, $expanded );
		Test_Suite::assert_same( 2, count( $GLOBALS['test_http_requests'] ), 'A compact search result must be verified with the requested custom field.' );
		Test_Suite::assert_contains( 'custom_fields=fld_submission', $GLOBALS['test_http_requests'][1]['url'] );

		test_http_reset();
		test_http_queue_json(
			array(
				'data' => array(
					'items' => array(
						array( 'item' => array( 'id' => 78, 'custom_fields' => array( 'fld_submission' => 'submission-exact-001-copy' ) ) ),
					),
				),
			)
		);
		test_http_queue_json( array( 'data' => array( 'id' => 88 ) ) );
		$submission = array(
			'submission_id'    => 'submission-exact-001',
			'form_id'          => 'woocommerce_checkout',
			'first_name'       => 'Order',
			'last_name'        => 'Owner',
			'language'         => 'SP',
			'utm_source'       => 'google',
			'utm_campaign'     => 'summer',
			'pap_visitor_id'   => '0123456789abcdef0123456789abcdef',
			'pap_affiliate_id' => 'partner-88',
			'source_id'        => 'wc-order-501',
			'value'            => 123.456,
			'currency'         => 'usd',
			'password'         => 'never-in-a-deal',
		);
		$created = $client->ensure_deal( $submission, 22 );
		Test_Suite::assert_same( 88, $created );
		Test_Suite::assert_same( 2, count( $GLOBALS['test_http_requests'] ) );
		$search = $GLOBALS['test_http_requests'][0];
		$post   = $GLOBALS['test_http_requests'][1];
		$body   = json_decode( (string) $post['args']['body'], true );
		Test_Suite::assert_contains( 'fields=custom_fields', $search['url'] );
		Test_Suite::assert_contains( 'exact_match=true', $search['url'] );
		Test_Suite::assert_same( 22, $body['person_id'] ?? 0 );
		Test_Suite::assert_same( 'open', $body['status'] ?? '' );
		Test_Suite::assert_same( 7, $body['pipeline_id'] ?? 0 );
		Test_Suite::assert_same( 9, $body['stage_id'] ?? 0 );
		Test_Suite::assert_same( 123.46, $body['value'] ?? 0 );
		Test_Suite::assert_same( 'USD', $body['currency'] ?? '' );
		Test_Suite::assert_same( 'es', $body['custom_fields']['fld_language'] ?? '' );
		Test_Suite::assert_same( 'google', $body['custom_fields']['fld_utm_source'] ?? '' );
		Test_Suite::assert_same( 'summer', $body['custom_fields']['fld_utm_campaign'] ?? '' );
		Test_Suite::assert_same( 'partner-88', $body['custom_fields']['fld_affiliate'] ?? '' );
		Test_Suite::assert_same( 'submission-exact-001', $body['custom_fields']['fld_submission'] ?? '' );
		Test_Suite::assert_not_contains( 'never-in-a-deal', (string) $post['args']['body'] );
	}
);

Test_Suite::run(
	'Pipedrive v2 Deal field provisioning uses field_name and field_code',
	static function (): void {
		test_http_reset();
		$existing = array();
		foreach ( Mediline_Integrations_Pipedrive::field_definitions() as $suffix => $definition ) {
			if ( 'language' === $suffix ) {
				continue;
			}
			$existing[] = array( 'field_name' => $definition['name'], 'field_type' => $definition['type'], 'field_code' => 'code_' . $suffix );
		}
		test_http_queue_json( array( 'success' => true, 'data' => $existing ) );
		test_http_queue_json( array( 'success' => true, 'data' => array( 'field_name' => 'MEDILINE Language', 'field_type' => 'varchar', 'field_code' => 'code_language' ) ) );
		$client = new Mediline_Integrations_Pipedrive( array( 'pipedrive_api_base' => 'https://company.pipedrive.com' ), 'api-token' );
		$result = $client->provision_fields();
		Test_Suite::assert_false( is_wp_error( $result ) );
		Test_Suite::assert_same( 'code_language', $result['pipedrive_field_language'] ?? '' );
		Test_Suite::assert_same( 2, count( $GLOBALS['test_http_requests'] ) );
		$create_body = json_decode( (string) $GLOBALS['test_http_requests'][1]['args']['body'], true );
		Test_Suite::assert_same( 'MEDILINE Language', $create_body['field_name'] ?? '' );
		Test_Suite::assert_same( 'varchar', $create_body['field_type'] ?? '' );
		Test_Suite::assert_not_has_key( 'name', $create_body );
	}
);

Test_Suite::run(
	'PAP sale uses the final 32 visitor characters and never sends Commission',
	static function (): void {
		test_http_reset();
		test_http_queue_text( 'OK', 200 );
		$pap = new Mediline_Integrations_PAP(
			array(
				'pap_sale_endpoint'                    => 'https://mediline.postaffiliatepro.com/scripts/sale.php',
				'pap_account_id'                       => 'default1',
				'pap_sale_status'                      => 'A',
				'pap_duplicate_protection_confirmed'   => 1,
				'pap_fraud_protection_enabled'         => 1,
				'pap_fraud_data_field'                  => 5,
			)
		);
		$result = $pap->register_sale(
			array(
				'pap_visitor_id'   => 'ACCOUNT1' . '0123456789abcdef0123456789abcdef',
				'pap_affiliate_id' => 'partner-88!$',
				'order_id'         => 'pipedrive-deal-501',
				'total_cost'       => 123.4,
				'currency'         => 'eur',
				'product_id'       => 'Premium plan',
				'data1'            => '501',
				'commission'       => '999999.00',
			)
		);
		Test_Suite::assert_false( is_wp_error( $result ) );
		Test_Suite::assert_same( 'pipedrive-deal-501', $result['order_id'] ?? '' );
		Test_Suite::assert_same( 1, count( $GLOBALS['test_http_requests'] ) );
		$request = $GLOBALS['test_http_requests'][0];
		$body    = $request['args']['body'];
		Test_Suite::assert_same( '0123456789abcdef0123456789abcdef', $body['visitorId'] ?? '' );
		Test_Suite::assert_not_has_key( 'AffiliateID', $body, 'AffiliateID is fallback-only when a visitor ID is present.' );
		Test_Suite::assert_same( 'pipedrive-deal-501', $body['OrderID'] ?? '' );
		Test_Suite::assert_same( '123.40', $body['TotalCost'] ?? '' );
		Test_Suite::assert_same( 'EUR', $body['Currency'] ?? '' );
		Test_Suite::assert_same( 'default1', $body['AccountId'] ?? '' );
		Test_Suite::assert_same( 'A', $body['PStatus'] ?? '' );
		Test_Suite::assert_same( md5( '123.40,pipedrive-deal-501,unit-test-fraud-secret' ), $body['data5'] ?? '' );
		foreach ( array_keys( $body ) as $key ) {
			Test_Suite::assert_false( false !== stripos( (string) $key, 'commission' ), 'Commission must be calculated by PAP, not supplied by this site.' );
		}
	}
);

Test_Suite::run(
	'PAP ignores malformed visitor IDs and falls back only to a trusted fixed affiliate ID',
	static function (): void {
		test_http_reset();
		test_http_queue_text( 'OK', 200 );
		$pap = new Mediline_Integrations_PAP(
			array(
				'pap_sale_endpoint'                  => 'https://mediline.postaffiliatepro.com/scripts/sale.php',
				'pap_account_id'                     => 'default1',
				'pap_duplicate_protection_confirmed' => 1,
				'pap_fraud_protection_enabled'       => 1,
				'pap_fraud_data_field'                => 5,
			)
		);
		$result = $pap->register_sale(
			array(
				'pap_visitor_id'   => 'too-short',
				'pap_affiliate_id' => 'partner-88',
				'pap_affiliate_trusted' => true,
				'order_id'         => 'pipedrive-deal-502',
				'total_cost'       => 10,
				'currency'         => 'EUR',
			)
		);
		Test_Suite::assert_false( is_wp_error( $result ) );
		$body = $GLOBALS['test_http_requests'][0]['args']['body'];
		Test_Suite::assert_not_has_key( 'visitorId', $body );
		Test_Suite::assert_same( 'partner-88', $body['AffiliateID'] ?? '' );
	}
);

Test_Suite::run(
	'PAP never forces a client-supplied affiliate ID for direct traffic',
	static function (): void {
		test_http_reset();
		$pap = new Mediline_Integrations_PAP(
			array(
				'pap_sale_endpoint'                  => 'https://mediline.postaffiliatepro.com/scripts/sale.php',
				'pap_duplicate_protection_confirmed' => 1,
				'pap_fraud_protection_enabled'       => 1,
			)
		);
		$result = $pap->register_sale(
			array(
				'pap_visitor_id'   => '',
				'pap_affiliate_id' => 'attacker-controlled',
				'order_id'         => 'pipedrive-deal-503',
				'total_cost'       => 10,
				'currency'         => 'EUR',
			)
		);
		Test_Suite::assert_true( is_wp_error( $result ) );
		Test_Suite::assert_same( 'mediline_pap_attribution', $result->get_error_code() );
		Test_Suite::assert_same( 0, count( $GLOBALS['test_http_requests'] ) );
	}
);

Test_Suite::run(
	'outbox payload encryption authenticates data and admin summaries expose no payload',
	static function (): void {
		Test_Suite::assert_true( Mediline_Integrations_Crypto::is_supported(), 'The selected PHP runtime must provide AES-256-GCM.' );
		$payload  = array( 'email' => 'private@example.test', 'password' => 'never-store-plaintext', 'deal_id' => 501 );
		$envelope = Mediline_Integrations_Crypto::encrypt( $payload );
		Test_Suite::assert_false( is_wp_error( $envelope ) );
		Test_Suite::assert_not_contains( 'private@example.test', (string) $envelope );
		Test_Suite::assert_not_contains( 'never-store-plaintext', (string) $envelope );
		Test_Suite::assert_same( $payload, Mediline_Integrations_Crypto::decrypt( $envelope ) );

		$tampered       = json_decode( (string) $envelope, true );
		$ciphertext     = base64_decode( $tampered['ct'], true );
		$ciphertext[0]  = chr( ord( $ciphertext[0] ) ^ 1 );
		$tampered['ct'] = base64_encode( $ciphertext );
		$opened         = Mediline_Integrations_Crypto::decrypt( (string) json_encode( $tampered ) );
		Test_Suite::assert_true( is_wp_error( $opened ) );
		Test_Suite::assert_same( 'mediline_crypto_auth_failed', $opened->get_error_code() );

		$row = array(
			'id' => '1', 'event_key' => 'lead:1', 'job_type' => 'pipedrive_submission', 'source_type' => 'lead',
			'source_id' => '1', 'status' => 'pending', 'attempts' => '0', 'max_attempts' => '10',
			'available_at' => '2026-01-01 00:00:00', 'locked_at' => '', 'lock_token' => 'private-lock',
			'payload_ciphertext' => (string) $envelope, 'remote_person_id' => '', 'remote_deal_id' => '',
			'remote_sale_id' => '', 'last_error' => '', 'created_at' => '2026-01-01 00:00:00',
			'updated_at' => '2026-01-01 00:00:00', 'completed_at' => '',
		);
		$method = new ReflectionMethod( Mediline_Integrations_DB::class, 'admin_row' );
		$method->setAccessible( true );
		$summary = $method->invoke( null, $row );
		Test_Suite::assert_not_has_key( 'payload', $summary );
		Test_Suite::assert_not_has_key( 'payload_ciphertext', $summary );
		Test_Suite::assert_not_has_key( 'lock_token', $summary );
		Test_Suite::assert_not_contains( 'private@example.test', (string) json_encode( $summary ) );
	}
);

Test_Suite::finish();
