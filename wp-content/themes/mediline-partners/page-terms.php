<?php
/**
 * Native Mediline Partner Program terms page.
 *
 * @package Mediline_Partners
 */

get_header();

$home_url     = mediline_partners_language_home_url();
$register_url = mediline_partners_option( 'pap_signup_url' );
$login_url    = mediline_partners_option( 'pap_login_url' );
?>
<main id="top" class="terms-page">
	<header class="site-header terms-site-header">
		<div class="header-inner">
			<?php mediline_partners_brand( false, $home_url ); ?>
			<nav class="desktop-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'mediline-partners' ); ?>">
				<a href="<?php echo esc_url( $home_url . '#program' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_program' ) ); ?></a>
				<a href="<?php echo esc_url( $home_url . '#templates' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a>
				<a href="<?php echo esc_url( $home_url . '#conditions' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_conditions' ) ); ?></a>
			</nav>
			<div class="header-actions">
				<?php mediline_partners_language_switcher(); ?>
				<a href="<?php echo esc_url( $login_url ); ?>" class="login-link"><span><?php echo esc_html( mediline_partners_option( 'nav_login' ) ); ?></span><?php mediline_partners_login_arrow(); ?></a>
				<a href="<?php echo esc_url( $register_url ); ?>" class="button button-dark compact"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?></a>
			</div>
			<button class="menu-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle menu', 'mediline-partners' ); ?>" aria-expanded="false"><span></span><span></span></button>
		</div>
	</header>

	<div class="mobile-panel">
		<nav>
			<a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( mediline_partners_t( 'back_home', 'Back to program' ) ); ?></a>
			<a href="<?php echo esc_url( $home_url . '#templates' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a>
			<a href="<?php echo esc_url( $home_url . '#conditions' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_conditions' ) ); ?></a>
		</nav>
		<?php mediline_partners_language_switcher( 'mobile' ); ?>
		<a href="<?php echo esc_url( $register_url ); ?>" class="button button-light"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?> <?php mediline_partners_arrow(); ?></a>
	</div>

	<section class="terms-hero section-shell">
		<div class="terms-grid" aria-hidden="true"></div>
		<div class="terms-hero-copy reveal visible">
			<span class="eyebrow"><i class="status-dot"></i><?php echo esc_html( mediline_partners_option( 'terms_kicker' ) ); ?></span>
			<h1><?php echo wp_kses( mediline_partners_option( 'terms_heading' ), mediline_partners_allowed_inline_html() ); ?></h1>
			<p><?php echo esc_html( mediline_partners_option( 'terms_intro' ) ); ?></p>
			<div class="terms-source"><span>01</span><div><b><?php echo esc_html( mediline_partners_option( 'terms_updated' ) ); ?></b><small><?php echo esc_html( mediline_partners_t( 'terms_editable_note', 'Editable in WordPress → Mediline Content' ) ); ?></small></div></div>
		</div>
		<aside class="terms-summary reveal visible">
			<span class="terms-summary-kicker"><?php echo esc_html( mediline_partners_t( 'program_snapshot', 'Program snapshot' ) ); ?></span>
			<div><span><?php echo esc_html( mediline_partners_option( 'condition_1_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_1_value' ) ); ?></strong></div>
			<div><span><?php echo esc_html( mediline_partners_option( 'condition_6_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_6_value' ) ); ?></strong></div>
			<div><span><?php echo esc_html( mediline_partners_option( 'condition_3_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_3_value' ) ); ?></strong></div>
			<div><span><?php echo esc_html( mediline_partners_option( 'condition_4_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_4_value' ) ); ?></strong></div>
			<div><span><?php echo esc_html( mediline_partners_option( 'condition_5_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_5_value' ) ); ?></strong></div>
		</aside>
	</section>

	<section class="terms-content section-shell">
		<aside class="terms-aside reveal">
			<span><?php echo esc_html( mediline_partners_t( 'terms_contents', 'Program terms' ) ); ?></span>
			<p><?php echo esc_html( mediline_partners_option( 'conditions_footnote' ) ); ?></p>
			<a href="<?php echo esc_url( $register_url ); ?>" class="button button-dark"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?> <?php mediline_partners_arrow(); ?></a>
		</aside>
		<article class="terms-document reveal">
			<?php echo wp_kses_post( mediline_partners_option( 'terms_body' ) ); ?>
		</article>
	</section>

	<footer class="terms-footer">
		<div class="footer-main section-shell">
			<?php mediline_partners_brand( true, $home_url ); ?>
			<div class="footer-nav"><span><?php echo esc_html( mediline_partners_t( 'navigate', 'Navigate' ) ); ?></span><a href="<?php echo esc_url( $home_url ); ?>"><?php echo esc_html( mediline_partners_t( 'back_home', 'Back to program' ) ); ?></a><a href="<?php echo esc_url( $home_url . '#templates' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a><a href="<?php echo esc_url( $home_url . '#faq' ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_faq' ) ); ?></a></div>
			<div class="footer-nav"><span><?php echo esc_html( mediline_partners_t( 'partner_access', 'Partner access' ) ); ?></span><a href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?> →</a><a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_login' ) ); ?> →</a></div>
			<div class="footer-note"><span><?php echo esc_html( mediline_partners_t( 'support', 'Support' ) ); ?></span><p><a href="mailto:<?php echo esc_attr( mediline_partners_option( 'support_email' ) ); ?>"><?php echo esc_html( mediline_partners_option( 'support_email' ) ); ?></a></p></div>
		</div>
		<div class="footer-bottom section-shell"><span><?php echo esc_html( mediline_partners_option( 'footer_copyright' ) ); ?></span><span><?php echo esc_html( mediline_partners_option( 'footer_tagline' ) ); ?></span></div>
	</footer>
</main>
<?php get_footer(); ?>
