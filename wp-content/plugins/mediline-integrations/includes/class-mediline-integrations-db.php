<?php
/**
 * Durable encrypted outbox storage for external integrations.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for Pipedrive and Post Affiliate Pro background jobs.
 */
final class Mediline_Integrations_DB {
	const DB_VERSION = '1.3.0';

	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_COMPLETED  = 'completed';
	const STATUS_FAILED     = 'failed';

	/**
	 * Get the site-prefixed outbox table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'mediline_integration_events';
	}

	/**
	 * Create or upgrade the outbox table.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_key varchar(191) NOT NULL,
			job_type varchar(64) NOT NULL,
			source_type varchar(32) NOT NULL DEFAULT '',
			source_id varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			max_attempts smallint(5) unsigned NOT NULL DEFAULT 8,
			available_at datetime NOT NULL,
			locked_at datetime NULL,
			lock_token varchar(64) NOT NULL DEFAULT '',
			locked_by varchar(96) NOT NULL DEFAULT '',
			wake_requested tinyint(1) unsigned NOT NULL DEFAULT 0,
			payload_ciphertext longtext NOT NULL,
			remote_person_id varchar(191) NOT NULL DEFAULT '',
			remote_deal_id varchar(191) NOT NULL DEFAULT '',
			remote_sale_id varchar(191) NOT NULL DEFAULT '',
			last_error varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_key (event_key),
			KEY due_jobs (status,available_at),
			KEY typed_jobs (job_type,status),
			KEY retention (status,updated_at),
			KEY source_lookup (source_type,source_id(150)),
			KEY remote_deal_lookup (remote_deal_id),
			KEY lock_lookup (lock_token)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'mediline_integrations_db_version', self::DB_VERSION, false );
	}

	/**
	 * Upgrade storage when the plugin schema changes.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'mediline_integrations_db_version' ) ) {
			self::activate();
		}
	}

	/**
	 * Enqueue a job once. Reusing event_key returns the original job ID unchanged.
	 *
	 * @param string $event_key Stable idempotency key (never an email address).
	 * @param string $job_type  Machine-readable worker job type.
	 * @param array  $payload   Data encrypted before it reaches MySQL.
	 * @param array  $args      source_type, source_id, max_attempts, available_at.
	 * @return int|WP_Error Job ID or a safe error.
	 */
	public static function enqueue( $event_key, $job_type, array $payload, array $args = array() ) {
		global $wpdb;

		$event_key = self::event_key( $event_key );
		$job_type  = self::job_type( $job_type );
		if ( '' === $event_key || '' === $job_type ) {
			return new WP_Error( 'mediline_job_invalid_identifier', __( 'The integration job identifier is invalid.', 'mediline-integrations' ) );
		}

		$raw_source_type = $args['source_type'] ?? '';
		$raw_source_id   = $args['source_id'] ?? '';
		if ( ! is_scalar( $raw_source_type ) || ! is_scalar( $raw_source_id ) ) {
			return new WP_Error( 'mediline_job_invalid_source', __( 'The integration job source is invalid.', 'mediline-integrations' ) );
		}

		$source_type = self::source_type( $raw_source_type );
		$source_id   = self::source_id( $raw_source_id );
		if (
			( '' !== trim( (string) $raw_source_type ) && '' === $source_type ) ||
			( '' !== trim( (string) $raw_source_id ) && '' === $source_id )
		) {
			return new WP_Error( 'mediline_job_invalid_source', __( 'The integration job source is invalid.', 'mediline-integrations' ) );
		}

		$max_attempts = min( 100, max( 1, absint( $args['max_attempts'] ?? 8 ) ) );
		$available_at = self::datetime( $args['available_at'] ?? null, self::now() );
		if ( '' === $available_at ) {
			return new WP_Error( 'mediline_job_invalid_schedule', __( 'The integration job schedule is invalid.', 'mediline-integrations' ) );
		}

		$ciphertext = Mediline_Integrations_Crypto::encrypt( $payload );
		if ( is_wp_error( $ciphertext ) ) {
			return $ciphertext;
		}

		$now   = self::now();
		$table = self::table_name();
		$sql   = $wpdb->prepare(
			"INSERT INTO {$table}
				(event_key,job_type,source_type,source_id,status,attempts,max_attempts,available_at,payload_ciphertext,created_at,updated_at)
			VALUES (%s,%s,%s,%s,%s,0,%d,%s,%s,%s,%s)
			ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
			$event_key,
			$job_type,
			$source_type,
			$source_id,
			self::STATUS_PENDING,
			$max_attempts,
			$available_at,
			$ciphertext,
			$now,
			$now
		);

		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			return new WP_Error( 'mediline_job_enqueue_failed', __( 'The integration job could not be queued.', 'mediline-integrations' ) );
		}

		$job_id = absint( $wpdb->insert_id );
		if ( ! $job_id ) {
			$job_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE event_key=%s", $event_key ) ) );
		}

		return $job_id ?: new WP_Error( 'mediline_job_enqueue_failed', __( 'The integration job could not be queued.', 'mediline-integrations' ) );
	}

	/**
	 * Atomically claim the oldest due job and decrypt it for the worker.
	 *
	 * Stale processing locks may be reclaimed. Each claim increments attempts.
	 *
	 * @param string $worker_id Safe worker label for diagnostics.
	 * @param int    $lock_ttl  Seconds until an abandoned processing lock is stale.
	 * @return array|null|WP_Error Worker row with payload and lock_token, null when empty.
	 */
	public static function claim_due( $worker_id = '', $lock_ttl = 300 ) {
		global $wpdb;

		$worker_id = self::worker_id( $worker_id );
		$lock_ttl  = min( DAY_IN_SECONDS, max( 30, absint( $lock_ttl ) ) );
		$now       = self::now();
		$stale_at  = gmdate( 'Y-m-d H:i:s', time() - $lock_ttl );
		$table     = self::table_name();
		$token     = str_replace( '-', '', wp_generate_uuid4() );

		// Do not leave an exhausted abandoned job permanently marked processing.
		// A fresh Won webhook may have requested one more PAP attempt while the
		// previous worker still held the lock. Consume that wake atomically here so
		// a crashed worker cannot turn the new business event into a terminal fail.
		// Keep wake_requested last: MySQL evaluates single-table assignments from
		// left to right, and every revival expression must see the original flag.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status=IF(job_type='pap_sale' AND wake_requested=1,%s,%s),
				     attempts=IF(job_type='pap_sale' AND wake_requested=1,0,attempts),
				     available_at=IF(job_type='pap_sale' AND wake_requested=1,%s,available_at),
				     lock_token='', locked_by='', locked_at=NULL,
				     last_error=IF(job_type='pap_sale' AND wake_requested=1,'',%s),
				     completed_at=IF(job_type='pap_sale' AND wake_requested=1,NULL,completed_at),
				     remote_sale_id=IF(job_type='pap_sale' AND wake_requested=1,'',remote_sale_id),
				     updated_at=%s, wake_requested=0
				 WHERE status=%s AND locked_at IS NOT NULL AND locked_at<=%s AND attempts>=max_attempts",
				self::STATUS_PENDING,
				self::STATUS_FAILED,
				$now,
				'lock_timeout_max_attempts',
				$now,
				self::STATUS_PROCESSING,
				$stale_at
			)
		);

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status=%s, attempts=attempts+1, lock_token=%s, locked_by=%s,
				     locked_at=%s, updated_at=%s
				 WHERE (
				     (status=%s AND available_at<=%s AND attempts<max_attempts)
				     OR
				     (status=%s AND locked_at IS NOT NULL AND locked_at<=%s AND attempts<max_attempts)
				 )
				 ORDER BY available_at ASC, id ASC
				 LIMIT 1",
				self::STATUS_PROCESSING,
				$token,
				$worker_id,
				$now,
				$now,
				self::STATUS_PENDING,
				$now,
				self::STATUS_PROCESSING,
				$stale_at
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'mediline_job_claim_failed', __( 'The integration queue could not be read.', 'mediline-integrations' ) );
		}
		if ( 0 === (int) $result ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE lock_token=%s", $token ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'mediline_job_claim_lost', __( 'The integration job lock could not be confirmed.', 'mediline-integrations' ) );
		}

		$payload = Mediline_Integrations_Crypto::decrypt( $row['payload_ciphertext'] );
		if ( is_wp_error( $payload ) ) {
			self::fail( (int) $row['id'], $token, 'payload_decryption_failed' );
			return new WP_Error( 'mediline_job_payload_unavailable', __( 'The integration job payload could not be opened.', 'mediline-integrations' ) );
		}

		return self::worker_row( $row, $payload );
	}

	/**
	 * Complete a locked job and record only non-sensitive remote identifiers.
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $lock_token Token returned by claim_due().
	 * @param array  $remote_ids person_id, deal_id and/or sale_id.
	 * @return true|WP_Error
	 */
	public static function complete( $job_id, $lock_token, array $remote_ids = array() ) {
		global $wpdb;

		$job_id     = absint( $job_id );
		$lock_token = self::lock_token( $lock_token );
		if ( ! $job_id || '' === $lock_token ) {
			return new WP_Error( 'mediline_job_invalid_lock', __( 'The integration job lock is invalid.', 'mediline-integrations' ) );
		}

		$now  = self::now();
		$data = array(
			'status'       => self::STATUS_COMPLETED,
			'wake_requested'=> 0,
			'lock_token'   => '',
			'locked_by'    => '',
			'locked_at'    => null,
			'last_error'   => '',
			'updated_at'   => $now,
			'completed_at' => $now,
		);

		$mapping = array(
			'person_id'        => 'remote_person_id',
			'remote_person_id' => 'remote_person_id',
			'deal_id'          => 'remote_deal_id',
			'remote_deal_id'   => 'remote_deal_id',
			'sale_id'          => 'remote_sale_id',
			'remote_sale_id'   => 'remote_sale_id',
		);
		foreach ( $mapping as $input_key => $column ) {
			if ( array_key_exists( $input_key, $remote_ids ) ) {
				$data[ $column ] = self::remote_id( $remote_ids[ $input_key ] );
			}
		}

		// Once a CRM submission is delivered, immediately discard contact PII
		// from the local outbox. Retain only the encrypted immutable provenance
		// needed to authorize a later Won -> PAP sale.
		if ( ! empty( $data['remote_deal_id'] ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT job_type,payload_ciphertext FROM ' . self::table_name() . ' WHERE id=%d AND status=%s AND lock_token=%s',
					$job_id,
					self::STATUS_PROCESSING,
					$lock_token
				),
				ARRAY_A
			);
			if ( is_array( $row ) && 'pipedrive_submission' === $row['job_type'] ) {
				$payload = Mediline_Integrations_Crypto::decrypt( $row['payload_ciphertext'] );
				if ( is_wp_error( $payload ) ) {
					return $payload;
				}
				$allowed = array_flip( array( 'submission_id', 'pap_visitor_id', 'pap_affiliate_id', 'pap_affiliate_fixed', 'value', 'currency' ) );
				$minimal = array_intersect_key( $payload, $allowed );
				$redacted = Mediline_Integrations_Crypto::encrypt( $minimal );
				if ( is_wp_error( $redacted ) ) {
					return $redacted;
				}
				$data['payload_ciphertext'] = $redacted;
			}
		}

		$result = $wpdb->update(
			self::table_name(),
			$data,
			array(
				'id'         => $job_id,
				'status'     => self::STATUS_PROCESSING,
				'lock_token' => $lock_token,
			),
			null,
			array( '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'mediline_job_complete_failed', __( 'The integration job could not be completed.', 'mediline-integrations' ) );
		}
		if ( 0 === (int) $result ) {
			return new WP_Error( 'mediline_job_lock_lost', __( 'The integration job is no longer owned by this worker.', 'mediline-integrations' ) );
		}

		return true;
	}

	/**
	 * Return a locked job to pending with a bounded delay, or fail it at its cap.
	 *
	 * @param int    $job_id       Job ID.
	 * @param string $lock_token   Token returned by claim_due().
	 * @param string $error_code   Machine-readable code only; arbitrary messages are discarded.
	 * @param int    $delay_seconds Retry delay, capped at seven days.
	 * @return true|WP_Error
	 */
	public static function retry( $job_id, $lock_token, $error_code, $delay_seconds = 60 ) {
		global $wpdb;

		$job_id     = absint( $job_id );
		$lock_token = self::lock_token( $lock_token );
		if ( ! $job_id || '' === $lock_token ) {
			return new WP_Error( 'mediline_job_invalid_lock', __( 'The integration job lock is invalid.', 'mediline-integrations' ) );
		}

		$table = self::table_name();
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempts,max_attempts FROM {$table} WHERE id=%d AND status=%s AND lock_token=%s",
				$job_id,
				self::STATUS_PROCESSING,
				$lock_token
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'mediline_job_lock_lost', __( 'The integration job is no longer owned by this worker.', 'mediline-integrations' ) );
		}

		$terminal      = (int) $row['attempts'] >= (int) $row['max_attempts'];
		$delay_seconds = min( 7 * DAY_IN_SECONDS, max( 0, absint( $delay_seconds ) ) );
		$status        = $terminal ? self::STATUS_FAILED : self::STATUS_PENDING;
		$available_at  = $terminal ? self::now() : gmdate( 'Y-m-d H:i:s', time() + $delay_seconds );
		$error_code    = self::error_code( $error_code );
		$last_error   = self::error_code( $terminal ? 'max_attempts_' . $error_code : $error_code );
		$now          = self::now();

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET attempts=IF(job_type='pap_sale' AND wake_requested=1,0,attempts),
				     available_at=IF(job_type='pap_sale' AND wake_requested=1,%s,%s),
				     lock_token='', locked_by='', locked_at=NULL,
				     last_error=IF(job_type='pap_sale' AND wake_requested=1,'',%s),
				     updated_at=%s,
				     status=IF(job_type='pap_sale' AND wake_requested=1,%s,%s),
				     wake_requested=0
				 WHERE id=%d AND status=%s AND lock_token=%s",
				$now,
				$available_at,
				$last_error,
				$now,
				self::STATUS_PENDING,
				$status,
				$job_id,
				self::STATUS_PROCESSING,
				$lock_token
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'mediline_job_retry_failed', __( 'The integration job could not be rescheduled.', 'mediline-integrations' ) );
		}
		if ( 0 === (int) $result ) {
			return new WP_Error( 'mediline_job_lock_lost', __( 'The integration job is no longer owned by this worker.', 'mediline-integrations' ) );
		}

		return true;
	}

	/**
	 * Mark a locked job terminally failed using a non-sensitive error code.
	 *
	 * @param int    $job_id     Job ID.
	 * @param string $lock_token Token returned by claim_due().
	 * @param string $error_code Machine-readable code only.
	 * @return true|WP_Error
	 */
	public static function fail( $job_id, $lock_token, $error_code = 'integration_error' ) {
		global $wpdb;

		$job_id     = absint( $job_id );
		$lock_token = self::lock_token( $lock_token );
		if ( ! $job_id || '' === $lock_token ) {
			return new WP_Error( 'mediline_job_invalid_lock', __( 'The integration job lock is invalid.', 'mediline-integrations' ) );
		}

		$table = self::table_name();
		$now   = self::now();
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET attempts=IF(job_type='pap_sale' AND wake_requested=1,0,attempts),
				     available_at=IF(job_type='pap_sale' AND wake_requested=1,%s,available_at),
				     lock_token='', locked_by='', locked_at=NULL,
				     last_error=IF(job_type='pap_sale' AND wake_requested=1,'',%s),
				     updated_at=%s,
				     status=IF(job_type='pap_sale' AND wake_requested=1,%s,%s),
				     wake_requested=0
				 WHERE id=%d AND status=%s AND lock_token=%s",
				$now,
				self::error_code( $error_code ),
				$now,
				self::STATUS_PENDING,
				self::STATUS_FAILED,
				$job_id,
				self::STATUS_PROCESSING,
				$lock_token
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'mediline_job_fail_failed', __( 'The integration job status could not be updated.', 'mediline-integrations' ) );
		}
		if ( 0 === (int) $result ) {
			return new WP_Error( 'mediline_job_lock_lost', __( 'The integration job is no longer owned by this worker.', 'mediline-integrations' ) );
		}

		return true;
	}

	/**
	 * Get one admin-safe job summary. Encrypted payload and lock token are omitted.
	 *
	 * @param int $job_id Job ID.
	 * @return array|null
	 */
	public static function get( $job_id ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( ! $job_id ) {
			return null;
		}

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id=%d', $job_id ), ARRAY_A );
		return is_array( $row ) ? self::admin_row( $row ) : null;
	}

	/**
	 * Return the encrypted local source that created a completed Pipedrive Deal.
	 *
	 * This is intentionally worker-only: callers receive the original trusted
	 * payload in memory, while admin list/get methods continue to hide it.
	 *
	 * @param int $deal_id Pipedrive Deal ID.
	 * @return array|null|WP_Error
	 */
	public static function completed_submission_for_deal( $deal_id ) {
		global $wpdb;

		$deal_id = absint( $deal_id );
		if ( ! $deal_id ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id,event_key,source_type,source_id,payload_ciphertext FROM ' . self::table_name() . ' WHERE job_type=%s AND status=%s AND remote_deal_id=%s ORDER BY id ASC LIMIT 1',
				'pipedrive_submission',
				self::STATUS_COMPLETED,
				(string) $deal_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return null;
		}
		$payload = Mediline_Integrations_Crypto::decrypt( $row['payload_ciphertext'] );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		return array(
			'id'          => (int) $row['id'],
			'event_key'   => $row['event_key'],
			'source_type' => $row['source_type'],
			'source_id'   => $row['source_id'],
			'payload'     => $payload,
		);
	}

	/**
	 * Wake an existing per-Deal PAP job after a fresh Won transition.
	 * Completed jobs with a real remote sale ID remain immutable; failed jobs
	 * and legacy completed "skipped-*" rows may be tried again safely.
	 *
	 * @param int $job_id PAP outbox job ID.
	 * @return true|WP_Error
	 */
	public static function wake_pap_sale( $job_id ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( ! $job_id ) {
			return new WP_Error( 'mediline_sale_wake_invalid', __( 'The PAP sale job is invalid.', 'mediline-integrations' ) );
		}
		$table = self::table_name();
		$now   = self::now();
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET wake_requested=CASE WHEN status=%s THEN 1 ELSE 0 END,
				     attempts=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN 0 ELSE attempts END,
				     available_at=CASE WHEN status IN (%s,%s) OR (status=%s AND remote_sale_id LIKE %s) THEN %s ELSE available_at END,
				     lock_token=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN '' ELSE lock_token END,
				     locked_by=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN '' ELSE locked_by END,
				     locked_at=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN NULL ELSE locked_at END,
				     last_error=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN '' ELSE last_error END,
				     completed_at=CASE WHEN status=%s AND remote_sale_id LIKE %s THEN NULL ELSE completed_at END,
				     updated_at=%s,
				     status=CASE WHEN status=%s OR (status=%s AND remote_sale_id LIKE %s) THEN %s ELSE status END,
				     remote_sale_id=CASE WHEN remote_sale_id LIKE %s THEN '' ELSE remote_sale_id END
				 WHERE id=%d AND job_type=%s
				   AND (status IN (%s,%s,%s) OR (status=%s AND remote_sale_id LIKE %s))",
				self::STATUS_PROCESSING,
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%',
				self::STATUS_PENDING, self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%', $now,
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%',
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%',
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%',
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%',
				self::STATUS_COMPLETED, 'skipped-%',
				$now,
				self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%', self::STATUS_PENDING,
				'skipped-%',
				$job_id, 'pap_sale',
				self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_FAILED, self::STATUS_COMPLETED, 'skipped-%'
			)
		);
		if ( false === $result ) {
			return new WP_Error( 'mediline_sale_wake_failed', __( 'The PAP sale job could not be reactivated.', 'mediline-integrations' ) );
		}
		return true;
	}

	/**
	 * Apply bounded retention to terminal outbox rows.
	 * Failed rows may still contain encrypted contact data; completed CRM rows
	 * have already been reduced to minimal attribution provenance.
	 *
	 * @return array<string,int>|WP_Error
	 */
	public static function purge_expired( $failed_days = 30, $completed_days = 730 ) {
		global $wpdb;

		$failed_days    = min( 3650, max( 1, absint( $failed_days ) ) );
		$completed_days = min( 3650, max( 30, absint( $completed_days ) ) );
		$table          = self::table_name();
		$failed_before  = gmdate( 'Y-m-d H:i:s', time() - $failed_days * DAY_IN_SECONDS );
		$done_before    = gmdate( 'Y-m-d H:i:s', time() - $completed_days * DAY_IN_SECONDS );

		$failed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status=%s AND updated_at<%s LIMIT 1000",
				self::STATUS_FAILED,
				$failed_before
			)
		);
		if ( false === $failed ) {
			return new WP_Error( 'mediline_retention_failed', __( 'Failed integration rows could not be purged.', 'mediline-integrations' ) );
		}
		$completed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status=%s AND updated_at<%s LIMIT 1000",
				self::STATUS_COMPLETED,
				$done_before
			)
		);
		if ( false === $completed ) {
			return new WP_Error( 'mediline_retention_failed', __( 'Completed integration rows could not be purged.', 'mediline-integrations' ) );
		}
		return array( 'failed' => (int) $failed, 'completed' => (int) $completed );
	}

	/**
	 * List admin-safe job summaries with bounded pagination.
	 *
	 * @param array $args status, job_type, source_type, search, page, per_page, orderby, order.
	 * @return array {items: array, total: int, page: int, per_page: int}
	 */
	public static function list_events( array $args = array() ) {
		global $wpdb;

		$table      = self::table_name();
		$page       = max( 1, absint( $args['page'] ?? 1 ) );
		$per_page   = min( 100, max( 1, absint( $args['per_page'] ?? 20 ) ) );
		$offset     = ( $page - 1 ) * $per_page;
		$conditions = array( '1=1' );
		$params     = array();

		$status = self::status( $args['status'] ?? '' );
		if ( '' !== $status ) {
			$conditions[] = 'status=%s';
			$params[]     = $status;
		}

		$job_type = self::job_type( $args['job_type'] ?? '' );
		if ( '' !== $job_type ) {
			$conditions[] = 'job_type=%s';
			$params[]     = $job_type;
		}

		$source_type = self::source_type( $args['source_type'] ?? '' );
		if ( '' !== $source_type ) {
			$conditions[] = 'source_type=%s';
			$params[]     = $source_type;
		}

		$search = self::short_text( $args['search'] ?? '', 64 );
		if ( '' !== $search ) {
			$like         = '%' . $wpdb->esc_like( $search ) . '%';
			$conditions[] = '(event_key LIKE %s OR source_id LIKE %s OR remote_person_id LIKE %s OR remote_deal_id LIKE %s OR remote_sale_id LIKE %s)';
			array_push( $params, $like, $like, $like, $like, $like );
		}

		$where_sql = implode( ' AND ', $conditions );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( $params ) {
			$count_sql = $wpdb->prepare( $count_sql, $params );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		$allowed_orderby = array( 'id', 'created_at', 'updated_at', 'available_at', 'attempts', 'status', 'job_type' );
		$orderby_input   = $args['orderby'] ?? '';
		$orderby         = is_string( $orderby_input ) && in_array( $orderby_input, $allowed_orderby, true ) ? $orderby_input : 'id';
		$order_input     = $args['order'] ?? 'DESC';
		$order           = is_scalar( $order_input ) && 'ASC' === strtoupper( (string) $order_input ) ? 'ASC' : 'DESC';
		$list_sql        = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset;
		if ( $params ) {
			$list_sql = $wpdb->prepare( $list_sql, $params );
		}

		$rows  = $wpdb->get_results( $list_sql, ARRAY_A );
		$items = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$items[] = self::admin_row( $row );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Build the limited row exposed to a queue worker.
	 *
	 * @param array $row     Database row.
	 * @param array $payload Decrypted job payload.
	 * @return array
	 */
	private static function worker_row( array $row, array $payload ) {
		return array(
			'id'               => (int) $row['id'],
			'event_key'        => $row['event_key'],
			'job_type'         => $row['job_type'],
			'source_type'      => $row['source_type'],
			'source_id'        => $row['source_id'],
			'attempts'         => (int) $row['attempts'],
			'max_attempts'     => (int) $row['max_attempts'],
			'lock_token'       => $row['lock_token'],
			'payload'          => $payload,
			'remote_person_id' => $row['remote_person_id'],
			'remote_deal_id'   => $row['remote_deal_id'],
			'remote_sale_id'   => $row['remote_sale_id'],
		);
	}

	/**
	 * Remove encrypted and lock material from a row exposed in wp-admin.
	 *
	 * @param array $row Database row.
	 * @return array
	 */
	private static function admin_row( array $row ) {
		return array(
			'id'               => (int) $row['id'],
			'event_key'        => $row['event_key'],
			'job_type'         => $row['job_type'],
			'source_type'      => $row['source_type'],
			'source_id'        => $row['source_id'],
			'status'           => $row['status'],
			'attempts'         => (int) $row['attempts'],
			'max_attempts'     => (int) $row['max_attempts'],
			'available_at'     => $row['available_at'],
			'locked_at'        => $row['locked_at'],
			'remote_person_id' => $row['remote_person_id'],
			'remote_deal_id'   => $row['remote_deal_id'],
			'remote_sale_id'   => $row['remote_sale_id'],
			'last_error'       => $row['last_error'],
			'created_at'       => $row['created_at'],
			'updated_at'       => $row['updated_at'],
			'completed_at'     => $row['completed_at'],
		);
	}

	/** @return string */
	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/** @return string */
	private static function event_key( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,190}\z/', $value ) ? $value : '';
	}

	/** @return string */
	private static function job_type( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return preg_match( '/\A[a-z][a-z0-9_-]{0,63}\z/', $value ) ? $value : '';
	}

	/** @return string */
	private static function source_type( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/\A[a-z][a-z0-9_-]{0,31}\z/', $value ) ? $value : '';
	}

	/** @return string */
	private static function source_id( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		return preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,190}\z/', $value ) ? $value : '';
	}

	/** @return string */
	private static function status( $value ) {
		$value   = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		$allowed = array( self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_FAILED );
		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/** @return string */
	private static function worker_id( $value ) {
		$value = self::short_text( $value, 96 );
		$value = preg_replace( '/[^A-Za-z0-9._:-]+/', '-', $value );
		return '' !== $value ? $value : 'wordpress';
	}

	/** @return string */
	private static function lock_token( $value ) {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		return preg_match( '/\A[A-Za-z0-9-]{16,64}\z/', $value ) ? $value : '';
	}

	/** @return string */
	private static function remote_id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = trim( (string) $value );
		return preg_match( '/\A[A-Za-z0-9._:-]{0,191}\z/', $value ) ? $value : '';
	}

	/**
	 * Restrict persisted errors to safe machine codes, never response bodies.
	 *
	 * @return string
	 */
	private static function error_code( $value ) {
		$value = is_scalar( $value ) ? strtolower( trim( (string) $value ) ) : '';
		return preg_match( '/\A[a-z0-9][a-z0-9._:-]{0,190}\z/', $value ) ? $value : 'integration_error';
	}

	/** @return string */
	private static function short_text( $value, $length ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$value = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length );
		}
		return substr( $value, 0, $length );
	}

	/**
	 * Validate a UTC MySQL datetime or Unix timestamp.
	 *
	 * @param mixed  $value   Input datetime.
	 * @param string $default Default when null or empty.
	 * @return string Empty when invalid.
	 */
	private static function datetime( $value, $default ) {
		if ( null === $value || '' === $value ) {
			return $default;
		}
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$timestamp = (int) $value;
			return $timestamp > 0 ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		}
		if ( ! is_string( $value ) || ! preg_match( '/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value ) ) {
			return '';
		}

		$date   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			return '';
		}

		return $date->format( 'Y-m-d H:i:s' ) === $value ? $value : '';
	}
}
