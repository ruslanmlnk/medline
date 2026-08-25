<?php
/**
 * Not-found template.
 *
 * @package Mediline_Partners
 */

get_header();
?>
<main class="wp-content-shell section-shell section-space">
	<article class="wp-content-entry">
		<span class="eyebrow"><?php esc_html_e( 'Error 404', 'mediline-partners' ); ?></span>
		<h1><?php esc_html_e( 'Page not found.', 'mediline-partners' ); ?></h1>
		<div class="wp-entry-content">
			<p><?php esc_html_e( 'The page may have moved or no longer exists.', 'mediline-partners' ); ?></p>
			<p><a class="button button-dark" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to partner program', 'mediline-partners' ); ?> →</a></p>
		</div>
	</article>
</main>
<?php get_footer(); ?>
