<?php
/** Storefront-to-catalog attribution boundary regression tests. */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function mediline_store_valid_language( $requested = '' ): string {
	$language = sanitize_key( is_scalar( $requested ) ? (string) $requested : '' );
	return $language ?: 'en';
}

require dirname( __DIR__, 2 ) . '/mediline-store-core/includes/class-mediline-store-api.php';
require dirname( __DIR__, 2 ) . '/mediline-catalog-core/includes/class-mediline-catalog-api.php';

function storefront_attribution_fixture(): array {
	return array(
		'language'         => 'de',
		'utm_source'       => 'newsletter',
		'landing_url'      => 'https://Shop.Example.test/apply?utm_source=mail&utm_medium=cpc&gclid=G-123&fbclid=FB-456&password=do-not-copy#private',
		'pap_visitor_id'   => '0123456789abcdef0123456789abcdef',
		'pap_affiliate_id' => 'partner-42',
		'password'         => 'top-secret-password',
		'current'          => array(
			'utm_campaign' => 'launch',
			'landing_url'  => 'https://shop.example.test/offer?utm_medium=cpc&fbclid=FB-456&token=nested-secret#fragment',
			'password'     => 'nested-state-secret',
		),
		'first'            => array(
			'utm_term'     => 'medical',
			'landing_url'  => 'https://shop.example.test/start?utm_content=hero&api_key=private-key',
		),
	);
}

function assert_safe_storefront_attribution( array $result ): void {
	$json = (string) json_encode( $result );

	Test_Suite::assert_contains( 'utm_source=mail', $result['landing_url'] ?? '' );
	Test_Suite::assert_contains( 'gclid=G-123', $result['landing_url'] ?? '' );
	Test_Suite::assert_not_contains( 'password', strtolower( $json ) );
	Test_Suite::assert_not_contains( 'top-secret', $json );
	Test_Suite::assert_not_contains( 'do-not-copy', $json );
	Test_Suite::assert_not_contains( 'nested-secret', $json );
	Test_Suite::assert_not_contains( 'private-key', $json );
	Test_Suite::assert_not_contains( '#fragment', $json );
	Test_Suite::assert_contains( 'utm_content=hero', $result['first']['landing_url'] ?? '' );
	Test_Suite::assert_same( 'launch', $result['current']['utm_campaign'] ?? '' );
	Test_Suite::assert_contains( 'utm_medium=cpc', $result['current']['landing_url'] ?? '' );
	Test_Suite::assert_contains( 'fbclid=FB-456', $result['current']['landing_url'] ?? '' );
}

Test_Suite::run(
	'Store Core allowlists landing URL data before forwarding checkout attribution',
	static function (): void {
		$result = Mediline_Store_API::sanitize_attribution( storefront_attribution_fixture(), 'de' );
		assert_safe_storefront_attribution( $result );
	}
);

Test_Suite::run(
	'Catalog Core independently revalidates landing URL data received from a store',
	static function (): void {
		$result = Mediline_Catalog_API::sanitize_attribution( storefront_attribution_fixture(), 'de' );
		assert_safe_storefront_attribution( $result );
	}
);

Test_Suite::run('API invoice attribution persists without a browser session and preserves the fixed affiliate', static function (): void {
 $order = new class {
  public array $meta = array('_mediline_affiliate_id'=>'trusted-owner');
  public function update_meta_data($key,$value): void { $this->meta[$key]=$value; }
 };
 $raw=storefront_attribution_fixture();$raw['submission_id']='invoice-test';
 $clean=Mediline_Catalog_API::sanitize_attribution($raw,'de');
 Mediline_Catalog_API::attach_attribution_meta($order,$clean);
 Test_Suite::assert_same('newsletter',$order->meta['_mediline_utm_source']);
 Test_Suite::assert_same('0123456789abcdef0123456789abcdef',$order->meta['_mediline_pap_visitor_id']);
 Test_Suite::assert_same('trusted-owner',$order->meta['_mediline_affiliate_id']);
 Test_Suite::assert_same('invoice-test',$order->meta['_mediline_attribution_submission_id']);
 Test_Suite::assert_not_contains('password',json_encode($order->meta));
});
Test_Suite::finish();
