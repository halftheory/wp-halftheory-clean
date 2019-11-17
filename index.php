<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<?php if (!in_the_loop()) : ?>
<?php get_header(); ?>

	<div id="primary" class="content">
	<?php if (is_home_page()) : /* functions-common */?>
	<?php elseif (is_404()) : ?>
		<h1 class="entry-title"><?php _e('Page not found'); ?></h1>
	<?php elseif (is_search()) : ?>
		<h1 class="entry-title"><?php _e('Search Results'); printf(' - "%1$s"', get_search_query()); ?></h1>
	<?php elseif (is_post_type_archive()) : ?>
		<h1 class="entry-title"><?php post_type_archive_title(); ?></h1>
	<?php elseif (is_tax()) : 
		$obj = get_queried_object();
		if (!empty($obj)) : ?>
			<h1 class="entry-title"><?php echo $obj->name; ?></h1>
		<?php endif; ?>
	<?php elseif (is_singular()) : ?>
		<?php the_title('<h1 class="entry-title">', '</h1>'); ?>
	<?php elseif (is_category()) : ?>
		<h1 class="entry-title"><?php single_cat_title(); ?></h1>
	<?php elseif (is_tag()) : ?>
		<h1 class="entry-title"><?php _e('Tag'); ?> - <?php single_tag_title(); ?></h1>
	<?php elseif (is_author()) : ?>
		<h1 class="entry-title"><?php _e('Author'); ?> - <?php the_author(); ?></h1>
	<?php elseif (is_date()) : 
		$date_format = get_option('date_format');
		if (is_year()) { $date_format = 'Y'; }
		elseif (is_month()) { $date_format = 'F Y'; }
		?>
		<h1 class="entry-title"><?php _e('Date'); ?> - <?php the_time($date_format); ?></h1>
	<?php endif; ?>

	<?php
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