<?php
namespace Halftheory\Themes\Halftheory_Clean;

use Halftheory\Lib\Filters;

#[AllowDynamicProperties]
class Halftheory_Clean_Filters extends Filters {

	public static $handle;
	protected static $instance;
	protected $data = array();

	protected static $filters = array();

	public function __construct( $autoload = false ) {
		$this->load_functions('wp');
		parent::__construct($autoload);
	}

	protected function autoload() {
		// Global.
		add_action('after_setup_theme', array( $this, 'global_after_setup_theme' ), 20);
		add_action('init', array( $this, 'global_init' ), 20);
		add_filter('posts_orderby', array( $this, 'global_posts_orderby' ), 20, 2);
		if ( is_public() ) {
			// Public.
			add_filter('request', array( $this, 'public_request' ), 20);
			add_action('pre_get_posts', array( $this, 'public_pre_get_posts' ), 20);
			add_action('wp', array( $this, 'public_wp' ), 9);
			add_action('template_redirect', array( $this, 'public_template_redirect' ), 20);
			add_filter('wp_default_scripts', array( $this, 'public_wp_default_scripts' ));
			add_action('wp_enqueue_scripts', array( $this, 'public_wp_enqueue_scripts' ), 20);
			add_filter('script_loader_src', array( $this, 'public_script_loader_src' ), 90, 2);
			add_filter('style_loader_src', array( $this, 'public_script_loader_src' ), 90, 2);
			add_filter('wp_title', array( $this, 'public_wp_title' ), 9, 3);
			add_action('wp_head', array( $this, 'public_wp_head' ), 20);
			add_filter('get_site_icon_url', array( $this, 'public_get_site_icon_url' ), 10, 3);
			add_filter('body_class', array( $this, 'public_body_class' ), 20, 2);
			add_filter('wp_nav_menu_objects', array( $this, 'public_wp_nav_menu_objects' ), 20, 2);
			add_filter('get_the_archive_title_prefix', array( $this, 'public_get_the_archive_title_prefix' ));
			add_filter('get_the_archive_title', array( $this, 'public_get_the_archive_title' ), 20, 3);
			add_filter('private_title_format', array( $this, 'public_private_title_format' ));
			add_filter('protected_title_format', array( $this, 'public_private_title_format' ));
			add_filter('post_class', array( $this, 'public_post_class' ), 20, 3);
			add_filter('the_content', array( $this, 'public_the_content' ), 9);
			add_filter('get_the_excerpt', array( $this, 'public_get_the_excerpt' ), 9, 2);
			add_filter('safe_style_css', array( $this, 'public_safe_style_css' ), 20);
			add_filter('embed_oembed_html', array( $this, 'public_embed_oembed_html' ), 10, 4);
			add_action('pre_ping', array( $this, 'public_pre_ping' ));
		} else {
			// Admin.
			add_action('after_switch_theme', array( $this, 'admin_after_switch_theme_update_options' ), 10, 2);
			add_action('rewrite_rules_array', array( $this, 'admin_rewrite_rules_array' ), 20);
		}
		parent::autoload();
	}

	// Global.

	public function global_after_setup_theme() {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		add_filter('admin_email_check_interval', '__return_zero');
		add_filter('allow_dev_auto_core_updates', '__return_false');
		add_filter('allow_major_auto_core_updates', '__return_false');
		add_filter('allow_minor_auto_core_updates', '__return_false');
		add_filter('automatic_updater_disabled', '__return_true');
		if ( ! is_development() ) {
			add_filter('deprecated_argument_trigger_error', '__return_false');
			add_filter('deprecated_file_trigger_error', '__return_false');
			add_filter('deprecated_function_trigger_error', '__return_false');
			add_filter('deprecated_hook_trigger_error', '__return_false');
		}
		add_filter('embed_oembed_discover', '__return_false');
		add_filter('pings_open', '__return_false');
		add_filter('xmlrpc_enabled', '__return_false');

		// Only when active.
		if ( isset($GLOBALS['Halftheory_Clean_Theme']) && $GLOBALS['Halftheory_Clean_Theme']->is_theme_active() ) {
			// Menus.
			register_nav_menus(
				array(
					'primary' => __('Primary Menu'),
					'footer' => __('Footer Menu'),
				)
			);
			// Options.
			add_theme_support('align-wide');
			add_theme_support('editor-styles');
			add_theme_support('html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ));
			add_theme_support('post-thumbnails', array( 'post', 'page' ));
		}
	}

	public function global_init() {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		foreach ( get_post_types(array( 'public' => true ), 'names') as $value ) {
			remove_post_type_support($value, 'trackbacks');
		}
	}

	public function global_posts_orderby( $orderby, $query ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $orderby;
		}
		// Sorting by post_date is unreliable when posts have the same post_date, so we also sort by ID. Helpful for adjacent posts.
		global $wpdb;
		if ( preg_match_all('/^' . $wpdb->posts . '\.(post_date|post_date_gmt|post_modified|post_modified_gmt) (DESC|ASC)$/i', $orderby, $matches, PREG_SET_ORDER) ) {
			$order_id = strtoupper(trim($matches[0][2])) === 'DESC' ? 'ASC' : 'DESC';
			$orderby .= ", $wpdb->posts.ID $order_id";
		}
		return $orderby;
	}

	// Public.

	public function public_request( $query_vars = array() ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $query_vars;
		}
		// Skip targeted queries.
		$array = array(
			'p',
			'subpost_id',
			'attachment_id',
			'pagename',
			'page_id',
			'tag_id',
			'category__in',
			'post__in',
			'post_name__in',
			'tag__in',
			'tag_slug__in',
			'author__in',
			'year',
			'monthnum',
			'w',
			'day',
			'hour',
			'minute',
			'second',
			'm',
			'date_query',
		);
		foreach ( $array as $value ) {
			if ( array_key_exists($value, $query_vars) ) {
				return $query_vars;
			}
		}
		// Search.
		if ( isset($query_vars['s']) ) {
			// Clean the search string.
			$query_vars['s'] = trim(str_replace('%20', ' ', $query_vars['s']));
			// Add most public post types.
			if ( ! array_key_exists('post_type', $query_vars) ) {
				$tmp = array_diff(get_post_types(array( 'public' => true ), 'names'), array( 'attachment', 'revision' ));
				$query_vars['post_type'] = array_values($tmp);
			}
		}
		return $query_vars;
	}

	public function public_pre_get_posts( $query ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			 return;
		}
		// Skip targeted queries.
		$array = array(
			'p',
			'subpost_id',
			'attachment_id',
			'pagename',
			'page_id',
			'tag_id',
			'category__in',
			'post__in',
			'post_name__in',
			'tag__in',
			'tag_slug__in',
			'author__in',
			'year',
			'monthnum',
			'w',
			'day',
			'hour',
			'minute',
			'second',
			'm',
			'date_query',
		);
		foreach ( $array as $value ) {
			if ( ! empty($query->get($value)) ) {
				return;
			}
		}
		// Main query.
		if ( $query->is_main_query() ) {
			// Add taxonomy.
			if ( empty($query->get('taxonomy')) ) {
				$queried_object = get_queried_object();
				if ( is_a($queried_object, 'WP_Term') ) {
					$query->set('taxonomy', $queried_object->taxonomy);
				}
			}
			// Add post_type.
			if ( empty($query->get('post_type')) ) {
				$array = array();
				if ( $tmp = get_taxonomy_objects($query->get('taxonomy')) ) {
					// Include the taxonomy objects.
					$array = $tmp;
				} else {
					// Most public post types.
					$array = array_value_unset(get_post_types(array( 'public' => true ), 'names'), 'attachment');
				}
				$query->set('post_type', array_values($array));
			}
		}
		// Add post_status for attachments.
		if ( in_array('attachment', make_array($query->get('post_type'))) ) {
			$tmp = make_array($query->get('post_status'));
			$tmp = array_value_unset($tmp, 'any');
			$tmp = array_values(array_unique(array_merge($tmp, array( 'publish', 'inherit' ))));
			$query->set('post_status', $tmp);
		}
	}

	public function public_wp( $wp ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
		remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
		remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
		remove_action('wp_head', 'feed_links_extra', 3);
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'wp_no_robots');
		remove_action('wp_head', 'wp_resource_hints', 2);
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');
		remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
		remove_action('wp_head', 'rest_output_link_wp_head', 10, 0);
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_scripts-index.php', 'wp_localize_community_events');
	}

	public function public_template_redirect() {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		// Fix search URLs.
		if ( is_search() && isset($_GET['s']) ) {
			if ( ! empty($_GET['s']) ) {
				$search_slug = rawurlencode(sanitize_text_field(get_search_query()));
				$replace_pairs = array(
					'%2F' => '/',
					'%2C' => ',',
					'%c2%a0' => ' ',
					'%C2%A0' => ' ',
					'%e2%80%93' => '-',
					'%E2%80%93' => '-',
					'%e2%80%94' => '-',
					'%E2%80%94' => '-',
					'&nbsp;' => ' ',
					'&#160;' => ' ',
					'&ndash;' => '-',
					'&#8211;' => '-',
					'&mdash;' => '-',
					'&#8212;' => '-',
				);
				$search_slug = strtr($search_slug, $replace_pairs);
				$url = home_url('/' . sanitize_title(__('Search')) . '/') . $search_slug;
			} else {
				$url = home_url();
			}
			if ( ht_wp_redirect($url) ) {
				exit;
			}
		}
	}

	public function public_wp_default_scripts( &$wp_scripts ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		// remove jquery migrate.
		$wp_scripts->remove('jquery');
		$wp_scripts->remove('jquery-core');
		$wp_scripts->add('jquery-core', '/wp-includes/js/jquery/jquery' . min_scripts() . '.js', array(), '3.7.1');
		$wp_scripts->add('jquery', false, array( 'jquery-core' ), '3.7.1');
	}

	public function public_wp_enqueue_scripts() {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		wp_deregister_script('wp-embed');

		// Only when active.
		if ( isset($GLOBALS['Halftheory_Clean_Theme']) && $GLOBALS['Halftheory_Clean_Theme']->is_theme_active() ) {
			// CSS.
			wp_enqueue_style('dashicons');
			wp_enqueue_style($GLOBALS['Halftheory_Clean_Theme']::$handle, get_stylesheet_directory_uri() . '/assets/css/style.css', array( 'dashicons' ), get_file_version(get_stylesheet_directory() . '/assets/css/style.css'));
			// JS.
			wp_enqueue_script($GLOBALS['Halftheory_Clean_Theme']::$handle, get_stylesheet_directory_uri() . '/assets/js/main.min.js', array( 'jquery' ), get_file_version(get_stylesheet_directory() . '/assets/js/main.min.js'), true);
		}
	}

	public function public_script_loader_src( $src, $handle ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $src;
		}
		if ( is_development() ) {
			return $src;
		}
		if ( strpos( $src, '?ver=' ) !== false || strpos( $src, '&ver=' ) !== false ) {
			$src = remove_query_arg('ver', $src);
		}
		return $src;
	}

	public function public_wp_title( $title, $sep, $seplocation ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $title;
		}
		remove_filter('wp_title', 'wptexturize');
		if ( is_feed() || did_action('get_header') === 0 ) {
			return $title;
		}
		$current_ancestors = current_ancestors();
		if ( empty($current_ancestors) ) {
			return $title;
		}
		$array = array();
		array_pop($current_ancestors);
		foreach ( $current_ancestors as $value ) {
			$array[] = implode(' : ', array_filter(array( $value['prepend'], $value['title'], $value['append'] )));
		}
		if ( ! empty($array) ) {
			$sep = ' ' . trim($sep) . ' ';
			if ( $seplocation === 'right' ) {
				$title = implode($sep, array_unique($array)) . $sep;
			} else {
				$array = array_reverse($array);
				$title = $sep . implode($sep, array_unique($array));
			}
		}
		return $title;
	}

	public function public_wp_head() {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		if ( is_feed() || did_action('get_header') === 0 ) {
			return;
		}
		$current_ancestors = current_ancestors();
		if ( empty($current_ancestors) ) {
			return;
		}
		$wp_title = wp_title('-', false);

		// Schema.org.
		$itemprop = array(
			'name' => get_bloginfo('name') . $wp_title,
			'description' => '',
			'url' => get_current_url(),
			'image' => '',
		);

		// Open Graph.
		$og = array(
			'type' => 'article',
			'site_name' => get_bloginfo('name'),
			'title' => trim($wp_title, ' -'),
			'description' => '',
			'url' => get_current_url(),
			'image' => '',
		);

		// Keywords.
		$keywords = get_keywords($current_ancestors);
		if ( ! empty($keywords) ) {
			echo '<meta name="keywords" content="' . esc_attr(implode(', ', $keywords)) . '" />' . "\n";
		}

		// Description.
		$description = null;
		foreach ( $current_ancestors as $value ) {
			if ( ! $value['resource_type'] ) {
				continue;
			}
			switch ( $value['resource_type'] ) {
				case 'post_type':
					$tmp = wp_strip_all_tags(the_excerpt_fallback(get_the_excerpt($value['object_id']), $value['object_id']), true);
					if ( ! empty($tmp) ) {
						$description = $tmp;
					}
					break;
				case 'taxonomy':
					$tmp = wp_strip_all_tags(term_description($value['object_id']), true);
					if ( ! empty($tmp) ) {
						$description = $tmp;
					}
					break;
				default:
					break;
			}
			if ( $description ) {
				break;
			}
		}
		if ( empty($description) && ht_is_front_page() ) {
			$description = get_bloginfo('description');
		}
		if ( ! empty($description) ) {
			$args = array(
				'append' => array(
					'short' => '',
					'long' => '',
					'always' => '',
				),
				'html' => false,
				'remove' => array(
					'breaks' => true,
					'email' => true,
					'url' => true,
				),
			);
			$description = get_excerpt($description, 500, $args);
			$description = wp_trim_words($description, 25);
			echo '<meta name="description" content="' . esc_attr($description) . '" />' . "\n";
			$og['description'] = $itemprop['description'] = $description;
		}

		// Thumbnail.
		$thumbnail_id = null;
		$fallback_args = array(
			'search' => array(
				'attached_media' => true,
				'gallery' => true,
				'content' => false,
				'parent' => true,
				'logo' => true,
			),
		);
		foreach ( $current_ancestors as $value ) {
			if ( ! $value['resource_type'] ) {
				continue;
			}
			switch ( $value['resource_type'] ) {
				case 'post_type':
					if ( $tmp = get_post_thumbnail_context('id', $value['object_id'], 'medium', array(), $fallback_args) ) {
						$thumbnail_id = $tmp;
					}
					break;
				case 'taxonomy':
					if ( function_exists('get_term_thumbnail_id') ) {
						if ( $tmp = get_term_thumbnail_id($value['object_id']) ) {
							$thumbnail_id = $tmp;
						}
					}
					break;
				default:
					break;
			}
			if ( $thumbnail_id ) {
				break;
			}
		}
		if ( ! empty($thumbnail_id) ) {
			if ( $url = get_image_context('url', $thumbnail_id, 'medium') ) {
				echo '<link rel="image_src" href="' . esc_url($url) . '" />' . "\n";
				$og['image'] = $itemprop['image'] = $url;
				if ( $tmp = get_image_info($thumbnail_id, 'medium') ) {
					$og['image:width'] = $tmp['width'];
					$og['image:height'] = $tmp['height'];
				}
			}
		}

		// Schema.org.
		foreach ( array_filter($itemprop) as $key => $value ) {
			if ( $key === 'image' ) {
				echo '<link itemprop="' . esc_attr($key) . '" href="' . esc_url($value) . '" />' . "\n";
				continue;
			}
			if ( str_starts_with($value, 'http') ) {
				echo '<meta itemprop="' . esc_attr($key) . '" content="' . esc_url($value) . '" />' . "\n";
				continue;
			}
			echo '<meta itemprop="' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
		}

		// Open Graph.
		foreach ( array_filter($og) as $key => $value ) {
			if ( str_starts_with($value, 'http') ) {
				echo '<meta property="og:' . esc_attr($key) . '" content="' . esc_url($value) . '" />' . "\n";
				continue;
			}
			echo '<meta property="og:' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
		}
	}

	public function public_get_site_icon_url( $url, $size = 512, $blog_id = 0 ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $url;
		}
		if ( str_contains($url, 'images/w-logo-blue-white-bg.png') ) {
			$url = null;
		}
		return $url ? set_url_scheme($url) : $url;
	}

	public function public_body_class( $classes = array(), $css_class = array() ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $classes;
		}
		// Prefixes to remove.
		$remove = array(
			'attachment-',
			'attachmentid-',
			'author-',
			'category-',
			'page-',
			'paged-',
			'parent-pageid-',
			'post-',
			'postid-',
			'search-',
			'single-',
			'tag-',
			'tax-',
			'term-',
			'theme-',
			'wp-',
		);
		foreach ( $classes as $key => $value ) {
			foreach ( $remove as $r ) {
				if ( str_starts_with($value, $r) ) {
					unset($classes[ $key ]);
					break;
				}
			}
		}
		return $classes;
	}

	public function public_wp_nav_menu_objects( $sorted_menu_items, $args ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $sorted_menu_items;
		}
		if ( empty($sorted_menu_items) ) {
			return $sorted_menu_items;
		}
		// Fix bug in wp-includes/nav-menu-template.php.
		// "Back-compat with wp_page_menu(): add "current_page_parent" to static home page link for any non-page query."
		// Actually adds unnecessary class to tax, custom post types, etc.
		global $wp_query;
		if ( isset($wp_query->is_page) && $wp_query->is_page ) {
			return $sorted_menu_items;
		}
		$front_page = get_post_front_page();
		if ( ! $front_page ) {
			return $sorted_menu_items;
		}
		$is_cpt = false;
		if ( $post_type = ht_get_post_type(ht_get_the_ID()) ) {
			$is_cpt = ! in_array($post_type, get_post_types(array( 'public' => true, '_builtin' => true ), 'names'), true);
		}
		foreach ( $sorted_menu_items as $key => &$menu_item ) {
			if ( property_exists($menu_item, 'classes') && is_array($menu_item->classes) && in_array('current_page_parent', $menu_item->classes, true) ) {
				if ( property_exists($menu_item, 'object_id') && (int) $front_page->ID === (int) $menu_item->object_id ) {
					if ( is_tax() || is_tag() || is_category() || $is_cpt ) {
						$menu_item->classes = array_values(array_value_unset($menu_item->classes, 'current_page_parent'));
					}
				}
			}
		}
		return $sorted_menu_items;
	}

	public function public_get_the_archive_title_prefix( $prefix ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $prefix;
		}
		return null;
	}

	public function public_get_the_archive_title( $title, $original_title, $prefix ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $title;
		}
		if ( $title === __('Archives') ) {
			if ( is_posts_page() ) {
				if ( $tmp = get_post_posts_page() ) {
					$title = get_the_title($tmp);
				}
			}
		}
		return $title;
	}

	public function public_private_title_format( $prepend, $post = 0 ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $prepend;
		}
		return '%s';
	}

	public function public_post_class( $classes = array(), $css_class = array(), $post_id = 0 ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $classes;
		}
		// prefixes to remove.
		$remove = array(
			'category-',
			'hentry',
			'page-',
			'post-',
			'status-',
			'tag-',
			'tax-',
			'term-',
			'type-',
		);
		foreach ( $classes as $key => $value ) {
			foreach ( $remove as $r ) {
				if ( str_starts_with($value, $r) ) {
					unset($classes[ $key ]);
					break;
				}
			}
		}
		return $classes;
	}

	public function public_the_content( $content = '' ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $content;
		}
		remove_filter('the_content', 'prepend_attachment');
		remove_filter('the_content', 'convert_smilies', 20);
		return $content;
	}

	public function public_get_the_excerpt( $excerpt = '', $post = null ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $excerpt;
		}
		remove_filter('get_the_excerpt', 'wp_trim_excerpt');
		$excerpt = excerpt_remove_footnotes($excerpt);
		return $excerpt;
	}

	public function public_safe_style_css( $attr = array() ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $attr;
		}
		if ( ! in_array('display', $attr) ) {
			$attr[] = 'display';
		}
		return $attr;
	}

	public function public_embed_oembed_html( $cache = '', $url = '', $attr = array(), $post_id = 0 ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $cache;
		}
		if ( empty($cache) ) {
			return $cache;
		}
		// Skip embeds for non-trusted providers (e.g. wordpress blogs).
		$oembed = _wp_oembed_get_object();
		if ( ! empty($oembed) && is_object($oembed) ) {
			if ( $oembed->get_provider($url, array( 'discover' => false )) === false ) {
				return $url;
			}
		}
		return $cache;
	}

	public function public_pre_ping( &$post_links ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		if ( empty($post_links) ) {
			return;
		}
		// No self ping.
		$home = str_replace(array( 'https:', 'http:' ), '', home_url());
		foreach ( $post_links as $key => $link ) {
			if ( strpos($link, $home) !== false ) {
				unset($post_links[ $key ]);
			}
		}
	}

	// Admin.

	public function admin_after_switch_theme_update_options( $old_theme_name = false, $old_theme_class = false ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return;
		}
		$defaults = array(
			'default_pingback_flag' => '',
			'default_ping_status' => 'closed',
		);
		foreach ( $defaults as $key => $value ) {
			update_option($key, $value);
		}
		flush_rewrite_rules(false);
	}

	public function admin_rewrite_rules_array( $rules ) {
		if ( ! $this->is_filter_active(__FUNCTION__) ) {
			return $rules;
		}
		global $wp_rewrite;
		if ( ! is_object($wp_rewrite) ) {
			return $rules;
		}
		$remove_endpoints = array(
			'trackback/?$',
			'embed/?$',
		);
		foreach ( $rules as $key => $value ) {
			$remove = false;
			foreach ( $remove_endpoints as $point ) {
				if ( strpos($key, $point) !== false ) {
					$remove = true;
					break;
				}
			}
			if ( $remove ) {
				unset($rules[ $key ]);
				continue;
			}
		}
		return $rules;
	}
}
