<?php
/*
Available actions:
halftheory_helper_pages_to_categories_loop_end_args
halftheory_helper_pages_to_categories_template
halftheory_helper_pages_to_categories_pagination_args
*/

// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_Pages_To_Categories', false) ) :
	class Halftheory_Helper_Pages_To_Categories {

        public $post_type = 'page';
        public $taxonomy = 'category';

		public function __construct() {
			add_filter('term_link', array( $this, 'term_link' ), 20, 3);
			add_filter('wp_nav_menu_objects', array( $this, 'wp_nav_menu_objects' ), 20, 2);
            add_action('loop_end', array( $this, 'loop_end' ), 20);
            if ( is_admin() ) {
                add_action('admin_print_styles', array( $this, 'admin_print_styles' ), 20);
                add_filter('post_class', array( $this, 'post_class' ), 20, 3);
            }
		}

		/* actions */

		public function term_link( $termlink, $term, $taxonomy = '' ) {
			if ( $taxonomy === $this->taxonomy ) {
				if ( $post = $this->get_post_from_term($term) ) {
					$termlink = get_permalink($post);
				}
			}
			return $termlink;
		}

		public function wp_nav_menu_objects( $sorted_menu_items, $args ) {
			if ( function_exists('is_front_end') ) {
				if ( ! is_front_end() ) {
					return $sorted_menu_items;
				}
			}
			global $wp_query;
			$current_term_id = null;
			$current_post_id = null;
			if ( $this->taxonomy === 'category' && $wp_query->is_category ) {
                $current_term_id = (int) $wp_query->queried_object_id;
            } elseif ( $this->taxonomy !== 'category' && $wp_query->is_tax ) {
                $current_term_id = (int) $wp_query->queried_object_id;
            } elseif ( $wp_query->is_singular ) {
				$current_post_id = (int) $wp_query->queried_object_id;
			}
			foreach ( $sorted_menu_items as &$item ) {
				if ( property_exists($item, 'ID') && property_exists($item, 'object_id') && property_exists($item, 'object') && property_exists($item, 'type') && property_exists($item, 'classes') ) {
					if ( $item->object === $this->post_type && $item->type === 'post_type' ) {
        				if ( $term = $this->get_term_from_post($item->object_id) ) {
        					if ( $current_term_id && $current_term_id === (int) $term->term_id && ! in_array('current-menu-item', $item->classes, true) ) {
								$item->classes[] = 'current-menu-item';
        					} elseif ( $current_post_id && has_term($term->term_id, $this->taxonomy, $current_post_id) && ! in_array('current-menu-parent', $item->classes, true) ) {
								$item->classes[] = 'current-menu-parent';
        					}
        				}
					}
				}
			}
			return $sorted_menu_items;
		}

        public function loop_end( $wp_query ) {
			if ( function_exists('is_front_end') ) {
				if ( ! is_front_end() ) {
					return;
				}
			}
            if ( ! is_main_query() ) {
                return;
            }
            if ( ! in_the_loop() ) {
                return;
            }
            if ( ! is_singular() ) {
                return;
            }
            if ( ! $wp_query->in_the_loop ) {
                return;
            }
            if ( ! $wp_query->is_singular ) {
                return;
            }
            if ( $this->post_type === 'page' && ! is_page() ) {
                return;
            }
            $args = array();
            if ( $term = $this->get_term_from_post(get_the_ID()) ) {
                if ( $this->taxonomy === 'category' ) {
    				$args['category_name'] = $term->slug;
                } else {
                    $args['tax_query'] = array(
                        array(
                            'taxonomy' => $this->taxonomy,
                            'field' => 'term_id',
                            'terms' => $term->term_id,
                        ),
                    );
                }
            }
        	$args = apply_filters('halftheory_helper_pages_to_categories_loop_end_args', $args);
        	if ( empty($args) ) {
        		return;
        	}
	        if ( is_paged() && ! isset($args['paged']) ) {
	            global $paged, $page;
	            $args['paged'] = ! empty($paged) ? $paged : $page;
	        }
            // Save original posts.
            global $posts;
            $original_posts = $posts;
            // Query posts.
            $posts = query_posts($args);
            if ( empty($posts) ) {
                $posts = $original_posts;
                wp_reset_query();
                return;
            }
            // remove this filter to prevent infinite looping.
            remove_action(current_action(), array( $this, __FUNCTION__ ), 20);
            // Start the loop.
            while ( have_posts() ) {
				the_post();
				global $post;
				$template = apply_filters('halftheory_helper_pages_to_categories_template', false, $post, $args);
				if ( empty($template) && method_exists('Halftheory_Clean', 'get_helper_plugin') ) {
					if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
						$template = $hp->get_template();
					}
				}
				if ( empty($template) ) {
					$template = locate_template(array( 'index.php' ), false);
				}
				if ( ! empty($template) && file_exists($template) ) {
					load_template($template, false);
				}
            }
            // End the loop.
			// Previous/next page navigation.
            $args = array(
                'prev_text'          => __('Previous'),
                'next_text'          => __('Next'),
                'before_page_number' => '<span class="meta-nav screen-reader-text">' . __('Page') . '</span>',
            );
            the_posts_pagination( apply_filters('halftheory_helper_pages_to_categories_pagination_args', $args) );
			if ( method_exists('Halftheory_Clean', 'get_helper_infinite_scroll') ) {
	            if ( $helper = Halftheory_Clean::get_instance()->get_helper_infinite_scroll() ) {
	            	$helper->setup_query();
	            }
	        }
            $posts = $original_posts;
            wp_reset_query();
        }

        /* actions - admin */

        public function admin_print_styles() {
            if ( function_exists('is_front_end') ) {
                if ( is_front_end() ) {
                    return;
                }
            }
            if ( ! function_exists('get_current_screen') ) {
                return;
            }
            if ( ! is_object(get_current_screen()) ) {
                return;
            }
            if ( strpos(get_current_screen()->id, 'edit-') === false ) {
                return;
            }
            global $typenow;
            if ( $typenow !== $this->post_type ) {
                return;
            }
            $tax = get_taxonomy($this->taxonomy);
            $label = is_object($tax) ? $tax->labels->singular_name : ucfirst($this->taxonomy);
            ?><style type="text/css">
table tr.pagestocategories-parent { background-color: #f0f0f1 !important; }
table tr.pagestocategories-parent td.title a.row-title:after { content: " (<?php echo $label; ?>)"; font-size: 85%; }
</style>
            <?php
        }

        public function post_class( $classes = array(), $class = array(), $post_id = 0 ) {
            if ( function_exists('is_front_end') ) {
                if ( is_front_end() ) {
                    return $classes;
                }
            }
            if ( ! function_exists('get_current_screen') ) {
                return $classes;
            }
            if ( ! is_object(get_current_screen()) ) {
                return $classes;
            }
            if ( strpos(get_current_screen()->id, 'edit-') === false ) {
                return $classes;
            }
            global $typenow;
            if ( $typenow !== $this->post_type ) {
                return $classes;
            }
            if ( $this->get_term_from_post($post_id) ) {
                $classes[] = 'pagestocategories-parent';
            }
            return $classes;
        }

		/* functions */

		public function get_post_from_term( $term ) {
			$post = false;
			if ( empty($term) ) {
				return $post;
			}
			$term = get_term($term, $this->taxonomy);
			if ( ! is_wp_error($term) && ! empty($term) && is_object($term) && property_exists($term, 'slug') ) {
				if ( $tmp = get_page_by_path($term->slug, OBJECT, $this->post_type) ) {
					if ( ! empty($tmp) && is_object($tmp) && in_array($tmp->post_status, array( 'publish', 'inherit' ), true) && wp_is_post_revision($tmp) === false ) {
						$post = $tmp;
					}
				}
			}
			return $post;
		}

		public function get_term_from_post( $post ) {
			$term = false;
			$post = get_post($post);
			if ( ! is_wp_error($post) && ! empty($post) && is_object($post) && in_array($post->post_status, array( 'publish', 'inherit' ), true) && wp_is_post_revision($post) === false && property_exists($post, 'post_name') ) {
            	if ( $tmp = get_term_by('slug', $post->post_name, $this->taxonomy) ) {
					if ( ! empty($tmp) && is_object($tmp) ) {
						$term = $tmp;
					}
            	}
			}
			return $term;
		}
	}
endif;
