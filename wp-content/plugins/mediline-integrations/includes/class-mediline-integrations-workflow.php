<?php
/**
 * Lead/order delivery, queue worker, and Pipedrive Won webhook.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mediline_Integrations_Workflow {
	const JOB_PIPEDRIVE = 'pipedrive_submission';
	const JOB_PAP_SALE  = 'pap_sale';
	const CRON_HOOK     = 'mediline_integrations_process_queue';
	const CLEANUP_HOOK  = 'mediline_integrations_cleanup_queue';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process_queue' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup_queue' ) );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'classic_checkout_order' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'checkout_order_created' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'checkout_order_created' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'payment_complete' ), 20, 1 );
		add_action( 'mediline_catalog_invoice_created', array( __CLASS__, 'checkout_order_created' ), 20, 1 );
		self::ensure_schedule();
	}

	public static function cron_schedules( $schedules ) {
		$schedules['mediline_every_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Every minute (Mediline integrations)', 'mediline-integrations' ),
		);
		return $schedules;
	}

	public static function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'mediline_every_minute', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function deactivate() {
		foreach ( array( self::CRON_HOOK, self::CLEANUP_HOOK ) as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			while ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}

	public static function cleanup_queue() {
		$failed_days = apply_filters( 'mediline_integrations_failed_retention_days', 30 );
		$completed_days = apply_filters( 'mediline_integrations_completed_retention_days', 730 );
		Mediline_Integrations_DB::purge_expired( $failed_days, $completed_days );
	}

	public static function register_routes() {
		register_rest_route(
			'mediline-integrations/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive_lead' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			'mediline-integrations/v1',
			'/pipedrive/won',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive_won_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** @return WP_REST_Response|WP_Error */
	public static function receive_lead( WP_REST_Request $request ) {
		if ( strlen( (string) $request->get_body() ) > 16384 ) {
			return new WP_Error( 'mediline_lead_size', 'Request is too large.', array( 'status' => 413 ) );
		}
		$authorized = self::validate_public_form_request( $request );
		if ( is_wp_error( $authorized ) ) {
			return $authorized;
		}
		if ( ! self::rate_limit( 'lead', 12, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'mediline_lead_rate', 'Too many submissions. Try again shortly.', array( 'status' => 429 ) );
		}

		$raw = $request->get_json_params();
		if ( ! is_array( $raw ) ) {
			return new WP_Error( 'mediline_lead_json', 'A JSON request body is required.', array( 'status' => 400 ) );
		}
		$form_id = sanitize_key( (string) ( $raw['form_id'] ?? $raw['form_type'] ?? '' ) );
		if ( ! in_array( $form_id, array( 'partner_application' ), true ) ) {
			return new WP_Error( 'mediline_lead_form', 'This form is not registered as a CRM lead form.', array( 'status' => 400 ) );
		}
		if ( '' !== self::text( $raw['company_website'] ?? '', 255 ) ) {
			return new WP_REST_Response( array( 'accepted' => true, 'ignored' => true ), 202 );
		}
		$email = strtolower( sanitize_email( (string) ( $raw['email'] ?? $raw['username'] ?? '' ) ) );
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'mediline_lead_email', 'A valid email is required.', array( 'status' => 400 ) );
		}

		$submission_id = self::submission_id( $raw['submission_id'] ?? '' );
		$payload       = array(
			'submission_id'    => $submission_id,
			'form_id'          => $form_id,
			'first_name'       => self::text( $raw['first_name'] ?? $raw['firstname'] ?? '', 100 ),
			'last_name'        => self::text( $raw['last_name'] ?? $raw['lastname'] ?? '', 100 ),
			'email'            => $email,
			'phone'            => '',
			'messenger'        => self::text( $raw['messenger'] ?? $raw['data26'] ?? '', 255 ),
			'language'         => Mediline_Integrations_Pipedrive::normalize_language( $raw['language'] ?? self::site_language() ),
			'pap_visitor_id'   => Mediline_Integrations_PAP::visitor_id( $raw['pap_visitor_id'] ?? '' ),
			'pap_affiliate_id' => Mediline_Integrations_PAP::affiliate_id( $raw['pap_affiliate_id'] ?? '' ),
			'landing_url'      => Mediline_Integrations_Attribution::sanitize_landing_url( $raw['landing_url'] ?? '' ),
			'utm_source'       => self::text( $raw['utm_source'] ?? '', 255 ),
			'utm_medium'       => self::text( $raw['utm_medium'] ?? '', 255 ),
			'utm_campaign'     => self::text( $raw['utm_campaign'] ?? '', 255 ),
			'utm_term'         => self::text( $raw['utm_term'] ?? '', 255 ),
			'utm_content'      => self::text( $raw['utm_content'] ?? '', 255 ),
			'gclid'            => self::text( $raw['gclid'] ?? '', 255 ),
			'fbclid'           => self::text( $raw['fbclid'] ?? '', 255 ),
			'source_id'        => $submission_id,
		);
		$job_id = Mediline_Integrations_DB::enqueue(
			'lead:' . $submission_id,
			self::JOB_PIPEDRIVE,
			$payload,
			array( 'source_type' => 'lead', 'source_id' => $submission_id, 'max_attempts' => 100 )
		);
		if ( is_wp_error( $job_id ) ) {
			return new WP_Error( 'mediline_lead_queue', 'The application could not be recorded. Please retry.', array( 'status' => 503 ) );
		}
		self::schedule_worker();
		return new WP_REST_Response( array( 'accepted' => true, 'submission_id' => $submission_id ), 202 );
	}

	/** @return WP_REST_Response|WP_Error */
	public static function receive_won_webhook( WP_REST_Request $request ) {
		if ( strlen( (string) $request->get_body() ) > 262144 ) {
			return new WP_Error( 'mediline_webhook_size', 'Request is too large.', array( 'status' => 413 ) );
		}
		if ( ! self::valid_webhook_auth( $request ) ) {
			return new WP_Error( 'mediline_webhook_auth', 'Invalid webhook credentials.', array( 'status' => 401 ) );
		}
		if ( ! self::rate_limit( 'webhook', 180, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'mediline_webhook_rate', 'Webhook rate limit exceeded.', array( 'status' => 429 ) );
		}
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'mediline_webhook_json', 'A JSON request body is required.', array( 'status' => 400 ) );
		}
		$meta     = is_array( $payload['meta'] ?? null ) ? $payload['meta'] : array();
		$data     = is_array( $payload['data'] ?? null ) ? $payload['data'] : array();
		$previous = is_array( $payload['previous'] ?? null ) ? $payload['previous'] : array();
		$version  = (string) ( $meta['version'] ?? '' );
		$action   = strtolower( (string) ( $meta['action'] ?? '' ) );
		$entity   = strtolower( (string) ( $meta['entity'] ?? '' ) );
		$status   = strtolower( (string) ( $data['status'] ?? '' ) );
		$prior    = strtolower( (string) ( $previous['status'] ?? '' ) );
		$deal_id  = absint( $data['id'] ?? ( $meta['entity_id'] ?? 0 ) );

		if ( '2.0' !== $version || 'change' !== $action || 'deal' !== $entity || 'won' !== $status || ! $prior || 'won' === $prior || ! $deal_id ) {
			return new WP_REST_Response( array( 'accepted' => true, 'ignored' => true ), 202 );
		}
		$settings = mediline_integrations_settings();
		if ( ! self::matches_expected_meta( $meta, $settings ) ) {
			return new WP_Error( 'mediline_webhook_tenant', 'Webhook tenant metadata does not match.', array( 'status' => 403 ) );
		}
		$event_id = self::text( $meta['id'] ?? '', 100 );
		$job_id   = Mediline_Integrations_DB::enqueue(
			'pap-sale-deal:' . $deal_id,
			self::JOB_PAP_SALE,
			array( 'deal_id' => $deal_id, 'webhook_event_id' => $event_id ),
			array( 'source_type' => 'pipedrive-deal', 'source_id' => (string) $deal_id, 'max_attempts' => 100 )
		);
		if ( is_wp_error( $job_id ) ) {
			return new WP_Error( 'mediline_webhook_queue', 'Webhook could not be durably recorded.', array( 'status' => 503 ) );
		}
		$woken = Mediline_Integrations_DB::wake_pap_sale( $job_id );
		if ( is_wp_error( $woken ) ) {
			return new WP_Error( 'mediline_webhook_queue', 'Webhook could not be durably scheduled.', array( 'status' => 503 ) );
		}
		self::schedule_worker();
		return new WP_REST_Response( array( 'accepted' => true, 'deal_id' => $deal_id ), 202 );
	}

	private static function matches_expected_meta( array $meta, array $settings ) {
		$checks = array(
			'pipedrive_company_id' => 'company_id',
			'pipedrive_webhook_id' => 'webhook_id',
		);
		foreach ( $checks as $setting => $meta_key ) {
			$expected = trim( (string) ( $settings[ $setting ] ?? '' ) );
			$actual   = trim( (string) ( $meta[ $meta_key ] ?? '' ) );
			if ( $expected && ( ! $actual || ! hash_equals( $expected, $actual ) ) ) {
				return false;
			}
		}
		$expected_host = strtolower( trim( (string) ( $settings['pipedrive_webhook_host'] ?? '' ) ) );
		$actual_host   = strtolower( trim( (string) ( $meta['host'] ?? '' ) ) );
		return ! $expected_host || ( $actual_host && hash_equals( $expected_host, $actual_host ) );
	}

	private static function valid_webhook_auth( WP_REST_Request $request ) {
		$expected_user = (string) mediline_integrations_secret( 'webhook_username' );
		$expected_pass = (string) mediline_integrations_secret( 'webhook_password' );
		if ( ! $expected_user || ! $expected_pass ) {
			return false;
		}
		$user = isset( $_SERVER['PHP_AUTH_USER'] ) ? (string) $_SERVER['PHP_AUTH_USER'] : '';
		$pass = isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : '';
		if ( ! $user && ! $pass ) {
			$header = trim( (string) $request->get_header( 'authorization' ) );
			if ( 0 === stripos( $header, 'Basic ' ) ) {
				$decoded = base64_decode( substr( $header, 6 ), true );
				if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
					list( $user, $pass ) = explode( ':', $decoded, 2 );
				}
			}
		}
		return hash_equals( $expected_user, $user ) && hash_equals( $expected_pass, $pass );
	}

	private static function validate_public_form_request( WP_REST_Request $request ) {
		$nonce = sanitize_text_field( (string) $request->get_header( 'x-mediline-lead-nonce' ) );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'mediline_public_lead' ) ) {
			return new WP_Error( 'mediline_lead_nonce', 'Invalid form token.', array( 'status' => 403 ) );
		}
		$source_origin = trim( (string) $request->get_header( 'origin' ) );
		if ( ! $source_origin ) {
			$source_origin = trim( (string) $request->get_header( 'referer' ) );
		}
		$actual   = self::normalized_origin( $source_origin );
		$expected = self::normalized_origin( home_url( '/' ) );
		if ( ! $actual || ! $expected || ! hash_equals( $expected, $actual ) ) {
			return new WP_Error( 'mediline_lead_origin', 'Invalid request origin.', array( 'status' => 403 ) );
		}
		$custom = apply_filters( 'mediline_integrations_validate_public_lead', true, $request );
		if ( is_wp_error( $custom ) ) {
			return $custom;
		}
		if ( true !== $custom ) {
			return new WP_Error( 'mediline_lead_verification', 'Form verification failed.', array( 'status' => 403 ) );
		}
		return true;
	}

	private static function normalized_origin( $url ) {
		$parts = is_scalar( $url ) ? wp_parse_url( trim( (string) $url ) ) : false;
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! $host ) {
			return '';
		}
		$port = absint( $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 ) );
		return $scheme . '://' . $host . ':' . $port;
	}

	private static function rate_limit( $scope, $limit, $window ) {
		global $wpdb;
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key = 'mediline_int_rate_' . hash_hmac( 'sha256', $scope . '|' . $ip, wp_salt( 'nonce' ) );
		$lock_name = 'mediline-rate-' . substr( hash( 'sha256', $key ), 0, 40 );
		$has_lock_api = is_object( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' );
		if ( $has_lock_api ) {
			$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,1)', $lock_name ) );
			if ( 1 !== $locked ) {
				return false;
			}
		}
		try {
			$n = (int) get_transient( $key );
			if ( $n >= $limit ) {
				return false;
			}
			return (bool) set_transient( $key, $n + 1, $window );
		} finally {
			if ( $has_lock_api ) {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
			}
		}
	}

	public static function classic_checkout_order( $order_id, $posted_data = array(), $order = null ) {
		self::queue_order( $order instanceof WC_Order ? $order : $order_id );
	}

	public static function checkout_order_created( $order ) {
		self::queue_order( $order );
	}

	public static function payment_complete( $order_id ) {
		self::queue_order( $order_id );
	}

	public static function queue_order( $order_or_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( absint( $order_or_id ) );
		if ( ! $order || ! $order->get_id() || ! $order->get_billing_email() ) {
			return;
		}
		$order_id = (int) $order->get_id();
		$fixed_affiliate = $order->get_meta( '_mediline_affiliate_id', true );
		$payload = array(
			'submission_id'    => 'wc-' . get_current_blog_id() . '-' . $order_id,
			'form_id'          => 'woocommerce_checkout',
			'first_name'       => self::text( $order->get_billing_first_name(), 100 ),
			'last_name'        => self::text( $order->get_billing_last_name(), 100 ),
			'email'            => strtolower( sanitize_email( $order->get_billing_email() ) ),
			'phone'            => self::text( $order->get_billing_phone(), 50 ),
			'language'         => Mediline_Integrations_Pipedrive::normalize_language( $order->get_meta( '_mediline_language', true ) ?: self::site_language() ),
			'pap_visitor_id'   => Mediline_Integrations_PAP::visitor_id( $order->get_meta( '_mediline_pap_visitor_id', true ) ),
			'pap_affiliate_id' => Mediline_Integrations_PAP::affiliate_id( $fixed_affiliate ?: $order->get_meta( '_mediline_pap_affiliate_id', true ) ),
			'pap_affiliate_fixed' => ! empty( $fixed_affiliate ),
			'landing_url'      => Mediline_Integrations_Attribution::sanitize_landing_url( $order->get_meta( '_mediline_landing_url', true ) ),
			'utm_source'       => self::text( $order->get_meta( '_mediline_utm_source', true ), 255 ),
			'utm_medium'       => self::text( $order->get_meta( '_mediline_utm_medium', true ), 255 ),
			'utm_campaign'     => self::text( $order->get_meta( '_mediline_utm_campaign', true ), 255 ),
			'utm_term'         => self::text( $order->get_meta( '_mediline_utm_term', true ), 255 ),
			'utm_content'      => self::text( $order->get_meta( '_mediline_utm_content', true ), 255 ),
			'gclid'            => self::text( $order->get_meta( '_mediline_gclid', true ), 255 ),
			'fbclid'           => self::text( $order->get_meta( '_mediline_fbclid', true ), 255 ),
			'source_id'        => 'wc-order-' . $order_id,
			'value'            => (float) $order->get_total(),
			'currency'         => strtoupper( (string) $order->get_currency() ),
		);
		$result = Mediline_Integrations_DB::enqueue(
			'pipedrive-order:' . $order_id,
			self::JOB_PIPEDRIVE,
			$payload,
			array( 'source_type' => 'wc-order', 'source_id' => (string) $order_id, 'max_attempts' => 100 )
		);
		if ( ! is_wp_error( $result ) ) {
			self::schedule_worker();
		}
	}

	public static function schedule_worker() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::CRON_HOOK, array(), 'mediline-integrations', true );
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK );
		}
	}

	public static function process_queue() {
		$worker = 'wp-' . substr( hash( 'sha256', wp_generate_uuid4() ), 0, 20 );
		for ( $i = 0; $i < 10; $i++ ) {
			$job = Mediline_Integrations_DB::claim_due( $worker, 300 );
			if ( ! is_array( $job ) ) {
				break;
			}
			try {
				$result = self::process_job( $job );
			} catch ( Throwable $exception ) {
				$result = new WP_Error( 'mediline_worker_exception', 'Integration worker exception.', array( 'retryable' => true ) );
			}
			if ( is_wp_error( $result ) ) {
				self::handle_job_error( $job, $result );
				continue;
			}
			Mediline_Integrations_DB::complete( $job['id'], $job['lock_token'], is_array( $result ) ? $result : array() );
		}
	}

	/** @return array<string,string>|WP_Error */
	private static function process_job( array $job ) {
		$settings = mediline_integrations_settings();
		if ( self::JOB_PIPEDRIVE === $job['job_type'] ) {
			if ( ! Mediline_Integrations_Pipedrive::configured() ) {
				return new WP_Error( 'pipedrive_not_configured', 'Pipedrive is not configured.', array( 'retryable' => true, 'retry_after' => HOUR_IN_SECONDS ) );
			}
			$client    = new Mediline_Integrations_Pipedrive( $settings, mediline_integrations_secret( 'pipedrive_api_token' ) );
			$person_id = $client->ensure_person( $job['payload'] );
			if ( is_wp_error( $person_id ) ) {
				return $person_id;
			}
			$deal_id = $client->ensure_deal( $job['payload'], $person_id );
			if ( is_wp_error( $deal_id ) ) {
				return $deal_id;
			}
			if ( 'wc-order' === $job['source_type'] && function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( absint( $job['source_id'] ) );
				if ( $order ) {
					$order->update_meta_data( '_mediline_pipedrive_person_id', (string) $person_id );
					$order->update_meta_data( '_mediline_pipedrive_deal_id', (string) $deal_id );
					$order->save();
				}
			}
			return array( 'person_id' => (string) $person_id, 'deal_id' => (string) $deal_id );
		}

		if ( self::JOB_PAP_SALE === $job['job_type'] ) {
			if ( ! Mediline_Integrations_Pipedrive::configured() || ! Mediline_Integrations_PAP::configured() ) {
				return new WP_Error( 'sale_integrations_not_configured', 'PAP/Pipedrive sale integration is not configured.', array( 'retryable' => true, 'retry_after' => HOUR_IN_SECONDS ) );
			}
			$deal_id = absint( $job['payload']['deal_id'] ?? 0 );
			$provenance = Mediline_Integrations_DB::completed_submission_for_deal( $deal_id );
			if ( is_wp_error( $provenance ) ) {
				return $provenance;
			}
			if ( ! is_array( $provenance ) ) {
				return new WP_Error(
					'sale_unmanaged_deal',
					'No completed local submission is bound to this Pipedrive Deal.',
					array( 'retryable' => (int) $job['attempts'] < 3, 'retry_after' => 60 )
				);
			}
			$client  = new Mediline_Integrations_Pipedrive( $settings, mediline_integrations_secret( 'pipedrive_api_token' ) );
			$deal    = $client->get_deal( $deal_id );
			if ( is_wp_error( $deal ) ) {
				return $deal;
			}
			$status = $deal['status'] ?? '';
			$status = is_array( $status ) ? ( $status['value'] ?? '' ) : $status;
			$status = strtolower( (string) $status );
			if ( 'won' !== $status ) {
				return new WP_Error( 'sale_deal_not_won', 'The Pipedrive Deal is no longer Won.', array( 'retryable' => true, 'retry_after' => 300 ) );
			}
			$expected_pipeline = absint( $settings['pipedrive_pipeline_id'] ?? 0 );
			$actual_pipeline   = $deal['pipeline_id'] ?? 0;
			$actual_pipeline   = is_array( $actual_pipeline ) ? ( $actual_pipeline['id'] ?? ( $actual_pipeline['value'] ?? 0 ) ) : $actual_pipeline;
			if ( $expected_pipeline && absint( $actual_pipeline ) !== $expected_pipeline ) {
				return new WP_Error( 'sale_other_pipeline', 'The Pipedrive Deal is outside the configured pipeline.', array( 'retryable' => true, 'retry_after' => 300 ) );
			}
			$value    = $deal['value'] ?? 0;
			$currency = $deal['currency'] ?? 'EUR';
			if ( is_array( $value ) ) {
				$currency = $value['currency'] ?? $currency;
				$value    = $value['value'] ?? 0;
			}
			if ( 'wc-order' === $provenance['source_type'] && function_exists( 'wc_get_order' ) ) {
				$trusted_order = wc_get_order( absint( $provenance['source_id'] ) );
				if ( ! $trusted_order ) {
					return new WP_Error( 'sale_order_missing', 'The local WooCommerce order is unavailable.', array( 'retryable' => true, 'retry_after' => 300 ) );
				}
				$value    = (float) $trusted_order->get_total();
				$currency = (string) $trusted_order->get_currency();
			}
			if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
				return new WP_Error( 'sale_zero_value', 'The Pipedrive Deal has no positive sale value.', array( 'retryable' => true, 'retry_after' => 300 ) );
			}
			$trusted = is_array( $provenance['payload'] ?? null ) ? $provenance['payload'] : array();
			$fixed_affiliate = ! empty( $trusted['pap_affiliate_fixed'] );
			// Public AffiliateID values are client-controlled. Only a server-pinned
			// generated-store owner may bypass PAP visitor attribution.
			$sale = array(
				'pap_visitor_id'   => $fixed_affiliate ? '' : Mediline_Integrations_PAP::visitor_id( $trusted['pap_visitor_id'] ?? '' ),
				'pap_affiliate_id' => $fixed_affiliate ? Mediline_Integrations_PAP::affiliate_id( $trusted['pap_affiliate_id'] ?? '' ) : '',
				'pap_affiliate_trusted' => $fixed_affiliate,
				'order_id'         => 'pipedrive-deal-' . $deal_id,
				'total_cost'       => $value,
				'currency'         => $currency,
				'product_id'       => self::text( $deal['title'] ?? '', 255 ),
				'data1'            => (string) $deal_id,
			);
			$pap    = new Mediline_Integrations_PAP( $settings );
			$result = $pap->register_sale( $sale );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'deal_id' => (string) $deal_id, 'sale_id' => (string) $result['order_id'] );
		}

		return new WP_Error( 'unknown_job_type', 'Unknown integration job type.', array( 'retryable' => false ) );
	}

	private static function handle_job_error( array $job, WP_Error $error ) {
		$data      = $error->get_error_data();
		$data      = is_array( $data ) ? $data : array();
		$retryable = ! isset( $data['retryable'] ) || (bool) $data['retryable'];
		$code      = sanitize_key( (string) $error->get_error_code() ) ?: 'integration_error';
		if ( ! $retryable ) {
			Mediline_Integrations_DB::fail( $job['id'], $job['lock_token'], $code );
			return;
		}
		$delay = absint( $data['retry_after'] ?? 0 );
		if ( ! $delay ) {
			$delay = min( HOUR_IN_SECONDS, 30 * ( 2 ** min( 7, max( 0, (int) $job['attempts'] - 1 ) ) ) + wp_rand( 0, 30 ) );
		}
		Mediline_Integrations_DB::retry( $job['id'], $job['lock_token'], $code, $delay );
	}

	private static function submission_id( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value ) ) {
			return $value;
		}
		return wp_generate_uuid4();
	}

	private static function site_language() {
		if ( function_exists( 'mediline_partners_current_language' ) ) {
			return mediline_partners_current_language();
		}
		return substr( determine_locale(), 0, 2 );
	}

	private static function text( $value, $max ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

}
