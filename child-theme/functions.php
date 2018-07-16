<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if (is_child_theme()) {
	@include_once(get_template_directory().'/app/functions-common.php');
}
/* actions */
if (!class_exists('Child_Theme')) {
	@include_once(dirname(__FILE__).'/app/class-child-theme.php');
}
if (class_exists('Child_Theme')) {
	$GLOBALS['Child_Theme'] = new Child_Theme();
}
?>