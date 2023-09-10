<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Shortcode_Code', false) ) :
	#[AllowDynamicProperties]
	class Halftheory_Helper_Shortcode_Code {

        public $shortcode = 'code';

		public function __construct() {
			if ( function_exists('is_public') ) {
				if ( ! is_public() ) {
					return;
				}
			} elseif ( is_admin() ) {
				return;
			}
            add_action('init', array( $this, 'init' ), 20);
		}

        /* actions */

		public function init() {
            // shortcode - [code].
            if ( ! shortcode_exists($this->shortcode) ) {
                $func = function ( $atts = array(), $content = '', $shortcode = '' ) {
                    return function_exists('trim_excess_space') ? trim_excess_space($content) : trim($content);
                };
                add_shortcode($this->shortcode, $func);
                if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
                    if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
                        $hp->add_shortcode_wpautop_control($this->shortcode);
                    }
                }
            }
		}
	}
endif;
