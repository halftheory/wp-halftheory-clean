<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Clean')) :
class Halftheory_Clean {

	public static $prefix = 'halftheoryclean';
	public static $plugins = array();
	public $admin = null;

	public function __construct($load_theme = false) {
		$this->setup_globals();
		if ($load_theme) {
			$this->setup_plugins();
			$this->setup_actions();
		}
	}

	protected function setup_globals() {
		$this->plugin_name = get_called_class();
		$this->plugin_title = ucwords(str_replace('_', ' ', $this->plugin_name));
		static::$prefix = sanitize_key($this->plugin_name);
		static::$prefix = preg_replace("/[^a-z0-9]/", "", static::$prefix);
	}

	protected function setup_plugins() {
		// only do this once
		if (!empty(static::$plugins)) {
			return;
		}
		$active_plugins = wp_get_active_and_valid_plugins();
		if (is_multisite()) {
			$active_sitewide_plugins = wp_get_active_network_plugins();
			$active_plugins = array_merge($active_plugins, $active_sitewide_plugins);
		}
		if (empty($active_plugins)) {
			return;
		}
		$func = function($plugin) {
			return str_replace(WP_PLUGIN_DIR.'/', '', $plugin);
		};
		$active_plugins = array_map($func, $active_plugins);
		foreach ($active_plugins as $key => $value) {
			if (isset(static::$plugins[$value])) {
				continue;
			}
			$plugin = false;
			if (is_child_theme() && is_readable(get_stylesheet_directory().'/app/plugins/'.$value)) {
				@include_once(get_stylesheet_directory().'/app/plugins/'.$value);
			}
			elseif (is_readable(__DIR__.'/plugins/'.$value)) {
				@include_once(__DIR__.'/plugins/'.$value);
			}
			if ($plugin && class_exists($plugin, false)) {
				static::$plugins[$value] = new $plugin();
				unset($plugin);
			}
		}
	}

	protected function setup_actions() {
		add_action('after_switch_theme', array($this, 'activation'), 10, 2);
		add_action('switch_theme', array($this, 'deactivation'), 10, 3);

		add_action('after_setup_theme', array($this, 'after_setup_theme'), 20);
		add_action('rewrite_rules_array', array($this, 'rewrite_rules_array'), 20);
		add_action('init', array($this, 'init'), 20);
		add_action('widgets_init', array($this, 'widgets_init_remove_recent_comments'), 20);
		add_action('widgets_init', array($this, 'widgets_init'), 20);
		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'), 20);
		add_filter('wp_default_scripts', array($this, 'wp_default_scripts_remove_jquery_migrate'));

		add_action('wp', function() {
			remove_action('wp_head', 'feed_links_extra', 3);
			remove_action('wp_head', 'rsd_link');
			remove_action('wp_head', 'wlwmanifest_link');
			remove_action('wp_head', 'print_emoji_detection_script', 7);
			remove_action('wp_head', 'wp_generator');
			remove_action('wp_head', 'wp_no_robots');
			remove_action('wp_head', 'wp_resource_hints', 2);
			remove_action('wp_head', 'wp_oembed_add_discovery_links');
			remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
			remove_action('wp_head', 'rest_output_link_wp_head', 10, 0);
			remove_action('wp_print_styles', 'print_emoji_styles');
		}, 9);
		
		add_action('wp_head', array($this, 'wp_head'), 20);
		add_action('wp_head', array($this, 'wp_site_icon'), 100);
		remove_filter('wp_title', 'wptexturize');
		add_filter('wp_title', array($this, 'wp_title'));
		add_filter('protected_title_format', array($this, 'protected_title_format'));
		add_filter('private_title_format', array($this, 'private_title_format'));
		add_filter('get_wp_title_rss', array($this, 'get_wp_title_rss'));
		add_filter('the_author', array($this, 'the_author'));
		add_action('pre_ping', array($this, 'no_self_ping'));
		add_filter('the_content', array($this, 'the_content'), 12); // after autoembeds (priority 7), after shortcodes (priority 11)
		add_filter('get_the_excerpt', array($this, 'get_the_excerpt'));

		add_filter('xmlrpc_enabled', '__return_false');
		add_action('pings_open', '__return_false');
		add_filter('comments_open', '__return_false', 10, 2);
		add_filter('feed_links_show_comments_feed', '__return_false');
		remove_filter('the_content', 'convert_smilies', 20);

		add_filter('automatic_updater_disabled', '__return_true');
	}

	/* helpers for child themes - not in parent __construct */

	protected function setup_admin() {
		if (is_user_logged_in()) {
			if (!class_exists('Halftheory_Helper_Admin')) {
				@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-admin.php');
			}
			if (class_exists('Halftheory_Helper_Admin')) {
				$this->admin = new Halftheory_Helper_Admin();
			}
		}
	}

	protected function setup_helper_infinite_scroll() {
		if (!class_exists('Halftheory_Helper_Infinite_Scroll')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-infinite-scroll.php');
		}
		if (class_exists('Halftheory_Helper_Infinite_Scroll')) {
			$this->infinite_scroll = new Halftheory_Helper_Infinite_Scroll();
		}
	}

	protected function setup_helper_minify() {
		if (!class_exists('Halftheory_Helper_Minify')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-minify.php');
		}
		if (class_exists('Halftheory_Helper_Minify')) {
			$this->minify = new Halftheory_Helper_Minify();
		}
	}
	
	/* install */

	public static function activation($old_theme_name = false, $old_theme_class = false) {
		$defaults = array(
			// General
			'users_can_register' => 0,
			'date_format' => 'j F Y',
			'time_format' => 'g:i A',
			// Discussion
			'default_pingback_flag' => '',
			'default_ping_status' => 'closed',
			'default_comment_status' => 'closed',
			'require_name_email' => 1,
			'comment_registration' => 1,
			'comments_notify' => 1,
			'moderation_notify' => 1,
			'comment_moderation' => 1,
			'comment_whitelist' => 1,
			'show_avatars' => '',
			// Media
			'thumbnail_crop' => 1,
			'thumbnail_size_w' => 300,
			'thumbnail_size_h' => 300,
			'medium_size_w' => 600,
			'medium_size_h' => 600,
			'medium_large_size_w' => 1000,
			'medium_large_size_h' => 1000,
			'large_size_w' => 2000,
			'large_size_h' => 2000,
			'uploads_use_yearmonth_folders' => '',
			// Permalinks
			'permalink_structure' => '/%category%/%postname%/',
		);
		foreach ($defaults as $key => $value) {
			update_option($key, $value);
		}
		flush_rewrite_rules();
	}

	public static function deactivation($new_theme_name = false, $new_theme_class = false, $old_theme_class = false) {
		flush_rewrite_rules();
	}

	/* actions */

	public function after_setup_theme() { // first available action after plugins_loaded
		if (is_front_end()) {
			define('CURRENT_URL', get_current_uri());
			$url = trailingslashit(remove_query_arg(array_keys($_GET), CURRENT_URL));
			define('CURRENT_URL_NOQUERY', $url);
		}
		add_theme_support('automatic-feed-links');
		add_theme_support('post-thumbnails', array('post', 'page'));
		add_theme_support('html5', array('comment-list', 'comment-form', 'search-form', 'gallery', 'caption'));
		add_theme_support('custom-logo', array(
			'height'      => 600,
			'width'       => 600,
			'flex-width' => true,
			'flex-height' => true,
		));
	}

	public function rewrite_rules_array($rules) {
		if (is_front_end()) {
		    return $rules;
		}
	    global $wp_rewrite;
	    $remove_endpoints = array(
	    	'trackback/?$',
	    	'embed/?$',
	    	$wp_rewrite->comments_pagination_base,
	    );
	    $remove_startpoints = array(
	    	$wp_rewrite->author_base,
	    	$wp_rewrite->comments_base,
	    );
	    foreach ($rules as $key => $value) {
	    	$remove = false;
	    	foreach ($remove_endpoints as $point) {
		    	if (strpos($key, $point) !== false) {
		    		$remove = true;
		    		break;
		    	}
	    	}
	    	if ($remove) {
		    	unset($rules[$key]);
		    	continue;
	    	}
	    	foreach ($remove_startpoints as $point) {
		    	if (preg_match("/^".$point."/", $key)) {
		    		$remove = true;
		    		break;
		    	}
		    }
	    	if ($remove) {
		    	unset($rules[$key]);
		    	continue;
	    	}
	    }
		#print_r($rules);
	    return $rules;
	}

	public function init() {
		register_nav_menu('primary-menu', 'Primary Menu');
		remove_post_type_support('attachment', 'comments');
		remove_post_type_support('page', 'comments');
		remove_post_type_support('post', 'comments');
		remove_post_type_support('post', 'trackbacks');
		add_post_type_support('page', 'excerpt');
	}

	public function widgets_init_remove_recent_comments() {
		// remove recent comments widget
		global $wp_widget_factory;
 		remove_action('wp_head', array($wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style'));
		unregister_widget('WP_Widget_Recent_Comments');
	}

	public function widgets_init() {
		register_sidebar(array(
			'name'          => 'Sidebar',
			'id'            => 'sidebar-1',
			'description'   => 'Add widgets here to appear in your sidebar.',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		));

		register_sidebar(array(
			'name'          => 'Content Bottom 1',
			'id'            => 'sidebar-2',
			'description'   => 'Appears at the bottom of the content on posts and pages.',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		));

		register_sidebar(array(
			'name'          => 'Content Bottom 2',
			'id'            => 'sidebar-3',
			'description'   => 'Appears at the bottom of the content on posts and pages.',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		));
	}

	public function wp_enqueue_scripts() {
		// slicknav
		if (has_nav_menu('primary-menu')) {
			// header
			wp_enqueue_style('slicknav', get_template_directory_uri().'/assets/js/slicknav/slicknav.min.css', array(), null, 'screen');
			wp_enqueue_style('slicknav-init', get_template_directory_uri().'/assets/js/slicknav/slicknav-init.css', array('slicknav'), null, 'screen');
			// footer
			wp_enqueue_script('slicknav-js', get_template_directory_uri().'/assets/js/slicknav/jquery.slicknav.min.js', array('jquery'), null, true);
			wp_enqueue_script('slicknav-init', get_template_directory_uri().'/assets/js/slicknav/slicknav-init.js', array('jquery', 'slicknav-js'), null, true);
			$data = array(
			    'brand' => '<a href="'.esc_url(network_home_url('/')).'">'.get_bloginfo('name').'</a>'
			);
			wp_localize_script('slicknav-init', 'slicknav', $data);
		}

		// header
		if (is_child_theme()) {
			wp_enqueue_style('parent-style', get_template_directory_uri().'/style.css', array(), filemtime(get_template_directory().'/style.css'));
		}
		wp_enqueue_style('theme-style', get_stylesheet_uri(), array(), filemtime(get_stylesheet_directory().'/style.css'));
		// footer
		wp_deregister_script('wp-embed');
	}
	public function wp_default_scripts_remove_jquery_migrate(&$scripts) {
		if (!is_front_end()) {
			return;
		}
		$scripts->remove('jquery');
		$scripts->add('jquery', false, array('jquery-core'), '1.12.4');
	}

	public function wp_head() {
		if (!is_front_end()) {
			return;
		}

		global $post;
		if (empty($post)) {
			return;
		}

		list($title, $ancestors) = $this->get_title_ancestors('', current_filter());
		$itemprop = array(
			'name' => '',
			'description' => '',
			'image' => '',
		);
		$og = array(
			'title' => '',
			'type' => 'article',
			'url' => esc_url(CURRENT_URL),
			'image' => '',
			'description' => '',
			'site_name' => esc_attr(get_bloginfo('name')),
		);

		// keywords
		$keywords = array_merge(
			$ancestors,
			(array)$title
		);
		$keywords = array_unique($keywords);
		$keywords = array_filter($keywords);
		if (!empty($keywords)) {
			echo '<meta name="keywords" content="'.esc_attr(implode(", ", $keywords)).'" />'."\n";
			$itemprop['name'] = esc_attr(implode(" - ", $keywords));
			$og['title'] = esc_attr(implode(" - ", $keywords));
		}
			
		// description
		$excerpt = '';
		if (is_singular()) {
			$excerpt = get_the_excerpt_filtered($post); // as of May 2018 throws error in PHP7
			if (empty($excerpt) && !empty($post->post_content)) {
				$excerpt = wp_trim_words(get_the_content_filtered($post->post_content));
			}
			if (!empty($excerpt)) {
				$excerpt = strip_all_shortcodes($excerpt);
				$excerpt = sanitize_text_field($excerpt);
				$excerpt = trim_excess_space($excerpt);
			}
		}
		$description = array_merge(
			(array)$excerpt,
			$ancestors,
			(array)$title,
			(array)get_bloginfo('description')
		);
		$trim_stop = function($value) {
		    return trim($value, " .");
		};
		$description = array_map($trim_stop, $description);
		$description = array_unique($description);
		$description = array_filter($description);
		if (!empty($description)) {
			$description = implode(". ", $description);
			$description_trim = wp_trim_words($description, 25);
			// no change
			if ($description == $description_trim) {
				$description .= ".";
			}
			else {
				$description = $description_trim;
			}
			echo '<meta name="description" content="'.esc_attr($description).'" />'."\n";
			$itemprop['description'] = esc_attr($description);
			$og['description'] = esc_attr($description);
		}

		// image_src
		$image_src = get_the_page_thumbnail_src($post->ID, 'medium', true);
		if (!empty($image_src)) {
			echo '<link rel="image_src" href="'.esc_url($image_src[0]).'" />'."\n";
			$itemprop['image'] = esc_url($image_src[0]);
			$og['image'] = esc_url($image_src[0]);
			$og['image:width'] = $image_src[1];
			$og['image:height'] = $image_src[2];
		}

		// Schema.org
		foreach ($itemprop as $key => $value) {
			if (!empty($value)) {
				switch ($key) {
					case 'image':
						echo '<link itemprop href="'.$value.'" />'."\n";
						break;
					
					default:
						echo '<meta itemprop="'.$key.'" content="'.$value.'">'."\n";
						break;
				}
			}
		}

		// Twitter Card
		echo '<meta name="twitter:card" content="summary" />'."\n";

		// Open Graph
		foreach ($og as $key => $value) {
			if (!empty($value)) {
				echo '<meta property="og:'.$key.'" content="'.$value.'" />'."\n";
			}
		}
	}

	public function wp_site_icon() {
		// only if no wp icon. use filter above for wp icon.
		if (has_site_icon()) {
			return;
		}
		$search_files = array(
			get_stylesheet_directory().'/assets/favicon/favicon.ico',
			get_stylesheet_directory().'/assets/images/favicon/favicon.ico',
			get_template_directory().'/assets/favicon/favicon.ico',
			get_template_directory().'/assets/images/favicon/favicon.ico',
		);
		$search_urls = array(
			get_stylesheet_directory_uri().'/assets/favicon/',
			get_stylesheet_directory_uri().'/assets/images/favicon/',
			get_template_directory_uri().'/assets/favicon/',
			get_template_directory_uri().'/assets/images/favicon/',
		);
		$search_icon = array_combine($search_files, $search_urls);
		$favicon_uri = '';
		foreach ($search_icon as $key => $value) {
			if (file_exists($key)) {
				$favicon_uri = $value;
				break;
			}
		}
		if (empty($favicon_uri)) {
			return;
		}
		if (!is_localhost() && url_exists(site_url('/favicon.ico'))) {
			$favicon_uri = site_url('/'); // requires htaccess mod_rewrite
		}
		// favicons
		$favicon_uri = set_url_scheme($favicon_uri);
		// http://www.favicon-generator.org/
		echo '<link rel="shortcut icon" type="image/x-icon" href="'.$favicon_uri.'favicon.ico" />'."\n";
		echo '<link rel="icon" type="image/x-icon" href="'.$favicon_uri.'favicon.ico" />'."\n";
		echo '<link rel="apple-touch-icon" sizes="57x57" href="'.$favicon_uri.'apple-icon-57x57.png" />
<link rel="apple-touch-icon" sizes="60x60" href="'.$favicon_uri.'apple-icon-60x60.png" />
<link rel="apple-touch-icon" sizes="72x72" href="'.$favicon_uri.'apple-icon-72x72.png" />
<link rel="apple-touch-icon" sizes="76x76" href="'.$favicon_uri.'apple-icon-76x76.png" />
<link rel="apple-touch-icon" sizes="114x114" href="'.$favicon_uri.'apple-icon-114x114.png" />
<link rel="apple-touch-icon" sizes="120x120" href="'.$favicon_uri.'apple-icon-120x120.png" />
<link rel="apple-touch-icon" sizes="144x144" href="'.$favicon_uri.'apple-icon-144x144.png" />
<link rel="apple-touch-icon" sizes="152x152" href="'.$favicon_uri.'apple-icon-152x152.png" />
<link rel="apple-touch-icon" sizes="180x180" href="'.$favicon_uri.'apple-icon-180x180.png" />
<link rel="icon" type="image/png" sizes="192x192" href="'.$favicon_uri.'android-icon-192x192.png" />
<link rel="icon" type="image/png" sizes="32x32" href="'.$favicon_uri.'favicon-32x32.png" />
<link rel="icon" type="image/png" sizes="96x96" href="'.$favicon_uri.'favicon-96x96.png" />
<link rel="icon" type="image/png" sizes="16x16" href="'.$favicon_uri.'favicon-16x16.png" />
<link rel="manifest" href="'.$favicon_uri.'manifest.json" />
<meta name="msapplication-TileColor" content="#ffffff" />
<meta name="msapplication-TileImage" content="'.$favicon_uri.'ms-icon-144x144.png" />
<meta name="theme-color" content="#ffffff" />'."\n";
		// from wp
		echo '<link rel="apple-touch-icon-precomposed" href="'.$favicon_uri.'apple-icon-180x180.png" />'."\n";
	}

	public function wp_title($title, $sep = '-', $seplocation = '') {
		// prepend ancestors to $title here
		// change $title with other filters - https://developer.wordpress.org/reference/functions/wp_title/
		if (!is_front_end()) {
			return $title;
		}
		$title_new = trim($title,' '.$sep);
		list($title_new, $ancestors) = $this->get_title_ancestors($title_new, current_filter());
		if (!empty($ancestors)) {
			$ancestors = array_merge(
				$ancestors,
				(array)$title_new
			);
			$ancestors = array_unique($ancestors);
			if ($seplocation == 'right') {
				$ancestors = array_reverse($ancestors);
				$title = implode(" $sep ", $ancestors);
			}
			else {
				$title = implode(" $sep ", $ancestors);
			}
		}
		else {
			if ($seplocation == 'right') {
				$title = $title.get_bloginfo('name');
			}
			else {
				$title = get_bloginfo('name').$title;
			}
		}
		return $title;
	}

	public function protected_title_format($title, $post = 0) {
		return str_replace('Protected: ', '', $title);
	}
	public function private_title_format($title, $post = 0) {
		return str_replace('Private: ', '', $title);
	}

	public function get_wp_title_rss($str) {
		$description = get_bloginfo('description');
		if (!empty($description)) {
			$str .= ' - '.$description;
		}
		return $str;
	}

	public function the_author($str) {
		if (is_front_end() && is_multisite()) {
			global $post;
			if (is_super_admin($post->post_author)) {
				$str = get_bloginfo('name');
			}
		}
		return $str;
	}

	public function no_self_ping(&$links) {
		$home = get_option('home');
		foreach ($links as $l => $link) {
			if (strpos($link, $home) === false) {
				unset($links[$l]);
			}
		}
	}

	public function the_content($str = '') {
		if (empty($str)) {
			return $str;
		}
		if (!in_the_loop()) {
			return $str;
		}
		if (is_signup_page()) {
			return $str;
		}
		if (is_login_page()) {
			return $str;
		}
		$str = set_url_scheme_blob($str);
		$str = make_clickable($str);
		return $str;
	}

	public function get_the_excerpt($str = '', $post = 0) {
		if (empty($str)) {
			return $str;
		}
		if (!in_the_loop()) {
			return $str;
		}
		if (is_signup_page()) {
			return $str;
		}
		if (is_login_page()) {
			return $str;
		}
		$str = trim_excess_space($str);
		$str = set_url_scheme_blob($str);
		$str = make_clickable($str);
		return $str;
	}

	/* functions */

	public function get_title_ancestors($title = '', $current_filter = '') {
		$ancestors = array(
			get_bloginfo('name'),
		);
		$title_new = null;
		// some pages don't need more ancestors
		if (is_author()) {
			$title = __('Author').': '.get_the_author();
		}
		elseif (is_category()) {
			$title_new = single_cat_title('', false);
			if (is_taxonomy_hierarchical('category')) {
				$obj = get_queried_object();
				if (!empty($obj)) {
					$arr = get_ancestors($obj->term_id, 'category');
					if (!empty($arr)) {
						$arr = array_reverse($arr);
						foreach ($arr as $value) {
							$term = get_term($value, 'category');
							if (!empty($term) && !is_wp_error($term)) {
								$ancestors[] = $term->name;
							}
						}
					}
				}
			}
		}
		elseif (is_date()) {
			$date_format = get_option('date_format');
			if (is_year()) { $date_format = 'Y'; }
			elseif (is_month()) { $date_format = 'F Y'; }
			elseif ($current_filter == 'wp_title') {
				$title = get_the_time($date_format);
			}
			$title_new = __('Date').': '.get_the_time($date_format);			
		}
		elseif (is_post_type_archive()) {
			$title_new = post_type_archive_title('', false);
		}
		elseif (is_search()) {
			$title_new = __('Search Results').': '.get_search_query();
		}
		elseif (is_tag()) {
			$title_new = __('Tag').': '.single_tag_title('', false);
		}
		elseif (is_tax()) {
			$obj = get_queried_object();
			if (!empty($obj)) {
				$title_new = $obj->name;
				if (is_taxonomy_hierarchical($obj->taxonomy)) {
					$arr = get_ancestors($obj->term_id, $obj->taxonomy);
					if (!empty($arr)) {
						$arr = array_reverse($arr);
						foreach ($arr as $value) {
							$term = get_term($value, $obj->taxonomy);
							if (!empty($term) && !is_wp_error($term)) {
								$ancestors[] = $term->name;
							}
						}
					}
				}
			}
		}
		elseif (is_404()) {
			$title = __('Page not found');
		}
		elseif (is_login_page()) {
			$title = __('Login');
		}
		elseif (is_signup_page()) {
			$title = __('Sign Up');
		}
		elseif (is_home_page()) {
			$ancestors[] = get_bloginfo('description');
			$title = get_bloginfo('name');
		}
		else {
			global $post;
			$post_ID = $post->ID;
			if (empty($post_ID)) {
				// some plugins like buddypress hide the real post_id in queried_object_id
				global $wp_query;
				if (isset($wp_query->queried_object_id) && !empty($wp_query->queried_object_id)) {
					$post_ID = $wp_query->queried_object_id;
					if (isset($wp_query->queried_object)) {
						$post = $wp_query->queried_object;
					}
				}
			}
			// search ancestors - pages, maybe custom post types
			$arr = get_ancestors($post_ID, $post->post_type);
			if (!empty($arr)) {
				$arr = array_reverse($arr);
				foreach ($arr as $value) {
					$ancestors[] = get_post_field('post_title', $value, 'raw');
				}
			}
			// or taxonomies - post(category), maybe custom post types
			else {
				$taxonomy_objects = get_object_taxonomies($post->post_type, 'objects');
				if (!empty($taxonomy_objects)) {
					foreach ($taxonomy_objects as $taxonomy) {
						if ($taxonomy->name == 'post_tag' || $taxonomy->name == 'post_format') {
							continue;
						}
						$terms = wp_get_post_terms($post_ID, $taxonomy->name);
						if (!empty($terms) && !is_wp_error($terms)) {
							foreach ($terms as $term) {
								if ($term->name == 'Uncategorized') {
									continue;
								}
								$ancestors[] = $term->name;
								if (is_taxonomy_hierarchical($term->taxonomy)) {
									$arr = get_ancestors($term->term_id, $term->taxonomy);
									if (!empty($arr)) {
										$arr = array_reverse($arr);
										foreach ($arr as $value) {
											$term_parent = get_term($value, $term->taxonomy);
											if (!empty($term_parent) && !is_wp_error($term_parent)) {
												$ancestors[] = $term_parent->name;
											}
										}
									}
								}
								break;
							}
							break;
						}
					}
				}
			}
			if (is_singular()) {
				$title_new = $post->post_title;
			}
		}

		if (empty($title) && !empty($title_new)) {
			$title = $title_new;
		}
		if (empty($title)) {
			$title = get_bloginfo('name');
		}

		$title = sanitize_text_field($title);
		$title = trim_excess_space($title);
		$ancestors = array_map('sanitize_text_field', $ancestors);
		$ancestors = array_map('trim_excess_space', $ancestors);
		$ancestors = array_unique($ancestors);
		$ancestors = array_filter($ancestors);

		return apply_filters(static::$prefix.'_get_title_ancestors', array($title, $ancestors));
	}

	public static function post_thumbnail($is_singular = null) {
		if (post_password_required() || is_attachment() || !has_post_thumbnail()) {
			return;
		}
		if (is_null($is_singular)) {
			$is_singular = is_singular();
		}
		if ($is_singular) :
		?>
		<div class="post-thumbnail post-thumbnail-singular">
			<a class="post-thumbnail" href="<?php the_post_thumbnail_url(); ?>" aria-hidden="true" rel="lightbox-post-thumbnail">
				<?php the_post_thumbnail( 'post-thumbnail', array('alt' => the_title_attribute('echo=0')) ); ?>
			</a>
		</div><!-- .post-thumbnail -->
		<?php else : ?>
		<div class="post-thumbnail">
			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true">
				<?php the_post_thumbnail( 'post-thumbnail', array('alt' => the_title_attribute('echo=0')) ); ?>
			</a>
		</div><!-- .post-thumbnail -->
		<?php endif;
	}

}
endif;
?>