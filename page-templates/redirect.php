<?php
/*
Template Name: Redirect
Template Post Type: post, page
*/

$url = false;
$load_template = false;

if ( isset($_GET['url']) ) {
	$url = sanitize_url(wp_unslash($_GET['url']));
} else {
	// Exit if accessed directly.
	defined('ABSPATH') || exit;

	if ( ! in_the_loop() && is_singular() ) {
		$url = get_post_field('post_content', get_the_ID(), 'raw');
		$url = trim(wp_strip_all_tags($url));
		$url = preg_replace("/[\n\r\t]/is", '', $url);
		$url = do_shortcode($url);
	}
	$load_template = true;
}

if ( $url ) {
	if ( strpos($url, 'http') === 0 ) {
		$url = set_url_scheme($url);
		if ( ! function_exists('wp_redirect_extended') && is_readable(get_template_directory() . '/app/functions-common.php') ) {
			include_once get_template_directory() . '/app/functions-common.php';
		}
		if ( function_exists('wp_redirect_extended') ) {
			if ( wp_redirect_extended($url) ) {
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
}

if ( $load_template ) {
	get_template_part('index');
}
