<?php
/**
 * Native multilingual routing, language management and UI translations.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mediline_partners_default_languages() {
	return array(
		'en' => array( 'code' => 'en', 'label' => 'EN', 'name' => 'English', 'html_lang' => 'en' ),
		'fr' => array( 'code' => 'fr', 'label' => 'FR', 'name' => 'Français', 'html_lang' => 'fr' ),
		'de' => array( 'code' => 'de', 'label' => 'DE', 'name' => 'Deutsch', 'html_lang' => 'de' ),
		'sp' => array( 'code' => 'sp', 'label' => 'SP', 'name' => 'Español', 'html_lang' => 'es' ),
		'it' => array( 'code' => 'it', 'label' => 'IT', 'name' => 'Italiano', 'html_lang' => 'it' ),
	);
}

function mediline_partners_default_language() {
	return 'en';
}

function mediline_partners_languages() {
	$saved = get_option( 'mediline_partner_languages', false );
	if ( ! is_array( $saved ) || empty( $saved['en'] ) ) {
		return mediline_partners_default_languages();
	}
	return $saved;
}

function mediline_partners_sanitize_language_code( $code ) {
	$code = strtolower( str_replace( '_', '-', sanitize_text_field( $code ) ) );
	$code = preg_replace( '/[^a-z0-9-]/', '', $code );
	return substr( trim( $code, '-' ), 0, 12 );
}

function mediline_partners_current_language() {
	$languages = mediline_partners_languages();
	$requested = mediline_partners_sanitize_language_code( (string) get_query_var( 'mediline_lang' ) );
	return $requested && isset( $languages[ $requested ] ) ? $requested : mediline_partners_default_language();
}

function mediline_partners_language_home_url( $code = '' ) {
	$code = $code ?: mediline_partners_current_language();
	return mediline_partners_default_language() === $code ? home_url( '/' ) : home_url( '/' . $code . '/' );
}

function mediline_partners_route_url( $route = '', $code = '' ) {
	$code  = $code ?: mediline_partners_current_language();
	$route = trim( sanitize_title( $route ), '/' );
	if ( mediline_partners_default_language() === $code ) {
		return home_url( $route ? '/' . $route . '/' : '/' );
	}
	return home_url( '/' . $code . ( $route ? '/' . $route : '' ) . '/' );
}

function mediline_partners_current_view_route() {
	$access = get_query_var( 'mediline_access' );
	if ( in_array( $access, array( 'login', 'register' ), true ) ) {
		return $access;
	}
	return get_query_var( 'mediline_legal' ) ? 'terms-conditions' : '';
}

function mediline_partners_language_view_url( $code ) {
	return mediline_partners_route_url( mediline_partners_current_view_route(), $code );
}

function mediline_partners_is_landing_view() {
	return is_front_page() || ( get_query_var( 'mediline_lang' ) && ! get_query_var( 'mediline_access' ) && ! get_query_var( 'mediline_legal' ) );
}

/**
 * Small interface dictionary. Editable marketing content is stored separately.
 */
function mediline_partners_t( $key, $fallback = '' ) {
	static $dictionary = null;
	if ( null === $dictionary ) {
		$dictionary = array(
			'en' => array(
				'language' => 'Language', 'all' => 'All', 'see_how' => 'See how it works', 'store_templates' => 'Store templates', 'partner_login' => 'Partner login', 'full_preview' => 'Full preview', 'live_preview' => 'Live storefront preview', 'screen' => 'screen', 'screens' => 'screens',
				'visual_tone' => 'Visual tone', 'best_for' => 'Best for', 'traffic_markets' => 'Traffic markets', 'europe_usa' => 'Europe + USA', 'preview_only' => 'Preview only.', 'preview_note' => 'Approved partners access the template and launch resources inside the Post Affiliate Pro panel.', 'apply_partner' => 'Apply to become a partner', 'previous_template' => 'Previous', 'next_template' => 'Next', 'template_preview' => 'Template preview',
				'navigate' => 'Navigate', 'partner_access' => 'Partner access', 'terms' => 'Terms', 'public_partner_site' => 'Public partner website', 'support' => 'Support', 'back_program' => 'Back to program',
				'email' => 'Email address', 'password' => 'Password', 'show' => 'Show', 'hide' => 'Hide', 'keep_signed_in' => 'Keep me signed in', 'forgot_password' => 'Forgot password?', 'first_name' => 'First name', 'last_name' => 'Last name', 'messenger' => 'Messenger link(s)', 'accept_prefix' => 'I accept the', 'program_terms' => 'program rules and terms', 'sign_in_panel' => 'Sign in to partner panel', 'submit_application' => 'Submit application', 'not_partner' => 'Not a partner yet?', 'already_account' => 'Already have an account?', 'apply_now' => 'Apply now', 'sign_in' => 'Sign in', 'pap_security' => 'Account processing and secure access are handled by Post Affiliate Pro.',
				'commission_range' => 'Commission range', 'per_sale' => 'Per attributed sale', 'attribution' => 'Attribution', 'next_payout' => 'Next payout cycle', 'weekly_usdt' => 'Weekly / USDT', 'eu_usa_traffic' => 'EU + USA traffic', 'pap_access' => 'PAP-powered access',
				'category_conversion' => 'Conversion', 'category_performance' => 'Performance', 'category_global' => 'Global', 'category_content' => 'Content', 'shop' => 'Shop', 'guides' => 'Guides', 'about' => 'About', 'menu' => 'Menu', 'tracked_pap' => 'Tracked with Post Affiliate Pro', 'partner_storefront' => 'Mediline partner storefront',
				'previous_screenshot' => 'Previous screenshot', 'next_screenshot' => 'Next screenshot', 'choose_screenshot' => 'Choose screenshot', 'open_screenshot' => 'Open screenshot', 'storefront_screen' => 'Storefront screen', 'screenshots' => 'screenshots',
			),
			'fr' => array(
				'language' => 'Langue', 'all' => 'Tous', 'see_how' => 'Voir le fonctionnement', 'store_templates' => 'Modèles de boutique', 'partner_login' => 'Connexion partenaire', 'full_preview' => 'Aperçu complet', 'live_preview' => 'Aperçu de la boutique', 'screen' => 'écran', 'screens' => 'écrans',
				'visual_tone' => 'Style visuel', 'best_for' => 'Idéal pour', 'traffic_markets' => 'Marchés ciblés', 'europe_usa' => 'Europe + États-Unis', 'preview_only' => 'Aperçu uniquement.', 'preview_note' => 'Les partenaires approuvés accèdent au modèle et aux ressources dans le panneau Post Affiliate Pro.', 'apply_partner' => 'Devenir partenaire', 'previous_template' => 'Précédent', 'next_template' => 'Suivant', 'template_preview' => 'Aperçu du modèle',
				'navigate' => 'Navigation', 'partner_access' => 'Accès partenaire', 'terms' => 'Conditions', 'public_partner_site' => 'Site public partenaires', 'support' => 'Assistance', 'back_program' => 'Retour au programme',
				'email' => 'Adresse e-mail', 'password' => 'Mot de passe', 'show' => 'Afficher', 'hide' => 'Masquer', 'keep_signed_in' => 'Rester connecté', 'forgot_password' => 'Mot de passe oublié ?', 'first_name' => 'Prénom', 'last_name' => 'Nom', 'messenger' => 'Lien(s) de messagerie', 'accept_prefix' => "J’accepte les", 'program_terms' => 'règles et conditions du programme', 'sign_in_panel' => 'Accéder au panneau partenaire', 'submit_application' => 'Envoyer la candidature', 'not_partner' => 'Pas encore partenaire ?', 'already_account' => 'Vous avez déjà un compte ?', 'apply_now' => 'Postuler', 'sign_in' => 'Se connecter', 'pap_security' => 'Le traitement du compte et l’accès sécurisé sont gérés par Post Affiliate Pro.',
				'commission_range' => 'Taux de commission', 'per_sale' => 'Par vente attribuée', 'attribution' => 'Attribution', 'next_payout' => 'Prochain paiement', 'weekly_usdt' => 'Hebdomadaire / USDT', 'eu_usa_traffic' => 'Trafic Europe + USA', 'pap_access' => 'Accès via PAP',
				'category_conversion' => 'Conversion', 'category_performance' => 'Performance', 'category_global' => 'International', 'category_content' => 'Contenu', 'shop' => 'Boutique', 'guides' => 'Guides', 'about' => 'À propos', 'menu' => 'Menu', 'tracked_pap' => 'Suivi avec Post Affiliate Pro', 'partner_storefront' => 'Boutique partenaire Mediline',
				'previous_screenshot' => 'Capture précédente', 'next_screenshot' => 'Capture suivante', 'choose_screenshot' => 'Choisir une capture', 'open_screenshot' => 'Ouvrir la capture', 'storefront_screen' => 'Écran de boutique', 'screenshots' => 'captures',
			),
			'de' => array(
				'language' => 'Sprache', 'all' => 'Alle', 'see_how' => 'So funktioniert es', 'store_templates' => 'Shop-Vorlagen', 'partner_login' => 'Partner-Login', 'full_preview' => 'Vollansicht', 'live_preview' => 'Live-Shop-Vorschau', 'screen' => 'Ansicht', 'screens' => 'Ansichten',
				'visual_tone' => 'Visueller Stil', 'best_for' => 'Geeignet für', 'traffic_markets' => 'Zielmärkte', 'europe_usa' => 'Europa + USA', 'preview_only' => 'Nur Vorschau.', 'preview_note' => 'Freigegebene Partner erhalten die Vorlage und Ressourcen im Post Affiliate Pro Panel.', 'apply_partner' => 'Als Partner bewerben', 'previous_template' => 'Zurück', 'next_template' => 'Weiter', 'template_preview' => 'Vorlagenvorschau',
				'navigate' => 'Navigation', 'partner_access' => 'Partnerzugang', 'terms' => 'Bedingungen', 'public_partner_site' => 'Öffentliche Partnerseite', 'support' => 'Support', 'back_program' => 'Zurück zum Programm',
				'email' => 'E-Mail-Adresse', 'password' => 'Passwort', 'show' => 'Anzeigen', 'hide' => 'Ausblenden', 'keep_signed_in' => 'Angemeldet bleiben', 'forgot_password' => 'Passwort vergessen?', 'first_name' => 'Vorname', 'last_name' => 'Nachname', 'messenger' => 'Messenger-Link(s)', 'accept_prefix' => 'Ich akzeptiere die', 'program_terms' => 'Programmregeln und Bedingungen', 'sign_in_panel' => 'Im Partnerbereich anmelden', 'submit_application' => 'Bewerbung senden', 'not_partner' => 'Noch kein Partner?', 'already_account' => 'Bereits registriert?', 'apply_now' => 'Jetzt bewerben', 'sign_in' => 'Anmelden', 'pap_security' => 'Kontoverarbeitung und sicherer Zugriff erfolgen über Post Affiliate Pro.',
				'commission_range' => 'Provision', 'per_sale' => 'Pro zugeordneter Bestellung', 'attribution' => 'Zuordnung', 'next_payout' => 'Nächste Auszahlung', 'weekly_usdt' => 'Wöchentlich / USDT', 'eu_usa_traffic' => 'Traffic EU + USA', 'pap_access' => 'PAP-Zugang',
				'category_conversion' => 'Conversion', 'category_performance' => 'Performance', 'category_global' => 'International', 'category_content' => 'Content', 'shop' => 'Shop', 'guides' => 'Ratgeber', 'about' => 'Über uns', 'menu' => 'Menü', 'tracked_pap' => 'Tracking mit Post Affiliate Pro', 'partner_storefront' => 'Mediline Partner-Shop',
				'previous_screenshot' => 'Vorherige Ansicht', 'next_screenshot' => 'Nächste Ansicht', 'choose_screenshot' => 'Ansicht auswählen', 'open_screenshot' => 'Ansicht öffnen', 'storefront_screen' => 'Shop-Ansicht', 'screenshots' => 'Ansichten',
			),
			'sp' => array(
				'language' => 'Idioma', 'all' => 'Todos', 'see_how' => 'Cómo funciona', 'store_templates' => 'Plantillas de tienda', 'partner_login' => 'Acceso de socios', 'full_preview' => 'Vista completa', 'live_preview' => 'Vista previa de la tienda', 'screen' => 'pantalla', 'screens' => 'pantallas',
				'visual_tone' => 'Estilo visual', 'best_for' => 'Ideal para', 'traffic_markets' => 'Mercados', 'europe_usa' => 'Europa + EE. UU.', 'preview_only' => 'Solo vista previa.', 'preview_note' => 'Los socios aprobados acceden a la plantilla y los recursos desde Post Affiliate Pro.', 'apply_partner' => 'Solicitar acceso', 'previous_template' => 'Anterior', 'next_template' => 'Siguiente', 'template_preview' => 'Vista de plantilla',
				'navigate' => 'Navegación', 'partner_access' => 'Acceso de socios', 'terms' => 'Condiciones', 'public_partner_site' => 'Sitio público para socios', 'support' => 'Soporte', 'back_program' => 'Volver al programa',
				'email' => 'Correo electrónico', 'password' => 'Contraseña', 'show' => 'Mostrar', 'hide' => 'Ocultar', 'keep_signed_in' => 'Mantener la sesión', 'forgot_password' => '¿Olvidaste la contraseña?', 'first_name' => 'Nombre', 'last_name' => 'Apellido', 'messenger' => 'Enlace(s) de mensajería', 'accept_prefix' => 'Acepto las', 'program_terms' => 'reglas y condiciones del programa', 'sign_in_panel' => 'Entrar al panel de socios', 'submit_application' => 'Enviar solicitud', 'not_partner' => '¿Aún no eres socio?', 'already_account' => '¿Ya tienes una cuenta?', 'apply_now' => 'Solicitar ahora', 'sign_in' => 'Iniciar sesión', 'pap_security' => 'La cuenta y el acceso seguro se gestionan mediante Post Affiliate Pro.',
				'commission_range' => 'Comisión', 'per_sale' => 'Por venta atribuida', 'attribution' => 'Atribución', 'next_payout' => 'Próximo pago', 'weekly_usdt' => 'Semanal / USDT', 'eu_usa_traffic' => 'Tráfico UE + EE. UU.', 'pap_access' => 'Acceso mediante PAP',
				'category_conversion' => 'Conversión', 'category_performance' => 'Rendimiento', 'category_global' => 'Global', 'category_content' => 'Contenido', 'shop' => 'Tienda', 'guides' => 'Guías', 'about' => 'Acerca de', 'menu' => 'Menú', 'tracked_pap' => 'Seguimiento con Post Affiliate Pro', 'partner_storefront' => 'Tienda asociada Mediline',
				'previous_screenshot' => 'Captura anterior', 'next_screenshot' => 'Captura siguiente', 'choose_screenshot' => 'Elegir captura', 'open_screenshot' => 'Abrir captura', 'storefront_screen' => 'Pantalla de tienda', 'screenshots' => 'capturas',
			),
			'it' => array(
				'language' => 'Lingua', 'all' => 'Tutti', 'see_how' => 'Come funziona', 'store_templates' => 'Template negozio', 'partner_login' => 'Accesso partner', 'full_preview' => 'Anteprima completa', 'live_preview' => 'Anteprima negozio', 'screen' => 'schermata', 'screens' => 'schermate',
				'visual_tone' => 'Stile visivo', 'best_for' => 'Ideale per', 'traffic_markets' => 'Mercati', 'europe_usa' => 'Europa + USA', 'preview_only' => 'Solo anteprima.', 'preview_note' => 'I partner approvati accedono al template e alle risorse nel pannello Post Affiliate Pro.', 'apply_partner' => 'Candidati come partner', 'previous_template' => 'Precedente', 'next_template' => 'Successivo', 'template_preview' => 'Anteprima template',
				'navigate' => 'Navigazione', 'partner_access' => 'Accesso partner', 'terms' => 'Condizioni', 'public_partner_site' => 'Sito pubblico partner', 'support' => 'Supporto', 'back_program' => 'Torna al programma',
				'email' => 'Indirizzo e-mail', 'password' => 'Password', 'show' => 'Mostra', 'hide' => 'Nascondi', 'keep_signed_in' => 'Resta connesso', 'forgot_password' => 'Password dimenticata?', 'first_name' => 'Nome', 'last_name' => 'Cognome', 'messenger' => 'Link di messaggistica', 'accept_prefix' => 'Accetto le', 'program_terms' => 'regole e condizioni del programma', 'sign_in_panel' => 'Accedi al pannello partner', 'submit_application' => 'Invia candidatura', 'not_partner' => 'Non sei ancora partner?', 'already_account' => 'Hai già un account?', 'apply_now' => 'Candidati ora', 'sign_in' => 'Accedi', 'pap_security' => 'La gestione dell’account e l’accesso sicuro sono affidati a Post Affiliate Pro.',
				'commission_range' => 'Commissione', 'per_sale' => 'Per vendita attribuita', 'attribution' => 'Attribuzione', 'next_payout' => 'Prossimo pagamento', 'weekly_usdt' => 'Settimanale / USDT', 'eu_usa_traffic' => 'Traffico UE + USA', 'pap_access' => 'Accesso tramite PAP',
				'category_conversion' => 'Conversione', 'category_performance' => 'Performance', 'category_global' => 'Globale', 'category_content' => 'Contenuti', 'shop' => 'Negozio', 'guides' => 'Guide', 'about' => 'Chi siamo', 'menu' => 'Menu', 'tracked_pap' => 'Tracciato con Post Affiliate Pro', 'partner_storefront' => 'Negozio partner Mediline',
				'previous_screenshot' => 'Schermata precedente', 'next_screenshot' => 'Schermata successiva', 'choose_screenshot' => 'Scegli schermata', 'open_screenshot' => 'Apri schermata', 'storefront_screen' => 'Schermata negozio', 'screenshots' => 'schermate',
			),
		);
	}

	$language = mediline_partners_current_language();
	if ( isset( $dictionary[ $language ][ $key ] ) ) {
		return $dictionary[ $language ][ $key ];
	}
	return isset( $dictionary['en'][ $key ] ) ? $dictionary['en'][ $key ] : $fallback;
}

function mediline_partners_category_label( $category ) {
	$key = 'category_' . sanitize_key( strtolower( $category ) );
	return mediline_partners_t( $key, $category );
}

function mediline_partners_language_attributes( $output ) {
	$languages = mediline_partners_languages();
	$current   = mediline_partners_current_language();
	$html_lang = isset( $languages[ $current ]['html_lang'] ) ? $languages[ $current ]['html_lang'] : $current;
	if ( preg_match( '/lang=("|\')[^"\']*("|\')/', $output ) ) {
		return preg_replace( '/lang=("|\')[^"\']*("|\')/', 'lang="' . esc_attr( $html_lang ) . '"', $output, 1 );
	}
	return 'lang="' . esc_attr( $html_lang ) . '" ' . $output;
}
add_filter( 'language_attributes', 'mediline_partners_language_attributes' );

function mediline_partners_language_body_class( $classes ) {
	$classes[] = 'mediline-lang-' . sanitize_html_class( mediline_partners_current_language() );
	return $classes;
}
add_filter( 'body_class', 'mediline_partners_language_body_class' );

function mediline_partners_hreflang_links() {
	if ( get_query_var( 'mediline_builder' ) ) {
		return;
	}
	if ( ! mediline_partners_is_landing_view() && ! get_query_var( 'mediline_access' ) && ! get_query_var( 'mediline_legal' ) ) {
		return;
	}
	foreach ( mediline_partners_languages() as $code => $language ) {
		$html_lang = $language['html_lang'] ?: $code;
		echo '<link rel="alternate" hreflang="' . esc_attr( $html_lang ) . '" href="' . esc_url( mediline_partners_language_view_url( $code ) ) . '">' . "\n";
	}
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( mediline_partners_language_view_url( mediline_partners_default_language() ) ) . '">' . "\n";
}
add_action( 'wp_head', 'mediline_partners_hreflang_links', 3 );

function mediline_partners_virtual_canonical() {
	if ( get_query_var( 'mediline_builder' ) ) {
		return;
	}
	if ( ! get_query_var( 'mediline_lang' ) && ! get_query_var( 'mediline_access' ) && ! get_query_var( 'mediline_legal' ) ) {
		return;
	}
	echo '<link rel="canonical" href="' . esc_url( mediline_partners_language_view_url( mediline_partners_current_language() ) ) . '">' . "\n";
}
add_action( 'wp_head', 'mediline_partners_virtual_canonical', 4 );

function mediline_partners_language_switcher( $context = 'header' ) {
	$languages = mediline_partners_languages();
	if ( count( $languages ) < 2 ) {
		return;
	}
	$current = mediline_partners_current_language();
	if ( 'mobile' === $context ) {
		?>
		<div class="language-mobile"><span><?php echo esc_html( mediline_partners_t( 'language', 'Language' ) ); ?></span><div><?php foreach ( $languages as $code => $language ) : ?><a class="<?php echo $code === $current ? 'active' : ''; ?>" href="<?php echo esc_url( mediline_partners_language_view_url( $code ) ); ?>" lang="<?php echo esc_attr( $language['html_lang'] ); ?>" hreflang="<?php echo esc_attr( $language['html_lang'] ); ?>"><?php echo esc_html( $language['label'] ); ?></a><?php endforeach; ?></div></div>
		<?php
		return;
	}
	$active = $languages[ $current ];
	?>
	<div class="language-switcher language-switcher-<?php echo esc_attr( $context ); ?>" data-language-switcher>
		<button type="button" class="language-switcher-button" aria-expanded="false" aria-haspopup="true"><i aria-hidden="true"></i><b><?php echo esc_html( $active['label'] ); ?></b><span><?php echo esc_html( $active['name'] ); ?></span><em aria-hidden="true"></em></button>
		<div class="language-switcher-menu" hidden>
			<span><?php echo esc_html( mediline_partners_t( 'language', 'Language' ) ); ?></span>
			<?php foreach ( $languages as $code => $language ) : ?>
				<a class="<?php echo $code === $current ? 'active' : ''; ?>" href="<?php echo esc_url( mediline_partners_language_view_url( $code ) ); ?>" lang="<?php echo esc_attr( $language['html_lang'] ); ?>" hreflang="<?php echo esc_attr( $language['html_lang'] ); ?>"><b><?php echo esc_html( $language['label'] ); ?></b><span><?php echo esc_html( $language['name'] ); ?></span><?php if ( $code === $current ) : ?><i aria-hidden="true">✓</i><?php endif; ?></a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

function mediline_partners_languages_admin_menu() {
	add_theme_page( __( 'Mediline Languages', 'mediline-partners' ), __( 'Languages', 'mediline-partners' ), 'edit_theme_options', 'mediline-partners-languages', 'mediline_partners_render_languages_page' );
}
add_action( 'admin_menu', 'mediline_partners_languages_admin_menu' );

function mediline_partners_languages_admin_assets( $hook ) {
	if ( 'appearance_page_mediline-partners-languages' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'mediline-partners-languages-admin', MEDILINE_PARTNERS_URI . '/assets/css/admin-languages.css', array(), MEDILINE_PARTNERS_VERSION );
	wp_enqueue_script( 'mediline-partners-languages-admin', MEDILINE_PARTNERS_URI . '/assets/js/admin-languages.js', array(), MEDILINE_PARTNERS_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'mediline_partners_languages_admin_assets' );

function mediline_partners_render_language_row( $language, $index, $locked = false ) {
	?>
	<div class="mp-language-row" data-language-row>
		<span class="mp-language-order" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
		<label><span><?php esc_html_e( 'URL code', 'mediline-partners' ); ?></span><input name="languages[<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $language['code'] ); ?>" maxlength="12" <?php echo $locked ? 'readonly' : ''; ?>></label>
		<label><span><?php esc_html_e( 'Short label', 'mediline-partners' ); ?></span><input name="languages[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $language['label'] ); ?>" maxlength="5"></label>
		<label><span><?php esc_html_e( 'Language name', 'mediline-partners' ); ?></span><input name="languages[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $language['name'] ); ?>"></label>
		<label><span><?php esc_html_e( 'HTML language', 'mediline-partners' ); ?></span><input name="languages[<?php echo esc_attr( $index ); ?>][html_lang]" value="<?php echo esc_attr( $language['html_lang'] ); ?>" maxlength="12"></label>
		<?php if ( $locked ) : ?><span class="mp-language-base"><?php esc_html_e( 'DEFAULT', 'mediline-partners' ); ?></span><?php else : ?><button type="button" class="mp-language-remove" data-remove-language aria-label="<?php esc_attr_e( 'Remove language', 'mediline-partners' ); ?>">×</button><?php endif; ?>
	</div>
	<?php
}

function mediline_partners_render_languages_page() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	$languages = mediline_partners_languages();
	?>
	<div class="wrap mp-languages-admin">
		<div class="mp-languages-hero"><span>MEDILINE / LOCALIZATION</span><h1><?php esc_html_e( 'Website languages', 'mediline-partners' ); ?></h1><p><?php esc_html_e( 'Add or remove public languages. English is the canonical default and remains at the root URL; every additional language receives its own URL prefix.', 'mediline-partners' ); ?></p></div>
		<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Languages saved and routes refreshed.', 'mediline-partners' ); ?></p></div><?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="mp-languages-form">
			<input type="hidden" name="action" value="mediline_save_languages">
			<?php wp_nonce_field( 'mediline_save_languages' ); ?>
			<div class="mp-languages-head"><div><h2><?php esc_html_e( 'Active languages', 'mediline-partners' ); ?></h2><p><?php esc_html_e( 'Use ISO-style lowercase URL codes. SP is configured as requested and exposes the correct HTML language code “es”.', 'mediline-partners' ); ?></p></div><button type="button" class="button mp-add-language" data-add-language><?php esc_html_e( '+ Add language', 'mediline-partners' ); ?></button></div>
			<div class="mp-language-rows" data-language-rows><?php $index = 0; foreach ( $languages as $code => $language ) { mediline_partners_render_language_row( $language, $index, 'en' === $code ); $index++; } ?></div>
			<template data-language-template><?php mediline_partners_render_language_row( array( 'code' => '', 'label' => '', 'name' => '', 'html_lang' => '' ), 999, false ); ?></template>
			<div class="mp-languages-save"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save languages', 'mediline-partners' ); ?></button><span><?php esc_html_e( 'Saving refreshes localized routes automatically.', 'mediline-partners' ); ?></span></div>
		</form>
	</div>
	<?php
}

function mediline_partners_save_languages() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage languages.', 'mediline-partners' ) );
	}
	check_admin_referer( 'mediline_save_languages' );
	$rows      = isset( $_POST['languages'] ) && is_array( $_POST['languages'] ) ? wp_unslash( $_POST['languages'] ) : array();
	$defaults  = mediline_partners_default_languages();
	$languages = array();

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$code = mediline_partners_sanitize_language_code( $row['code'] ?? '' );
		if ( ! $code || isset( $languages[ $code ] ) || ( 'en' !== $code && in_array( $code, array( 'login', 'register', 'wp-admin', 'wp-json' ), true ) ) ) {
			continue;
		}
		$label     = strtoupper( substr( sanitize_text_field( $row['label'] ?? $code ), 0, 5 ) );
		$name      = sanitize_text_field( $row['name'] ?? $label );
		$html_lang = mediline_partners_sanitize_language_code( $row['html_lang'] ?? $code );
		$languages[ $code ] = array( 'code' => $code, 'label' => $label ?: strtoupper( $code ), 'name' => $name ?: $label, 'html_lang' => $html_lang ?: $code );
	}

	if ( ! isset( $languages['en'] ) ) {
		$languages = array( 'en' => $defaults['en'] ) + $languages;
	} else {
		$english   = $languages['en'];
		unset( $languages['en'] );
		$languages = array( 'en' => $english ) + $languages;
	}

	update_option( 'mediline_partner_languages', $languages, false );
	update_option( 'mediline_partners_flush_language_rules', '1', false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'mediline-partners-languages', 'updated' => '1' ), admin_url( 'themes.php' ) ) );
	exit;
}
add_action( 'admin_post_mediline_save_languages', 'mediline_partners_save_languages' );

/**
 * Flush on the following request, after init has registered only the newly
 * active language routes. This also removes routes for deleted languages.
 */
function mediline_partners_flush_saved_language_rules() {
	if ( '1' !== get_option( 'mediline_partners_flush_language_rules' ) ) {
		return;
	}
	flush_rewrite_rules( false );
	delete_option( 'mediline_partners_flush_language_rules' );
}
add_action( 'admin_init', 'mediline_partners_flush_saved_language_rules', 40 );
