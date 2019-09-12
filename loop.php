<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

$infinite_scroll = '';
if (!empty($_POST)) {
	if (isset($_POST['action'])) {
		if ($_POST['action'] == 'infinite_scroll') {
			if (isset($_POST['param']) && isset($_POST['value'])) {
				$infinite_scroll = 'archive';
			}
			else {
				$infinite_scroll = 'posts';
			}
		}
	}
}

if (have_posts()) : 
	// Start the loop.
	while (have_posts()) : the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
	if (is_archive() || is_author() || is_category() || is_date() || is_search() || is_tag() || is_tax() || $infinite_scroll == 'archive') {
		the_title('<h2><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
		if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
			Halftheory_Clean::post_thumbnail();
		}
		the_excerpt();
	}
	elseif ((is_home_page() && get_option('show_on_front') == 'posts') || $infinite_scroll == 'posts') {
		the_title('<h2 ><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
		if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
			Halftheory_Clean::post_thumbnail();
		}
		the_content();
	}
	else {
		the_title('<h1>', '</h1>');
		if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
			Halftheory_Clean::post_thumbnail();
		}
		the_content();
	}	
	edit_post_link(
		sprintf(__('Edit').'<span class="screen-reader-text"> "%s"</span>', get_the_title()),
		'<span class="edit-link">',
		'</span>'
	); ?>
	</article>
	<?php // End the loop.
	endwhile;
endif; ?>