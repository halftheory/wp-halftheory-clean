<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if ( is_child_theme() && is_readable(get_template_directory() . '/app/functions-common.php') ) {
	include_once get_template_directory() . '/app/functions-common.php';
}
/* theme */
if ( ! class_exists('Halftheory_Clean_Child_Theme', false) && is_readable(dirname(__FILE__) . '/app/class-child-theme.php') ) {
	include_once dirname(__FILE__) . '/app/class-child-theme.php';
}
if ( class_exists('Halftheory_Clean_Child_Theme', false) && ! isset($GLOBALS['Halftheory_Clean_Child_Theme']) ) {
    $GLOBALS['Halftheory_Clean_Child_Theme'] = Halftheory_Clean_Child_Theme::get_instance(true);
}
