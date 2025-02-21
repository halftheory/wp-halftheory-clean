<?php
// Exit if accessed directly.
defined('ABSPATH') || exit(__FILE__);

if ( is_readable(__DIR__ . '/vendor/autoload.php') ) {
	require __DIR__ . '/vendor/autoload.php';
} else {
	exit('composer u');
}

if ( ! function_exists('halftheoryclean') ) {
	function halftheoryclean( $autoload = false ) {
		return class_exists('Halftheory\Themes\Halftheory_Clean\Halftheory_Clean_Theme') ? Halftheory\Themes\Halftheory_Clean\Halftheory_Clean_Theme::get_instance($autoload) : null;
	}
}

if ( ! is_child_theme() && ! isset($GLOBALS['Halftheory_Clean_Theme']) ) {
	$GLOBALS['Halftheory_Clean_Theme'] = halftheoryclean(true);
}
