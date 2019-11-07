<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if (is_child_theme()) {
	@include_once(get_template_directory().'/app/functions-common.php');
}
/* theme */
if (!class_exists('Halftheory_Clean_Child_Theme')) {
	@include_once(dirname(__FILE__).'/app/class-child-theme.php');
}
if (class_exists('Halftheory_Clean_Child_Theme')) {
	$GLOBALS['Halftheory_Clean_Child_Theme'] = new Halftheory_Clean_Child_Theme(true);
}
?>