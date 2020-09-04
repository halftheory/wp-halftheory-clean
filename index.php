<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<?php if (!in_the_loop()) : ?>
<?php get_header(); ?>

	<div id="primary" class="content">
	<?php
	// title
	if (method_exists('Halftheory_Clean', 'get_title_public')) {
		if ($title = Halftheory_Clean::get_title_public()) {
			echo '<h1 class="entry-title">'.$title.'</h1>';
		}
	}
	else {
		the_title('<h1 class="entry-title">','</h1>');
	}
	// description
	if ((is_tax() || is_tag() || is_category()) && !empty_notzero(term_description())) {
		echo get_the_content_filtered(term_description());
	}
	// posts
	if (have_posts()) {
		// Start the loop.
		while (have_posts()) {
			the_post();
			get_template_part('template-parts/content', get_post_type());
		} // End the loop.

		// Previous/next page navigation.
		the_posts_pagination(array(
			'prev_text'          => __('Previous'),
			'next_text'          => __('Next'),
			'before_page_number' => '<span class="meta-nav screen-reader-text">'.__('Page').'</span>',
		));
	}
	// If no content
	else {
		_e('Sorry, no posts found.');
	}
	?>
	</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
<?php else :
	get_template_part('template-parts/content', get_post_type());
endif;
?>