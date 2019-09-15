<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Minify')) :
class Halftheory_Helper_Minify {

	public $minify_path = '';
	public $config = array(
		'css' => true,
		'js' => true,
	);

	public function __construct() {
		// search for minify - child theme, parent theme, ABSPATH, DOCUMENT_ROOT
		// folder names - minify, min
		$paths = array(
			get_stylesheet_directory(),
			get_template_directory(),
			dirname(ABSPATH),
			$_SERVER['DOCUMENT_ROOT'],
		);
		$paths = array_unique($paths);
		foreach ($paths as $value) {
			if (is_readable($value.'/minify')) {
				$this->minify_path = $value.'/minify';
				break;
			}
			elseif (is_readable($value.'/min')) {
				$this->minify_path = $value.'/min';
				break;
			}
		}
		// start buffer before html output
		add_action('get_header', array($this, 'get_header'), 90);
	}

	/* actions */

	public function get_header($name = '') {
		if (empty($this->minify_path)) {
			return;
		}
		if (function_exists('is_front_end')) {
			if (!is_front_end()) {
				return;
			}
		}
		elseif (is_admin()) {
			return;
		}
		// only when top level
		if (ob_get_level() !== 1) {
			return;
		}
		if (!is_readable($this->minify_path)) {
			return;
		}
		if (!is_readable($this->minify_path.'/lib/Minify/HTML.php')) {
			return;
		}
		do_action('halftheory_helper_minify_header');
		ob_start(array(&$this, 'sanitize_output'));
	}

	/* functions */

	public function sanitize_output($buffer) {
		if (!is_array($this->config)) {
			if (function_exists('make_array')) {
				$this->config = make_array($this->config);
			}
			else {
				$this->config = (array)$this->config;
			}
		}
		require_once($this->minify_path.'/lib/Minify/HTML.php');
		$options = array();
		if ($this->config('css')) {
			/*
			// strips content - disabled for now
			require_once($this->minify_path.'/lib/Minify/CommentPreserver.php');
			require_once($this->minify_path.'/lib/Minify/CSS.php');
			$options['cssMinifier'] = array('Minify_CSS', 'minify');
			*/
		}
		if ($this->config('js')) {
			/*
			// strips content - disabled for now
			require_once($this->minify_path.'/lib/Minify/JS/JShrink/src/JShrink/Minifier.php');
			require_once($this->minify_path.'/lib/Minify/JS/JShrink.php');
			$options['jsMinifier'] = array('JShrink', 'minify');
			*/
		}
		$buffer = Minify_HTML::minify($buffer, $options);
		return $buffer;
	}

	private function config($key) {
		if (isset($this->config[$key])) {
			if (!empty($this->config[$key])) {
				return true;
			}
		}
		return false;
	}
	
}
endif;
?>