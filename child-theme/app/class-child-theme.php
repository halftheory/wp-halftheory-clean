<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Clean')) {
	@include_once(get_template_directory().'/app/class-halftheory-clean.php');
}

if (!class_exists('Halftheory_Clean_Child_Theme') && class_exists('Halftheory_Clean')) :
class Halftheory_Clean_Child_Theme extends Halftheory_Clean {

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
		if (is_user_logged_in()) {
			if (!class_exists('Halftheory_Admin_Helper')) {
				@include_once(get_template_directory().'/app/class-halftheory-admin-helper.php');
			}
			if (class_exists('Halftheory_Admin_Helper')) {
				$this->admin = new Halftheory_Admin_Helper();
			}
		}
	}

	/* actions */
	/* functions */
	
}
endif;
?>