<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Clean', false) && is_readable(get_template_directory() . '/app/class-halftheory-clean.php') ) {
	include_once get_template_directory() . '/app/class-halftheory-clean.php';
}

if ( ! class_exists('Halftheory_Clean_Child_Theme', false) && class_exists('Halftheory_Clean', false) ) :
	final class Halftheory_Clean_Child_Theme extends Halftheory_Clean {

		protected function __construct( $load_actions = false ) {
			parent::__construct($load_actions);
		}

		protected function setup_globals() {
			parent::setup_globals();
		}

		protected function setup_plugins() {
			parent::setup_plugins();
		}

		protected function setup_actions() {
			parent::setup_actions();
			parent::setup_helper_admin();
			#parent::setup_helper_cdn();
			#parent::setup_helper_featured_video();
			#parent::setup_helper_gallery_carousel();
			#parent::setup_helper_infinite_scroll();
			#parent::setup_helper_minify();
			#parent::setup_helper_pages_to_categories();
			#parent::setup_helper_plugin();
            #parent::setup_helper_shortcode_code();
		}

		/* actions */
		/* functions */
	}
endif;
