<?php
/**
 * Transactional smoke test against the real local WordPress database.
 *
 * Run manually with the OSPanel PHP binary. All table mutations are rolled
 * back, including successful assertions.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST']   = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '80';
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

global $wpdb;
$table  = Mediline_Integrations_DB::table_name();
$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
$retention_index = $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name='retention'", 2 );
if ( Mediline_Integrations_DB::DB_VERSION !== get_option( 'mediline_integrations_db_version' ) || 'retention' !== $retention_index ) {
	throw new RuntimeException( 'The live outbox schema is not at the expected production version.' );
}
if ( ! wp_next_scheduled( Mediline_Integrations_Workflow::CRON_HOOK ) || ! wp_next_scheduled( Mediline_Integrations_Workflow::CLEANUP_HOOK ) ) {
	throw new RuntimeException( 'The delivery or retention cron event is not scheduled.' );
}
echo "[PASS] live outbox schema and retention index are current\n";
$wpdb->query( 'START TRANSACTION' );

try {
	$payload = array(
		'submission_id'      => 'smoke-transaction-submission',
		'email'              => 'transaction-only@example.test',
		'pap_visitor_id'     => '0123456789abcdef0123456789abcdef',
		'pap_affiliate_id'   => 'partner-smoke',
		'pap_affiliate_fixed'=> false,
	);
	$first = Mediline_Integrations_DB::enqueue( 'smoke:transaction', 'pipedrive_submission', $payload, array( 'source_type' => 'lead', 'source_id' => 'smoke-transaction' ) );
	$again = Mediline_Integrations_DB::enqueue( 'smoke:transaction', 'pipedrive_submission', $payload, array( 'source_type' => 'lead', 'source_id' => 'smoke-transaction' ) );
	if ( is_wp_error( $first ) || $first !== $again ) {
		throw new RuntimeException( 'Idempotent enqueue failed.' );
	}
	$ciphertext = (string) $wpdb->get_var( $wpdb->prepare( "SELECT payload_ciphertext FROM {$table} WHERE id=%d", $first ) );
	if ( false !== strpos( $ciphertext, 'transaction-only@example.test' ) ) {
		throw new RuntimeException( 'Outbox payload was stored in plaintext.' );
	}
	$job = Mediline_Integrations_DB::claim_due( 'transaction-smoke', 300 );
	if ( ! is_array( $job ) || (int) $job['id'] !== (int) $first ) {
		throw new RuntimeException( 'Due job could not be claimed.' );
	}
	$completed = Mediline_Integrations_DB::complete( $job['id'], $job['lock_token'], array( 'person_id' => '41', 'deal_id' => '501' ) );
	if ( true !== $completed ) {
		throw new RuntimeException( 'Job could not be completed.' );
	}
	$trusted = Mediline_Integrations_DB::completed_submission_for_deal( 501 );
	if ( ! is_array( $trusted ) || 'partner-smoke' !== ( $trusted['payload']['pap_affiliate_id'] ?? '' ) ) {
		throw new RuntimeException( 'Minimal Deal provenance could not be recovered.' );
	}
	if ( isset( $trusted['payload']['email'] ) ) {
		throw new RuntimeException( 'Contact PII was not redacted after CRM delivery.' );
	}
	echo "[PASS] real WordPress encrypted outbox, idempotency, locking, provenance, and PII redaction\n";

	$sale_id = Mediline_Integrations_DB::enqueue(
		'smoke:pap-sale-wake',
		'pap_sale',
		array( 'deal_id' => 501 ),
		array( 'source_type' => 'pipedrive-deal', 'source_id' => '501' )
	);
	$sale_job = Mediline_Integrations_DB::claim_due( 'transaction-smoke-sale', 300 );
	if ( is_wp_error( $sale_id ) || ! is_array( $sale_job ) || (int) $sale_job['id'] !== (int) $sale_id ) {
		throw new RuntimeException( 'PAP sale job could not be claimed.' );
	}
	if ( true !== Mediline_Integrations_DB::wake_pap_sale( $sale_id ) ) {
		throw new RuntimeException( 'A concurrent fresh Won event could not be persisted.' );
	}
	$processing_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,wake_requested FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if ( ! is_array( $processing_state ) || 'processing' !== $processing_state['status'] || 1 !== (int) $processing_state['wake_requested'] ) {
		throw new RuntimeException( 'The in-flight PAP sale did not retain its fresh Won wake request.' );
	}
	if ( true !== Mediline_Integrations_DB::fail( $sale_job['id'], $sale_job['lock_token'], 'smoke_retry' ) ) {
		throw new RuntimeException( 'The in-flight PAP sale could not resolve its wake request.' );
	}
	$sale_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,attempts,wake_requested,remote_sale_id FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if ( ! is_array( $sale_state ) || 'pending' !== $sale_state['status'] || 0 !== (int) $sale_state['attempts'] || 0 !== (int) $sale_state['wake_requested'] || '' !== $sale_state['remote_sale_id'] ) {
		throw new RuntimeException( 'The concurrent fresh Won event was lost while failing an in-flight job.' );
	}
	$sale_job = Mediline_Integrations_DB::claim_due( 'transaction-smoke-sale-again', 300 );
	if ( ! is_array( $sale_job ) || true !== Mediline_Integrations_DB::fail( $sale_job['id'], $sale_job['lock_token'], 'smoke_retry' ) || true !== Mediline_Integrations_DB::wake_pap_sale( $sale_id ) ) {
		throw new RuntimeException( 'A failed PAP sale job could not be reactivated.' );
	}
	$sale_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,attempts,wake_requested,remote_sale_id FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if ( ! is_array( $sale_state ) || 'pending' !== $sale_state['status'] || 0 !== (int) $sale_state['attempts'] || 0 !== (int) $sale_state['wake_requested'] || '' !== $sale_state['remote_sale_id'] ) {
		throw new RuntimeException( 'The PAP sale job was not safely reset for retry.' );
	}
	$retry_job = Mediline_Integrations_DB::claim_due( 'transaction-smoke-sale-retry-race', 300 );
	if ( ! is_array( $retry_job ) || true !== Mediline_Integrations_DB::wake_pap_sale( $sale_id ) || true !== Mediline_Integrations_DB::retry( $retry_job['id'], $retry_job['lock_token'], 'smoke_retry', 300 ) ) {
		throw new RuntimeException( 'A concurrent PAP retry wake request could not be resolved.' );
	}
	$retry_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,attempts,wake_requested,available_at FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if ( ! is_array( $retry_state ) || 'pending' !== $retry_state['status'] || 0 !== (int) $retry_state['attempts'] || 0 !== (int) $retry_state['wake_requested'] || $retry_state['available_at'] > gmdate( 'Y-m-d H:i:s', time() + 1 ) ) {
		throw new RuntimeException( 'The fresh Won event was delayed or lost by an in-flight retry.' );
	}
	$wpdb->update(
		$table,
		array( 'status' => 'completed', 'attempts' => 4, 'remote_sale_id' => 'skipped-not-won', 'completed_at' => gmdate( 'Y-m-d H:i:s' ) ),
		array( 'id' => $sale_id ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%d' )
	);
	if ( true !== Mediline_Integrations_DB::wake_pap_sale( $sale_id ) ) {
		throw new RuntimeException( 'A legacy skipped PAP sale could not be migrated back to pending.' );
	}
	$legacy_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,attempts,remote_sale_id,completed_at FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if ( ! is_array( $legacy_state ) || 'pending' !== $legacy_state['status'] || 0 !== (int) $legacy_state['attempts'] || '' !== $legacy_state['remote_sale_id'] || null !== $legacy_state['completed_at'] ) {
		throw new RuntimeException( 'Legacy completed skipped PAP state was not safely revived.' );
	}
	$wpdb->update(
		$table,
		array( 'max_attempts' => 1, 'available_at' => gmdate( 'Y-m-d H:i:s' ) ),
		array( 'id' => $sale_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	$stale_job = Mediline_Integrations_DB::claim_due( 'transaction-smoke-stale-sale', 30 );
	if ( ! is_array( $stale_job ) || (int) $stale_job['id'] !== (int) $sale_id || true !== Mediline_Integrations_DB::wake_pap_sale( $sale_id ) ) {
		throw new RuntimeException( 'The max-attempt PAP sale could not enter the stale-worker race.' );
	}
	$wpdb->update(
		$table,
		array( 'locked_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ),
		array( 'id' => $sale_id ),
		array( '%s' ),
		array( '%d' )
	);
	$reclaimed_job = Mediline_Integrations_DB::claim_due( 'transaction-smoke-stale-reclaim', 30 );
	$reclaimed_state = $wpdb->get_row( $wpdb->prepare( "SELECT status,attempts,wake_requested FROM {$table} WHERE id=%d", $sale_id ), ARRAY_A );
	if (
		! is_array( $reclaimed_job ) ||
		(int) $reclaimed_job['id'] !== (int) $sale_id ||
		! is_array( $reclaimed_state ) ||
		'processing' !== $reclaimed_state['status'] ||
		1 !== (int) $reclaimed_state['attempts'] ||
		0 !== (int) $reclaimed_state['wake_requested']
	) {
		throw new RuntimeException( 'A fresh Won event was lost after an exhausted PAP worker crashed.' );
	}
	if ( true !== Mediline_Integrations_DB::fail( $reclaimed_job['id'], $reclaimed_job['lock_token'], 'smoke_finished' ) ) {
		throw new RuntimeException( 'The reclaimed stale PAP sale could not be finalized.' );
	}
	echo "[PASS] fresh Won transition survives in-flight races and wakes failed per-Deal PAP jobs\n";

	$retention_id = Mediline_Integrations_DB::enqueue(
		'smoke:retention',
		'pipedrive_submission',
		array( 'email' => 'expired-transaction-only@example.test' ),
		array( 'source_type' => 'lead', 'source_id' => 'expired-transaction-only' )
	);
	$old = gmdate( 'Y-m-d H:i:s', time() - 45 * DAY_IN_SECONDS );
	$wpdb->update(
		$table,
		array( 'status' => 'failed', 'updated_at' => $old ),
		array( 'id' => $retention_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	$purged = Mediline_Integrations_DB::purge_expired( 30, 730 );
	if ( is_wp_error( $purged ) || (int) ( $purged['failed'] ?? 0 ) < 1 || $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d", $retention_id ) ) ) {
		throw new RuntimeException( 'Expired failed contact data was not purged.' );
	}
	echo "[PASS] expired failed contact data is removed by bounded retention\n";
} finally {
	$wpdb->query( 'ROLLBACK' );
}

$after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
if ( $before !== $after ) {
	throw new RuntimeException( 'Transactional smoke test changed the live queue.' );
}
echo "[PASS] transaction rolled back; live queue unchanged\n";
