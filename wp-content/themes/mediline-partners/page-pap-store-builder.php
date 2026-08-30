<?php
/**
 * PAP affiliate-panel session exchange page.
 *
 * @package Mediline_Partners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow,noarchive">
	<title>Store Builder sign-in</title>
	<link rel="stylesheet" href="<?php echo esc_url( MEDILINE_PARTNERS_URI . '/assets/css/pap-builder-bridge.css?ver=' . rawurlencode( MEDILINE_PARTNERS_VERSION ) ); ?>">
	<script defer src="<?php echo esc_url( MEDILINE_PARTNERS_URI . '/assets/js/pap-builder-bridge.js?ver=' . rawurlencode( MEDILINE_PARTNERS_VERSION ) ); ?>"></script>
</head>
<body class="pap-builder-bridge-page">
	<main class="pap-builder-bridge" data-pap-builder-bridge data-endpoint="<?php echo esc_url( rest_url( 'mediline/v1/builder/pap-session' ) ); ?>">
		<div class="pap-builder-bridge-mark" aria-hidden="true">M<span>+</span></div>
		<p class="pap-builder-bridge-kicker">MEDILINE / PARTNER STORES</p>
		<h1 data-bridge-title>Opening Store Builder…</h1>
		<p data-bridge-message>Securely confirming your Post Affiliate Pro session.</p>
		<div class="pap-builder-bridge-progress" data-bridge-progress aria-label="Loading"><i></i></div>
		<button type="button" data-bridge-retry hidden>Try again</button>
	</main>
</body>
</html>

