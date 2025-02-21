<?php
// Exit if accessed directly.
defined('ABSPATH') || exit(__FILE__);

if ( ! in_the_loop() ) {
	get_header();
	if ( is_404() ) {
		esc_html_e('Page not found.');
	} elseif ( have_posts() ) {
		// Archives.
		if ( ! is_singular() ) {
			?>
			<div id="articles">
			<?php
			if ( $tmp = is_title_ok(get_the_archive_title()) ) {
				echo wp_kses_post('<h1 class="entry-title" role="heading" itemprop="name">' . $tmp . '</h1>');
			}
			$tmp = trim(get_the_archive_description());
			if ( ! empty($tmp) ) {
				echo wp_kses_post(apply_filters('the_content', $tmp));
			}
		}
		// Start the loop.
		while ( have_posts() ) {
			the_post();
			get_template_part('parts/content', get_post_type());
		}
		// End the loop.
		// Previous/next page navigation.
		the_posts_pagination(pagination_args());
		if ( ! is_singular() ) {
			?>
			</div>
			<?php
		}
	} else {
		// No content.
		esc_html_e('No posts found.');
	}
	get_sidebar();
	get_footer();
} else {
	get_template_part('parts/content', get_post_type());
}
