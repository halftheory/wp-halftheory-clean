<?php
/*
Available actions:
halftheory_helper_minify_header
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Minify', false) ) :
	class Halftheory_Helper_Minify {

		public static $minify_path = null;
		public static $minify_config = array(
			'css' => true,
			'js' => true,
		);

		public function __construct() {
			if ( function_exists('is_front_end') ) {
				if ( ! is_front_end() ) {
					return;
				}
			} elseif ( is_admin() ) {
				return;
			}
			// not compatible with cache plugins.
            $active_plugins = function_exists('get_active_plugins') ? get_active_plugins() : wp_get_active_and_valid_plugins();
			$cache_plugins = array(
				'w3-total-cache',
				'wp-optimize',
				'wp-super-cache',
			);
			foreach ( $active_plugins as $plugin ) {
				foreach ( $cache_plugins as $value ) {
					if ( strpos($plugin, $value) !== false ) {
						return;
					}
				}
			}
			// start buffer before html output.
			$priority = array( 90 );
			if ( function_exists('get_filter_next_priority') ) {
				$priority[] = get_filter_next_priority('get_header');
			}
			add_action('get_header', array( $this, 'get_header' ), max($priority));
		}

		private function init() {
			// path
			if ( is_null(static::$minify_path) ) {
				// search for minify - child theme, parent theme, ABSPATH, DOCUMENT_ROOT
				// folder names - minify, min
				$paths = array(
					get_stylesheet_directory(),
					get_template_directory(),
					dirname(ABSPATH),
					$_SERVER['DOCUMENT_ROOT'],
				);
				$paths = array_unique($paths);
				foreach ( $paths as $value ) {
					if ( is_readable($value . '/minify') && is_dir($value . '/minify') ) {
						static::$minify_path = $value . '/minify';
						break;
					} elseif ( is_readable($value . '/min') && is_dir($value . '/min') ) {
						static::$minify_path = $value . '/min';
						break;
					}
				}
			}
			static::$minify_path = untrailingslashit(static::$minify_path);
			// config
			if ( ! is_array(static::$minify_config) ) {
				if ( function_exists('make_array') ) {
					static::$minify_config = make_array(static::$minify_config);
				} else {
					static::$minify_config = (array) static::$minify_config;
				}
			}
		}

		/* actions */

		public function get_header( $name = '' ) {
			$this->init();
			if ( empty(static::$minify_path) ) {
				return;
			}
			if ( empty(static::$minify_config) ) {
				return;
			}
			if ( ! is_readable(static::$minify_path) ) {
				return;
			}
			if ( ! is_readable(static::$minify_path . '/lib/Minify/HTML.php') ) {
				return;
			}
			// only when top level.
			if ( ob_get_level() !== 1 ) {
				return;
			}
			do_action('halftheory_helper_minify_header');
			ob_start(array( &$this, 'sanitize_output' ));
		}

		/* functions */

		private function sanitize_output( $buffer ) {
			require_once static::$minify_path . '/lib/Minify/HTML.php';
			$options = array();
			if ( $this->config('css') ) {
				$class_arr = array(
					'Minify_CSS_Compressor' => '/lib/Minify/CSS/Compressor.php',
					'Minify_CommentPreserver' => '/lib/Minify/CommentPreserver.php',
					'Minify_CSS_UriRewriter' => '/lib/Minify/CSS/UriRewriter.php',
					'Minify_CSS' => '/lib/Minify/CSS.php',
				);
				$go = true;
				foreach ( $class_arr as $key => $value ) {
					if ( ! class_exists($key, false) && is_readable(static::$minify_path . $value) ) {
						require_once static::$minify_path . $value;
					}
					if ( ! class_exists($key, false) ) {
						$go = false;
						break;
					}
				}
				if ( $go && method_exists('Minify_CSS', 'minify') ) {
					$options['cssMinifier'] = array( 'Minify_CSS', 'minify' );
				}
			}
			if ( $this->config('js') ) {
				// crashes! disabled for now...
				// /lib/Minify/JS/JShrink/src/JShrink/Minifier.php
				// > $jshrink = new Minifier(); // namespace issue?
				/*
				$class_arr = array(
					'JShrink\Minifier' => '/lib/Minify/JS/JShrink/src/JShrink/Minifier.php',
					'Minify\JS\JShrink' => '/lib/Minify/JS/JShrink.php',
				);
				$go = true;
				foreach ( $class_arr as $key => $value ) {
					if ( ! class_exists($key, false) && is_readable(static::$minify_path . $value) ) {
						require_once static::$minify_path . $value;
					}
					if ( ! class_exists($key, false) ) {
						$go = false;
						break;
					}
				}
				if ( $go && method_exists('Minify\JS\JShrink','minify') ) {
					$options['jsMinifier'] = array( '\Minify\JS\JShrink', 'minify' );
				}
				*/
			}
			$buffer = Minify_HTML::minify($buffer, $options);
			return $buffer;
		}

		private function config( $key ) {
			if ( array_key_exists($key, static::$minify_config) ) {
				if ( ! empty(static::$minify_config[ $key ]) ) {
					return true;
				}
			} elseif ( in_array($key, static::$minify_config, true) ) {
				return true;
			}
			return false;
		}
	}
endif;
