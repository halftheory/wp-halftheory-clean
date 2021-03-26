<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if ( ! is_child_theme() && is_readable(dirname(__FILE__) . '/app/functions-common.php') ) {
	include_once dirname(__FILE__) . '/app/functions-common.php';
} elseif ( is_readable(get_template_directory() . '/app/functions-common.php') ) {
	include_once get_template_directory() . '/app/functions-common.php';
}
/* theme */
if ( ! class_exists('Halftheory_Clean', false) && is_readable(dirname(__FILE__) . '/app/class-halftheory-clean.php') ) {
	include_once dirname(__FILE__) . '/app/class-halftheory-clean.php';
}
if ( ! is_child_theme() && class_exists('Halftheory_Clean', false) && ! isset($GLOBALS['Halftheory_Clean']) ) {
    $GLOBALS['Halftheory_Clean'] = Halftheory_Clean::get_instance(true);
}
