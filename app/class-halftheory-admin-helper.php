<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Admin_Helper')) :
class Halftheory_Admin_Helper {

	public static $current_screen_id;
	public $wp_before_admin_bar_render_remove = array();
	public $admin_menu_include = array();
	public $admin_menu_exclude = array();

	public function __construct() {
	}

	public function setup_globals() {
		global $current_user;
		// toolbar
		if (is_super_admin()) {
			$this->wp_before_admin_bar_render_remove = array(
				'wp-logo',
				#'my-sites',
				#'new-content',
				#'updates',
				'comments',
				#'search',
				#'customize',
				#'themes',
				#'widgets',
				#'edit-site',
				#'view-site',
				#'menus'
			);
		}
		elseif (is_array($current_user->roles)) {
			if (in_array('administrator', $current_user->roles)) {
				$this->wp_before_admin_bar_render_remove = array(
					'wp-logo',
					#'my-sites',
					#'new-content',
					'updates',
					'comments',
					'search',
					'customize',
					'themes',
					'widgets',
					'edit-site',
					'view-site',
					'menus'
				);
			}
			elseif (in_array('editor', $current_user->roles)) {
				$this->wp_before_admin_bar_render_remove = array(
					'wp-logo',
					'my-sites',
					#'new-content',
					'updates',
					'comments',
					'search',
					'customize',
					'themes',
					'widgets',
					'edit-site',
					'view-site',
					'menus'
				);
			}
			else {
				$this->wp_before_admin_bar_render_remove = array(
					'wp-logo',
					'my-sites',
					'new-content',
					'updates',
					'comments',
					'search',
					'customize',
					'themes',
					'widgets',
					'edit-site',
					'view-site',
					'menus'
				);
			}
		}
		// admin_menu
		if (is_super_admin()) {
			$this->admin_menu_exclude = array(
				'edit-comments.php' => '*',
			);
		}
		elseif (is_array($current_user->roles)) {
			if (in_array('administrator', $current_user->roles)) {
				$this->admin_menu_exclude = array(
					'edit-comments.php' => '*',
				);
			}
			elseif (in_array('editor', $current_user->roles)) {
				$this->admin_menu_include = array(
					'Dashboard' => '*',
					'separator1' => '',
					'Pages' => '*',
					'Posts' => '*',
					'Media' => '*',
					'separator2' => '',
			        'Appearance' => array('Menus'),
					'separator-last' => '',
				);
			}
			else {
				$this->admin_menu_include = array(
					'Dashboard' => '*',
					'separator1' => '',
					'Pages' => '*',
					'Posts' => '*',
					'Media' => '*',
					'separator-last' => '',
				);
			}
		}
	}

	public function setup_actions() {
		if (!is_user_logged_in()) {
			return;
		}
		// toolbar changes on frontend + backend
		add_action('wp_before_admin_bar_render', array($this, 'wp_before_admin_bar_render'), 20);
		add_action('admin_bar_menu', array($this, 'admin_bar_menu'), 20);

		if (is_front_end()) {
			return;
		}

		add_action('admin_menu', array($this, 'admin_menu'), (9553+10));
		add_action('current_screen', array($this, 'current_screen'));
		add_action('wp_update_nav_menu', array($this, 'wp_update_nav_menu'), 20, 2);
		add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
		// disable screen options
		add_filter('screen_options_show_screen', '__return_false', 100);
		// disable notices
		remove_action('admin_notices', 'update_nag', 3);
		remove_action('admin_notices', 'maintenance_nag', 10);
		if (is_multisite()) {
			remove_action('admin_notices', 'site_admin_notice');
			remove_action('network_admin_notices', 'site_admin_notice');
			remove_action('network_admin_notices', 'update_nag', 3);
			remove_action('network_admin_notices', 'maintenance_nag', 10);
		}
		// disable footer
		add_filter('admin_footer_text', '__return_empty_string', 100);
		add_filter('update_footer', '__return_empty_string', 100);
		// Admin Color Scheme
		remove_all_actions('admin_color_scheme_picker');
	}

	/* actions */

	public function wp_before_admin_bar_render() {
		if (!isset($this->wp_before_admin_bar_render_remove)) {
			return;
		}
		if (empty($this->wp_before_admin_bar_render_remove)) {
			return;
		}
		// remove stuff
		global $wp_admin_bar;
		foreach ((array)$this->wp_before_admin_bar_render_remove as $value) {
			$wp_admin_bar->remove_menu($value);
		}
	}

	public function admin_bar_menu($wp_admin_bar) {
		// replace Howdy with Welcome
		$my_account = $wp_admin_bar->get_node('my-account');
		$newtitle = str_replace('Howdy', __('Welcome'), $my_account->title);
		$wp_admin_bar->add_node(array(
			'id' => 'my-account',
			'title' => $newtitle,
		));
	}

	public function admin_menu() {
		if (!is_array($GLOBALS['menu'])) {
			return;
		}
		$has_submenu = true;
		if (!is_array($GLOBALS['submenu'])) {
			$has_submenu = false;
		}
		if (!empty($this->admin_menu_include)) {
		    $menu_positions = array_keys($this->admin_menu_include);
		 	$menu_new = $submenu_new = array();
		    foreach ($GLOBALS['menu'] as $value) {
		    	$key = false;
		    	$i = false;
		        if (array_key_exists($value[2], $this->admin_menu_include)) { // array key 2 most exact - separators, plugins, etc.
		        	$key = $value[2];
		        	$i = array_search($value[2], $menu_positions);
		        }
		        elseif (array_key_exists($value[0], $this->admin_menu_include)) { // array key 0 is title.
		        	$key = $value[0];
		        	$i = array_search($value[0], $menu_positions);
		        }
		        if ($key === false) {
					remove_menu_page($value[2]);
					continue;
		        }
				$menu_new[$i] = $value;
				// submenus
				if (!$has_submenu) {
					continue;
				}
				elseif (empty($this->admin_menu_include[$key]) && isset($GLOBALS['submenu'][$value[2]])) {
					foreach ($GLOBALS['submenu'][$value[2]] as $subvalue) {
						remove_submenu_page($value[2], $subvalue[2]);
					}
				}
				elseif ($this->admin_menu_include[$key] === '*' && isset($GLOBALS['submenu'][$value[2]])) {
					$submenu_new[$value[2]] = $GLOBALS['submenu'][$value[2]];
				}
				elseif (isset($GLOBALS['submenu'][$value[2]])) {
					$this->admin_menu_include[$key] = make_array($this->admin_menu_include[$key]);
					foreach ($GLOBALS['submenu'][$value[2]] as $subvalue) {
						$j = false;
				        if (in_array($subvalue[2], $this->admin_menu_include[$key])) {
				        	$j = array_search($subvalue[2], $this->admin_menu_include[$key]);
				        }
				        elseif (in_array($subvalue[0], $this->admin_menu_include[$key])) {
				        	$j = array_search($subvalue[0], $this->admin_menu_include[$key]);
				        }
				        if ($j !== false) {
				        	if (!isset($submenu_new[$value[2]])) {
				        		$submenu_new[$value[2]] = array();
				        	}
				        	$submenu_new[$value[2]][$j] = $subvalue;
				        }
				    	else {
							remove_submenu_page($value[2], $subvalue[2]);
						}
					}
				}
		    }
		    if (!empty($menu_new)) {
		    	ksort($menu_new);
		    	$GLOBALS['menu'] = $menu_new;
			    if (!empty($submenu_new)) {
			    	foreach ($submenu_new as $key => $value) {
			    		ksort($submenu_new[$key]);
			    	}
			    	$GLOBALS['submenu'] = $submenu_new;
			    }
		    }
		}
		if (!empty($this->admin_menu_exclude)) {
		    foreach ($GLOBALS['menu'] as $value) {
		    	$key = false;
		        if (array_key_exists($value[2], $this->admin_menu_exclude)) { // array key 2 most exact - separators, plugins, etc.
		        	$key = $value[2];
		        }
		        elseif (array_key_exists($value[0], $this->admin_menu_exclude)) { // array key 0 is title.
		        	$key = $value[0];
		        }
		        if ($key === false) {
		        	continue;
		        }
		        // remove all
		        if (empty($this->admin_menu_exclude[$key]) || $this->admin_menu_exclude[$key] === '*') {
					remove_menu_page($value[2]);
					if (!$has_submenu) {
						continue;
					}
					elseif (isset($GLOBALS['submenu'][$value[2]])) {
						foreach ($GLOBALS['submenu'][$value[2]] as $subvalue) {
							remove_submenu_page($value[2], $subvalue[2]);
						}
					}
				}
		        // remove some
				else {
					$this->admin_menu_exclude[$key] = make_array($this->admin_menu_exclude[$key]);
					if (!$has_submenu) {
						continue;
					}
					elseif (isset($GLOBALS['submenu'][$value[2]])) {
						foreach ($GLOBALS['submenu'][$value[2]] as $subvalue) {
							$j = false;
					        if (in_array($subvalue[2], $this->admin_menu_exclude[$key])) {
					        	$j = array_search($subvalue[2], $this->admin_menu_exclude[$key]);
					        }
					        elseif (in_array($subvalue[0], $this->admin_menu_exclude[$key])) {
					        	$j = array_search($subvalue[0], $this->admin_menu_exclude[$key]);
					        }
					        if ($j !== false) {
								remove_submenu_page($value[2], $subvalue[2]);
							}
						}
					}
				}
			}
		}
	}

	public function current_screen($current_screen) {
		self::$current_screen_id = $current_screen->id;
	}

	public function wp_update_nav_menu($menu_id, $menu_data = null) {
		if (!in_array(self::$current_screen_id, array('nav-menus'))) {
			return;
		}
	    if ($menu_data !== null) {
	    	// If $menu_data !== null, this means the action was fired in nav-menu.php, BEFORE the menu items have been updated, and we should ignore it.
	        return;
		}
		/*
		update page structure to mirror menu structure:
		1. front page
		2. primary menu items
		3. other menus (order alphabetical by menu name)
		4. other pages (not front or in menus, order page)
		*/
		$page_structure = array();
		$page_structure_flat = array(); // check for duplicates here

		$add_arr_to_parent = function(&$pages, $parent, $arr = array()) use (&$add_arr_to_parent) {
			$parent_found = false;
			$ids = array_column($pages, 'ID');
			$parent_key = array_search($parent, $ids);
			if ($parent_key !== false && is_int($parent_key)) {
				if (!empty($arr)) {
					$pages[$parent_key]['children'][] = $arr;
				}
				$parent_found = $pages[$parent_key]['ID'];
			}
			else {
				foreach ($pages as $key => &$value) {
					if (empty($value['children'])) {
						continue;
					}
					if ($parent_found = $add_arr_to_parent($value['children'], $parent, $arr)) {
						break;
					}
				}
			}
			return $parent_found;
		};

		if (get_option('show_on_front') == 'page') {
			$front = get_option('page_on_front');
			$page_structure[] = array('ID' => $front, 'title' => get_post_field('post_title', $front, 'raw'), 'children' => array());
			$page_structure_flat[] = get_option('page_on_front');
		}

		$theme_menus = get_nav_menu_locations();
		$other_menus = get_terms(array(
			'taxonomy' => 'nav_menu',
			'orderby' => 'name',
			'fields' => 'ids',
			'exclude' => $theme_menus,
		));
		$menus = array_merge((array)$theme_menus, (array)$other_menus);
		if (!empty($menus)) {
			foreach ($menus as $menu) {
			    if ($items = wp_get_nav_menu_items($menu)) {
			    	foreach ($items as $item) {
						if ($item->object != 'page') {
							continue;
						}
						if (in_array($item->object_id, $page_structure_flat)) {
							continue;
						}
						$title = $item->post_title;
						if (empty($title)) {
							$title = $item->title;
						}
						$arr = array('ID' => $item->object_id, 'title' => $title, 'children' => array());
						if ($item->menu_item_parent == 0) {
							$page_structure[] = $arr;
							$page_structure_flat[] = $item->object_id;
						}
						else {
							$parent = $item->menu_item_parent;
							$parent_object = get_post_meta($parent, '_menu_item_object', true);
							// maybe parent is not a page, find next parent until page or top
							while ($parent_object !== 'page') {
								$parent = (int)get_post_meta($parent, '_menu_item_menu_item_parent', true);
								if ($parent == 0) {
									$parent_object = 'page';
								}
								else {
									$parent_object = get_post_meta($parent, '_menu_item_object', true);
								}
							}
							if ($parent == 0) {
								$page_structure[] = $arr;
								$page_structure_flat[] = $item->object_id;
							}
							else {
								$parent_id = get_post_meta($parent, '_menu_item_object_id', true);
								// find existing parent
								if ($add_arr_to_parent($page_structure, $parent_id, $arr)) {
									$page_structure_flat[] = $item->object_id;
								}
							}
						}
			    	}
			    }
			}
		}

		$posts = get_posts(array(
			'no_found_rows' => true,
			'nopaging' => true,
			'ignore_sticky_posts' => true,
			'exclude' => $page_structure_flat,
			'post_status' => 'all',
			'post_type' => 'page',
			'orderby' => 'menu_order,post_title',
			'order' => 'ASC',
        ));
        if (!empty($posts)) {
        	foreach ($posts as $item) {
				if (in_array($item->ID, $page_structure_flat)) {
					continue;
				}
        		$arr = array('ID' => $item->ID, 'title' => $item->post_title, 'children' => array());
				if ($item->post_parent == 0) {
					$page_structure[] = $arr;
					$page_structure_flat[] = $item->ID;
				}
				elseif ($add_arr_to_parent($page_structure, $item->post_parent, $arr)) {
					$page_structure_flat[] = $item->ID;
				}
				else {
					$page_structure[] = $arr;
					$page_structure_flat[] = $item->ID;
				}
        	}
        }

        if (empty($page_structure)) {
        	return;
        }
        $update_posts = function($arr, $parent = 0) use (&$update_posts) {
	        foreach ($arr as $key => $value) {
				$old = get_post($value['ID'], ARRAY_A);
				if (!$old) {
					continue;
				}
				$new = array(
					'ID' => $value['ID'],
					'post_parent' => $parent,
					'menu_order' => $key,
				);
		        // only update the page if something has changed
				if ($new['menu_order'] !== $old['menu_order'] || $new['post_parent'] !== $old['post_parent']) {
					wp_update_post($new);
				}
				if (!empty($value['children'])) {
					$update_posts($value['children'], $value['ID']);
				}
	        }
        };
        $update_posts($page_structure);
	}

	public function heartbeat_settings($settings) {
		$settings['interval'] = 120;
		return $settings;
	}

	/* functions */
	
}
endif;
?>