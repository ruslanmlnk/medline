<?php
/**
 * Minimal, retry-aware Pipedrive API v2 client.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mediline_Integrations_Pipedrive {
	/** @var array<string,mixed> */
	private $settings;

	/** @var string */
	private $api_token;

	public function __construct( array $settings, $api_token ) {
		$this->settings  = $settings;
		$this->api_token = trim( (string) $api_token );
	}

	public static function configured() {
		$settings = function_exists( 'mediline_integrations_settings' ) ? mediline_integrations_settings() : array();
		return self::credentials_configured() && self::required_fields_configured( $settings );
	}

	public static function credentials_configured() {
		$settings = function_exists( 'mediline_integrations_settings' ) ? mediline_integrations_settings() : array();
		$token    = function_exists( 'mediline_integrations_secret' ) ? mediline_integrations_secret( 'pipedrive_api_token' ) : '';
		$base     = self::normalize_api_base( $settings['pipedrive_api_base'] ?? '' );
		return ! empty( $settings['pipedrive_enabled'] ) && ! empty( $base ) && ! empty( $token );
	}

	/**
	 * Accept only the canonical HTTPS origin of an official Pipedrive host.
	 * This prevents a malformed setting from sending the API token elsewhere.
	 */
	public static function normalize_api_base( $value ) {
		$url   = is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), array( 'https' ) ) : '';
		$parts = $url ? wp_parse_url( $url ) : false;
		if ( ! is_array( $parts ) || 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return '';
		}
		$host = strtolower( rtrim( (string) ( $parts['host'] ?? '' ), '.' ) );
		if ( ! $host || ( 'pipedrive.com' !== $host && ! str_ends_with( $host, '.pipedrive.com' ) ) ) {
			return '';
		}
		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! empty( $parts['query'] ) || ! empty( $parts['fragment'] ) ) {
			return '';
		}
		if ( isset( $parts['port'] ) && 443 !== absint( $parts['port'] ) ) {
			return '';
		}
		if ( isset( $parts['path'] ) && '' !== trim( (string) $parts['path'], '/' ) ) {
			return '';
		}
		return 'https://' . $host;
	}

	public static function required_fields_configured( array $settings ) {
		foreach ( self::required_field_suffixes() as $suffix ) {
			$code = sanitize_key( (string) ( $settings[ 'pipedrive_field_' . $suffix ] ?? '' ) );
			if ( ! $code ) {
				return false;
			}
		}
		return true;
	}

	/** @return string[] */
	public static function required_field_suffixes() {
		return array( 'language', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'pap_visitor_id', 'pap_affiliate_id', 'submission_id' );
	}

	/**
	 * Execute a Pipedrive API request and return its decoded data node.
	 *
	 * @return mixed|WP_Error
	 */
	public function request( $method, $path, array $body = array(), array $query = array() ) {
		$base = self::normalize_api_base( $this->settings['pipedrive_api_base'] ?? '' );
		if ( ! $this->api_token || ! $base ) {
			return new WP_Error( 'mediline_pipedrive_configuration', 'Pipedrive HTTPS API base and API token are required.', array( 'retryable' => false ) );
		}

		$url = $base . '/' . ltrim( (string) $path, '/' );
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		$args = array(
			'method'      => strtoupper( (string) $method ),
			'timeout'     => 8,
			'redirection' => 0,
			'headers'     => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'x-api-token'  => $this->api_token,
			),
		);
		if ( 'GET' !== $args['method'] && $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mediline_pipedrive_network',
				$response->get_error_message(),
				array( 'retryable' => true, 'status' => 0 )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$decoded = '' === trim( $raw ) ? array() : json_decode( $raw, true );
		if ( $status < 200 || $status >= 300 ) {
			$retryable = 429 === $status || $status >= 500;
			$retry     = (int) wp_remote_retrieve_header( $response, 'x-ratelimit-reset' );
			if ( ! $retry ) {
				$retry = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			}
			$message = 'Pipedrive API request failed.';
			if ( is_array( $decoded ) ) {
				$candidate = $decoded['error_info'] ?? ( $decoded['error'] ?? ( $decoded['message'] ?? '' ) );
				if ( is_scalar( $candidate ) && $candidate ) {
					$message = sanitize_text_field( (string) $candidate );
				}
			}
			return new WP_Error(
				'mediline_pipedrive_http_' . $status,
				$message,
				array( 'retryable' => $retryable, 'status' => $status, 'retry_after' => max( 0, $retry ) )
			);
		}
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'mediline_pipedrive_json', 'Pipedrive returned invalid JSON.', array( 'retryable' => true, 'status' => $status ) );
		}
		return array_key_exists( 'data', $decoded ) ? $decoded['data'] : $decoded;
	}

	/**
	 * Find or create a Person using an exact email (or phone) match.
	 *
	 * @return int|WP_Error
	 */
	public function ensure_person( array $submission ) {
		$email = strtolower( sanitize_email( (string) ( $submission['email'] ?? '' ) ) );
		$phone = sanitize_text_field( (string) ( $submission['phone'] ?? '' ) );
		if ( ! $email && ! $phone ) {
			return new WP_Error( 'mediline_pipedrive_contact', 'A valid email or phone is required for Pipedrive.', array( 'retryable' => false ) );
		}

		$needle = $email ?: preg_replace( '/[^0-9+]/', '', $phone );
		$lock   = 'mediline_pd_contact_' . substr( hash( 'sha256', $needle ), 0, 32 );
		if ( ! add_option( $lock, time(), '', false ) ) {
			$locked_at = (int) get_option( $lock, 0 );
			if ( $locked_at && $locked_at < time() - 60 ) {
				delete_option( $lock );
				if ( add_option( $lock, time(), '', false ) ) {
					return $this->ensure_person_under_lock( $submission, $email, $phone, $lock );
				}
			}
			$existing = $this->find_person( $email, $phone );
			if ( is_wp_error( $existing ) || $existing ) {
				return $existing;
			}
			return new WP_Error( 'mediline_pipedrive_contact_locked', 'Contact creation is already in progress.', array( 'retryable' => true ) );
		}

		return $this->ensure_person_under_lock( $submission, $email, $phone, $lock );
	}

	/** @return int|WP_Error */
	private function ensure_person_under_lock( array $submission, $email, $phone, $lock ) {
		try {
			$existing = $this->find_person( $email, $phone );
			if ( is_wp_error( $existing ) || $existing ) {
				return $existing;
			}
			$name = trim( sanitize_text_field( (string) ( $submission['first_name'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $submission['last_name'] ?? '' ) ) );
			if ( ! $name ) {
				$name = $email ?: $phone;
			}
			$payload = array( 'name' => $name );
			if ( $email ) {
				$payload['emails'] = array( array( 'value' => $email, 'primary' => true, 'label' => 'work' ) );
			}
			if ( $phone ) {
				$payload['phones'] = array( array( 'value' => $phone, 'primary' => true, 'label' => 'work' ) );
			}
			$created = $this->request( 'POST', '/api/v2/persons', $payload );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$id = absint( is_array( $created ) ? ( $created['id'] ?? 0 ) : 0 );
			return $id ?: new WP_Error( 'mediline_pipedrive_person_response', 'Pipedrive did not return the Person ID.', array( 'retryable' => true ) );
		} finally {
			delete_option( $lock );
		}
	}

	/** @return int|WP_Error */
	private function find_person( $email, $phone ) {
		$term  = $email ?: $phone;
		$field = $email ? 'email' : 'phone';
		$data  = $this->request(
			'GET',
			'/api/v2/persons/search',
			array(),
			array( 'term' => $term, 'fields' => $field, 'exact_match' => 'true', 'limit' => 10 )
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$items = is_array( $data ) ? ( $data['items'] ?? $data ) : array();
		foreach ( is_array( $items ) ? $items : array() as $result ) {
			$item = is_array( $result ) && isset( $result['item'] ) ? $result['item'] : $result;
			if ( ! is_array( $item ) ) {
				continue;
			}
			$values = $email ? ( $item['emails'] ?? ( $item['email'] ?? array() ) ) : ( $item['phones'] ?? ( $item['phone'] ?? array() ) );
			foreach ( is_array( $values ) ? $values : array( $values ) as $value ) {
				$value = is_array( $value ) ? ( $value['value'] ?? '' ) : $value;
				$left  = $email ? strtolower( trim( (string) $value ) ) : preg_replace( '/[^0-9+]/', '', (string) $value );
				$right = $email ? strtolower( trim( (string) $email ) ) : preg_replace( '/[^0-9+]/', '', (string) $phone );
				if ( $left && hash_equals( $right, $left ) ) {
					return absint( $item['id'] ?? 0 );
				}
			}
		}
		return 0;
	}

	/**
	 * Idempotently create the Deal for a submission.
	 *
	 * @return int|WP_Error
	 */
	public function ensure_deal( array $submission, $person_id ) {
		$submission_id = sanitize_text_field( (string) ( $submission['submission_id'] ?? '' ) );
		$field_code    = sanitize_key( (string) ( $this->settings['pipedrive_field_submission_id'] ?? '' ) );
		if ( ! $submission_id || ! $field_code ) {
			return new WP_Error( 'mediline_pipedrive_idempotency', 'Pipedrive Submission ID field is required for safe Deal creation.', array( 'retryable' => true, 'retry_after' => HOUR_IN_SECONDS ) );
		}
		$existing = $this->find_deal_by_submission( $submission_id, $field_code );
		if ( is_wp_error( $existing ) || $existing ) {
			return $existing;
		}

		$source = sanitize_text_field( (string) ( $submission['form_id'] ?? 'submission' ) );
		$name   = trim( sanitize_text_field( (string) ( $submission['first_name'] ?? '' ) ) . ' ' . sanitize_text_field( (string) ( $submission['last_name'] ?? '' ) ) );
		$title  = sprintf( 'Mediline %s%s', $source, $name ? ' — ' . $name : '' );
		$body   = array(
			'title'         => self::truncate( $title, 255 ),
			'person_id'     => absint( $person_id ),
			'status'        => 'open',
			'custom_fields' => $this->custom_fields( $submission ),
		);
		$pipeline = absint( $this->settings['pipedrive_pipeline_id'] ?? 0 );
		$stage    = absint( $this->settings['pipedrive_stage_id'] ?? 0 );
		if ( $pipeline ) {
			$body['pipeline_id'] = $pipeline;
		}
		if ( $stage ) {
			$body['stage_id'] = $stage;
		}
		if ( isset( $submission['value'] ) && is_numeric( $submission['value'] ) ) {
			$body['value']    = round( (float) $submission['value'], 2 );
			$body['currency'] = strtoupper( substr( sanitize_key( (string) ( $submission['currency'] ?? 'EUR' ) ), 0, 3 ) );
		}

		$created = $this->request( 'POST', '/api/v2/deals', $body );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$id = absint( is_array( $created ) ? ( $created['id'] ?? 0 ) : 0 );
		return $id ?: new WP_Error( 'mediline_pipedrive_deal_response', 'Pipedrive did not return the Deal ID.', array( 'retryable' => true ) );
	}

	/** A readable checkout snapshot; never include order keys or arbitrary private metadata. */
	public static function order_note_content( $order ) {
		$html = '<h2>Order #' . esc_html( $order->get_order_number() ) . ' — checkout details</h2>';
		$row = static function ( $label, $value ) {
			return '<b>' . esc_html( $label ) . ':</b> ' . nl2br( esc_html( (string) $value ) ) . '<br>';
		};
		$html .= $row( 'Payment method', $order->get_payment_method_title() ?: $order->get_payment_method() );
		$html .= $row( 'Payment code', $order->get_payment_method() );
		if ( $order->get_meta( '_mediline_crypto_invoice', true ) ) {
			$html .= $row( 'Crypto network', $order->get_meta( '_mediline_crypto_network', true ) );
			$html .= $row( 'Crypto status', $order->get_meta( '_mediline_crypto_status', true ) ?: 'awaiting' );
			$html .= $row( 'Crypto amount / received', $order->get_meta( '_mediline_crypto_amount', true ) . ' / ' . $order->get_meta( '_mediline_crypto_received', true ) );
			$html .= $row( 'Crypto transactions', implode( ', ', (array) $order->get_meta( '_mediline_crypto_transactions', true ) ) );
		}
		$html .= $row( 'Order status', $order->get_status() );
		foreach ( array( 'billing' => 'Billing / customer', 'shipping' => 'Shipping address' ) as $type => $title ) {
			$html .= '<h3>' . esc_html( $title ) . '</h3>';
			foreach ( $order->get_address( $type ) as $key => $value ) {
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$html .= $row( ucwords( str_replace( '_', ' ', $key ) ), $value );
				}
			}
		}
		$currency = $order->get_currency();
		$html .= '<h3>Products</h3>';
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$html .= $row( $item->get_name(), $item->get_quantity() . ' ×; total ' . $item->get_total() . ' ' . $currency );
			if ( $product && $product->get_sku() ) {
				$html .= $row( 'SKU', $product->get_sku() );
			}
			foreach ( $item->get_formatted_meta_data() as $meta ) {
				$html .= $row( wp_strip_all_tags( $meta->display_key ), wp_strip_all_tags( $meta->display_value ) );
			}
		}
		$html .= '<h3>Totals</h3>';
		foreach ( array( 'Subtotal' => $order->get_subtotal(), 'Discount' => $order->get_discount_total(), 'Shipping' => $order->get_shipping_total(), 'Tax' => $order->get_total_tax(), 'Total' => $order->get_total() ) as $label => $value ) {
			$html .= $row( $label, $value . ' ' . $currency );
		}
		$html .= $row( 'Delivery method', $order->get_shipping_method() );
		$html .= $row( 'Customer comment', $order->get_customer_note() );
		foreach ( array( '_mediline_language' => 'Language', '_mediline_affiliate_id' => 'Partner ID', '_mediline_privacy_consent' => 'Privacy consent recorded at' ) as $key => $label ) {
			$value = $order->get_meta( $key, true );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$html .= $row( $label, $value );
			}
		}
		return $html;
	}

	/** Create/update one pinned note, reconciling a lost API response before retrying. */
	public function sync_order_note( $order, $deal_id ) {
		$deal_id = absint( $deal_id );
		$marker = 'Mediline checkout reference: ' . hash( 'sha256', home_url( '/' ) . '|wc-order|' . $order->get_id() );
		$content = self::order_note_content( $order ) . '<p>' . esc_html( $marker ) . '</p>';
		if ( strlen( $content ) > 95000 ) {
			return new WP_Error( 'mediline_order_note_size', 'Order details exceed the Pipedrive note limit.', array( 'retryable' => false ) );
		}
		$lock = 'mediline_pd_note_' . $deal_id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 300 ) {
				delete_option( $lock );
			}
			return new WP_Error( 'mediline_order_note_locked', 'Order note sync is in progress.', array( 'retryable' => true ) );
		}
		try {
			$note_id = 0;
			for ( $start = 0; $start < 10000; $start += 100 ) {
				$notes = $this->request( 'GET', '/api/v1/notes', array(), array( 'deal_id' => $deal_id, 'start' => $start, 'limit' => 100 ) );
				if ( is_wp_error( $notes ) ) { return $notes; }
				foreach ( (array) $notes as $note ) {
					if ( false !== strpos( (string) ( $note['content'] ?? '' ), $marker ) ) {
						$note_id = absint( $note['id'] );
						break;
					}
				}
				if ( $note_id || count( (array) $notes ) < 100 ) { break; }
			}
			if ( $start >= 10000 && ! $note_id ) {
				return new WP_Error( 'mediline_order_note_pagination', 'Cannot safely reconcile order note.', array( 'retryable' => false ) );
			}
			$result = $this->request( $note_id ? 'PUT' : 'POST', '/api/v1/notes' . ( $note_id ? '/' . $note_id : '' ), array( 'content' => $content, 'deal_id' => $deal_id, 'pinned_to_deal_flag' => 1 ) );
			if ( is_wp_error( $result ) ) { return $result; }
			if ( empty( $result['id'] ) ) {
				return new WP_Error( 'mediline_order_note_response', 'Pipedrive did not return a note ID.', array( 'retryable' => true ) );
			}
			$order->update_meta_data( '_mediline_pipedrive_note_id', absint( $result['id'] ) );
			$order->save();
			return absint( $result['id'] );
		} finally {
			delete_option( $lock );
		}
	}

	/** @return int|WP_Error */
	private function find_deal_by_submission( $submission_id, $field_code ) {
		$data = $this->request(
			'GET',
			'/api/v2/deals/search',
			array(),
			array( 'term' => $submission_id, 'fields' => 'custom_fields', 'exact_match' => 'true', 'limit' => 10 )
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$items = is_array( $data ) ? ( $data['items'] ?? $data ) : array();
		foreach ( is_array( $items ) ? $items : array() as $result ) {
			$item = is_array( $result ) && isset( $result['item'] ) ? $result['item'] : $result;
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			$value = $this->field_value( $item, $field_code );
			if ( '' === $value ) {
				$full = $this->request(
					'GET',
					'/api/v2/deals/' . absint( $item['id'] ),
					array(),
					array( 'custom_fields' => $field_code )
				);
				if ( is_wp_error( $full ) ) {
					return $full;
				}
				$value = $this->field_value( $full, $field_code );
			}
			if ( hash_equals( (string) $submission_id, (string) $value ) ) {
				return absint( $item['id'] );
			}
		}
		return 0;
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_deal( $deal_id ) {
		$data = $this->request( 'GET', '/api/v2/deals/' . absint( $deal_id ) );
		return is_array( $data ) ? $data : ( is_wp_error( $data ) ? $data : new WP_Error( 'mediline_pipedrive_deal', 'Pipedrive Deal was not returned.', array( 'retryable' => true ) ) );
	}

	/** @return array<string,mixed> */
	public function custom_fields( array $submission ) {
		$map = array(
			'language'         => 'language',
			'utm_source'       => 'utm_source',
			'utm_medium'       => 'utm_medium',
			'utm_campaign'     => 'utm_campaign',
			'utm_term'         => 'utm_term',
			'utm_content'      => 'utm_content',
			'pap_visitor_id'   => 'pap_visitor_id',
			'pap_affiliate_id' => 'pap_affiliate_id',
			'submission_id'    => 'submission_id',
			'form_id'          => 'form_id',
			'landing_url'      => 'landing_url',
			'messenger'        => 'messenger',
			'source_id'        => 'source_id',
		);
		$fields = array();
		foreach ( $map as $setting_suffix => $payload_key ) {
			$code = sanitize_key( (string) ( $this->settings[ 'pipedrive_field_' . $setting_suffix ] ?? '' ) );
			if ( ! $code || ! isset( $submission[ $payload_key ] ) || '' === (string) $submission[ $payload_key ] ) {
				continue;
			}
			$value = sanitize_text_field( (string) $submission[ $payload_key ] );
			if ( 'language' === $payload_key ) {
				$value = self::normalize_language( $value );
			}
			$fields[ $code ] = self::truncate( $value, 'landing_url' === $payload_key ? 2000 : 255 );
		}
		return $fields;
	}

	/**
	 * Provision the account-specific deal fields and return setting-key => code.
	 * Existing fields with a different type are rejected rather than mutated.
	 *
	 * @return array<string,string>|WP_Error
	 */
	public function provision_fields() {
		$existing = $this->request( 'GET', '/api/v2/dealFields', array(), array( 'limit' => 500 ) );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}
		$by_name = array();
		$existing_rows = is_array( $existing ) && isset( $existing['items'] ) && is_array( $existing['items'] ) ? $existing['items'] : $existing;
		foreach ( is_array( $existing_rows ) ? $existing_rows : array() as $field ) {
			$field_name = is_array( $field ) ? ( $field['field_name'] ?? ( $field['name'] ?? '' ) ) : '';
			if ( $field_name ) {
				$by_name[ strtolower( (string) $field_name ) ] = $field;
			}
		}
		$result = array();
		foreach ( self::field_definitions() as $suffix => $definition ) {
			$name  = $definition['name'];
			$field = $by_name[ strtolower( $name ) ] ?? null;
			if ( $field && ! in_array( (string) ( $field['field_type'] ?? $field['type'] ?? '' ), array( 'varchar', 'text' ), true ) ) {
				return new WP_Error( 'mediline_pipedrive_field_type', sprintf( 'Pipedrive field "%s" exists with an incompatible type.', $name ), array( 'retryable' => false ) );
			}
			if ( ! $field ) {
				$field = $this->request( 'POST', '/api/v2/dealFields', array( 'field_name' => $name, 'field_type' => $definition['type'] ) );
				if ( is_wp_error( $field ) ) {
					return $field;
				}
			}
			$code = sanitize_key( (string) ( $field['field_code'] ?? ( $field['key'] ?? ( $field['code'] ?? '' ) ) ) );
			if ( ! $code ) {
				return new WP_Error( 'mediline_pipedrive_field_code', sprintf( 'Pipedrive did not return the code for "%s".', $name ), array( 'retryable' => true ) );
			}
			$result[ 'pipedrive_field_' . $suffix ] = $code;
		}
		return $result;
	}

	/** @return array<string,array<string,string>> */
	public static function field_definitions() {
		return array(
			'language'         => array( 'name' => 'MEDILINE Language', 'type' => 'varchar' ),
			'utm_source'       => array( 'name' => 'MEDILINE UTM Source', 'type' => 'varchar' ),
			'utm_medium'       => array( 'name' => 'MEDILINE UTM Medium', 'type' => 'varchar' ),
			'utm_campaign'     => array( 'name' => 'MEDILINE UTM Campaign', 'type' => 'varchar' ),
			'utm_term'         => array( 'name' => 'MEDILINE UTM Term', 'type' => 'varchar' ),
			'utm_content'      => array( 'name' => 'MEDILINE UTM Content', 'type' => 'varchar' ),
			'pap_visitor_id'   => array( 'name' => 'MEDILINE PAP Visitor ID', 'type' => 'varchar' ),
			'pap_affiliate_id' => array( 'name' => 'MEDILINE PAP Affiliate ID', 'type' => 'varchar' ),
			'submission_id'    => array( 'name' => 'MEDILINE Submission ID', 'type' => 'varchar' ),
			'form_id'          => array( 'name' => 'MEDILINE Form ID', 'type' => 'varchar' ),
			'landing_url'      => array( 'name' => 'MEDILINE Landing URL', 'type' => 'text' ),
			'messenger'        => array( 'name' => 'MEDILINE Messenger', 'type' => 'varchar' ),
			'source_id'        => array( 'name' => 'MEDILINE Source ID', 'type' => 'varchar' ),
		);
	}

	public function field_value( array $deal, $code ) {
		$custom = isset( $deal['custom_fields'] ) && is_array( $deal['custom_fields'] ) ? $deal['custom_fields'] : array();
		$value  = $custom[ $code ] ?? ( $deal[ $code ] ?? '' );
		if ( is_array( $value ) ) {
			$value = $value['value'] ?? '';
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	public static function normalize_language( $language ) {
		$language = strtolower( sanitize_key( (string) $language ) );
		if ( 'sp' === $language ) {
			return 'es';
		}
		return preg_match( '/^[a-z]{2,3}$/', $language ) ? $language : 'en';
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}
}
