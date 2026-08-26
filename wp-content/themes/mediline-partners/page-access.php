<?php
/**
 * Partner login and registration routes.
 *
 * Credentials are posted directly to the configured Post Affiliate Pro
 * endpoint and are never stored by WordPress.
 *
 * @package Mediline_Partners
 */

$mode       = get_query_var( 'mediline_access' );
$is_login   = 'login' === $mode;
$pap_action = $is_login ? mediline_partners_option( 'pap_login_url' ) : mediline_partners_option( 'pap_signup_url' );
$heading    = mediline_partners_option( $is_login ? 'login_heading' : 'register_heading' );
$body       = mediline_partners_option( $is_login ? 'login_body' : 'register_body' );
$kicker     = mediline_partners_option( $is_login ? 'login_kicker' : 'register_kicker' );
$visual_kicker  = mediline_partners_option( $is_login ? 'login_visual_kicker' : 'register_visual_kicker' );
$visual_heading = mediline_partners_option( $is_login ? 'login_visual_heading' : 'register_visual_heading' );

get_header();
?>
<main class="auth-page" data-access-mode="<?php echo esc_attr( $mode ); ?>">
	<header class="auth-header">
		<?php mediline_partners_brand( false, mediline_partners_language_home_url() ); ?>
		<div class="auth-header-actions"><?php mediline_partners_language_switcher( 'auth' ); ?><a class="auth-back" href="<?php echo esc_url( mediline_partners_language_home_url() ); ?>"><span aria-hidden="true">←</span> <?php echo esc_html( mediline_partners_t( 'back_program', 'Back to program' ) ); ?></a></div>
	</header>

	<section class="auth-shell">
		<div class="auth-form-panel">
			<div class="auth-form-wrap">
				<div class="auth-kicker"><i></i> Mediline Partner Program <b><?php echo esc_html( $kicker ); ?></b></div>
				<h1><?php echo wp_kses( $heading, mediline_partners_allowed_inline_html() ); ?></h1>
				<p class="auth-intro"><?php echo esc_html( $body ); ?></p>

				<form class="partner-form" action="<?php echo esc_url( $pap_action ); ?>" method="post"<?php if ( ! $is_login ) : ?> data-mediline-crm-form data-mediline-form-type="partner_application"<?php endif; ?>>
					<?php if ( $is_login ) : ?>
						<label class="auth-field auth-field-full"><span><?php echo esc_html( mediline_partners_t( 'email', 'Email address' ) ); ?></span><input name="username" type="email" autocomplete="email" placeholder="name@company.com" required></label>
						<label class="auth-field auth-field-full"><span><?php echo esc_html( mediline_partners_t( 'password', 'Password' ) ); ?></span><span class="password-field"><input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required><button type="button" class="password-toggle" aria-label="<?php echo esc_attr( mediline_partners_t( 'show', 'Show' ) ); ?>"><?php echo esc_html( mediline_partners_t( 'show', 'Show' ) ); ?></button></span></label>
						<div class="auth-form-row"><label class="remember-check"><input type="checkbox" name="rememberMe" value="Y"><span><?php echo esc_html( mediline_partners_t( 'keep_signed_in', 'Keep me signed in' ) ); ?></span></label><a href="<?php echo esc_url( mediline_partners_option( 'pap_login_url' ) . '#PasswordRequest' ); ?>" class="auth-inline-link"><?php echo esc_html( mediline_partners_t( 'forgot_password', 'Forgot password?' ) ); ?></a></div>
					<?php else : ?>
						<label class="mediline-form-trap" aria-hidden="true"><span>Website</span><input name="company_website" type="text" tabindex="-1" autocomplete="off"></label>
						<input type="hidden" name="pap_visitor_id" id="pap_visitor_id" value="">
						<input type="hidden" name="pap_affiliate_id" id="pap_affiliate_id" value="">
						<input type="hidden" name="language" value="<?php echo esc_attr( mediline_partners_current_language() ); ?>">
						<input type="hidden" name="utm_source" value=""><input type="hidden" name="utm_medium" value=""><input type="hidden" name="utm_campaign" value=""><input type="hidden" name="utm_term" value=""><input type="hidden" name="utm_content" value="">
						<input type="hidden" name="gclid" value=""><input type="hidden" name="fbclid" value=""><input type="hidden" name="landing_url" value="">
						<input type="hidden" name="submission_id" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
						<label class="auth-field"><span><?php echo esc_html( mediline_partners_t( 'first_name', 'First name' ) ); ?></span><input name="firstname" type="text" autocomplete="given-name" required></label>
						<label class="auth-field"><span><?php echo esc_html( mediline_partners_t( 'last_name', 'Last name' ) ); ?></span><input name="lastname" type="text" autocomplete="family-name" required></label>
						<label class="auth-field auth-field-full"><span><?php echo esc_html( mediline_partners_t( 'email', 'Email address' ) ); ?></span><input name="username" type="email" autocomplete="email" placeholder="name@company.com" required></label>
						<label class="auth-field auth-field-full"><span><?php echo esc_html( mediline_partners_t( 'messenger', 'Messenger link(s)' ) ); ?></span><textarea name="data26" rows="3" placeholder="Telegram / WhatsApp" required></textarea></label>
						<label class="terms-check auth-field-full"><input type="checkbox" name="agreeWithTerms" value="Y" required><span><?php echo esc_html( mediline_partners_t( 'accept_prefix', 'I accept the' ) ); ?> <a href="<?php echo esc_url( mediline_partners_route_url( 'terms-conditions' ) ); ?>"><?php echo esc_html( mediline_partners_t( 'program_terms', 'program rules and terms' ) ); ?></a>.</span></label>
					<?php endif; ?>

					<button class="auth-submit auth-field-full" type="submit"><span><?php echo esc_html( mediline_partners_t( $is_login ? 'sign_in_panel' : 'submit_application', $is_login ? 'Sign in to partner panel' : 'Submit application' ) ); ?></span><span class="auth-arrow" aria-hidden="true">↗</span></button>
				</form>

				<div class="auth-switch"><span><?php echo esc_html( mediline_partners_t( $is_login ? 'not_partner' : 'already_account', $is_login ? 'Not a partner yet?' : 'Already have an account?' ) ); ?></span><a href="<?php echo esc_url( mediline_partners_route_url( $is_login ? 'register' : 'login' ) ); ?>"><?php echo esc_html( mediline_partners_t( $is_login ? 'apply_now' : 'sign_in', $is_login ? 'Apply now' : 'Sign in' ) ); ?> <span aria-hidden="true">→</span></a></div>
				<p class="auth-security"><i aria-hidden="true">P</i> <?php echo esc_html( mediline_partners_t( 'pap_security', 'Account processing and secure access are handled by Post Affiliate Pro.' ) ); ?></p>
			</div>
		</div>

		<aside class="auth-visual" aria-label="<?php esc_attr_e( 'Mediline partner program highlights', 'mediline-partners' ); ?>">
			<div class="auth-visual-grid"></div><div class="auth-orbit auth-orbit-one"></div><div class="auth-orbit auth-orbit-two"></div>
			<span class="auth-visual-index">PARTNER ACCESS / 2026</span>
			<div class="auth-visual-copy"><span><?php echo esc_html( $visual_kicker ); ?></span><h2><?php echo wp_kses( $visual_heading, mediline_partners_allowed_inline_html() ); ?></h2></div>
			<div class="auth-metric auth-metric-main"><span><?php echo esc_html( mediline_partners_t( 'commission_range', 'Commission range' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_1_value' ) ); ?></strong><i><?php echo esc_html( mediline_partners_t( 'per_sale', 'Per attributed sale' ) ); ?></i></div>
			<div class="auth-metric auth-metric-small"><span><?php echo esc_html( mediline_partners_t( 'attribution', 'Attribution' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_6_value' ) ); ?></strong></div>
			<div class="auth-payout"><i></i><span><?php echo esc_html( mediline_partners_t( 'next_payout', 'Next payout cycle' ) ); ?></span><strong><?php echo esc_html( mediline_partners_t( 'weekly_usdt', 'Weekly / USDT' ) ); ?></strong></div>
			<div class="auth-bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
			<div class="auth-visual-foot"><span><?php echo esc_html( mediline_partners_t( 'eu_usa_traffic', 'EU + USA traffic' ) ); ?></span><span><?php echo esc_html( mediline_partners_t( 'pap_access', 'PAP-powered access' ) ); ?></span></div>
		</aside>
	</section>
</main>
<?php get_footer(); ?>
