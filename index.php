<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<!-- #header -->
<?php get_header(); ?>

		<div id="primary" class="content">
		<?php if ( have_posts() ) : ?>

			<?php if ( is_front_page() ) : ?>
			<?php endif; ?>

			<?php
			// Start the loop.
			while ( have_posts() ) : the_post(); ?>
			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php
			if (is_singular()) {
				the_title('<h1>', '</h1>');
			}
			else {
				the_title('<h1><a href="'.esc_url(get_the_permalink()).'">', '</a></h1>');
			}
			if (method_exists($GLOBALS['Halftheory_Clean'], 'post_thumbnail')) {
				$GLOBALS['Halftheory_Clean']->post_thumbnail();
			}
			the_content();

			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					'Edit<span class="screen-reader-text"> "%s"</span>',
					get_the_title()
				),
				'<span class="edit-link">',
				'</span>'
			); ?>
			</article>
			<?php // End the loop.
			endwhile;

			// Previous/next page navigation.
			the_posts_pagination( array(
				'prev_text'          => 'Previous page',
				'next_text'          => 'Next page',
				'before_page_number' => '<span class="meta-nav screen-reader-text">Page</span>',
			) );

		// If no content
		else :
			echo 'Sorry, no posts found.';

		endif; ?>
		</div><!-- #primary -->

<?php get_sidebar(); ?>
<!-- #footer -->
<?php get_footer(); ?>
