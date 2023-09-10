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
	#[AllowDynamicProperties]
	class Halftheory_Helper_Infinite_Scroll {

		public $container = 'primary';
		public $pagination_selector = '.pagination';
		private $ajax_action = 'infinite_scroll';
		private $query = null;

		public function __construct() {
    		if ( function_exists('is_public') ) {
    			if ( ! is_public() ) {
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
			if ( ! isset($_POST, $_POST['page']) ) {
				wp_die( -1 );
			}
			$args = array( 'paged' => $_POST['page'], 'post_status' => 'publish,inherit' );

			// get current query.
			if ( isset($_POST['query']) ) {
				$_POST['query'] = make_array(wp_unslash($_POST['query']));
				if ( isset($_POST['query']['paged']) ) {
					unset($_POST['query']['paged']);
				}
				$args = array_merge($args, $_POST['query']);
			} elseif ( isset($_POST['param'], $_POST['value']) ) {
				$post_param = wp_unslash($_POST['param']);
				$post_value = wp_unslash($_POST['value']);
				// fallback.
				// turn date_query and tax_query into arrays.
				if ( $post_param === 'date_query' ) {
					$args[ $post_param ] = array(
						'after' => strtotime($post_value),
						'before' => strtotime('+1 day', strtotime($post_value)),
					);
				} elseif ( $post_param === 'tax_query' ) {
					$term = get_term( (int) $post_value);
					if ( ! empty($term) && ! is_wp_error($term) ) {
						$args[ $post_param ] = array(
							array(
								'taxonomy' => $term->taxonomy,
								'field' => 'term_id',
								'terms' => (int) $post_value,
							),
						);
					}
				} else {
					$args[ $post_param ] = $post_value;
				}
			}

			if ( is_multisite() && isset($_POST['blog_id']) ) {
				switch_to_blog($_POST['blog_id']);
			}
			if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
				if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
					$args = $hp->check_wp_query_args($args);
				}
			}

			$posts = query_posts($args);
			if ( empty($posts) ) {
				wp_reset_query();
				if ( is_multisite() && isset($_POST['blog_id']) ) {
					restore_current_blog();
				}
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
				if ( file_exists($_POST['template']) ) {
					$template_default = $_POST['template'];
				} else {
					$template_default = locate_template(array( ltrim($_POST['template'], '/\\') ), false);
				}
			}
			// Start the loop.
			while ( have_posts() ) {
				the_post();
				global $post;
				$template = apply_filters('halftheory_helper_infinite_scroll_template', $template_default, $post, $args);
				if ( empty($template) && method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
					if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
						$template = $hp->get_template($wp_query);
					}
				}
				if ( empty($template) ) {
					$template = locate_template(array( 'index.php' ), false);
				}
				if ( ! empty($template) && file_exists($template) ) {
					load_template($template, false);
				}
			} // End the loop.
			wp_reset_query();
			if ( is_multisite() && isset($_POST['blog_id']) ) {
				restore_current_blog();
			}
			wp_die();
		}

		public function get_footer( $name = '' ) {
			$this->enqueue_scripts();
		}

		/* functions */

		public function setup_query( $query = null, $blog_id = null ) {
			if ( is_null($query) && ! is_object($this->query) ) {
				global $wp_query;
				$query = $wp_query;
			}
			if ( is_object($query) && is_a($query, 'WP_Query') ) {
				if ( $query->max_num_pages > 1 ) {
					$this->query = $query;
					if ( ! is_null($blog_id) ) {
						$this->query_blog_id = $blog_id;
					} elseif ( is_multisite() && ms_is_switched() ) {
						$this->query_blog_id = get_current_blog_id();
					}
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
            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			if ( method_exists('Halftheory_Clean', 'get_theme_version') ) {
				wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.css', array(), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/infinite-scroll/infinite-scroll.css'), 'screen');
				wp_enqueue_script('jqueryrotate', get_template_directory_uri() . '/app/helpers/infinite-scroll/jQueryRotateCompressed.js', array( 'jquery' ), '2.3', true);
                wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll' . $min . '.js', array( 'jquery', 'jqueryrotate' ), Halftheory_Clean::get_instance()->get_theme_version(get_template_directory() . '/app/helpers/infinite-scroll/infinite-scroll' . $min . '.js'), true);
			} else {
				wp_enqueue_style($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll.css', array(), '', 'screen');
				wp_enqueue_script('jqueryrotate', get_template_directory_uri() . '/app/helpers/infinite-scroll/jQueryRotateCompressed.js', array( 'jquery' ), '2.3', true);
				wp_enqueue_script($handle, get_template_directory_uri() . '/app/helpers/infinite-scroll/infinite-scroll' . $min . '.js', array( 'jquery', 'jqueryrotate' ), '', true);
			}
			// build the data array.
			$data = array(
				'action' => $this->ajax_action,
			);
			if ( is_multisite() && isset($this->query_blog_id) ) {
				$data['blog_id'] = $this->query_blog_id;
			}

			// get current query.
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
			// fallback.
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
			if ( method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
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
                'pagination_more' => '<button id="infinite-scroll-more">' . __('Load more') . '</button>',
				'loader' => get_template_directory_uri() . '/app/helpers/infinite-scroll/ajax-loader.png',
			);
			if ( is_paged() ) {
	            global $paged, $page;
				$js_data['paged'] = ! empty($paged) ? $paged : $page;
			}
			// $js_data['data']['template'] can be set with this filter.
			wp_localize_script($handle, $handle, apply_filters('halftheory_helper_infinite_scroll_script_data', $js_data));
		}
	}
endif;
