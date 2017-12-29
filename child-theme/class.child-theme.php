<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Child_Theme')) :

@include_once(get_template_directory().'/functions-common.php');
@include_once(get_template_directory().'/class.halftheory-clean.php');

if (class_exists('Halftheory_Clean')) :
class Child_Theme extends Halftheory_Clean {

	var $plugins = array();
	var $admin = null;

	public function __construct() {
		parent::__construct();
		#$this->setup_globals();
		#$this->setup_actions();
		$this->setup_plugins();
		$this->setup_admin();
	}

	protected function setup_globals() {
		parent::setup_globals();
		#$this->plugin_name = get_called_class();
		#$this->plugin_title = ucwords(str_replace('_', ' ', $this->plugin_name));
		$this->prefix = sanitize_key($this->plugin_name);
		$this->prefix = preg_replace("/[^a-z0-9]/", "", $this->prefix);
	}

	protected function setup_actions() {
		parent::setup_actions();
	}

	private function setup_plugins() {
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
endif;
?>