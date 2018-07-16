<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Clean')) {
	@include_once(get_template_directory().'/app/class-halftheory-clean.php');
}

if (!class_exists('Child_Theme') && class_exists('Halftheory_Clean')) :
class Child_Theme extends Halftheory_Clean {

	var $admin = null;

	public function __construct() {
		parent::__construct();
		$this->setup_admin();
	}

	protected function setup_globals() {
		parent::setup_globals();
	}

	protected function setup_plugins() {
		parent::setup_plugins();
	}

	protected function setup_actions() {
		parent::setup_actions();
	}

	private function setup_admin() {
		if (is_front_end()) {
			return;
		}
	}

	/* actions */
	/* functions */
	
}
endif;
?>