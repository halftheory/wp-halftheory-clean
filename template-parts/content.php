<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;
?>
<article id="post-<?php the_ID(); ?>" itemscope itemtype="<?php echo esc_url(set_url_scheme('http://schema.org/Article')); ?>" <?php post_class(); ?>>
<?php
// Meta.
if ( method_exists('Halftheory_Clean', 'print_post_schema') ) {
	Halftheory_Clean::get_instance()->print_post_schema('Article');
}
// Content.
if ( is_singular() ) {
	if ( method_exists('Halftheory_Clean', 'post_thumbnail') ) {
		Halftheory_Clean::get_instance()->post_thumbnail();
	}
	the_content();
	if ( is_attachment() ) {
		the_excerpt();
	}
} elseif ( is_home_page() && get_post_type() === 'post' && get_option('show_on_front') === 'posts' ) {
	the_title('<h2 class="entry-title" itemprop="name"><a href="' . esc_url(get_permalink()) . '">', '</a></h2>');
	if ( method_exists('Halftheory_Clean', 'post_thumbnail') ) {
		Halftheory_Clean::get_instance()->post_thumbnail();
	}
	the_content();
} else {
	the_title('<h2 class="entry-title" itemprop="name"><a href="' . esc_url(get_permalink()) . '">', '</a></h2>');
	if ( method_exists('Halftheory_Clean', 'post_thumbnail') ) {
		Halftheory_Clean::get_instance()->post_thumbnail();
	}
	the_excerpt();
}
// Edit.
edit_post_link(
	wp_sprintf(__('Edit') . ' <span class="screen-reader-text">"%s"</span>', get_the_title()),
	"\n" . '<span class="edit-link">',
	'</span>'
);
?>
</article>
