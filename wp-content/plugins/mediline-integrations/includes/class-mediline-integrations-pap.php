<?php
/**
 * Post Affiliate Pro server-to-server sale registration.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mediline_Integrations_PAP {
	/** @var array<string,mixed> */
	private $settings;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	public static function configured() {
		$settings = function_exists( 'mediline_integrations_settings' ) ? mediline_integrations_settings() : array();
		$secret   = function_exists( 'mediline_integrations_secret' ) ? mediline_integrations_secret( 'pap_fraud_secret' ) : '';
		return ! empty( $settings['pap_sale_enabled'] )
			&& ! empty( $settings['pap_sale_endpoint'] )
			&& ! empty( $settings['pap_duplicate_protection_confirmed'] )
			&& ! empty( $settings['pap_fraud_protection_enabled'] )
			&& ! empty( $secret );
	}

	/**
	 * Register one authoritative sale. PAP must calculate commission itself.
	 *
	 * @param array<string,mixed> $sale Normalized sale data.
	 * @return array<string,mixed>|WP_Error
	 */
	public function register_sale( array $sale ) {
		$endpoint = esc_url_raw( (string) ( $this->settings['pap_sale_endpoint'] ?? '' ) );
		if ( ! $endpoint || 0 !== stripos( $endpoint, 'https://' ) ) {
			return new WP_Error( 'mediline_pap_configuration', 'A Post Affiliate Pro HTTPS sale endpoint is required.', array( 'retryable' => false ) );
		}
		if ( empty( $this->settings['pap_duplicate_protection_confirmed'] ) ) {
			return new WP_Error( 'mediline_pap_duplicate_protection', 'PAP duplicate OrderID protection must be confirmed before sale tracking is enabled.', array( 'retryable' => true, 'retry_after' => HOUR_IN_SECONDS ) );
		}
		$fraud_secret = function_exists( 'mediline_integrations_secret' ) ? mediline_integrations_secret( 'pap_fraud_secret' ) : '';
		if ( empty( $this->settings['pap_fraud_protection_enabled'] ) || ! $fraud_secret ) {
			return new WP_Error( 'mediline_pap_fraud_protection', 'PAP Sale Tracking Fraud Protection and its server secret are required.', array( 'retryable' => true, 'retry_after' => HOUR_IN_SECONDS ) );
		}

		$visitor = self::visitor_id( $sale['pap_visitor_id'] ?? '' );
		$partner = ! empty( $sale['pap_affiliate_trusted'] ) ? self::affiliate_id( $sale['pap_affiliate_id'] ?? '' ) : '';
		if ( ! $visitor && ! $partner ) {
			return new WP_Error( 'mediline_pap_attribution', 'The Pipedrive Deal has no trusted PAP visitor or fixed affiliate ID.', array( 'retryable' => false ) );
		}

		$order_id = self::order_id( $sale['order_id'] ?? '' );
		$total    = isset( $sale['total_cost'] ) && is_numeric( $sale['total_cost'] ) ? round( max( 0, (float) $sale['total_cost'] ), 2 ) : null;
		if ( ! $order_id || null === $total ) {
			return new WP_Error( 'mediline_pap_sale', 'PAP OrderID and TotalCost are required.', array( 'retryable' => false ) );
		}

		$currency = strtoupper( substr( sanitize_key( (string) ( $sale['currency'] ?? 'EUR' ) ), 0, 3 ) );
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'EUR';
		}
		$body = array(
			'OrderID'   => $order_id,
			'TotalCost' => number_format( $total, 2, '.', '' ),
			'Currency'  => $currency,
		);
		$account_id = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $this->settings['pap_account_id'] ?? 'default1' ) ), 0, 64 );
		if ( $account_id ) {
			$body['AccountId'] = $account_id;
		}
		if ( $visitor ) {
			$body['visitorId'] = $visitor;
		} elseif ( $partner ) {
			$body['AffiliateID'] = $partner;
		}
		if ( ! empty( $sale['product_id'] ) ) {
			$body['ProductID'] = self::truncate( sanitize_text_field( (string) $sale['product_id'] ), 255 );
		}
		if ( ! empty( $sale['data1'] ) ) {
			$body['data1'] = self::truncate( sanitize_text_field( (string) $sale['data1'] ), 255 );
		}
		$fraud_field = absint( $this->settings['pap_fraud_data_field'] ?? 5 );
		if ( ! in_array( $fraud_field, array( 2, 3, 4, 5 ), true ) ) {
			$fraud_field = 5;
		}
		$body[ 'data' . $fraud_field ] = md5( $body['TotalCost'] . ',' . $body['OrderID'] . ',' . $fraud_secret );
		$status = strtoupper( sanitize_key( (string) ( $this->settings['pap_sale_status'] ?? '' ) ) );
		if ( in_array( $status, array( 'A', 'P', 'D' ), true ) ) {
			$body['PStatus'] = $status;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'text/plain, application/json;q=0.9, */*;q=0.5' ),
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mediline_pap_network', $response->get_error_message(), array( 'retryable' => true, 'status' => 0 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$text = trim( (string) wp_remote_retrieve_body( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'mediline_pap_http_' . $code,
				'Post Affiliate Pro sale endpoint returned HTTP ' . $code . '.',
				array( 'retryable' => 429 === $code || $code >= 500, 'status' => $code )
			);
		}
		if ( $text && preg_match( '/(?:tracking\s+failed|invalid\s+(?:sale|request)|fatal\s+error|^\s*error\b)/i', $text ) ) {
			return new WP_Error( 'mediline_pap_rejected', 'Post Affiliate Pro rejected the sale request.', array( 'retryable' => false, 'status' => $code ) );
		}

		return array(
			'ok'       => true,
			'order_id' => $order_id,
			'status'   => $code,
		);
	}

	public static function visitor_id( $value ) {
		$value = preg_replace( '/[^A-Za-z0-9]/', '', (string) $value );
		$value = strlen( $value ) > 32 ? substr( $value, -32 ) : $value;
		return 32 === strlen( $value ) ? $value : '';
	}

	public static function affiliate_id( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_.@-]/', '', (string) $value ), 0, 191 );
	}

	public static function order_id( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '-', (string) $value ), 0, 100 );
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}
}
