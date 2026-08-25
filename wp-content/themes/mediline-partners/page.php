<?php
/**
 * Standard WordPress page template.
 *
 * @package Mediline_Partners
 */

get_header();
?>
<main class="wp-content-shell section-shell section-space">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'wp-content-entry' ); ?>>
			<h1><?php the_title(); ?></h1>
			<div class="wp-entry-content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
