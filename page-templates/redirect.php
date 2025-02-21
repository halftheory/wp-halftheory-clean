<?php
/*
Template Name: Redirect
Template Post Type: post, page
*/

$url = null;
$load_template = false;

if ( isset($_GET, $_GET['url']) ) {
	$url = sanitize_url(wp_unslash($_GET['url']));
} else {
	// Exit if accessed directly.
	defined('ABSPATH') || exit(__FILE__);

	if ( ! in_the_loop() && is_singular() ) {
		$url = get_post_field('post_content', get_the_ID(), 'raw');
		$url = trim(wp_strip_all_tags($url));
		$url = preg_replace('/[\n\r\t]/s', '', $url);
		$url = do_shortcode($url);
	}
	$load_template = true;
}

if ( $url && str_starts_with($url, 'http') ) {
	$url = set_url_scheme($url);
	if ( function_exists('ht_wp_redirect') ) {
		if ( ht_wp_redirect($url) ) {
			exit;
		}
	} elseif ( function_exists('wp_redirect') ) {
		if ( wp_redirect($url) ) {
			exit;
		}
	} else {
		header('Location: ' . $url, true, 302);
		exit;
	}
}

if ( $load_template ) {
	get_template_part('index');
}
