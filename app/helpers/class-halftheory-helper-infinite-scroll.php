<?php
/*
Available filters:
halftheory_helper_infinite_scroll_theme_support
halftheory_helper_infinite_scroll_template
halftheory_helper_infinite_scroll_script_data
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Infinite_Scroll', false) ) :
	class Halftheory_Helper_Infinite_Scroll {

		public $container = 'primary';
		public $pagination_selector = '.pagination';
		private $ajax_action = 'infinite_scroll';
		private $query = null;

		public function __construct() {
    		if ( function_exists('is_front_end') ) {
    			if ( ! is_front_end() ) {
    				return;
    			}
    		} elseif ( is_admin() && ! wp_doing_ajax() ) {
    			return;
    		}

			add_action('after_setup_theme', array( $this, 'after_setup_theme' ), 20);
			add_action('wp_ajax_' . $this->ajax_action, array( $this, 'wp_ajax_infinite_scroll' )); // for logged in user
			add_action('wp_ajax_nopriv_' . $this->ajax_action, array( $this, 'wp_ajax_infinite_scroll' )); // if user not logged in
			add_action('get_footer', array( $this, 'get_footer' ), 20);
		}

		/* actions */

		public function after_setup_theme() {
			$arr = array(
				'container' => $this->container,
				'footer_widgets' => false,
			);
			$arr = apply_filters('halftheory_helper_infinite_scroll_theme_support', $arr);
			add_theme_support('infinite-scroll', $arr);
		}

		public function wp_ajax_infinite_scroll() {
			if ( ! isset($_POST) ) {
				wp_die();
			}
			if ( empty($_POST) ) {
				wp_die();
			}
			if ( ! isset($_POST['page']) ) {
				wp_die();
			}
			$args = array( 'paged' => $_POST['page'], 'post_status' => 'publish,inherit' );

			// get current query.
			if ( isset($_POST['query']) ) {
				$_POST['query'] = make_array($_POST['query']);
				if ( isset($_POST['query']['paged']) ) {
					unset($_POST['query']['paged']);
				}
				$args = array_merge($args, $_POST['query']);
			} elseif ( isset($_POST['param']) && isset($_POST['value']) ) {
				// fallback.
				// turn date_query and tax_query into arrays.
				if ( $_POST['param'] === 'date_query' ) {
					$args[ $_POST['param'] ] = array(
						'after' => strtotime($_POST['value']),
						'before' => strtotime('+1 day', strtotime($_POST['value'])),
					);
				} elseif ( $_POST['param'] === 'tax_query' ) {
					$term = get_term( (int) $_POST['value']);
					if ( ! empty($term) && ! is_wp_error($term) ) {
						$args[ $_POST['param'] ] = array(
							array(
								'taxonomy' => $term->taxonomy,
								'field' => 'term_id',
								'terms' => (int) $_POST['value'],
							),
						);
					}
				} else {
					$args[ $_POST['param'] ] = $_POST['value'];
				}
			}

			$posts = query_posts($args);
			if ( empty($posts) ) {
				wp_reset_query();
				wp_die();
			}
			// overwrite wp_query conditions from the main query.
			global $wp_query;
			if ( isset($_POST['conditions']) ) {
				foreach ( $_POST['conditions'] as $key => $value ) {
					if ( isset($wp_query->$key) ) {
						$wp_query->$key = is_true($value);
					}
				}
			}
			$template_default = false;
			if ( isset($_POST['template']) ) {
				if ( strpos($_POST['template'], ABSPATH) === false ) {
					$_POST['template'] = trailingslashit(get_stylesheet_directory()) . ltrim($_POST['template'], '/\\');
				}
				$template_default = $_POST['template'];
			}
			// Start the loop.
			while ( have_posts() ) {
				the_post();
				global $post;
				$template = $template_default;
				$template = apply_filters('halftheory_helper_infinite_scroll_template', $template, $post, $args);
				if ( empty($template) && class_exists('Halftheory_Clean', false) ) {
					if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
						$template = $hp->get_template($wp_query);
					}
				}
				if ( $template ) {
					load_template($template, false);
				} else {
					load_template(get_stylesheet_directory() . '/index.php', false);
				}
			} // End the loop.
			wp_reset_query();
			wp_die();
		}

		public function get_footer( $name = '' ) {
			$this->enqueue_scripts();
		}

		/* functions */

		public function setup_query( $query = null ) {
			if ( is_null($query) && ! is_object($this->query) ) {
				global $wp_query;
				$query = $wp_query;
			}
			if ( is_object($query) && is_a($query, 'WP_Query') ) {
				if ( $query->max_num_pages > 1 ) {
					$this->query = $query;
				}
			}
		}

		private function enqueue_scripts_conditions() {
			if ( is_object($this->query) ) {
				// force true if already run setup_query.
				return true;
			}
			if ( wp_doing_ajax() ) {
				return false;
			}
			if ( is_embed() ) {
				return false;
			} elseif ( is_404() ) {
				return false;
			} elseif ( is_privacy_policy() ) {
				return false;
			} elseif ( is_singular() ) {
				return false;
			}
			if ( function_exists('is_login_page') ) {
				if ( is_login_page() ) {
					return false;
				}
			}
			if ( function_exists('is_signup_page') ) {
				if ( is_signup_page() ) {
					return false;
				}
			}
			$this->setup_query();
			if ( ! is_object($this->query) ) {
				return false;
			}
			global $paged;
			if ( absint($paged) >= $this->query->max_num_pages ) {
				return false;
			}
			return true;
		}

		private function enqueue_scripts() {
			if ( ! $this->enqueue_scripts_conditions() ) {
				return;
			}
			$handle = $this->ajax_action;
			if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
				wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.css', array(), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/infinite-scroll/infinite-scroll.css'), 'screen');
				wp_enqueue_script('jqueryrotate', get_template_directory_uri() . '/app/helpers/infinite-scroll/jQueryRotateCompressed.js', array( 'jquery' ), '2.3', true);
				wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.min.js', array( 'jquery', 'jqueryrotate' ), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/infinite-scroll/infinite-scroll.min.js'), true);
			} else {
				wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.css', array(), '', 'screen');
				wp_enqueue_script('jqueryrotate', get_template_directory_uri() . '/app/helpers/infinite-scroll/jQueryRotateCompressed.js', array( 'jquery' ), '2.3', true);
				wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.min.js', array( 'jquery', 'jqueryrotate' ), '', true);
			}
			// build the data array
			$data = array(
				'action' => $this->ajax_action,
			);

			// get current query
			if ( is_array($this->query->query) ) {
				$arr = array();
				foreach ( $this->query->query as $key => $value ) {
					if ( $key === 'paged' ) {
						continue;
					}
					if ( ! empty_notzero($value) ) {
						$arr[ $key ] = $value;
					}
				}
				if ( ! empty($arr) ) {
					$data['query'] = $arr;
				}
			}
			// fallback
			if ( ! isset($data['query']) ) {
				if ( $this->query->is_author() ) {
					$obj = $this->query->get_queried_object();
					if ( ! empty($obj) ) {
						$data['param'] = 'author';
						$data['value'] = $obj->ID;
					}
				} elseif ( $this->query->is_category() ) {
					$obj = $this->query->get_queried_object();
					if ( ! empty($obj) ) {
						$data['param'] = 'cat';
						$data['value'] = $obj->term_id;
					}
				} elseif ( $this->query->is_date() ) {
					if ( $this->query->is_year() ) {
						$data['param'] = 'year';
						$data['value'] = get_the_time('Y', $this->query->post);
					} elseif ( $this->query->is_month() ) {
						$data['param'] = 'm';
						$data['value'] = get_the_time('Ym', $this->query->post);
					} else {
						$data['param'] = 'date_query';
						$data['value'] = get_the_time('Ymd', $this->query->post);
					}
				} elseif ( $this->query->is_search() ) {
					$data['param'] = 's';
					// can't use get_search_query()!
					$data['value'] = esc_attr($this->query->get('s'));
				} elseif ( $this->query->is_post_type_archive() ) {
					$obj = $this->query->get_queried_object();
					if ( ! empty($obj) ) {
						$data['param'] = 'post_type';
						$data['value'] = $obj->post_type;
					}
				} elseif ( $this->query->is_tag() ) {
					$obj = $this->query->get_queried_object();
					if ( ! empty($obj) ) {
						$data['param'] = 'tag_id';
						$data['value'] = $obj->term_id;
					}
				} elseif ( $this->query->is_tax() ) {
					$obj = $this->query->get_queried_object();
					if ( ! empty($obj) ) {
						$data['param'] = 'tax_query';
						$data['value'] = $obj->term_id;
					}
				}
			}

			// get current query conditions.
			if ( class_exists('Halftheory_Clean', false) ) {
				if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
					$arr = array();
					foreach ( $hp->get_template_tags() as $key => $value ) {
						if ( isset($this->query->$key) ) {
							$arr[ $key ] = (int) $this->query->$key;
						}
					}
					if ( ! empty($arr) ) {
						$data['conditions'] = $arr;
					}
				}
			}
			// compile all the js data.
			$js_data = array(
				'ajaxurl' => esc_url(admin_url('admin-ajax.php')),
				'data' => $data,
				'max' => $this->query->max_num_pages,
				'container' => $this->container,
				'pagination_selector' => $this->pagination_selector,
				'loader' => apply_filters('halftheory_helper_infinite_scroll_loader', get_template_directory_uri() . '/app/helpers/infinite-scroll/ajax-loader.png'),
			);
			if ( is_paged() ) {
				global $paged;
				$js_data['paged'] = $paged;
			}
			// $js_data['data']['template'] can be set with this filter.
			wp_localize_script($handle, $handle, apply_filters('halftheory_helper_infinite_scroll_script_data', $js_data));
		}
	}
endif;
