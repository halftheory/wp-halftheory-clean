<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Helper_Admin')) :
class Halftheory_Helper_Admin {

	public static $current_screen_id;
	public $wp_before_admin_bar_render_remove = array();
	public $admin_menu_include = array();
	public $admin_menu_exclude = array();
	public $screen_options_show_screen = false;

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
		// screen_options_show_screen
		if (is_super_admin()) {
			$this->screen_options_show_screen = true;
		}
		elseif (is_array($current_user->roles)) {
			if (in_array('administrator', $current_user->roles)) {
				$this->screen_options_show_screen = true;
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

		add_action('admin_menu', array($this,'admin_menu'), (9553+10));
		add_action('current_screen', array($this,'current_screen'));
		add_action('wp_update_nav_menu', array($this,'wp_update_nav_menu'), 20, 2);
		add_filter('heartbeat_settings', array($this,'heartbeat_settings'));
    	// regenerate_images
		if (class_exists('Halftheory_Clean')) {
			if (apply_filters(Halftheory_Clean::$prefix.'_image_size_actions', true)) {
	    		add_filter('media_row_actions', array($this,'media_row_actions'), 20, 3);
	    		add_action('post_action_regenerate_images', array($this,'post_action_regenerate_images'));
	    	}
	    }
		// disable screen options
		if (!$this->screen_options_show_screen) {
			add_filter('screen_options_show_screen', '__return_false', 100);
		}
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
		if (empty($this->wp_before_admin_bar_render_remove)) {
			return;
		}
		// remove stuff
		global $wp_admin_bar;
		foreach ($this->wp_before_admin_bar_render_remove as $value) {
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
		if (!isset($GLOBALS['menu'])) {
			return;
		}
		if (!is_array($GLOBALS['menu'])) {
			return;
		}
		$has_submenu = true;
		if (!isset($GLOBALS['submenu'])) {
			$has_submenu = false;
		}
		elseif (!is_array($GLOBALS['submenu'])) {
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
		static::$current_screen_id = $current_screen->id;
	}

	public function wp_update_nav_menu($menu_id, $menu_data = null) {
		if (!in_array(static::$current_screen_id, array('nav-menus'))) {
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
        if (!empty($posts) && !is_wp_error($posts)) {
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
					wp_update_post(wp_slash($new));
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

	public function media_row_actions($actions = array(), $post = 0, $detached = false) {
		if (!wp_attachment_is_image($post)) {
			return $actions;
		}
		$actions['regenerate_images'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			wp_nonce_url("post.php?action=regenerate_images&amp;post=$post->ID", 'regenerate-images-post_'.$post->ID),
			esc_attr( sprintf( __('Regenerate images for &#8220;%s&#8221;'), $post->post_title) ),
			__('Regenerate Images')
		);
		return $actions;
	}
	public function post_action_regenerate_images($post_id = 0) {
		check_admin_referer('regenerate-images-post_'.$post_id);
		global $post_type, $post_type_object, $post;
		if ($post_id) {
			$post = get_post($post_id);
		}
		if (is_object($post)) {
			$post_type = $post->post_type;
			$post_type_object = get_post_type_object($post_type);
		}
		if (!$post) {
			wp_die( __('The item you are trying to edit no longer exists.') );
		}
		if (!$post_type_object) {
			wp_die( __('Invalid post type.') );
		}
		if (!current_user_can('edit_post', $post->ID)) {
			wp_die( __('Sorry, you are not allowed to edit this item.') );
		}
		$args = array(
			'return_if_size_already_deleted' => false,
			'delete_unregistered_thumbnail_files' => true,
		);
		if (!$this->regenerate_images($post->ID, $args)) {
			wp_die( __('Regenerate images failed.') );
		}
		wp_redirect( add_query_arg('posted', 1, admin_url('upload.php')) );
		exit();
	}

	/* functions */

	public function wp_filesystem() {
		global $wp_filesystem;
		if (!$wp_filesystem || !is_object($wp_filesystem)) {
			if (!function_exists('WP_Filesystem')) {
				require_once(ABSPATH.'wp-admin/includes/file.php');
			}
			$fs = WP_Filesystem();
			if ($fs === false) {
				return false;
			}
			if (!$wp_filesystem || !is_object($wp_filesystem)) {
				return false;
			}
		}
		return $wp_filesystem;
	}

	public function regenerate_images($id, $args = array()) {
		if (!wp_attachment_is_image($id)) {
			return false;
		}
		if (function_exists('wp_get_original_image_path')) {
			$fullsizepath = wp_get_original_image_path($id);
		}
		else {
			$fullsizepath = get_attached_file($id);
		}
		if ($fullsizepath === false || !file_exists($fullsizepath)) {
			return $fullsizepath;
		}

		$args = wp_parse_args($args, array(
			'return_if_size_already_deleted' => 'medium_large',
			'delete_unregistered_thumbnail_files' => true,
		));

		$old_metadata = wp_get_attachment_metadata($id);

		if ($args['return_if_size_already_deleted'] && !empty($args['return_if_size_already_deleted'])) {
			if (!isset($old_metadata['sizes'][$args['return_if_size_already_deleted']])) {
				return $old_metadata;
			}
		}

		$wp_filesystem = $this->wp_filesystem();
		if (!$wp_filesystem) {
			return false;
		}

		if (!function_exists('wp_generate_attachment_metadata')) {
			require_once(ABSPATH.'wp-admin/includes/admin.php');
		}
		$new_metadata = wp_generate_attachment_metadata($id, $fullsizepath);

		if ($args['delete_unregistered_thumbnail_files']) {
			// Delete old sizes that are still in the metadata.
			$intermediate_image_sizes = get_intermediate_image_sizes();
			foreach ($old_metadata['sizes'] as $old_size => $old_size_data) {
				if (in_array($old_size, $intermediate_image_sizes)) {
					continue;
				}
				if ($wp_filesystem->delete(path_join(dirname($fullsizepath), $old_size_data['file']))) {
					if (array_key_exists($old_size, $new_metadata['sizes'])) {
						unset($new_metadata['sizes'][$old_size]);
					}
				}
			}
		}

		wp_update_attachment_metadata($id, $new_metadata);
		return $new_metadata;
	}

	public function regenerate_all_images($args = array()) {
		$query_args = array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'nopaging' => true,
			'ignore_sticky_posts' => true,
			'orderby' => 'modified',
			'suppress_filters' => false,
		);
		$posts = get_posts($query_args);
		if (empty($posts) || is_wp_error($posts)) {
			return false;
		}
		foreach ($posts as $post) {
			$this->regenerate_images($post->ID, $args);
		}
		// will probably time out
		return true;
	}

	public function attach_thumbnails_to_posts() {
		/*
		SELECT m.post_id AS postid, m.meta_value AS thumbnailid, p.post_parent AS postparent FROM wp_postmeta m
		RIGHT JOIN wp_posts p ON m.meta_value = p.ID
		WHERE m.meta_key = '_thumbnail_id'
		AND p.post_parent = 0
		AND (p.post_status = 'inherit' OR p.post_status = 'publish')
		AND p.post_type = 'attachment'

		SELECT m.post_id AS postid, m.meta_value AS thumbnailid, p.post_parent AS postparent FROM wp_postmeta m, wp_posts p
		WHERE m.meta_key = '_thumbnail_id'
		AND m.meta_value = p.ID
		AND p.post_parent = 0
		AND (p.post_status = 'inherit' OR p.post_status = 'publish')
		AND p.post_type = 'attachment'

		UPDATE wp_posts p, wp_postmeta m SET p.post_parent = m.post_id
		WHERE m.meta_key = '_thumbnail_id'
		AND m.meta_value = p.ID
		AND p.post_parent = 0
		AND (p.post_status = 'inherit' OR p.post_status = 'publish')
		AND p.post_type = 'attachment'
		*/

		// only for published posts
		$args = array(
			'post_type' => 'any',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'nopaging' => true,
			'ignore_sticky_posts' => true,
			'orderby' => 'modified',
			'suppress_filters' => false,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_thumbnail_id',
					'compare' => 'EXISTS',
				)
			)
		);
		$posts = get_posts($args);
		if (empty($posts) || is_wp_error($posts)) {
			return false;
		}
		global $wpdb;
		$sql = "UPDATE $wpdb->posts p, $wpdb->postmeta m
			SET p.post_parent = m.post_id
			WHERE m.meta_key = '_thumbnail_id'
			AND m.meta_value = p.ID
			AND m.post_id IN (".implode(",", $posts).")
			AND p.post_parent = 0
			AND (p.post_status = 'inherit' OR p.post_status = 'publish')
			AND p.post_type = 'attachment'";
		$wpdb->query($sql);
		return true;
	}

}
endif;
?>