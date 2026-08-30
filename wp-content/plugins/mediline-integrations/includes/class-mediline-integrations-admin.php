<?php
/**
 * Administration and safe provisioning tools.
 *
 * @package Mediline_Integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mediline_Integrations_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_mediline_integrations_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_mediline_integrations_provision', array( __CLASS__, 'provision' ) );
		add_action( 'admin_post_mediline_integrations_webhook', array( __CLASS__, 'register_webhook' ) );
		add_action( 'admin_post_mediline_integrations_rotate_webhook', array( __CLASS__, 'rotate_webhook_credentials' ) );
		add_action( 'admin_post_mediline_integrations_retry', array( __CLASS__, 'retry_job' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MEDILINE_INTEGRATIONS_DIR . 'mediline-integrations.php' ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mediline-integrations' ) ) . '">' . esc_html__( 'Settings', 'mediline-integrations' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page(
			__( 'Mediline Integrations', 'mediline-integrations' ),
			__( 'Mediline CRM', 'mediline-integrations' ),
			'manage_options',
			'mediline-integrations',
			array( __CLASS__, 'page' ),
			'dashicons-randomize',
			57
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = mediline_integrations_settings();
		$secret   = (bool) mediline_integrations_secret( 'pipedrive_api_token' );
		$pap_fraud_secret = (bool) mediline_integrations_secret( 'pap_fraud_secret' );
		$pap_v3_token = (bool) mediline_integrations_secret( 'pap_api_v3_token' );
		$webhook  = rest_url( 'mediline-integrations/v1/pipedrive/won' );
		$mapped = Mediline_Integrations_Pipedrive::required_fields_configured( $settings );
		$jobs = Mediline_Integrations_DB::list_events( array( 'per_page' => 30 ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mediline PAP & CRM Integrations', 'mediline-integrations' ); ?></h1>
			<?php self::notice(); ?>
			<p><?php esc_html_e( 'PAP click attribution is captured in the browser. Pipedrive Won is the only authoritative sale trigger, preventing duplicate commissions.', 'mediline-integrations' ); ?></p>

			<div style="display:flex;gap:14px;flex-wrap:wrap;margin:18px 0">
				<?php self::status_card( __( 'PAP click', 'mediline-integrations' ), ! empty( $settings['pap_click_enabled'] ) && ! empty( $settings['pap_click_script_url'] ), __( 'Tracker configured', 'mediline-integrations' ), __( 'Disabled', 'mediline-integrations' ) ); ?>
				<?php self::status_card( __( 'PAP sale security', 'mediline-integrations' ), ! empty( $settings['pap_sale_enabled'] ) && ! empty( $settings['pap_sale_endpoint'] ) && ! empty( $settings['pap_duplicate_protection_confirmed'] ) && ! empty( $settings['pap_fraud_protection_enabled'] ) && $pap_fraud_secret, __( 'Duplicate + checksum gates ready', 'mediline-integrations' ), __( 'Protection setup required', 'mediline-integrations' ) ); ?>
				<?php self::status_card( __( 'PAP API v3 identity', 'mediline-integrations' ), Mediline_Integrations_Pap_V3::configured(), __( 'Fail-closed verification ready', 'mediline-integrations' ), __( 'API v3 key required', 'mediline-integrations' ) ); ?>
				<?php self::status_card( __( 'Pipedrive auth', 'mediline-integrations' ), Mediline_Integrations_Pipedrive::credentials_configured(), __( 'Enabled', 'mediline-integrations' ), __( 'Needs API credentials', 'mediline-integrations' ) ); ?>
				<?php self::status_card( __( 'Deal fields', 'mediline-integrations' ), $mapped, __( 'Mapped', 'mediline-integrations' ), __( 'Run provisioning', 'mediline-integrations' ) ); ?>
				<?php self::status_card( __( 'Won webhook', 'mediline-integrations' ), ! empty( $settings['pipedrive_webhook_id'] ), __( 'Registered', 'mediline-integrations' ), __( 'Not registered', 'mediline-integrations' ) ); ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:1100px">
				<input type="hidden" name="action" value="mediline_integrations_save">
				<?php wp_nonce_field( 'mediline_integrations_save' ); ?>
				<h2><?php esc_html_e( 'Post Affiliate Pro', 'mediline-integrations' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Click tracking', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pap_click_enabled" value="1" <?php checked( ! empty( $settings['pap_click_enabled'] ) ); ?>> <?php esc_html_e( 'Load the PAP click tracker sitewide', 'mediline-integrations' ); ?></label></td></tr>
					<tr><th><label for="pap_click_script_url"><?php esc_html_e( 'Tracking script URL', 'mediline-integrations' ); ?></label></th><td><input id="pap_click_script_url" class="large-text code" type="url" name="pap_click_script_url" value="<?php echo esc_attr( $settings['pap_click_script_url'] ); ?>"></td></tr>
					<tr><th><label for="pap_account_id"><?php esc_html_e( 'Account ID', 'mediline-integrations' ); ?></label></th><td><input id="pap_account_id" class="regular-text" name="pap_account_id" value="<?php echo esc_attr( $settings['pap_account_id'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'API v3 identity verification', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pap_api_v3_enabled" value="1" <?php checked( ! empty( $settings['pap_api_v3_enabled'] ) ); ?>> <?php esc_html_e( 'Require PAP API v3 for Store Builder affiliate identity', 'mediline-integrations' ); ?></label><p class="description"><?php esc_html_e( 'Fail closed: Store Builder access is denied whenever API v3 cannot verify userid, refid and status.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><label for="pap_api_v3_base"><?php esc_html_e( 'API v3 base URL', 'mediline-integrations' ); ?></label></th><td><input id="pap_api_v3_base" class="large-text code" type="url" name="pap_api_v3_base" value="<?php echo esc_attr( $settings['pap_api_v3_base'] ); ?>" placeholder="https://account.postaffiliatepro.com/api/v3"></td></tr>
					<tr><th><label for="pap_api_v3_token"><?php esc_html_e( 'API v3 Bearer key', 'mediline-integrations' ); ?></label></th><td><input id="pap_api_v3_token" class="regular-text" type="password" autocomplete="new-password" name="pap_api_v3_token" value="" placeholder="<?php echo esc_attr( $pap_v3_token ? __( 'Stored securely — leave blank to keep', 'mediline-integrations' ) : __( 'Affiliates Read key required', 'mediline-integrations' ) ); ?>"><label style="margin-left:12px"><input type="checkbox" name="clear_pap_api_v3_token" value="1"> <?php esc_html_e( 'Clear stored key', 'mediline-integrations' ); ?></label><p class="description"><?php esc_html_e( 'Stored in the authenticated encrypted secrets envelope; never rendered back into wp-admin.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Sale tracking', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pap_sale_enabled" value="1" <?php checked( ! empty( $settings['pap_sale_enabled'] ) ); ?>> <?php esc_html_e( 'Register a PAP sale only after Pipedrive marks the Deal Won', 'mediline-integrations' ); ?></label></td></tr>
					<tr><th><label for="pap_sale_endpoint"><?php esc_html_e( 'S2S sale endpoint', 'mediline-integrations' ); ?></label></th><td><input id="pap_sale_endpoint" class="large-text code" type="url" name="pap_sale_endpoint" value="<?php echo esc_attr( $settings['pap_sale_endpoint'] ); ?>"></td></tr>
					<tr><th><label for="pap_sale_status"><?php esc_html_e( 'Initial sale status', 'mediline-integrations' ); ?></label></th><td><select id="pap_sale_status" name="pap_sale_status"><option value="" <?php selected( $settings['pap_sale_status'], '' ); ?>><?php esc_html_e( 'PAP campaign default', 'mediline-integrations' ); ?></option><option value="A" <?php selected( $settings['pap_sale_status'], 'A' ); ?>>Approved</option><option value="P" <?php selected( $settings['pap_sale_status'], 'P' ); ?>>Pending</option><option value="D" <?php selected( $settings['pap_sale_status'], 'D' ); ?>>Declined</option></select></td></tr>
					<tr><th><?php esc_html_e( 'Duplicate protection', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pap_duplicate_protection_confirmed" value="1" <?php checked( ! empty( $settings['pap_duplicate_protection_confirmed'] ) ); ?>> <?php esc_html_e( 'I confirmed PAP Fraud Protection recognizes repeated OrderID values for a long interval', 'mediline-integrations' ); ?></label><p class="description"><?php esc_html_e( 'This is a hard gate: sales remain queued until enabled in PAP and confirmed here.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'Sale fraud checksum', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pap_fraud_protection_enabled" value="1" <?php checked( ! empty( $settings['pap_fraud_protection_enabled'] ) ); ?>> <?php esc_html_e( 'Require PAP Sale Tracking Fraud Protection checksum', 'mediline-integrations' ); ?></label></td></tr>
					<tr><th><label for="pap_fraud_data_field"><?php esc_html_e( 'Checksum data field', 'mediline-integrations' ); ?></label></th><td><select id="pap_fraud_data_field" name="pap_fraud_data_field"><?php foreach ( array( 2, 3, 4, 5 ) as $field_number ) : ?><option value="<?php echo esc_attr( $field_number ); ?>" <?php selected( (int) $settings['pap_fraud_data_field'], $field_number ); ?>>data<?php echo esc_html( $field_number ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Must match the data field selected in the PAP fraud-protection plugin. data1 is reserved for the Pipedrive Deal ID.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><label for="pap_fraud_secret"><?php esc_html_e( 'Checksum secret', 'mediline-integrations' ); ?></label></th><td><input id="pap_fraud_secret" class="regular-text" type="password" autocomplete="new-password" name="pap_fraud_secret" value="" placeholder="<?php echo esc_attr( $pap_fraud_secret ? __( 'Stored securely — leave blank to keep', 'mediline-integrations' ) : __( 'Must match PAP', 'mediline-integrations' ) ); ?>"><label style="margin-left:12px"><input type="checkbox" name="clear_pap_fraud_secret" value="1"> <?php esc_html_e( 'Clear stored secret', 'mediline-integrations' ); ?></label></td></tr>
				</table>

				<h2><?php esc_html_e( 'Pipedrive', 'mediline-integrations' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'CRM delivery', 'mediline-integrations' ); ?></th><td><label><input type="checkbox" name="pipedrive_enabled" value="1" <?php checked( ! empty( $settings['pipedrive_enabled'] ) ); ?>> <?php esc_html_e( 'Create/update Person and create one Deal per submission/order', 'mediline-integrations' ); ?></label></td></tr>
					<tr><th><label for="pipedrive_api_base"><?php esc_html_e( 'Company API base', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_api_base" class="large-text code" type="url" name="pipedrive_api_base" value="<?php echo esc_attr( $settings['pipedrive_api_base'] ); ?>" placeholder="https://company.pipedrive.com"><p class="description"><?php esc_html_e( 'HTTPS only; do not include /api/v2.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><label for="pipedrive_api_token"><?php esc_html_e( 'API token', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_api_token" class="regular-text" type="password" autocomplete="new-password" name="pipedrive_api_token" value="" placeholder="<?php echo esc_attr( $secret ? __( 'Stored securely — leave blank to keep', 'mediline-integrations' ) : __( 'Required', 'mediline-integrations' ) ); ?>"><label style="margin-left:12px"><input type="checkbox" name="clear_pipedrive_api_token" value="1"> <?php esc_html_e( 'Clear stored token', 'mediline-integrations' ); ?></label></td></tr>
					<tr><th><label for="pipedrive_pipeline_id"><?php esc_html_e( 'Pipeline ID', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_pipeline_id" type="number" min="0" name="pipedrive_pipeline_id" value="<?php echo esc_attr( $settings['pipedrive_pipeline_id'] ); ?>"></td></tr>
					<tr><th><label for="pipedrive_stage_id"><?php esc_html_e( 'Initial stage ID', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_stage_id" type="number" min="0" name="pipedrive_stage_id" value="<?php echo esc_attr( $settings['pipedrive_stage_id'] ); ?>"></td></tr>
					<tr><th><label for="pipedrive_company_id"><?php esc_html_e( 'Expected company ID', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_company_id" class="regular-text" name="pipedrive_company_id" value="<?php echo esc_attr( $settings['pipedrive_company_id'] ); ?>"><p class="description"><?php esc_html_e( 'Optional additional webhook tenant check.', 'mediline-integrations' ); ?></p></td></tr>
					<tr><th><label for="pipedrive_webhook_host"><?php esc_html_e( 'Expected webhook host', 'mediline-integrations' ); ?></label></th><td><input id="pipedrive_webhook_host" class="regular-text" name="pipedrive_webhook_host" value="<?php echo esc_attr( $settings['pipedrive_webhook_host'] ); ?>" placeholder="company.pipedrive.com"></td></tr>
				</table>

				<details style="margin:16px 0"><summary><strong><?php esc_html_e( 'Tenant-specific Deal field codes', 'mediline-integrations' ); ?></strong></summary>
					<table class="form-table" role="presentation">
					<?php foreach ( Mediline_Integrations_Pipedrive::field_definitions() as $suffix => $definition ) : $key = 'pipedrive_field_' . $suffix; ?>
						<tr><th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $definition['name'] ); ?></label></th><td><input id="<?php echo esc_attr( $key ); ?>" class="regular-text code" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>"></td></tr>
					<?php endforeach; ?>
					</table>
				</details>
				<?php submit_button( __( 'Save integration settings', 'mediline-integrations' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Pipedrive setup actions', 'mediline-integrations' ); ?></h2>
			<p><strong><?php esc_html_e( 'Webhook URL:', 'mediline-integrations' ); ?></strong> <code><?php echo esc_html( $webhook ); ?></code></p>
			<?php if ( 0 !== stripos( $webhook, 'https://' ) ) : ?><p style="color:#b32d2e"><strong><?php esc_html_e( 'Production warning:', 'mediline-integrations' ); ?></strong> <?php esc_html_e( 'Pipedrive requires a public HTTPS URL without redirects.', 'mediline-integrations' ); ?></p><?php endif; ?>
			<div style="display:flex;gap:10px;flex-wrap:wrap">
				<?php self::action_form( 'mediline_integrations_provision', __( 'Provision/verify Deal fields', 'mediline-integrations' ), 'button button-secondary' ); ?>
				<?php self::action_form( 'mediline_integrations_webhook', __( 'Register Won webhook', 'mediline-integrations' ), 'button button-secondary' ); ?>
				<?php self::action_form( 'mediline_integrations_rotate_webhook', __( 'Rotate webhook credentials', 'mediline-integrations' ), 'button button-secondary', __( 'Existing Pipedrive webhook credentials will stop working until it is registered again.', 'mediline-integrations' ) ); ?>
			</div>

			<h2 style="margin-top:28px"><?php esc_html_e( 'Encrypted delivery queue', 'mediline-integrations' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( '%d recorded jobs. Payloads are encrypted and are never displayed here.', 'mediline-integrations' ), (int) $jobs['total'] ) ); ?></p>
			<table class="widefat striped"><thead><tr><th>ID</th><th>Type</th><th>Source</th><th>Status</th><th>Attempts</th><th>Remote IDs</th><th>Last error</th><th>Updated</th><th></th></tr></thead><tbody>
			<?php if ( empty( $jobs['items'] ) ) : ?><tr><td colspan="9"><?php esc_html_e( 'No integration jobs yet.', 'mediline-integrations' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $jobs['items'] as $job ) : ?>
				<tr><td><?php echo esc_html( $job['id'] ); ?></td><td><code><?php echo esc_html( $job['job_type'] ); ?></code></td><td><?php echo esc_html( trim( $job['source_type'] . ' ' . $job['source_id'] ) ); ?></td><td><?php echo esc_html( $job['status'] ); ?></td><td><?php echo esc_html( $job['attempts'] . '/' . $job['max_attempts'] ); ?></td><td><?php echo esc_html( trim( 'P:' . $job['remote_person_id'] . ' D:' . $job['remote_deal_id'] . ' S:' . $job['remote_sale_id'] ) ); ?></td><td><code><?php echo esc_html( $job['last_error'] ); ?></code></td><td><?php echo esc_html( $job['updated_at'] ); ?></td><td><?php if ( 'failed' === $job['status'] ) { self::retry_form( $job['id'] ); } ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private static function status_card( $title, $ok, $yes, $no ) {
		?><div class="card" style="min-width:180px;margin:0"><h3 style="margin-top:0"><?php echo esc_html( $title ); ?></h3><p style="margin-bottom:0;color:<?php echo $ok ? '#008a20' : '#b32d2e'; ?>"><strong><?php echo esc_html( $ok ? $yes : $no ); ?></strong></p></div><?php
	}

	private static function action_form( $action, $label, $class, $confirm = '' ) {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : ''; ?>><input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>"><?php wp_nonce_field( $action ); ?><button class="<?php echo esc_attr( $class ); ?>" type="submit"><?php echo esc_html( $label ); ?></button></form><?php
	}

	private static function retry_form( $job_id ) {
		?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="mediline_integrations_retry"><input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>"><?php wp_nonce_field( 'mediline_integrations_retry_' . $job_id ); ?><button class="button-link" type="submit"><?php esc_html_e( 'Retry', 'mediline-integrations' ); ?></button></form><?php
	}

	private static function notice() {
		$code = sanitize_key( (string) ( $_GET['mediline_notice'] ?? '' ) );
		if ( ! $code ) {
			return;
		}
		$messages = array(
			'saved'             => __( 'Integration settings saved.', 'mediline-integrations' ),
			'provisioned'       => __( 'Pipedrive Deal fields verified and mapped.', 'mediline-integrations' ),
			'webhook_registered'=> __( 'Pipedrive Won webhook registered.', 'mediline-integrations' ),
			'webhook_rotated'   => __( 'Webhook credentials rotated. Register the webhook again.', 'mediline-integrations' ),
			'job_retried'       => __( 'The failed job was returned to the queue.', 'mediline-integrations' ),
		);
		$error = get_transient( 'mediline_integrations_admin_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'mediline_integrations_admin_error_' . get_current_user_id() );
			?><div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div><?php
		} elseif ( isset( $messages[ $code ] ) ) {
			?><div class="notice notice-success"><p><?php echo esc_html( $messages[ $code ] ); ?></p></div><?php
		}
	}

	public static function save() {
		self::guard( 'mediline_integrations_save' );
		$current = mediline_integrations_settings();
		$next    = $current;
		foreach ( array( 'pap_click_enabled', 'pap_api_v3_enabled', 'pap_sale_enabled', 'pap_duplicate_protection_confirmed', 'pap_fraud_protection_enabled', 'pipedrive_enabled' ) as $key ) {
			$next[ $key ] = ! empty( $_POST[ $key ] ) ? 1 : 0;
		}
		foreach ( array( 'pap_click_script_url', 'pap_sale_endpoint' ) as $key ) {
			$url = esc_url_raw( wp_unslash( $_POST[ $key ] ?? '' ), array( 'https' ) );
			$next[ $key ] = 0 === stripos( $url, 'https://' ) ? untrailingslashit( $url ) : '';
		}
		$next['pipedrive_api_base'] = Mediline_Integrations_Pipedrive::normalize_api_base( wp_unslash( $_POST['pipedrive_api_base'] ?? '' ) );
		$next['pap_api_v3_base'] = Mediline_Integrations_Pap_V3::normalize_api_base( wp_unslash( $_POST['pap_api_v3_base'] ?? '' ) );
		$next['pap_account_id']         = self::short( $_POST['pap_account_id'] ?? 'default1', 64 );
		$next['pap_sale_status']        = in_array( wp_unslash( $_POST['pap_sale_status'] ?? '' ), array( '', 'A', 'P', 'D' ), true ) ? wp_unslash( $_POST['pap_sale_status'] ?? '' ) : '';
		$fraud_field = absint( $_POST['pap_fraud_data_field'] ?? 5 );
		$next['pap_fraud_data_field'] = in_array( $fraud_field, array( 2, 3, 4, 5 ), true ) ? $fraud_field : 5;
		$next['pipedrive_pipeline_id']  = absint( $_POST['pipedrive_pipeline_id'] ?? 0 ) ?: '';
		$next['pipedrive_stage_id']     = absint( $_POST['pipedrive_stage_id'] ?? 0 ) ?: '';
		$next['pipedrive_company_id']   = self::identifier( $_POST['pipedrive_company_id'] ?? '' );
		$next['pipedrive_webhook_host'] = strtolower( self::short( $_POST['pipedrive_webhook_host'] ?? '', 191 ) );
		foreach ( Mediline_Integrations_Pipedrive::field_definitions() as $suffix => $definition ) {
			$key          = 'pipedrive_field_' . $suffix;
			$next[ $key ] = self::field_code( $_POST[ $key ] ?? '' );
		}
		update_option( 'mediline_integrations_settings', $next, false );

		$secret_changes = array();
		if ( ! empty( $_POST['clear_pap_fraud_secret'] ) ) {
			$secret_changes['pap_fraud_secret'] = '';
		} elseif ( isset( $_POST['pap_fraud_secret'] ) && '' !== trim( (string) wp_unslash( $_POST['pap_fraud_secret'] ) ) ) {
			$secret_changes['pap_fraud_secret'] = self::short( wp_unslash( $_POST['pap_fraud_secret'] ), 255 );
		}
		if ( ! empty( $_POST['clear_pipedrive_api_token'] ) ) {
			$secret_changes['pipedrive_api_token'] = '';
		} elseif ( isset( $_POST['pipedrive_api_token'] ) && '' !== trim( (string) wp_unslash( $_POST['pipedrive_api_token'] ) ) ) {
			$secret_changes['pipedrive_api_token'] = self::short( wp_unslash( $_POST['pipedrive_api_token'] ), 255 );
		}
		if ( ! empty( $_POST['clear_pap_api_v3_token'] ) ) {
			$secret_changes['pap_api_v3_token'] = '';
		} elseif ( isset( $_POST['pap_api_v3_token'] ) && '' !== trim( (string) wp_unslash( $_POST['pap_api_v3_token'] ) ) ) {
			$secret_changes['pap_api_v3_token'] = self::short( wp_unslash( $_POST['pap_api_v3_token'] ), 512 );
		}
		if ( $secret_changes ) {
			$result = mediline_integrations_save_secrets( $secret_changes );
			if ( is_wp_error( $result ) ) {
				self::error( $result->get_error_message() );
			}
		}
		self::redirect( 'saved' );
	}

	public static function provision() {
		self::guard( 'mediline_integrations_provision' );
		if ( ! Mediline_Integrations_Pipedrive::credentials_configured() ) {
			self::error( __( 'Save and enable valid Pipedrive API credentials first.', 'mediline-integrations' ) );
		}
		$settings = mediline_integrations_settings();
		$client   = new Mediline_Integrations_Pipedrive( $settings, mediline_integrations_secret( 'pipedrive_api_token' ) );
		$result   = $client->provision_fields();
		if ( is_wp_error( $result ) ) {
			self::error( $result->get_error_message() );
		}
		update_option( 'mediline_integrations_settings', array_merge( $settings, $result ), false );
		self::redirect( 'provisioned' );
	}

	public static function register_webhook() {
		self::guard( 'mediline_integrations_webhook' );
		if ( ! Mediline_Integrations_Pipedrive::configured() ) {
			self::error( __( 'Save valid Pipedrive credentials and provision all required Deal fields first.', 'mediline-integrations' ) );
		}
		$url = rest_url( 'mediline-integrations/v1/pipedrive/won' );
		if ( 0 !== stripos( $url, 'https://' ) ) {
			self::error( __( 'The public WordPress URL must use HTTPS before Pipedrive can register the webhook.', 'mediline-integrations' ) );
		}
		$settings = mediline_integrations_settings();
		$client   = new Mediline_Integrations_Pipedrive( $settings, mediline_integrations_secret( 'pipedrive_api_token' ) );
		if ( ! empty( $settings['pipedrive_webhook_id'] ) ) {
			$deleted = $client->request( 'DELETE', '/api/v1/webhooks/' . rawurlencode( (string) $settings['pipedrive_webhook_id'] ) );
			if ( is_wp_error( $deleted ) ) {
				$data = $deleted->get_error_data();
				if ( ! is_array( $data ) || 404 !== (int) ( $data['status'] ?? 0 ) ) {
					self::error( $deleted->get_error_message() );
				}
			}
			$settings['pipedrive_webhook_id'] = '';
			update_option( 'mediline_integrations_settings', $settings, false );
		}
		$result   = $client->request(
			'POST',
			'/api/v1/webhooks',
			array(
				'subscription_url'  => $url,
				'event_action'      => 'change',
				'event_object'      => 'deal',
				'version'           => '2.0',
				'name'              => 'Mediline PAP won sale',
				'http_auth_user'    => mediline_integrations_secret( 'webhook_username' ),
				'http_auth_password'=> mediline_integrations_secret( 'webhook_password' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			self::error( $result->get_error_message() );
		}
		$webhook_id = self::identifier( is_array( $result ) ? ( $result['id'] ?? '' ) : '' );
		if ( ! $webhook_id ) {
			self::error( __( 'Pipedrive created no identifiable webhook. Review the Pipedrive webhook dashboard before retrying.', 'mediline-integrations' ) );
		}
		$settings['pipedrive_webhook_id'] = $webhook_id;
		update_option( 'mediline_integrations_settings', $settings, false );
		self::redirect( 'webhook_registered' );
	}

	public static function rotate_webhook_credentials() {
		self::guard( 'mediline_integrations_rotate_webhook' );
		$settings = mediline_integrations_settings();
		if ( ! empty( $settings['pipedrive_webhook_id'] ) && Mediline_Integrations_Pipedrive::credentials_configured() ) {
			$client  = new Mediline_Integrations_Pipedrive( $settings, mediline_integrations_secret( 'pipedrive_api_token' ) );
			$deleted = $client->request( 'DELETE', '/api/v1/webhooks/' . rawurlencode( (string) $settings['pipedrive_webhook_id'] ) );
			if ( is_wp_error( $deleted ) ) {
				$data = $deleted->get_error_data();
				if ( ! is_array( $data ) || 404 !== (int) ( $data['status'] ?? 0 ) ) {
					self::error( $deleted->get_error_message() );
				}
			}
		}
		$result = mediline_integrations_save_secrets(
			array(
				'webhook_username' => 'mediline-pap',
				'webhook_password' => wp_generate_password( 40, true, true ),
			)
		);
		if ( is_wp_error( $result ) ) {
			self::error( $result->get_error_message() );
		}
		$settings['pipedrive_webhook_id'] = '';
		update_option( 'mediline_integrations_settings', $settings, false );
		self::redirect( 'webhook_rotated' );
	}

	public static function retry_job() {
		$job_id = absint( $_POST['job_id'] ?? 0 );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( 'mediline_integrations_retry_' . $job_id );
		global $wpdb;
		$wpdb->update(
			Mediline_Integrations_DB::table_name(),
			array( 'status' => 'pending', 'attempts' => 0, 'available_at' => gmdate( 'Y-m-d H:i:s' ), 'last_error' => '', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
			array( 'id' => $job_id, 'status' => 'failed' ),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
		Mediline_Integrations_Workflow::schedule_worker();
		self::redirect( 'job_retried' );
	}

	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 403 );
		}
		check_admin_referer( $action );
	}

	private static function error( $message ) {
		set_transient( 'mediline_integrations_admin_error_' . get_current_user_id(), self::short( $message, 300 ), MINUTE_IN_SECONDS );
		self::redirect( 'error' );
	}

	private static function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'mediline-integrations', 'mediline_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function identifier( $value ) {
		$value = self::short( wp_unslash( $value ), 100 );
		return preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );
	}

	private static function field_code( $value ) {
		$value = strtolower( self::short( wp_unslash( $value ), 64 ) );
		return preg_match( '/^[a-z0-9_]{0,64}$/', $value ) ? $value : '';
	}

	private static function short( $value, $max ) {
		$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
