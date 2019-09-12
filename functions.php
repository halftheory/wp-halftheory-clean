<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */
if (!is_child_theme()) {
	@include_once(dirname(__FILE__).'/app/functions-common.php');
}
else {
	@include_once(get_template_directory().'/app/functions-common.php');
}
/* plugin helper */
if (!class_exists('Halftheory_Helper_Plugin')) {
	@include_once(dirname(__FILE__).'/app/helpers/class-halftheory-helper-plugin.php');
}
/* theme */
if (!class_exists('Halftheory_Clean')) {
	@include_once(dirname(__FILE__).'/app/class-halftheory-clean.php');
}
if (!is_child_theme()) {
	$GLOBALS['Halftheory_Clean'] = new Halftheory_Clean();
}
?>