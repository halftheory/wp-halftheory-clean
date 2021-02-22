<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Clean')) {
	@include_once(get_template_directory().'/app/class-halftheory-clean.php');
}

if (!class_exists('Halftheory_Clean_Child_Theme') && class_exists('Halftheory_Clean')) :
final class Halftheory_Clean_Child_Theme extends Halftheory_Clean {

	public function __construct($load_theme = false) {
		parent::__construct($load_theme);
		if ($load_theme) {
			$this->setup_admin();
		}
	}

	protected function setup_globals() {
		parent::setup_globals();
	}

	protected function setup_plugins() {
		parent::setup_plugins();
	}

	protected function setup_actions() {
		parent::setup_actions();
		parent::setup_helper_cdn();
		parent::setup_helper_infinite_scroll();
		parent::setup_helper_minify();
		parent::setup_helper_plugin();
	}

	protected function setup_admin() {
		parent::setup_admin();
		if (is_null($this->admin)) {
			return;
		}
		$this->admin->setup_globals();
		$this->admin->setup_actions();
	}

	/* actions */
	/* functions */
	
}
endif;
?>