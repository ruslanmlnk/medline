<?php
/**
 * Mediline Partners landing page.
 *
 * @package Mediline_Partners
 */

get_header();

$register_url = mediline_partners_option( 'pap_signup_url' );
$login_url    = mediline_partners_option( 'pap_login_url' );
$templates    = mediline_partners_get_templates();
$faqs         = mediline_partners_get_faqs();
$template_set = array();
$categories   = array( 'All' );

foreach ( $templates as $index => $template_post ) {
	$template_set[] = mediline_partners_template_data( $template_post, $index );
	$category       = get_post_meta( $template_post->ID, '_mp_category', true );
	if ( $category && ! in_array( $category, $categories, true ) ) {
		$categories[] = $category;
	}
}
?>
<main id="top">
	<header class="site-header">
		<div class="header-inner">
			<?php mediline_partners_brand( false, '#top' ); ?>
			<nav class="desktop-nav" aria-label="<?php esc_attr_e( 'Main navigation', 'mediline-partners' ); ?>">
				<a href="#program"><?php echo esc_html( mediline_partners_option( 'nav_program' ) ); ?></a><a href="#how"><?php echo esc_html( mediline_partners_option( 'nav_how' ) ); ?></a><a href="#templates"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a><a href="#conditions"><?php echo esc_html( mediline_partners_option( 'nav_conditions' ) ); ?></a>
			</nav>
			<div class="header-actions"><?php mediline_partners_language_switcher(); ?><a href="<?php echo esc_url( $login_url ); ?>" class="login-link"><span><?php echo esc_html( mediline_partners_option( 'nav_login' ) ); ?></span><?php mediline_partners_login_arrow(); ?></a><a href="<?php echo esc_url( $register_url ); ?>" class="button button-dark compact"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?></a></div>
			<button class="menu-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle menu', 'mediline-partners' ); ?>" aria-expanded="false"><span></span><span></span></button>
		</div>
	</header>

	<div class="mobile-panel">
		<nav><a href="#program"><?php echo esc_html( mediline_partners_option( 'nav_program' ) ); ?></a><a href="#how"><?php echo esc_html( mediline_partners_option( 'nav_how' ) ); ?></a><a href="#templates"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a><a href="#conditions"><?php echo esc_html( mediline_partners_option( 'nav_conditions' ) ); ?></a><a href="#faq"><?php echo esc_html( mediline_partners_option( 'nav_faq' ) ); ?></a></nav>
		<?php mediline_partners_language_switcher( 'mobile' ); ?>
		<a href="<?php echo esc_url( $register_url ); ?>" class="button button-light"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?> <?php mediline_partners_arrow( true ); ?></a>
	</div>

	<section class="hero hero-v7 section-shell">
		<div class="hero-v7-grid" aria-hidden="true"></div>
		<div class="hero-v7-left-mask" aria-hidden="true"></div>
		<div class="hero-copy reveal visible">
			<span class="eyebrow"><i class="status-dot"></i> <?php echo esc_html( mediline_partners_option( 'hero_eyebrow' ) ); ?> <b><?php echo esc_html( mediline_partners_option( 'hero_status' ) ); ?></b></span>
			<h1><span><?php echo esc_html( mediline_partners_option( 'hero_line_1' ) ); ?></span><span><?php echo esc_html( mediline_partners_option( 'hero_line_2' ) ); ?></span><em><?php echo esc_html( mediline_partners_option( 'hero_commission' ) ); ?></em></h1>
			<p><?php echo esc_html( mediline_partners_option( 'hero_body' ) ); ?></p>
			<div class="hero-actions"><a href="<?php echo esc_url( $register_url ); ?>" class="button button-accent"><?php echo esc_html( mediline_partners_option( 'hero_primary_cta' ) ); ?> <?php mediline_partners_arrow( true ); ?></a><a href="<?php echo esc_url( $login_url ); ?>" class="text-link"><?php echo esc_html( mediline_partners_option( 'hero_secondary_cta' ) ); ?> <?php mediline_partners_arrow(); ?></a></div>
			<div class="hero-v7-proof" aria-label="<?php esc_attr_e( 'Program highlights', 'mediline-partners' ); ?>">
				<?php for ( $i = 1; $i <= 3; $i++ ) : ?><div><strong><?php echo esc_html( mediline_partners_option( "hero_proof_{$i}_value" ) ); ?></strong><span><?php echo esc_html( mediline_partners_option( "hero_proof_{$i}_label" ) ); ?></span></div><?php endfor; ?>
			</div>
		</div>
		<div class="hero-visual hero-v7-visual">
			<div class="poster-word poster-word-one" aria-hidden="true"><?php echo esc_html( mediline_partners_option( 'hero_art_word_1' ) ); ?></div><div class="poster-word poster-word-two" aria-hidden="true"><?php echo esc_html( mediline_partners_option( 'hero_art_word_2' ) ); ?></div>
			<div class="poster-orbit orbit-a" aria-hidden="true"></div><div class="poster-orbit orbit-b" aria-hidden="true"></div>
			<div class="money-art"><picture><source media="(max-width: 600px)" srcset="<?php echo esc_url( MEDILINE_PARTNERS_URI . '/assets/images/mediline-affiliate-money-art-768.webp' ); ?>" type="image/webp"><source srcset="<?php echo esc_url( MEDILINE_PARTNERS_URI . '/assets/images/mediline-affiliate-money-art.webp' ); ?>" type="image/webp"><img src="<?php echo esc_url( MEDILINE_PARTNERS_URI . '/assets/images/mediline-affiliate-money-art.png' ); ?>" alt="<?php esc_attr_e( 'Illustration of hands moving affiliate commission notes and tokens', 'mediline-partners' ); ?>" width="1145" height="1374" fetchpriority="high" decoding="async"></picture></div>
			<div class="commission-token token-forty"><span><?php echo esc_html( mediline_partners_option( 'hero_token_1_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'hero_token_1_value' ) ); ?></strong></div>
			<div class="commission-token token-fifty"><span><?php echo esc_html( mediline_partners_option( 'hero_token_2_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'hero_token_2_value' ) ); ?></strong></div>
			<div class="poster-ticket"><span><?php echo esc_html( mediline_partners_option( 'hero_ticket_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'hero_ticket_value' ) ); ?></strong><i><?php echo esc_html( mediline_partners_option( 'hero_ticket_meta' ) ); ?></i></div>
			<div class="poster-caption"><span><?php echo esc_html( mediline_partners_option( 'hero_caption_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'hero_caption_value' ) ); ?></strong></div>
		</div>
		<div class="hero-v7-rail"><span><?php echo esc_html( mediline_partners_option( 'hero_rail_left' ) ); ?></span><a href="#program" aria-label="<?php esc_attr_e( 'Explore the Mediline partner program', 'mediline-partners' ); ?>"><?php echo esc_html( mediline_partners_option( 'hero_rail_right' ) ); ?> <i>↓</i></a></div>
	</section>

	<section class="trust-strip"><?php for ( $i = 1; $i <= 4; $i++ ) : ?><span><?php echo esc_html( mediline_partners_option( "trust_{$i}" ) ); ?></span><?php endfor; ?></section>

	<section id="program" class="program-intro section-shell section-space">
		<div class="section-index reveal"><span>02</span><p><?php echo esc_html( mediline_partners_option( 'program_kicker' ) ); ?></p></div>
		<div class="intro-statement reveal"><h2><?php echo wp_kses( mediline_partners_option( 'program_heading' ), mediline_partners_allowed_inline_html() ); ?></h2><div class="intro-body"><p><?php echo esc_html( mediline_partners_option( 'program_body' ) ); ?></p><a href="#how" class="text-link"><?php echo esc_html( mediline_partners_t( 'see_how', 'See how it works' ) ); ?> <?php mediline_partners_arrow(); ?></a></div></div>
		<div class="value-grid">
			<?php for ( $i = 1; $i <= 3; $i++ ) : ?><article class="value-card <?php echo 2 === $i ? 'featured' : ''; ?> reveal"><span class="value-number">0<?php echo esc_html( $i ); ?></span><h3><?php echo wp_kses( mediline_partners_option( "program_card_{$i}_title" ), mediline_partners_allowed_inline_html() ); ?></h3><p><?php echo esc_html( mediline_partners_option( "program_card_{$i}_body" ) ); ?></p><?php if ( 2 === $i ) : ?><div class="value-sculpture"><i></i><i></i><i></i></div><?php endif; ?></article><?php endfor; ?>
		</div>
	</section>

	<section id="how" class="how-section section-space">
		<div class="section-shell">
			<div class="section-heading light reveal"><div><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'process_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'process_heading' ), mediline_partners_allowed_inline_html() ); ?></h2></div><p><?php echo esc_html( mediline_partners_option( 'process_body' ) ); ?></p></div>
			<div class="steps-list">
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?><article class="step-row reveal"><span>0<?php echo esc_html( $i ); ?></span><h3><?php echo esc_html( mediline_partners_option( "step_{$i}_title" ) ); ?></h3><p><?php echo esc_html( mediline_partners_option( "step_{$i}_body" ) ); ?></p><i class="<?php echo 5 === $i ? 'step-check' : 'step-arrow'; ?>"><?php echo 5 === $i ? '✓' : '→'; ?></i></article><?php endfor; ?>
			</div>
		</div>
	</section>

	<section id="templates" class="templates-section section-shell section-space">
		<div class="section-index reveal"><span>03</span><p><?php echo esc_html( mediline_partners_t( 'store_templates', 'Store templates' ) ); ?></p></div>
		<div class="templates-heading reveal"><div><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'templates_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'templates_heading' ), mediline_partners_allowed_inline_html() ); ?></h2></div><p><?php echo esc_html( mediline_partners_option( 'templates_body' ) ); ?></p></div>
		<div class="template-filters" role="group" aria-label="<?php esc_attr_e( 'Filter templates', 'mediline-partners' ); ?>">
			<?php foreach ( $categories as $index => $category ) : ?><button type="button" data-filter="<?php echo esc_attr( $category ); ?>" class="<?php echo 0 === $index ? 'active' : ''; ?>"><?php echo esc_html( 'All' === $category ? mediline_partners_t( 'all', 'All' ) : mediline_partners_category_label( $category ) ); ?></button><?php endforeach; ?>
		</div>
		<div class="template-grid">
			<?php foreach ( $template_set as $index => $template ) : ?>
				<button type="button" class="template-card reveal <?php echo 1 === $index % 3 ? 'offset' : ''; ?>" data-category="<?php echo esc_attr( $template['category'] ); ?>" data-template="<?php echo esc_attr( wp_json_encode( $template ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Open %s preview', 'mediline-partners' ), $template['name'] ) ); ?>">
					<div class="template-frame"><?php mediline_partners_store_cover( $template ); ?></div>
					<div class="template-meta"><div><span><?php echo esc_html( $template['number'] . ' / ' . $template['category_label'] ); ?></span><h3><?php echo esc_html( $template['name'] ); ?></h3></div><span class="preview-action"><?php echo esc_html( mediline_partners_t( 'full_preview', 'Full preview' ) ); ?> <?php mediline_partners_arrow( true ); ?></span></div>
				</button>
			<?php endforeach; ?>
		</div>
		<div class="resource-note reveal"><span class="resource-icon">P</span><div><h3><?php echo esc_html( mediline_partners_option( 'templates_note_title' ) ); ?></h3><p><?php echo esc_html( mediline_partners_option( 'templates_note_body' ) ); ?></p></div><a href="<?php echo esc_url( $login_url ); ?>" class="button button-dark"><?php echo esc_html( mediline_partners_t( 'partner_login', 'Partner login' ) ); ?> <?php mediline_partners_arrow( true ); ?></a></div>
	</section>

	<section id="benefits" class="benefits-section section-space">
		<div class="benefit-visual reveal">
			<div class="benefit-dashboard" aria-label="<?php echo esc_attr( mediline_partners_option( 'benefits_portal_label' ) ); ?>">
				<div class="benefit-dashboard-head">
					<span class="benefit-dashboard-label"><i></i><?php echo esc_html( mediline_partners_option( 'benefits_portal_label' ) ); ?></span>
					<strong class="benefit-dashboard-brand">M+</strong>
				</div>
				<div class="benefit-dashboard-performance">
					<div class="benefit-dashboard-rate">
						<span><?php echo esc_html( mediline_partners_option( 'benefits_rate_label' ) ); ?></span>
						<strong><?php echo esc_html( mediline_partners_option( 'condition_1_value' ) ); ?></strong>
						<small><?php echo esc_html( mediline_partners_option( 'benefit_1_title' ) ); ?></small>
					</div>
					<div class="benefit-dashboard-chart" aria-hidden="true">
						<span>01 / 04</span>
						<svg viewBox="0 0 240 150" preserveAspectRatio="none" focusable="false">
							<path class="benefit-chart-grid" d="M0 30H240 M0 75H240 M0 120H240"></path>
							<polyline class="benefit-chart-line" points="0,124 39,109 76,116 118,79 158,87 202,42 240,28"></polyline>
							<circle cx="202" cy="42" r="5"></circle><circle cx="240" cy="28" r="6"></circle>
						</svg>
					</div>
				</div>
				<div class="benefit-dashboard-metrics">
					<article><span><?php echo esc_html( mediline_partners_option( 'benefits_cookie_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'condition_6_value' ) ); ?></strong></article>
					<article><span><?php echo esc_html( mediline_partners_option( 'benefits_payout_label' ) ); ?></span><strong><?php echo esc_html( mediline_partners_option( 'hero_proof_2_value' ) ); ?></strong></article>
				</div>
				<div class="benefit-dashboard-status" aria-hidden="true"><span>01</span><i><b></b></i><span>04</span></div>
			</div>
		</div>
		<div class="benefits-copy reveal"><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'benefits_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'benefits_heading' ), mediline_partners_allowed_inline_html() ); ?></h2><div class="benefit-list"><?php for ( $i = 1; $i <= 4; $i++ ) : ?><article><span>0<?php echo esc_html( $i ); ?></span><div><h3><?php echo esc_html( mediline_partners_option( "benefit_{$i}_title" ) ); ?></h3><p><?php echo esc_html( mediline_partners_option( "benefit_{$i}_body" ) ); ?></p></div></article><?php endfor; ?></div></div>
	</section>

	<section id="conditions" class="conditions-section section-shell section-space">
		<div class="conditions-head reveal"><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'conditions_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'conditions_heading' ), mediline_partners_allowed_inline_html() ); ?></h2><p><?php echo esc_html( mediline_partners_option( 'conditions_body' ) ); ?></p></div>
		<div class="conditions-panel reveal"><div class="condition-main"><span class="condition-kicker"><?php echo esc_html( mediline_partners_option( 'commission_kicker' ) ); ?></span><h3><?php echo wp_kses( mediline_partners_option( 'commission_heading' ), mediline_partners_allowed_inline_html() ); ?></h3><p><?php echo esc_html( mediline_partners_option( 'commission_body' ) ); ?></p><a href="<?php echo esc_url( $register_url ); ?>" class="button button-accent"><?php echo esc_html( mediline_partners_option( 'hero_primary_cta' ) ); ?> <?php mediline_partners_arrow( true ); ?></a></div><div class="condition-table"><?php for ( $i = 1; $i <= 6; $i++ ) : ?><div><span><?php echo esc_html( mediline_partners_option( "condition_{$i}_label" ) ); ?></span><b><?php echo esc_html( mediline_partners_option( "condition_{$i}_value" ) ); ?></b></div><?php endfor; ?></div></div>
		<div class="traffic-policy reveal"><?php for ( $i = 1; $i <= 3; $i++ ) : ?><div><span><?php echo esc_html( mediline_partners_option( "traffic_{$i}_label" ) ); ?></span><b><?php echo esc_html( mediline_partners_option( "traffic_{$i}_value" ) ); ?></b></div><?php endfor; ?></div>
		<p class="conditions-footnote reveal"><?php echo esc_html( mediline_partners_option( 'conditions_footnote' ) ); ?></p>
	</section>

	<section id="faq" class="faq-section section-shell section-space">
		<div class="faq-title reveal"><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'faq_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'faq_heading' ), mediline_partners_allowed_inline_html() ); ?></h2></div>
		<div class="faq-list reveal">
			<?php foreach ( $faqs as $index => $faq ) : $faq_data = mediline_partners_faq_data( $faq ); ?><article class="<?php echo 0 === $index ? 'open' : ''; ?>"><button type="button" aria-expanded="<?php echo 0 === $index ? 'true' : 'false'; ?>"><span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><b><?php echo esc_html( $faq_data['question'] ); ?></b><i><?php echo 0 === $index ? '−' : '+'; ?></i></button><div class="faq-answer"><p><?php echo esc_html( $faq_data['answer'] ); ?></p></div></article><?php endforeach; ?>
		</div>
	</section>

	<section class="final-cta"><div class="cta-grid"></div><div class="cta-orb one"></div><div class="cta-orb two"></div><div class="final-cta-inner reveal"><span class="eyebrow"><?php echo esc_html( mediline_partners_option( 'cta_kicker' ) ); ?></span><h2><?php echo wp_kses( mediline_partners_option( 'cta_heading' ), mediline_partners_allowed_inline_html() ); ?></h2><p><?php echo esc_html( mediline_partners_option( 'cta_body' ) ); ?></p><div><a href="<?php echo esc_url( $register_url ); ?>" class="button button-light"><?php echo esc_html( mediline_partners_option( 'cta_register' ) ); ?> <?php mediline_partners_arrow( true ); ?></a><a href="<?php echo esc_url( $login_url ); ?>" class="button button-outline-light"><?php echo esc_html( mediline_partners_option( 'cta_login' ) ); ?></a></div></div></section>

	<footer>
		<div class="footer-main section-shell">
			<?php mediline_partners_brand( true, '#top' ); ?>
			<div class="footer-nav"><span><?php echo esc_html( mediline_partners_t( 'navigate', 'Navigate' ) ); ?></span><a href="#program"><?php echo esc_html( mediline_partners_option( 'nav_program' ) ); ?></a><a href="#how"><?php echo esc_html( mediline_partners_option( 'nav_how' ) ); ?></a><a href="#templates"><?php echo esc_html( mediline_partners_option( 'nav_templates' ) ); ?></a><a href="#faq"><?php echo esc_html( mediline_partners_option( 'nav_faq' ) ); ?></a></div>
			<div class="footer-nav"><span><?php echo esc_html( mediline_partners_t( 'partner_access', 'Partner access' ) ); ?></span><a href="<?php echo esc_url( $register_url ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_register' ) ); ?> →</a><a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( mediline_partners_option( 'nav_login' ) ); ?> →</a><a href="<?php echo esc_url( mediline_partners_route_url( 'terms-conditions' ) ); ?>"><?php echo esc_html( mediline_partners_t( 'terms', 'Terms' ) ); ?> ↗</a></div>
			<div class="footer-note"><span><?php echo esc_html( mediline_partners_t( 'public_partner_site', 'Public partner website' ) ); ?></span><p><?php echo esc_html( mediline_partners_option( 'footer_note' ) ); ?> <?php echo esc_html( mediline_partners_t( 'support', 'Support' ) ); ?>: <a href="mailto:<?php echo esc_attr( mediline_partners_option( 'support_email' ) ); ?>"><?php echo esc_html( mediline_partners_option( 'support_email' ) ); ?></a></p></div>
		</div>
		<div class="footer-bottom section-shell"><span><?php echo esc_html( mediline_partners_option( 'footer_copyright' ) ); ?></span><span><?php echo esc_html( mediline_partners_option( 'footer_tagline' ) ); ?></span></div>
	</footer>

	<div class="modal-backdrop" role="presentation" hidden>
		<div class="template-modal" role="dialog" aria-modal="true" aria-labelledby="template-modal-title">
			<div class="modal-topbar"><div><span class="modal-number"><?php echo esc_html( mediline_partners_t( 'template_preview', 'Template preview' ) ); ?> / 01</span><h2 id="template-modal-title"></h2></div><button type="button" class="modal-close" aria-label="<?php esc_attr_e( 'Close template preview', 'mediline-partners' ); ?>"><span></span><span></span></button></div>
			<div class="modal-content">
				<div class="modal-preview-wrap"></div>
				<aside class="modal-details">
					<div><span class="eyebrow modal-category"></span><h3 class="modal-tagline"></h3><p class="modal-description"></p></div>
					<dl><div><dt><?php echo esc_html( mediline_partners_t( 'visual_tone', 'Visual tone' ) ); ?></dt><dd class="modal-tone"></dd></div><div><dt><?php echo esc_html( mediline_partners_t( 'best_for', 'Best for' ) ); ?></dt><dd class="modal-audience"></dd></div><div><dt><?php echo esc_html( mediline_partners_t( 'traffic_markets', 'Traffic markets' ) ); ?></dt><dd><?php echo esc_html( mediline_partners_t( 'europe_usa', 'Europe + USA' ) ); ?></dd></div></dl>
					<div class="access-note"><span aria-hidden="true">i</span><p><b><?php echo esc_html( mediline_partners_t( 'preview_only', 'Preview only.' ) ); ?></b> <?php echo esc_html( mediline_partners_t( 'preview_note', 'Approved partners access the template and launch resources inside the Post Affiliate Pro panel.' ) ); ?></p></div>
					<a href="<?php echo esc_url( $register_url ); ?>" class="button button-dark button-wide"><?php echo esc_html( mediline_partners_t( 'apply_partner', 'Apply to become a partner' ) ); ?> <?php mediline_partners_arrow( true ); ?></a>
					<div class="modal-pagination"><button type="button" data-modal-step="-1">← <?php echo esc_html( mediline_partners_t( 'previous_template', 'Previous' ) ); ?></button><span class="modal-position">01 / 06</span><button type="button" data-modal-step="1"><?php echo esc_html( mediline_partners_t( 'next_template', 'Next' ) ); ?> →</button></div>
				</aside>
			</div>
		</div>
	</div>
</main>
<?php get_footer(); ?>
