<?php
// Exit if accessed directly.
defined('ABSPATH') || exit(__FILE__);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> <?php the_microdata_props('article'); ?>>
<?php
the_microdata_meta('article');
// Content.
if ( is_singular() ) {
	// Single.
	the_title('<h1 class="entry-title" ' . get_microdata_props('h1') . '>', '</h1>');
	ht_the_post_thumbnail();
	the_content();
	if ( is_attachment() ) {
		the_excerpt();
	}
} else {
	// List.
	the_title('<h2 class="entry-title" ' . get_microdata_props('h2') . '><a href="' . esc_url(get_permalink()) . '">', '</a></h2>');
	ht_the_post_thumbnail();
	the_excerpt();
}
// Edit.
edit_post_link(
	wp_sprintf(__('Edit') . ' <span class="screen-reader-text">"%s"</span>', get_the_title()),
	"\n" . '<p class="edit-link">',
	'/p>' . "\n"
);
?>
</article>
