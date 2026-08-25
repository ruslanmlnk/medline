<?php
/**
 * Generic WordPress fallback.
 *
 * @package Mediline_Partners
 */
get_header();
?>
<main class="wp-content-shell section-shell section-space">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'wp-content-entry' ); ?>>
				<h1><?php the_title(); ?></h1>
				<div class="wp-entry-content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Nothing found', 'mediline-partners' ); ?></h1>
	<?php endif; ?>
</main>
<?php get_footer(); ?>

