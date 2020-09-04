<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<article id="post-<?php the_ID(); ?>" itemscope itemtype="<?php echo set_url_scheme('http://schema.org/Article'); ?>" <?php post_class(); ?>>
<?php
// meta
if (method_exists('Halftheory_Clean', 'print_post_schema')) {
	Halftheory_Clean::print_post_schema('Article');
}
// content
if (is_singular()) {
	if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
		Halftheory_Clean::post_thumbnail();
	}
	the_content();
}
elseif (is_home_page() && get_post_type() == 'post' && get_option('show_on_front') == 'posts') {
	the_title('<h2 class="entry-title" itemprop="name"><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
	if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
		Halftheory_Clean::post_thumbnail();
	}
	the_content();
}
else {
	the_title('<h2 class="entry-title" itemprop="name"><a href="'.esc_url(get_the_permalink()).'">', '</a></h2>');
	if (method_exists('Halftheory_Clean', 'post_thumbnail')) {
		Halftheory_Clean::post_thumbnail();
	}
	the_excerpt();
}
edit_post_link(
	sprintf(__('Edit').'<span class="screen-reader-text"> "%s"</span>', get_the_title()),
	"\n".'<span class="edit-link">',
	'</span>'
); ?>
</article>
