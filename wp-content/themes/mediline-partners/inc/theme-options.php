<?php
/**
 * Editable landing-page content and partner-program settings.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults mirror the approved production design.
 */
function mediline_partners_default_options() {
	return array(
		'pap_signup_url'             => 'https://mediline.postaffiliatepro.com/affiliates/signup.php',
		'pap_login_url'              => 'https://mediline.postaffiliatepro.com/affiliates/login.php',
		'support_email'              => 'support@mediline.io',
		'nav_program'                => 'Program',
		'nav_how'                    => 'How it works',
		'nav_templates'              => 'Store templates',
		'nav_conditions'             => 'Conditions',
		'nav_faq'                    => 'FAQ',
		'nav_login'                  => 'Log in',
		'nav_register'               => 'Register',
		'hero_eyebrow'               => 'Mediline Partner Program',
		'hero_status'                => 'Applications open',
		'hero_line_1'                => 'Turn traffic.',
		'hero_line_2'                => 'Into revenue.',
		'hero_commission'            => '40–50% commission.',
		'hero_body'                  => 'Launch a localized Mediline storefront, send qualified traffic and earn on every attributed sale — with 90-day tracking and weekly payouts.',
		'hero_primary_cta'           => 'Apply to the program',
		'hero_secondary_cta'         => 'Partner login',
		'hero_proof_1_value'         => '90 days',
		'hero_proof_1_label'         => 'Attribution window',
		'hero_proof_2_value'         => 'Weekly',
		'hero_proof_2_label'         => 'USDT payout',
		'hero_proof_3_value'         => 'EU + USA',
		'hero_proof_3_label'         => 'Approved traffic',
		'hero_art_word_1'            => 'EARN',
		'hero_art_word_2'            => 'GROW',
		'hero_token_1_label'         => 'Commission',
		'hero_token_1_value'         => '40%',
		'hero_token_2_label'         => 'Up to',
		'hero_token_2_value'         => '50%',
		'hero_ticket_label'          => 'Qualified sale',
		'hero_ticket_value'          => '+ USDT',
		'hero_ticket_meta'           => 'Weekly payout',
		'hero_caption_label'         => 'Track every attributed sale',
		'hero_caption_value'         => 'Earn. Grow. Repeat.',
		'hero_rail_left'             => 'MEDILINE / PARTNERS / 2026',
		'hero_rail_right'            => 'Explore the program',
		'trust_1'                    => '40–50% COMMISSION',
		'trust_2'                    => '90-DAY ATTRIBUTION',
		'trust_3'                    => 'EUROPE + USA',
		'trust_4'                    => 'PAP-POWERED ACCESS',
		'program_kicker'             => 'The partnership',
		'program_heading'            => 'One program.<br>Built for <em>performance.</em>',
		'program_body'               => 'Mediline gives experienced affiliates the tools, tracking and commercial structure to turn qualified traffic into sustainable revenue.',
		'program_card_1_title'       => 'Real-time<br>visibility.',
		'program_card_1_body'        => 'Use detailed performance reporting to understand traffic, conversions and campaign momentum.',
		'program_card_2_title'       => '40–50%<br>commission.',
		'program_card_2_body'        => 'Your rate is determined weekly by the number of sales in the reporting period.',
		'program_card_3_title'       => 'Partner-first<br>support.',
		'program_card_3_body'        => 'Get practical guidance, proven resources and a streamlined system for running campaigns.',
		'process_kicker'             => 'From application to launch',
		'process_heading'            => 'Five clear steps.<br>One performance system.',
		'process_body'               => 'The public site shows the available storefront directions. Approved templates, resources and campaign tools live inside your partner workspace.',
		'step_1_title'               => 'Register',
		'step_1_body'                => 'Submit your application through Post Affiliate Pro.',
		'step_2_title'               => 'Get approved',
		'step_2_body'                => 'We review your affiliate experience, traffic profile and target market.',
		'step_3_title'               => 'Choose a storefront',
		'step_3_body'                => 'Select the template direction that matches your campaign and audience.',
		'step_4_title'               => 'Set language & region',
		'step_4_body'                => 'Prepare the version for an approved European or US market.',
		'step_5_title'               => 'Launch & optimize',
		'step_5_body'                => 'Go live, track performance and refine campaigns with real data.',
		'templates_kicker'           => 'Six distinct systems',
		'templates_heading'          => 'Six storefronts.<br>One performance core.',
		'templates_body'             => 'Each preview explores a different campaign rhythm, audience and visual logic. Open any direction for a closer look.',
		'templates_note_title'       => 'Templates are shown here for demonstration only.',
		'templates_note_body'        => 'Approved partners receive access to storefront templates and partner resources after signing in to the Post Affiliate Pro Affiliate Panel.',
		'benefits_kicker'            => 'The partner advantage',
		'benefits_heading'           => 'Made to convert.<br>Built to <em>improve.</em>',
		'benefits_portal_label'      => 'PARTNER PERFORMANCE / LIVE',
		'benefits_rate_label'        => 'RATE',
		'benefits_cookie_label'      => 'COOKIE',
		'benefits_payout_label'      => 'PAYOUT',
		'benefit_1_title'            => 'Competitive commission structure',
		'benefit_1_body'             => 'Earn 40–50%, with the weekly rate determined by sales volume.',
		'benefit_2_title'            => 'Performance analytics',
		'benefit_2_body'             => 'Use transparent data to monitor traffic, conversions and earnings.',
		'benefit_3_title'            => 'Partner support',
		'benefit_3_body'             => 'Get guidance and resources for launching, learning and optimizing.',
		'benefit_4_title'            => 'Streamlined operations',
		'benefit_4_body'             => 'Manage tracking and approved materials through Post Affiliate Pro.',
		'conditions_kicker'          => 'Program conditions',
		'conditions_heading'         => 'Clear numbers.<br>Real terms.',
		'conditions_body'            => 'The core commercial rules are published up front. Your live sales, current weekly rate and payout activity remain visible inside the affiliate panel.',
		'commission_kicker'          => 'Commission model',
		'commission_heading'         => '40–50%<br>per sale',
		'commission_body'            => 'The percentage depends on the number of sales in the reporting period, is determined weekly and is not changed retroactively.',
		'condition_1_label'          => 'Commission',
		'condition_1_value'          => '40–50%',
		'condition_2_label'          => 'Rate review',
		'condition_2_value'          => 'Weekly, by sales',
		'condition_3_label'          => 'Payout',
		'condition_3_value'          => 'Once a week',
		'condition_4_label'          => 'Payment currency',
		'condition_4_value'          => 'USDT',
		'condition_5_label'          => 'Minimum payout',
		'condition_5_value'          => '100 USDT',
		'condition_6_label'          => 'Cookie period',
		'condition_6_value'          => '90 days',
		'traffic_1_label'            => 'Traffic geography',
		'traffic_1_value'            => 'European countries + USA',
		'traffic_2_label'            => 'Traffic sources',
		'traffic_2_value'            => 'All types except prohibited methods',
		'traffic_3_label'            => 'Not accepted',
		'traffic_3_value'            => 'Fraud, incentives, spam, bots, click fraud and unapproved brand PPC',
		'conditions_footnote'        => 'Commission is accrued only on successfully paid orders. Cancellations, refunds and chargebacks may cancel or reverse commission. Payment-system fees are covered by the affiliate.',
		'faq_kicker'                 => 'Questions, answered',
		'faq_heading'                => 'Partner program<br>essentials.',
		'cta_kicker'                 => 'Your next campaign starts here',
		'cta_heading'                => 'Make your traffic<br>worth <em>more.</em>',
		'cta_body'                   => 'Join Mediline Partners for a clearer launch path, performance visibility and a commission model designed to reward results.',
		'cta_register'               => 'Register as a partner',
		'cta_login'                  => 'Partner login',
		'footer_note'                => 'Storefront resources are available after approval inside the PAP Affiliate Panel.',
		'footer_tagline'             => 'Performance, made visible.',
		'footer_copyright'           => '© 2026 Mediline. All rights reserved.',
		'terms_kicker'               => 'Mediline Partner Program',
		'terms_heading'              => 'Terms &<br><em>Conditions.</em>',
		'terms_updated'              => 'Legacy program terms',
		'terms_intro'                => 'These terms describe the commercial rules of the Mediline affiliate program. They preserve the core conditions published on the previous Mediline website and can be edited from WordPress.',
		'terms_body'                 => '<h2>1. Commission</h2><p>Approved affiliates earn a commission of <strong>40–50%</strong> on qualifying sales. The applicable rate depends on sales performance for the reporting period, is reviewed weekly and is not changed retroactively for a closed period.</p><h2>2. Tracking and attribution</h2><p>Affiliate referrals are tracked for <strong>90 days</strong>. A qualifying sale must be successfully attributed to the affiliate through the approved Mediline tracking setup.</p><h2>3. Payouts</h2><p>Affiliate payouts are processed <strong>once per week</strong> in <strong>USDT</strong>. The minimum payout threshold is <strong>100 USDT</strong>. Payment-system or transfer fees are covered by the affiliate.</p><h2>4. Valid commissions</h2><p>Commission is accrued only on successfully paid orders. Cancelled orders, refunds, chargebacks or fraudulent transactions may cancel or reverse the related commission.</p><h2>5. Approved markets</h2><p>The program accepts approved traffic from <strong>European countries and the United States</strong>, subject to Mediline approval and applicable product or market restrictions.</p><h2>6. Traffic sources</h2><p>Affiliates may use approved traffic sources except prohibited methods. The following are not accepted:</p><ul><li>fraudulent or misleading traffic;</li><li>incentivized traffic that has not been explicitly approved;</li><li>spam or unsolicited bulk messaging;</li><li>bots, automated traffic or click fraud;</li><li>unapproved bidding or PPC campaigns using the Mediline brand.</li></ul><h2>7. Compliance</h2><p>Affiliates are responsible for the accuracy of their promotional activity and for complying with applicable laws, advertising rules and the requirements of the traffic source they use.</p><h2>8. Program administration</h2><p>Live sales, the current commission rate and payout activity are available in the affiliate panel. Mediline may review traffic quality and qualifying transactions when calculating commissions.</p>',
	);
}

/**
 * Retrieve one merged setting.
 */
function mediline_partners_option( $key, $fallback = '' ) {
	static $options      = null;
	static $translations = null;
	if ( null === $options ) {
		$saved   = get_option( 'mediline_partner_options', array() );
		$options = wp_parse_args( is_array( $saved ) ? $saved : array(), mediline_partners_default_options() );
	}
	$language = mediline_partners_current_language();
	if ( mediline_partners_default_language() !== $language && ! in_array( $key, mediline_partners_connection_option_keys(), true ) ) {
		if ( null === $translations ) {
			$saved_translations = get_option( 'mediline_partner_translations', array() );
			$translations       = mediline_partners_default_content_translations();
			if ( is_array( $saved_translations ) ) {
				foreach ( $saved_translations as $code => $values ) {
					$translations[ $code ] = wp_parse_args( is_array( $values ) ? $values : array(), $translations[ $code ] ?? array() );
				}
			}
		}
		if ( ! empty( $translations[ $language ][ $key ] ) ) {
			return $translations[ $language ][ $key ];
		}
	}
	return array_key_exists( $key, $options ) ? $options[ $key ] : $fallback;
}

function mediline_partners_connection_option_keys() {
	return array( 'pap_signup_url', 'pap_login_url', 'support_email' );
}

/**
 * Fields that may include controlled inline markup.
 */
function mediline_partners_html_option_keys() {
	return array(
		'program_heading', 'program_card_1_title', 'program_card_2_title', 'program_card_3_title',
		'process_heading', 'templates_heading', 'benefits_heading', 'conditions_heading',
		'commission_heading', 'faq_heading', 'cta_heading',
	);
}

function mediline_partners_allowed_inline_html() {
	return array(
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'span'   => array( 'class' => true ),
	);
}

/**
 * Sanitize the entire grouped option payload.
 */
function mediline_partners_sanitize_options( $input ) {
	$defaults = mediline_partners_default_options();
	$clean    = array();
	$urls     = array( 'pap_signup_url', 'pap_login_url' );
	$html     = mediline_partners_html_option_keys();

	foreach ( $defaults as $key => $default ) {
		$value = isset( $input[ $key ] ) ? $input[ $key ] : $default;
		if ( in_array( $key, $urls, true ) ) {
			$clean[ $key ] = esc_url_raw( $value );
		} elseif ( 'support_email' === $key ) {
			$clean[ $key ] = sanitize_email( $value );
		} elseif ( in_array( $key, $html, true ) ) {
			$clean[ $key ] = wp_kses( $value, mediline_partners_allowed_inline_html() );
		} elseif ( 'terms_body' === $key ) {
			$clean[ $key ] = wp_kses_post( $value );
		} elseif ( str_ends_with( $key, '_body' ) || str_contains( $key, 'footnote' ) || 'footer_note' === $key ) {
			$clean[ $key ] = sanitize_textarea_field( $value );
		} else {
			$clean[ $key ] = sanitize_text_field( $value );
		}
	}

	return $clean;
}

function mediline_partners_sanitize_content_value( $key, $value ) {
	if ( in_array( $key, array( 'pap_signup_url', 'pap_login_url' ), true ) ) {
		return esc_url_raw( $value );
	}
	if ( 'support_email' === $key ) {
		return sanitize_email( $value );
	}
	if ( in_array( $key, mediline_partners_html_option_keys(), true ) ) {
		return wp_kses( $value, mediline_partners_allowed_inline_html() );
	}
	if ( 'terms_body' === $key ) {
		return wp_kses_post( $value );
	}
	if ( str_ends_with( $key, '_body' ) || str_contains( $key, 'footnote' ) || 'footer_note' === $key ) {
		return sanitize_textarea_field( $value );
	}
	return sanitize_text_field( $value );
}

/**
 * Settings schema used to render the admin editor.
 */
function mediline_partners_settings_schema() {
	return array(
		'Connections' => array(
			array( 'pap_signup_url', 'PAP registration URL', 'url' ),
			array( 'pap_login_url', 'PAP login URL', 'url' ),
			array( 'support_email', 'Support email', 'email' ),
		),
		'Navigation' => array(
			array( 'nav_program', 'Program label', 'text' ), array( 'nav_how', 'How it works label', 'text' ),
			array( 'nav_templates', 'Store templates label', 'text' ), array( 'nav_conditions', 'Conditions label', 'text' ),
			array( 'nav_faq', 'FAQ label', 'text' ), array( 'nav_login', 'Login label', 'text' ), array( 'nav_register', 'Register label', 'text' ),
		),
		'Hero' => array(
			array( 'hero_eyebrow', 'Eyebrow', 'text' ), array( 'hero_status', 'Status badge', 'text' ),
			array( 'hero_line_1', 'Headline — line 1', 'text' ), array( 'hero_line_2', 'Headline — line 2', 'text' ),
			array( 'hero_commission', 'Commission highlight', 'text' ), array( 'hero_body', 'Description', 'textarea' ),
			array( 'hero_primary_cta', 'Primary button', 'text' ), array( 'hero_secondary_cta', 'Login button', 'text' ),
			array( 'hero_proof_1_value', 'Metric 1 value', 'text' ), array( 'hero_proof_1_label', 'Metric 1 label', 'text' ),
			array( 'hero_proof_2_value', 'Metric 2 value', 'text' ), array( 'hero_proof_2_label', 'Metric 2 label', 'text' ),
			array( 'hero_proof_3_value', 'Metric 3 value', 'text' ), array( 'hero_proof_3_label', 'Metric 3 label', 'text' ),
			array( 'hero_art_word_1', 'Artwork word 1', 'text' ), array( 'hero_art_word_2', 'Artwork word 2', 'text' ),
			array( 'hero_token_1_label', 'Token 1 label', 'text' ), array( 'hero_token_1_value', 'Token 1 value', 'text' ),
			array( 'hero_token_2_label', 'Token 2 label', 'text' ), array( 'hero_token_2_value', 'Token 2 value', 'text' ),
			array( 'hero_ticket_label', 'Ticket label', 'text' ), array( 'hero_ticket_value', 'Ticket value', 'text' ), array( 'hero_ticket_meta', 'Ticket note', 'text' ),
			array( 'hero_caption_label', 'Floating caption label', 'text' ), array( 'hero_caption_value', 'Floating caption value', 'text' ),
			array( 'hero_rail_left', 'Bottom rail label', 'text' ), array( 'hero_rail_right', 'Bottom rail link', 'text' ),
			array( 'trust_1', 'Trust strip 1', 'text' ), array( 'trust_2', 'Trust strip 2', 'text' ),
			array( 'trust_3', 'Trust strip 3', 'text' ), array( 'trust_4', 'Trust strip 4', 'text' ),
		),
		'Partnership' => array(
			array( 'program_kicker', 'Section label', 'text' ), array( 'program_heading', 'Heading', 'html' ), array( 'program_body', 'Intro text', 'textarea' ),
			array( 'program_card_1_title', 'Card 1 title', 'html' ), array( 'program_card_1_body', 'Card 1 text', 'textarea' ),
			array( 'program_card_2_title', 'Card 2 title', 'html' ), array( 'program_card_2_body', 'Card 2 text', 'textarea' ),
			array( 'program_card_3_title', 'Card 3 title', 'html' ), array( 'program_card_3_body', 'Card 3 text', 'textarea' ),
		),
		'How it works' => array(
			array( 'process_kicker', 'Section label', 'text' ), array( 'process_heading', 'Heading', 'html' ), array( 'process_body', 'Intro text', 'textarea' ),
			array( 'step_1_title', 'Step 1 title', 'text' ), array( 'step_1_body', 'Step 1 text', 'textarea' ),
			array( 'step_2_title', 'Step 2 title', 'text' ), array( 'step_2_body', 'Step 2 text', 'textarea' ),
			array( 'step_3_title', 'Step 3 title', 'text' ), array( 'step_3_body', 'Step 3 text', 'textarea' ),
			array( 'step_4_title', 'Step 4 title', 'text' ), array( 'step_4_body', 'Step 4 text', 'textarea' ),
			array( 'step_5_title', 'Step 5 title', 'text' ), array( 'step_5_body', 'Step 5 text', 'textarea' ),
		),
		'Store templates section' => array(
			array( 'templates_kicker', 'Section label', 'text' ), array( 'templates_heading', 'Heading', 'html' ), array( 'templates_body', 'Intro text', 'textarea' ),
			array( 'templates_note_title', 'Resource note title', 'text' ), array( 'templates_note_body', 'Resource note text', 'textarea' ),
		),
		'Benefits' => array(
			array( 'benefits_kicker', 'Section label', 'text' ), array( 'benefits_heading', 'Heading', 'html' ),
			array( 'benefits_portal_label', 'Portal label', 'text' ), array( 'benefits_rate_label', 'Rate card label', 'text' ),
			array( 'benefits_cookie_label', 'Cookie card label', 'text' ), array( 'benefits_payout_label', 'Payout card label', 'text' ),
			array( 'benefit_1_title', 'Benefit 1 title', 'text' ), array( 'benefit_1_body', 'Benefit 1 text', 'textarea' ),
			array( 'benefit_2_title', 'Benefit 2 title', 'text' ), array( 'benefit_2_body', 'Benefit 2 text', 'textarea' ),
			array( 'benefit_3_title', 'Benefit 3 title', 'text' ), array( 'benefit_3_body', 'Benefit 3 text', 'textarea' ),
			array( 'benefit_4_title', 'Benefit 4 title', 'text' ), array( 'benefit_4_body', 'Benefit 4 text', 'textarea' ),
		),
		'Conditions' => array_merge(
			array(
				array( 'conditions_kicker', 'Section label', 'text' ), array( 'conditions_heading', 'Heading', 'html' ), array( 'conditions_body', 'Intro text', 'textarea' ),
				array( 'commission_kicker', 'Commission label', 'text' ), array( 'commission_heading', 'Commission heading', 'html' ), array( 'commission_body', 'Commission text', 'textarea' ),
			),
			array_reduce(
				range( 1, 6 ),
				function ( $fields, $index ) {
					$fields[] = array( "condition_{$index}_label", "Condition {$index} label", 'text' );
					$fields[] = array( "condition_{$index}_value", "Condition {$index} value", 'text' );
					return $fields;
				},
				array()
			),
			array_reduce(
				range( 1, 3 ),
				function ( $fields, $index ) {
					$fields[] = array( "traffic_{$index}_label", "Traffic row {$index} label", 'text' );
					$fields[] = array( "traffic_{$index}_value", "Traffic row {$index} value", 'textarea' );
					return $fields;
				},
				array()
			),
			array( array( 'conditions_footnote', 'Conditions footnote', 'textarea' ) )
		),
		'FAQ, CTA & footer' => array(
			array( 'faq_kicker', 'FAQ label', 'text' ), array( 'faq_heading', 'FAQ heading', 'html' ),
			array( 'cta_kicker', 'CTA label', 'text' ), array( 'cta_heading', 'CTA heading', 'html' ), array( 'cta_body', 'CTA text', 'textarea' ),
			array( 'cta_register', 'CTA register button', 'text' ), array( 'cta_login', 'CTA login button', 'text' ),
			array( 'footer_note', 'Footer note', 'textarea' ), array( 'footer_tagline', 'Footer tagline', 'text' ), array( 'footer_copyright', 'Copyright', 'text' ),
		),
		'Terms & conditions page' => array(
			array( 'terms_kicker', 'Page label', 'text' ),
			array( 'terms_heading', 'Page heading', 'html' ),
			array( 'terms_updated', 'Updated / source label', 'text' ),
			array( 'terms_intro', 'Intro text', 'textarea' ),
			array( 'terms_body', 'Terms content', 'richhtml' ),
		),
	);
}

function mediline_partners_register_settings() {
	register_setting(
		'mediline_partner_settings',
		'mediline_partner_options',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'mediline_partners_sanitize_options',
			'default'           => mediline_partners_default_options(),
		)
	);
}
add_action( 'admin_init', 'mediline_partners_register_settings' );

function mediline_partners_admin_menu() {
	add_theme_page(
		__( 'Mediline Content', 'mediline-partners' ),
		__( 'Mediline Content', 'mediline-partners' ),
		'edit_theme_options',
		'mediline-partners-content',
		'mediline_partners_render_settings_page'
	);
}
add_action( 'admin_menu', 'mediline_partners_admin_menu' );

function mediline_partners_admin_assets( $hook ) {
	if ( 'appearance_page_mediline-partners-content' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'mediline-partners-admin', MEDILINE_PARTNERS_URI . '/assets/css/admin.css', array(), MEDILINE_PARTNERS_VERSION );
	wp_enqueue_style( 'mediline-partners-admin-multilingual', MEDILINE_PARTNERS_URI . '/assets/css/admin-multilingual.css', array( 'mediline-partners-admin' ), MEDILINE_PARTNERS_VERSION );
}
add_action( 'admin_enqueue_scripts', 'mediline_partners_admin_assets' );

function mediline_partners_render_settings_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$languages    = mediline_partners_languages();
	$language     = isset( $_GET['lang'] ) ? mediline_partners_sanitize_language_code( wp_unslash( $_GET['lang'] ) ) : mediline_partners_default_language();
	$language     = isset( $languages[ $language ] ) ? $language : mediline_partners_default_language();
	$is_default   = mediline_partners_default_language() === $language;
	$base_values  = wp_parse_args( get_option( 'mediline_partner_options', array() ), mediline_partners_default_options() );
	$translations = get_option( 'mediline_partner_translations', array() );
	$translations = is_array( $translations ) ? $translations : array();
	$starter      = mediline_partners_default_content_translations();
	$values       = $is_default ? $base_values : wp_parse_args( isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array(), $starter[ $language ] ?? array() );
	$schema       = mediline_partners_settings_schema();
	if ( ! $is_default ) {
		unset( $schema['Connections'] );
	}
	?>
	<div class="wrap mp-settings">
		<div class="mp-settings-hero">
			<div><span>MEDILINE / PARTNERS / <?php echo esc_html( strtoupper( $language ) ); ?></span><h1><?php esc_html_e( 'Website content', 'mediline-partners' ); ?></h1></div>
			<p><?php echo $is_default ? esc_html__( 'Edit every fixed landing-page section here. Store previews and FAQ entries have their own menu items.', 'mediline-partners' ) : esc_html__( 'Edit the starter translation for this language. Fields without a translation safely fall back to English, so the public page is never incomplete.', 'mediline-partners' ); ?></p>
		</div>
		<nav class="mp-language-tabs" aria-label="<?php esc_attr_e( 'Content language', 'mediline-partners' ); ?>">
			<?php foreach ( $languages as $code => $item ) : ?><a class="<?php echo $code === $language ? 'active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'mediline-partners-content', 'lang' => $code ), admin_url( 'themes.php' ) ) ); ?>"><b><?php echo esc_html( $item['label'] ); ?></b><span><?php echo esc_html( $item['name'] ); ?></span></a><?php endforeach; ?>
		</nav>
		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Website content saved.', 'mediline-partners' ); ?></p></div><?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="mediline_save_content">
			<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
			<?php wp_nonce_field( 'mediline_save_content' ); ?>
			<nav class="mp-settings-nav" aria-label="Settings sections">
				<?php foreach ( array_keys( $schema ) as $index => $section ) : ?>
					<a href="#mp-section-<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $section ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="mp-settings-sections">
				<?php foreach ( $schema as $section => $fields ) : $section_index = array_search( $section, array_keys( $schema ), true ); ?>
					<section id="mp-section-<?php echo esc_attr( $section_index ); ?>" class="mp-settings-card">
						<h2><?php echo esc_html( $section ); ?></h2>
						<div class="mp-fields-grid">
							<?php foreach ( $fields as $field ) :
								list( $key, $label, $type ) = $field;
								$value       = isset( $values[ $key ] ) ? $values[ $key ] : '';
								$placeholder = ! $is_default && isset( $base_values[ $key ] ) ? wp_strip_all_tags( str_replace( '<br>', ' / ', $base_values[ $key ] ) ) : '';
								$is_large = in_array( $type, array( 'textarea', 'html', 'richhtml' ), true );
								?>
								<label class="mp-field <?php echo $is_large ? 'mp-field-wide' : ''; ?>">
									<span><?php echo esc_html( $label ); ?></span>
									<?php if ( $is_large ) : ?>
										<textarea name="content[<?php echo esc_attr( $key ); ?>]" rows="<?php echo 'richhtml' === $type ? '18' : ( 'html' === $type ? '3' : '4' ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
										<?php if ( 'html' === $type ) : ?><small><?php esc_html_e( 'Allowed: <br>, <em>, <strong>, <span>.', 'mediline-partners' ); ?></small><?php elseif ( 'richhtml' === $type ) : ?><small><?php esc_html_e( 'Legal content supports paragraphs, headings, strong text, links and lists.', 'mediline-partners' ); ?></small><?php endif; ?>
									<?php else : ?>
										<input type="<?php echo esc_attr( $type ); ?>" name="content[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>
			<div class="mp-save-bar"><?php submit_button( sprintf( __( 'Save %s content', 'mediline-partners' ), $languages[ $language ]['label'] ), 'primary', 'submit', false ); ?><span><?php echo $is_default ? esc_html__( 'Changes appear on the site immediately.', 'mediline-partners' ) : esc_html__( 'Starter translations and English fallback keep every language complete.', 'mediline-partners' ); ?></span></div>
		</form>
	</div>
	<?php
}

function mediline_partners_save_content() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to edit website content.', 'mediline-partners' ) );
	}
	check_admin_referer( 'mediline_save_content' );
	$languages = mediline_partners_languages();
	$language  = isset( $_POST['language'] ) ? mediline_partners_sanitize_language_code( wp_unslash( $_POST['language'] ) ) : mediline_partners_default_language();
	$language  = isset( $languages[ $language ] ) ? $language : mediline_partners_default_language();
	$input     = isset( $_POST['content'] ) && is_array( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : array();
	$defaults  = mediline_partners_default_options();

	if ( mediline_partners_default_language() === $language ) {
		$current = wp_parse_args( get_option( 'mediline_partner_options', array() ), $defaults );
		foreach ( $defaults as $key => $default ) {
			if ( array_key_exists( $key, $input ) ) {
				$current[ $key ] = mediline_partners_sanitize_content_value( $key, $input[ $key ] );
			}
		}
		update_option( 'mediline_partner_options', $current, false );
	} else {
		$translations = get_option( 'mediline_partner_translations', array() );
		$translations = is_array( $translations ) ? $translations : array();
		$clean        = isset( $translations[ $language ] ) && is_array( $translations[ $language ] ) ? $translations[ $language ] : array();
		foreach ( $defaults as $key => $default ) {
			if ( in_array( $key, mediline_partners_connection_option_keys(), true ) || ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = mediline_partners_sanitize_content_value( $key, $input[ $key ] );
			if ( '' === trim( (string) $value ) ) {
				unset( $clean[ $key ] );
			} else {
				$clean[ $key ] = $value;
			}
		}
		$translations[ $language ] = $clean;
		update_option( 'mediline_partner_translations', $translations, false );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'mediline-partners-content', 'lang' => $language, 'updated' => '1' ), admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_mediline_save_content', 'mediline_partners_save_content' );
