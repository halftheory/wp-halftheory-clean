<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<?php get_header(); ?>

	<div id="primary" class="content">
	<?php if (is_home_page()) : ?>
	<?php endif; ?>

	<?php if (is_author()) : ?>
		<h1 class="entry-title"><?php _e('Author'); ?> - <?php the_author(); ?></h1>
	<?php elseif (is_category()) : ?>
		<h1 class="entry-title"><?php single_cat_title(); ?></h1>
	<?php elseif (is_date()) : 
		$date_format = get_option('date_format');
		if (is_year()) { $date_format = 'Y'; }
		elseif (is_month()) { $date_format = 'F Y'; }
		?>
		<h1 class="entry-title"><?php _e('Date'); ?> - <?php the_time($date_format); ?></h1>
	<?php elseif (is_post_type_archive()) : ?>
		<h1 class="entry-title"><?php post_type_archive_title(); ?></h1>
	<?php elseif (is_search()) : ?>
		<h1 class="entry-title"><?php _e('Search Results'); printf(' - "%1$s"', get_search_query()); ?></h1>
	<?php elseif (is_tag()) : ?>
		<h1 class="entry-title"><?php _e('Tag'); ?> - <?php single_tag_title(); ?></h1>
	<?php elseif (is_tax()) : 
		$obj = get_queried_object();
		if (!empty($obj)) : ?>
			<h1 class="entry-title"><?php echo $obj->name; ?></h1>
		<?php endif; ?>
	<?php elseif (is_404()) : ?>
		<h1 class="entry-title"><?php _e('Page not found'); ?></h1>
	<?php endif; ?>

	<?php if (have_posts()) : 
		// Start the loop.
		while (have_posts()) : the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php
		if (is_archive() || is_author() || is_category() || is_date() || is_post_type_archive() || is_search() || is_tag() || is_tax()) {
			the_title('<h2 class="entry-title"><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
			if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
				Halftheory_Clean::post_thumbnail();
			}
			the_excerpt();
		}
		elseif (is_home_page() && get_post_type() == 'post' && get_option('show_on_front') == 'posts') {
			the_title('<h2 class="entry-title"><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
			if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
				Halftheory_Clean::post_thumbnail();
			}
			the_content();
		}
		else {
			the_title('<h1 class="entry-title">', '</h1>');
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

		// Previous/next page navigation.
		the_posts_pagination(array(
			'prev_text'          => __('Previous'),
			'next_text'          => __('Next'),
			'before_page_number' => '<span class="meta-nav screen-reader-text">'.__('Page').'</span>',
		));

	// If no content
	else :
		_e('Sorry, no posts found.');
	endif; ?>
	</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
