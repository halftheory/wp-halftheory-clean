<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if (!is_child_theme()) {
	@include_once(dirname(__FILE__).'/functions-common.php');
}
else {
	@include_once(get_template_directory().'/functions-common.php');
}

/* actions */
if (!class_exists('Halftheory_Clean')) {
	@include_once(dirname(__FILE__).'/class-halftheory-clean.php');
}
if (!is_child_theme()) {
	$GLOBALS['Halftheory_Clean'] = new Halftheory_Clean();
}
?>