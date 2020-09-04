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
/* theme */
if (!class_exists('Halftheory_Clean')) {
	@include_once(dirname(__FILE__).'/app/class-halftheory-clean.php');
}
if (!is_child_theme() && !isset($GLOBALS['Halftheory_Clean'])) {
	$GLOBALS['Halftheory_Clean'] = new Halftheory_Clean(true);
}
?>