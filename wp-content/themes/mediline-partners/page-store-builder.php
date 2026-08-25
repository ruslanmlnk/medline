<?php
/**
 * PAP-embeddable Mediline Store Builder.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$session    = mediline_partners_builder_session();
$authorized = (bool) $session;
$templates  = mediline_partners_get_templates();
$languages  = mediline_partners_languages();
$currencies = function_exists( 'mediline_catalog_supported_currencies' ) ? mediline_catalog_supported_currencies() : array( 'EUR' );
$catalog_ready = function_exists( 'mediline_catalog_register_store' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'mediline-builder-page' ); ?>>
<?php wp_body_open(); ?>
<?php if ( ! $authorized ) : ?>
	<main class="builder-locked">
		<div class="builder-locked-mark">M<span>+</span></div>
		<p>MEDILINE / PARTNER STORES</p>
		<h1>Store Builder is private.</h1>
		<p class="builder-locked-copy">Open this workspace from your authenticated Post Affiliate Pro panel.</p>
		<a href="<?php echo esc_url( mediline_partners_route_url( 'login' ) ); ?>">Partner login <span>↗</span></a>
	</main>
<?php else : ?>
	<div class="builder-app" data-builder-app>
		<header class="builder-topbar">
			<?php mediline_partners_brand( false, '#' ); ?>
			<div class="builder-topbar-meta"><span>STORE BUILDER</span><i></i><b><?php echo esc_html( $session['refid'] ?: $session['affiliate_id'] ); ?></b></div>
		</header>

		<main>
			<section class="builder-hero">
				<div class="builder-hero-copy">
					<span class="builder-kicker">01 / CHOOSE A STOREFRONT</span>
					<h1>Launch your<br><em>Mediline store.</em></h1>
				</div>
				<div class="builder-hero-side">
					<p>Select a storefront, preview every screen, configure the installation and download one ready-to-run package.</p>
					<div class="builder-capabilities" aria-label="Store Builder capabilities">
						<span><i></i> One-command install</span>
						<span><i></i> Catalog sync</span>
						<span><i></i> Multi-language</span>
					</div>
				</div>
			</section>

			<section class="builder-templates" aria-labelledby="builder-templates-title">
				<div class="builder-section-head"><div><span>AVAILABLE TEMPLATES</span><h2 id="builder-templates-title">Pick the right direction.</h2></div><p><?php echo esc_html( count( $templates ) ); ?> storefronts</p></div>
				<div class="builder-template-grid">
					<?php foreach ( $templates as $index => $template_post ) :
						$template = mediline_partners_template_data( $template_post, $index );
						$package  = mediline_partners_template_package( $template_post->ID );
						$gallery  = $template['gallery'];
						?>
						<article class="builder-template-card" data-builder-template data-template='<?php echo esc_attr( wp_json_encode( array(
							'id' => $template_post->ID,
							'name' => $template['name'],
							'category' => $template['category_label'],
							'description' => $template['description'],
							'gallery' => $gallery,
							'package_ready' => ! empty( $package['file'] ),
							'package_version' => $package['version'],
						) ) ); ?>'>
							<button type="button" class="builder-template-visual" data-template-preview aria-label="Preview <?php echo esc_attr( $template['name'] ); ?>">
								<?php mediline_partners_store_cover( $template ); ?>
								<span class="builder-preview-chip">PREVIEW <b>↗</b></span>
							</button>
							<div class="builder-template-info">
								<div><span><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?> / <?php echo esc_html( strtoupper( $template['category_label'] ) ); ?></span><h3><?php echo esc_html( $template['name'] ); ?></h3><p><?php echo esc_html( $template['tagline'] ); ?></p></div>
								<div class="builder-template-actions"><span class="builder-package-status <?php echo $package['file'] ? 'ready' : 'missing'; ?>"><?php echo $package['file'] ? esc_html( 'THEME ' . $package['version'] ) : 'THEME NOT UPLOADED'; ?></span><button type="button" data-use-template <?php disabled( empty( $package['file'] ) ); ?>>Use template <span>→</span></button></div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		</main>
	</div>

	<div class="builder-modal" data-builder-preview hidden>
		<div class="builder-modal-panel">
			<button class="builder-modal-close" type="button" data-close-preview aria-label="Close preview">×</button>
			<div class="builder-modal-media" data-preview-media></div>
			<div class="builder-modal-copy"><span data-preview-category></span><h2 data-preview-title></h2><p data-preview-description></p><button type="button" data-preview-use>Use this template <span>→</span></button></div>
		</div>
	</div>

	<aside class="builder-config" data-builder-config aria-hidden="true">
		<div class="builder-config-panel">
			<div class="builder-config-head">
				<div>
					<span>02 / CONFIGURE STOREFRONT</span>
					<h2 data-config-title>Store setup</h2>
					<p>Everything below will be embedded into the one-time installation configuration.</p>
				</div>
				<button type="button" data-config-close aria-label="Close setup">×</button>
			</div>

			<div class="builder-config-selected" aria-label="Selected storefront">
				<div class="builder-config-thumb"><img data-config-cover hidden alt=""></div>
				<div class="builder-config-selected-copy">
					<span>SELECTED STOREFRONT</span>
					<strong data-review-template>—</strong>
					<small data-config-package-meta>Installer package</small>
				</div>
				<div class="builder-config-selected-status"><i></i> THEME READY</div>
			</div>

			<form data-builder-form>
				<input type="hidden" name="template_id">

				<section class="builder-form-card">
					<div class="builder-form-card-head">
						<span>01</span>
						<div><h3>Store identity</h3><p>Public name and the domain where this storefront will be installed.</p></div>
					</div>
					<div class="builder-fields two">
						<label><span>Store name</span><input name="store_name" required placeholder="Santé Direct"></label>
						<label><span>Domain</span><input name="domain" required placeholder="store.example.com"><small>Enter a hostname only, without https://</small></label>
					</div>
				</section>

				<section class="builder-form-card">
					<div class="builder-form-card-head">
						<span>02</span>
						<div><h3>Market & languages</h3><p>Choose the market configuration and languages that the generated store receives.</p></div>
					</div>
					<div class="builder-fields three">
						<label><span>Region</span><select name="region"><option value="EU">Europe</option><option value="FR">France</option><option value="DE">Germany</option><option value="IT">Italy</option><option value="ES">Spain</option><option value="US">United States</option></select></label>
						<label><span>Currency</span><select name="currency"><?php foreach ( $currencies as $currency ) : ?><option value="<?php echo esc_attr( $currency ); ?>"><?php echo esc_html( $currency ); ?></option><?php endforeach; ?></select></label>
						<label><span>Primary language</span><select name="primary_language" data-primary-language><?php foreach ( $languages as $code => $language ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $language['label'] . ' — ' . $language['name'] ); ?></option><?php endforeach; ?></select></label>
					</div>
					<fieldset class="builder-language-checks"><legend>Additional languages</legend><?php foreach ( $languages as $code => $language ) : ?><label><input type="checkbox" name="languages[]" value="<?php echo esc_attr( $code ); ?>" <?php checked( 'en' === $code ); ?>><span><?php echo esc_html( $language['label'] ); ?></span><small><?php echo esc_html( $language['name'] ); ?></small></label><?php endforeach; ?></fieldset>
				</section>

				<section class="builder-form-card">
					<div class="builder-form-card-head">
						<span>03</span>
						<div><h3>WordPress access</h3><p>Create the administrator account that will exist after installation.</p></div>
					</div>
					<div class="builder-fields two">
						<label><span>Admin email</span><input type="email" name="admin_email" required placeholder="owner@example.com"></label>
						<label><span>Username</span><input name="admin_username" required value="admin" autocomplete="off"></label>
					</div>
					<label class="builder-password"><span>Password</span><div><input type="password" name="admin_password" required minlength="10" autocomplete="new-password"><button type="button" data-generate-password>Generate</button><button type="button" data-toggle-password>Show</button></div></label>
					<p class="builder-security-note"><strong>Private by design.</strong> The password is never written into the ZIP. The installer claims it once through the encrypted provisioning token.</p>
				</section>

				<?php if ( ! $catalog_ready ) : ?>
					<div class="builder-prereq-warning"><strong>Catalog Core is not active.</strong><span>Install and activate Mediline Catalog Core before generating an installation package.</span></div>
				<?php endif; ?>

				<div class="builder-review">
					<div class="builder-review-copy"><span>READY TO BUILD</span><strong data-review-template>—</strong><small data-review-version></small></div>
					<button type="submit" <?php disabled( ! $catalog_ready ); ?>><span class="builder-review-button-label">Generate store package</span><i aria-hidden="true">→</i></button>
				</div>
				<p class="builder-form-status" data-builder-status role="status" aria-live="polite"></p>
			</form>

			<div class="builder-ready" data-builder-ready hidden>
				<div class="builder-ready-mark">✓</div>
				<span>03 / ONE-COMMAND INSTALL</span>
				<h2>Fresh server → live store.</h2>
				<p>Run one command on a clean Linux VPS. It prepares the server, installs Docker when needed, downloads this storefront securely and completes WordPress, database, HTTPS, Store Core and the first catalog sync automatically.</p>
				<div class="builder-command builder-command-primary"><code data-install-command>Generating secure command…</code><button type="button" data-copy-command>Copy</button></div>
				<div class="builder-ready-notes"><span><i></i> Ubuntu / Debian / Fedora / RHEL-family</span><span><i></i> Requires root or sudo access</span><span><i></i> Secure command valid for 60 minutes</span></div>
				<div class="builder-ready-fallback"><span>MANUAL FALLBACK</span><a class="builder-download" href="#" data-builder-download>Download installation ZIP <span>↓</span></a></div>
				<small data-installation-id></small>
			</div>
		</div>
	</aside>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
