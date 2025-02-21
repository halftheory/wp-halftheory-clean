<?php
// Exit if accessed directly.
defined('ABSPATH') || exit(__FILE__);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> role="article" itemscope itemtype="<?php echo esc_url(set_url_scheme('http://schema.org/Article')); ?>">
<?php
// Content.
if ( is_singular() ) {
	// Single.
	the_title('<h1 class="entry-title" role="heading" itemprop="name">', '</h1>');
	ht_the_post_thumbnail();
	the_content();
	if ( is_attachment() ) {
		the_excerpt();
	}
} else {
	// List.
	the_title('<h2 class="entry-title" role="heading" itemprop="name"><a href="' . esc_url(get_permalink()) . '">', '</a></h2>');
	ht_the_post_thumbnail();
	the_excerpt();
}
// Edit.
edit_post_link(
	wp_sprintf(__('Edit') . ' <span class="screen-reader-text">"%s"</span>', get_the_title()),
	"\n" . '<p class="edit-link"><span class="edit-link">',
	'</span></p>' . "\n"
);
?>
</article>
