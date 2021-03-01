<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

@include_once(dirname(__FILE__).'/functions-common.php');

if (!class_exists('Halftheory_Clean')) :
class Halftheory_Clean {

	public static $prefix = 'halftheoryclean';
	public static $plugins = null;
	public $admin = null;
	public static $helper_plugin = null;
	public static $cache_blog = array();
	public static $cache_posts = array();

	public function __construct($load_theme = false) {
		$this->setup_globals();
		if ($load_theme) {
			$this->setup_plugins();
			$this->setup_actions();
		}
	}

	protected function setup_globals() {
		// only do this once
		if (static::$prefix !== 'halftheoryclean' || isset($this->plugin_name)) {
			return;
		}
		$this->plugin_name = get_called_class();
		$this->plugin_title = ucwords(str_replace('_', ' ', $this->plugin_name));
		static::$prefix = sanitize_key($this->plugin_name);
		static::$prefix = preg_replace("/[^a-z0-9]/", "", static::$prefix);
	}

	protected function setup_plugins() {
		// only do this once
		if (!is_null(static::$plugins)) {
			return;
		}
		static::$plugins = array();
		$active_plugins = self::get_active_plugins();
		if (empty($active_plugins)) {
			return;
		}
		$func = function($plugin) {
			return str_replace(WP_PLUGIN_DIR.'/', '', $plugin);
		};
		$active_plugins = array_map($func, $active_plugins);
		foreach ($active_plugins as $value) {
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
		add_action('after_switch_theme', array($this,'activation'), 10, 2);
		add_action('switch_theme', array($this,'deactivation'), 10, 3);

		add_action('after_setup_theme', array($this,'after_setup_theme'), 20);
		add_action('rewrite_rules_array', array($this,'rewrite_rules_array'), 20);
		add_action('init', array($this,'init'), 20);
		add_action('widgets_init', array($this,'widgets_init_remove_recent_comments'), 20);
		add_action('widgets_init', array($this,'widgets_init'), 20);
		add_filter('request', array($this,'request'), 20);
		add_action('template_redirect', array($this,'template_redirect'), 20);
		add_filter('wp_default_scripts', array($this,'wp_default_scripts_remove_jquery_migrate'));
		add_action('wp_enqueue_scripts', array($this,'wp_enqueue_scripts'), 20);
		add_action('wp_enqueue_scripts', array($this,'wp_enqueue_scripts_slicknav'), 20);

		add_action('wp', function() {
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
		}, 9);
		
		add_action('wp_head', array($this,'wp_head'), 20);
		add_action('wp_head', array($this,'wp_site_icon'), 100);
		remove_filter('wp_title', 'wptexturize');
		add_filter('wp_title', array($this,'wp_title'));
		add_filter('protected_title_format', array($this,'protected_title_format'));
		add_filter('private_title_format', array($this,'private_title_format'));
		add_filter('get_wp_title_rss', array($this,'get_wp_title_rss'));
		add_filter('the_author', array($this,'the_author'));
		add_action('pre_ping', array($this,'no_self_ping'));
		add_filter('the_guid', array($this,'the_guid'), 10, 2);
		add_filter('the_content', array($this,'the_content'), 12); // after autoembeds (priority 7), after shortcodes (priority 11), before obfuscate_email (priority 15)
		remove_filter('the_content', 'convert_smilies', 20);
		remove_filter('get_the_excerpt', 'wp_trim_excerpt', 10);
		add_filter('get_the_excerpt', array($this,'get_the_excerpt'));
		add_filter('embed_oembed_html', array($this,'embed_oembed_html'), 10, 4);

		if (apply_filters(static::$prefix.'_image_size_actions', true)) {
			add_filter('big_image_size_threshold', array($this,'big_image_size_threshold'), 10, 4);
			add_filter('intermediate_image_sizes', array($this,'intermediate_image_sizes'));
			add_filter('image_downsize', array($this,'image_downsize'), 10, 3);
		}

		add_filter('xmlrpc_enabled', '__return_false');
		add_action('pings_open', '__return_false');
		add_filter('comments_open', '__return_false', 10, 2);
		add_filter('feed_links_show_comments_feed', '__return_false');
		add_filter('embed_oembed_discover', '__return_false');
		add_filter('automatic_updater_disabled', '__return_true');
	}

	/* helpers for child themes - not in parent __construct */

	protected function setup_admin() {
		// only do this once
		if (!is_null($this->admin)) {
			return;
		}
		if (is_user_logged_in()) {
			if (!class_exists('Halftheory_Helper_Admin')) {
				@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-admin.php');
			}
			if (class_exists('Halftheory_Helper_Admin')) {
				$this->admin = new Halftheory_Helper_Admin();
			}
		}
	}

	protected function setup_helper_cdn() {
		// only do this once
		if (isset($this->cdn)) {
			return;
		}
		if (!class_exists('Halftheory_Helper_CDN')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-cdn.php');
		}
		if (class_exists('Halftheory_Helper_CDN')) {
			$this->cdn = new Halftheory_Helper_CDN();
		}
	}

	protected function setup_helper_infinite_scroll() {
		// only do this once
		if (isset($this->infinite_scroll)) {
			return;
		}
		if (!class_exists('Halftheory_Helper_Infinite_Scroll')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-infinite-scroll.php');
		}
		if (class_exists('Halftheory_Helper_Infinite_Scroll')) {
			$this->infinite_scroll = new Halftheory_Helper_Infinite_Scroll();
		}
	}

	protected function setup_helper_minify() {
		// only do this once
		if (isset($this->minify)) {
			return;
		}
		if (!class_exists('Halftheory_Helper_Minify')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-minify.php');
		}
		if (class_exists('Halftheory_Helper_Minify')) {
			$this->minify = new Halftheory_Helper_Minify();
		}
	}

	protected function setup_helper_plugin() {
		// only do this once
		if (!is_null(static::$helper_plugin)) {
			return;
		}
		if (!class_exists('Halftheory_Helper_Plugin')) {
			@include_once(dirname(__FILE__).'/helpers/class-halftheory-helper-plugin.php');
		}
		if (class_exists('Halftheory_Helper_Plugin')) {
			static::$helper_plugin = new Halftheory_Helper_Plugin(basename(__FILE__), static::$prefix, false);
		}
	}

	public static function get_helper_plugin() {
		if (is_object(static::$helper_plugin)) {
			if (is_a(static::$helper_plugin, 'Halftheory_Helper_Plugin')) {
				return static::$helper_plugin;
			}
		}
		return false;
	}
	
	/* install */

	public static function activation($old_theme_name = false, $old_theme_class = false) {
		$defaults = array(
			// General
			'users_can_register' => 0,
			'gmt_offset' => 1,
			'timezone_string' => '',
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
		if ($hp = self::get_helper_plugin()) {
			$hp->delete_transient_uninstall(static::$prefix);
			unset($hp);
		}
	}

	/* actions */

	public function after_setup_theme() { // first available action after plugins_loaded
		if (is_front_end()) {
			define('CURRENT_URL', get_current_uri());
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
		// wp-includes/media.php
		remove_image_size('1536x1536');
		remove_image_size('2048x2048');
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

	public function request($query_vars = array()) {
		if (!is_front_end()) {
			return $query_vars;
		}
		// search
		if (isset($query_vars['s'])) {
			// clean the search string
			$query_vars['s'] = trim(str_replace('%20', ' ', $query_vars['s']));
			// remove some post_types from search
			$query_vars['post_type'] = !isset($query_vars['post_type']) ? array_values(get_post_types(array('public'=>true),'names')) : make_array($query_vars['post_type']);
			$remove = array('attachment','revision','rl_gallery');
			$query_vars['post_type'] = array_values(array_diff($query_vars['post_type'], $remove));
		}
		// feed
		if (isset($query_vars['feed'])) {
			// remove some post_types from feed
			$query_vars['post_type'] = !isset($query_vars['post_type']) ? array_values(get_post_types(array('public'=>true),'names')) : make_array($query_vars['post_type']);
			$remove = array('page','attachment','revision','rl_gallery');
			$query_vars['post_type'] = array_values(array_diff($query_vars['post_type'], $remove));
		}
		return $query_vars;
	}

	public function template_redirect() {
		if (!is_front_end()) {
			return;
		}
		// search urls
		if (is_search() && isset($_GET['s'])) {
			$url = !empty($_GET['s']) ? home_url("/search/").str_replace('%2F', '/', rawurlencode(trim(get_query_var('s')))) : home_url();
			if (wp_redirect_extended($url)) {
				exit;
			}
		}
		// redirect attachment posts to the item
		if (is_singular() && is_attachment() && !in_the_loop() && !is_user_logged_in()) {
			$url = false;
			if ($image = self::get_image_context(get_the_ID(), 'url', 'large')) {
				$url = $image;
			}
			else {
				$url = wp_get_attachment_url(get_the_ID());
			}
			if (wp_redirect_extended($url)) {
				exit;
			}
		}
	}

	public function wp_default_scripts_remove_jquery_migrate(&$scripts) {
		if (!is_front_end()) {
			return;
		}
		$scripts->remove('jquery');
		$scripts->add('jquery', false, array('jquery-core'), '3.5.1');
	}
	public function wp_enqueue_scripts() {
		// header
		if (is_child_theme()) {
			wp_enqueue_style('parent-style', get_template_directory_uri().'/style.css', array(), self::get_theme_version(get_template_directory().'/style.css'));
		}
		wp_enqueue_style('theme-style', get_stylesheet_uri(), array(), self::get_theme_version(get_stylesheet_directory().'/style.css'));
		// footer
		wp_deregister_script('wp-embed');
	}
	public function wp_enqueue_scripts_slicknav() {
		// slicknav
		if (has_nav_menu('primary-menu')) {
	        wp_enqueue_style('dashicons');
			// header
			wp_enqueue_style('slicknav', get_template_directory_uri().'/assets/js/slicknav/slicknav.min.css', array(), '1.0.7', 'screen');
			wp_enqueue_style('slicknav-init', get_template_directory_uri().'/assets/js/slicknav/slicknav-init.css', array('slicknav'), self::get_theme_version(get_template_directory().'/assets/js/slicknav/slicknav-init.css'), 'screen');
			// footer
			wp_enqueue_script('slicknav', get_template_directory_uri().'/assets/js/slicknav/jquery.slicknav.min.js', array('jquery'), '1.0.7', true);
			wp_enqueue_script('slicknav-init', get_template_directory_uri().'/assets/js/slicknav/slicknav-init.js', array('jquery', 'slicknav'), self::get_theme_version(get_template_directory().'/assets/js/slicknav/slicknav-init.js'), true);
			$data = array(
			    'brand' => '<a href="'.esc_url(network_home_url('/')).'">'.get_bloginfo('name').'</a>'
			);
			wp_localize_script('slicknav-init', 'slicknav', $data);
		}
	}

	public function wp_head() {
		if (!is_front_end()) {
			return;
		}

		$post_id = self::get_page_id();
		$title = $this->get_title($post_id, ': ');
		$ancestors = $this->get_ancestors($post_id);

		$arr = array(
			'title' => '',
			'keywords' => '',
			'description' => '',
			'image_src' => '',
		);
		// title
		$arr['title'] = array_unique( array_merge($ancestors, (array)$title) );
		// keywords
		$terms = get_the_taxonomies($post_id, array('template' => __('###%s###%l'), 'term_template' => '%2$s'));
		if (!empty($terms)) {
			$func = function($str = '') {
				return preg_replace("/^###[^#]*###/i", '', $str);
			};
			$terms = array_map($func, $terms);
		}
		$arr['keywords'] = array_unique( array_merge($ancestors, $terms, (array)$title) );
		// description
		$excerpt = '';
		if (!empty($post_id) && $post = get_post($post_id)) {
			$excerpt = get_the_excerpt_filtered($post->post_excerpt);
			if (empty($excerpt) && !empty($post->post_content)) {
				$excerpt = get_the_excerpt_filtered($post->post_content);
			}
			$args = array(
				'plaintext' => true,
				'single_line' => true,
				'trim_title' => $arr['keywords'],
				'trim_urls' => true,
				'strip_shortcodes' => false,
				'strip_emails' => true,
				'strip_urls' => true,
				'add_dots' => false,
			);
			$excerpt = get_excerpt($excerpt, 500, $args);
		}
		if (empty($excerpt) && (is_tax() || is_tag() || is_category()) && !empty_notzero(term_description())) {
			$excerpt = wp_strip_all_tags(term_description(), true);
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
		$description = implode(". ", array_filter( array_unique($description) ) );
		$description_trim = wp_trim_words($description, 25);
		// no change
		if ($description == $description_trim) {
			$description .= ".";
		}
		else {
			$description_trim = preg_replace("/[^\w>;]+(\.\.\.|&#8230;|&hellip;)[\s]*$/i", "$1", $description_trim);
			$description = $description_trim;
		}
		$arr['description'] = $description;
		// image_src
		$arr['image_src'] = self::get_thumbnail_context($post_id, 'src', 'medium', '', array('custom_logo'=>true));

		$arr = apply_filters(static::$prefix.'_wp_head', $arr, $post_id);

		// print meta
		$itemprop = array(
			'name' => '',
			'description' => '',
			'url' => esc_url(CURRENT_URL),
			'image' => '',
		);
		$og = array(
			'type' => 'article',
			'site_name' => esc_attr(get_bloginfo('name')),
			'title' => '',
			'description' => '',
			'url' => esc_url(CURRENT_URL),
			'image' => '',
		);

		// title
		if (!empty($arr['title'])) {
			$itemprop['name'] = $og['title'] = esc_attr(implode(__(' - '), $arr['title']));
		}
		// keywords
		if (!empty($arr['keywords'])) {
			echo '<meta name="keywords" content="'.esc_attr(implode(__(', '), $arr['keywords'])).'" />'."\n";
		}
		// description
		if (!empty($arr['description'])) {
			$itemprop['description'] = $og['description'] = esc_attr($arr['description']);
			echo '<meta name="description" content="'.$itemprop['description'].'" />'."\n";
		}
		// image_src
		if (!empty($arr['image_src'])) {
			$itemprop['image'] = $og['image'] = esc_url($arr['image_src'][0]);
			if (isset($arr['image_src'][1])) {
				$og['image:width'] = $arr['image_src'][1];
			}
			if (isset($arr['image_src'][2])) {
				$og['image:height'] = $arr['image_src'][2];
			}
			echo '<link rel="image_src" href="'.$itemprop['image'].'" />'."\n";
		}

		// Schema.org
		foreach ($itemprop as $key => $value) {
			if (!empty($value)) {
				if ($key == 'image') {
					echo '<link itemprop="'.$key.'" href="'.$value.'" />'."\n";
					continue;
				}
				echo '<meta itemprop="'.$key.'" content="'.$value.'" />'."\n";
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

	public function wp_title($title_old, $sep = '-', $seplocation = '') {
		// prepend ancestors to $title here
		// change $title with other filters - https://developer.wordpress.org/reference/functions/wp_title/
		if (!is_front_end()) {
			return $title_old;
		}
		$title = $this->get_title(null, ': ');
		$ancestors = $this->get_ancestors();
		if (!empty($ancestors)) {
			$ancestors = array_unique( array_merge($ancestors, (array)$title) );
			if (is_paged()) {
				global $paged;
				$ancestors[] = __('Page').' '.$paged;
			}
			if ($seplocation == 'right') {
				$ancestors = array_reverse($ancestors);
			}
			$title = implode(" $sep ", $ancestors);
		}
		else {
			if ($seplocation == 'right') {
				$title = $title_old.get_bloginfo('name');
			}
			else {
				$title = get_bloginfo('name').$title_old;
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
			$str .= __(' - ').$description;
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

	public function the_guid($guid = '', $id = 0) {
		if (is_feed() && !empty($id)) {
			if ($str = get_permalink($id)) {
				$guid = $str;
			}
		}
		return $guid;
	}

	public function the_content($str = '') {
		if (!the_content_conditions($str)) {
			return $str;
		}
		$str = set_url_scheme_blob($str);
		$str = make_clickable($str);
		return $str;
	}

	public function get_the_excerpt($str = '', $post = null) {
		if (empty_notzero($str) && $post = get_post($post)) {
			$str = get_the_content('', false, $post);
		}
		if (!the_excerpt_conditions($str)) {
			return $str;
		}
		$str = strip_shortcodes_extended($str, array('caption','gallery','playlist','audio','video','embed'));
		$str = do_shortcode($str);
		if (is_feed()) {
			$args = array(
				'allowable_tags' => array('a','b','del','em','i','strong','u','br'),
				'plaintext' => false,
				'single_line' => false,
				'trim_urls' => true,
				'strip_shortcodes' => true,
				'strip_emails' => true,
				'strip_urls' => false,
				'add_dots' => true,
				'add_stop' => true,
			);
		}
		else {
			$args = array(
				'allowable_tags' => array('a','b','del','em','i','strong','u'),
				'plaintext' => false,
				'single_line' => true,
				'trim_urls' => true,
				'strip_shortcodes' => true,
				'strip_emails' => true,
				'strip_urls' => false,
				'add_dots' => true,
				'add_stop' => true,
			);
		}
		// remove tags before excerpt
		$tags = array_fill_keys($args['allowable_tags'], '');
		$tags['a'] = array('href','title');
		$str = strip_tags_attr($str, $tags);
		$str = get_excerpt($str, 500, $args);
		// feed - prepend thumbnail
		if (is_feed() && !empty($post)) {
			if ($image = self::get_thumbnail_context($post, 'img', 'medium', array('class'=>''), array('oembed_thumbnail_url'=>true, 'custom_logo'=>false, 'search_logo'=>false))) {
				$arr = array(
					'<a href="'.esc_url(get_permalink($post)).'">'.$image.'</a>',
					trim($str),
				);
				$str = implode("<br />\n", array_filter($arr));
			}
		}
		$str = set_url_scheme_blob($str);
		$str = make_clickable($str);
		return $str;
	}

	public function embed_oembed_html($cache = '', $url = '', $attr = array(), $post_ID = 0) {
		if (empty($cache)) {
			return $cache;
		}
		// Player Parameters
		$params = array(
			// https://developers.google.com/youtube/player_parameters
			'youtube.com' => array(
				'modestbranding'=>1,
				'rel'=>0,
			),
			// https://vimeo.zendesk.com/hc/en-us/articles/360001494447-Using-Player-Parameters
			'vimeo.com' => array(
				'color'=>'#7a28a3',
				'byline'=>0,
				'dnt'=>1,
				'fun'=>0,
			),
			// https://developer.dailymotion.com/player/#player-parameters
			'dailymotion.com' => array(
				'queue-autoplay-next'=>0,
				'queue-enable'=>0,
				'sharing-enable'=>0,
				'ui-logo'=>0,
			),
			// https://developers.soundcloud.com/docs/api/html5-widget#parameters
			'soundcloud.com' => array(
				'color'=>'#7a28a3',
				'sharing'=>'false',
				'download'=>'true',
				'show_user'=>'false',
			),
		);
		$params = apply_filters(static::$prefix.'_embed_oembed_html_params', $params);

		foreach ($params as $provider => $provider_params) {
			if (strpos($url, $provider) !== false) {
				if (preg_match_all("/ src=\"([^\"]+)\"/is", $cache, $matches)) {
					if (!empty($matches)) {
						if ($matches[1][0]) {
							$cache = preg_replace("/".preg_quote($matches[1][0], "/")."/", add_query_arg($provider_params, $matches[1][0]), $cache, 1);
						}
					}
				}
				break;
			}
		}
		return $cache;
	}

	public function big_image_size_threshold($threshold = 2000, $imagesize = array(), $file = '', $attachment_id = 0) {
		$large = get_option('large_size_w');
		if (!empty($large) && is_numeric($large)) {
			$threshold = (int)$large;
		}
		return $threshold;
	}

	public function intermediate_image_sizes($default_sizes = array()) {
		// remove medium_large
		if (in_array('medium', $default_sizes) && in_array('large', $default_sizes) && in_array('medium_large', $default_sizes)) {
			$key = array_search('medium_large', $default_sizes);
			if ($key !== false) {
				unset($default_sizes[$key]);
				$default_sizes = array_values($default_sizes);
			}
		}
		return $default_sizes;
	}

	public function image_downsize($out = false, $id = 0, $size = 'medium') {
		// intercept requests for medium_large
		if ($size === 'medium_large') {
			$sizes = get_intermediate_image_sizes();
			if (!in_array('medium_large', $sizes)) {
				if (in_array('large', $sizes)) {
					$out = image_downsize($id, 'large');
				}
				elseif (in_array('medium', $sizes)) {
					$out = image_downsize($id, 'medium');
				}
			}
		}
		return $out;
	}

	/* functions */

	public static function get_active_plugins() {
		$active_plugins = wp_get_active_and_valid_plugins();
		if (is_multisite()) {
			$active_sitewide_plugins = wp_get_active_network_plugins();
			$active_plugins = array_merge($active_plugins, $active_sitewide_plugins);
		}
		return $active_plugins;
	}

    public static function get_attachment_file_path($attachment_id, $size = null) {
    	$path = '';
		if (wp_attachment_is_image($attachment_id) && function_exists('wp_get_original_image_path')) { // avoids 'scaled' or edited images
			$path = wp_get_original_image_path($attachment_id);
		}
		else {
			$path = get_attached_file($attachment_id);
		}
		// other image sizes
		if (wp_attachment_is_image($attachment_id) && !empty($size) && $size !== 'full') {
			if ($str = wp_get_attachment_image_url($attachment_id, $size, false)) {
				$uploads = wp_get_upload_dir();
				$str = str_replace(trailingslashit($uploads['baseurl']), trailingslashit($uploads['basedir']), $str);
				if (file_exists($str)) {
					$path = $str;
				}
			}
		}
		$path = preg_replace("/[\/\\\]{2,}/s", DIRECTORY_SEPARATOR, $path);
		if (empty($path) || !file_exists($path)) {
			return false;
		}
		return $path;
	}

	public static function get_image_context($post_id = 0, $context = 'url', $size = 'medium', $attr = '') {
		if (!wp_attachment_is_image($post_id)) {
			return false;
		}
		$res = false;
		switch ($context) {
			case 'id':
				$res = (int)$post_id;
				break;
			case 'file':
				$res = self::get_attachment_file_path($post_id, $size);
				break;
			case 'src':
				$res = wp_get_attachment_image_src($post_id, $size, false); // returns array - url, width, height
				break;
			case 'img':
				$res = wp_get_attachment_image($post_id, $size, false, $attr); // <img
				break;
			case 'link':
				$res = wp_get_attachment_link($post_id, $size, false, false, false, $attr); // <a href="large.jpg"><img
				break;
			case 'url':
			default:
				$res = wp_get_attachment_image_url($post_id, $size, false);
				break;
		}
		if (empty_notzero($res)) {
			return false;
		}
		return $res;
	}

	public static function get_oembed_thumbnail_context($post_id = 0, $context = 'url', $attr = '') {
		$res = false;
		if (empty($post_id)) {
			return $res;
		}
		if (!in_array($context, array('src','img','link','url'))) {
			return $res;
		}
		$urls = get_urls( get_post_field('post_content', $post_id, 'raw') );
		if (empty($urls)) {
			return $res;
		}
		$thumbnail_obj = false;
		foreach ($urls as $url) {
			if ($arr = self::get_oembed_thumbnail_urls($url)) {
				$thumbnail_obj = current($arr);
				break;
			}
		}
		if (!$thumbnail_obj) {
			return $res;
		}
		$thumbnail_attr = array(
			'src' => set_url_scheme($thumbnail_obj->thumbnail_url),
			'alt' => '',
		);
		if (isset($thumbnail_obj->thumbnail_width)) {
			if (!empty($thumbnail_obj->thumbnail_width)) {
				$thumbnail_attr['width'] = absint($thumbnail_obj->thumbnail_width);
			}
		}
		if (isset($thumbnail_obj->thumbnail_height)) {
			if (!empty($thumbnail_obj->thumbnail_height)) {
				$thumbnail_attr['height'] = absint($thumbnail_obj->thumbnail_height);
			}
		}
		if (is_array($attr)) {
			$thumbnail_attr = array_merge($thumbnail_attr, $attr);
		}
		switch ($context) {
			case 'src':
				$res = array(0=>$thumbnail_attr['src']);
				if (isset($thumbnail_attr['width'])) {
					$res[1] = $res['width'] = $thumbnail_attr['width'];
				}
				if (isset($thumbnail_attr['width'])) {
					$res[2] = $res['height'] = $thumbnail_attr['height'];
				}
				break;
			case 'img':
				$res = '<img ';
				foreach ($thumbnail_attr as $key => $value) {
					$res .= $key.'="'.esc_attr($value).'" ';
				}
				$res .= '/>';
				break;
			case 'link':
				$res = '<a href="'.esc_url(set_url_scheme($url)).'"><img ';
				foreach ($thumbnail_attr as $key => $value) {
					$res .= $key.'="'.esc_attr($value).'" ';
				}
				$res .= '/></a>';
				break;
			case 'url':
			default:
				$res = $thumbnail_attr['src'];
				break;
		}
		return $res;
	}

	public static function get_oembed_thumbnail_urls($urls = array()) {
		if (empty($urls)) {
			return false;
		}
		$urls = make_array($urls);
		$urls = array_unique($urls);

		// get cache or transient - lifetime = 1 year
		$transient_name = static::$prefix.'_oembed_thumbnail_urls';
		$transient_urls = array();
		if (isset(static::$cache_blog[__FUNCTION__])) {
			$transient_urls = static::$cache_blog[__FUNCTION__];
		}
		elseif ($hp = self::get_helper_plugin()) {
			$switched = switch_to_native_blog();
			if ($arr = $hp->get_transient($transient_name)) {
				$transient_urls = $arr;
			}
			switch_from_native_blog($switched);
			unset($hp);
		}
		$transient_urls_new = $transient_urls; // track changes

		// return original urls as array keys
		// but store urls in a normalized way
		$oembed = _wp_oembed_get_object();
		$res_db = array();
		foreach ($urls as $url) {
			$key_db = untrailingslashit(set_url_scheme($url, 'http'));
			if (isset($res_db[$key_db])) {
				continue;
			}
			// transient
			if (array_key_exists($key_db, $transient_urls)) {
				if (isset($transient_urls[$key_db]->thumbnail_url)) {
					if (url_exists($transient_urls[$key_db]->thumbnail_url)) {
						$res_db[$key_db] = $transient_urls[$key_db];
						continue;
					}
				}
				unset($transient_urls_new[$key_db]);
			}
			// oembed
			if ($obj = $oembed->get_data($key_db, array('discover'=>false))) {
				if (isset($obj->thumbnail_url)) {
					if (!empty($obj->thumbnail_url) && strpos($obj->thumbnail_url, 'placeholder') === false) {
						$res_db[$key_db] = $transient_urls_new[$key_db] = $obj;
						continue;
					}
				}
			}
		}
		// changed
		if ($transient_urls_new !== $transient_urls) {
			// reduce the array
			foreach ($transient_urls_new as $key_db => $obj) {
				foreach ($obj as $key => $value) {
					if (strpos($key, 'thumbnail') !== false) {
						continue;
					}
					if (!is_numeric($value) && (strlen($value) > 250 || strlen($value) == 0)) {
						unset($obj->$key);
					}
				}
				$transient_urls_new[$key_db] = $obj;
			}
			static::$cache_blog[__FUNCTION__] = $transient_urls_new;
			// save transient
			if ($hp = self::get_helper_plugin()) {
				$switched = switch_to_native_blog();
				if (empty($transient_urls_new)) {
					$hp->delete_transient($transient_name);				
				}
				else {
					$hp->set_transient($transient_name, $transient_urls_new, '1 year');
				}
				switch_from_native_blog($switched);
				unset($hp);
			}
		}

		if (empty($res_db)) {
			return false;
		}
		$res = array();
		foreach ($urls as $url) {
			$key_db = untrailingslashit(set_url_scheme($url, 'http'));
			if (isset($res_db[$key_db])) {
				$res[$url] = $res_db[$key_db];
			}
		}
		if (empty($res)) {
			return false;
		}
		return $res;
	}

	/* functions - cache_blog */

	public static function get_cache_key($keys = array(), $values = array()) {
		$keys = make_array($keys);
		$values = make_array($values);
		if (count($keys) > count($values)) {
			$keys = array_slice($keys, 0, count($values));
		}
		elseif (count($values) > count($keys)) {
			$values = array_slice($values, 0, count($keys));
		}
		return http_build_query( array_filter( array_combine($keys, $values) ) );
	}

	public static function get_page_id($post_id = 0) {
		if (array_key_exists('page_id', static::$cache_blog)) {
			return static::$cache_blog['page_id'];
		}
		if (is_singular()) {
			$post_id = get_the_ID();
			if (empty($post_id)) {
				// some plugins like buddypress hide the real post_id in queried_object_id
				global $wp_query;
				$post_id = isset($wp_query->queried_object_id) ? (int)$wp_query->queried_object_id : 0;
				if (empty($post_id) && isset($wp_query->post)) {
					$post_id = $wp_query->post->ID;
				}
			}
		}
		elseif (is_posts_page()) {
			$post_id = get_posts_page_id();
		}
		$post_id = apply_filters(static::$prefix.'_get_page_id', $post_id);
		static::$cache_blog['page_id'] = (int)$post_id;
		return (int)$post_id;
	}

	public static function doing_function($str = '', $status = null) {
		if (empty($str)) {
			return false;
		}
		if (!isset(static::$cache_blog[__FUNCTION__])) {
			static::$cache_blog[__FUNCTION__] = array();
		}
		if ($status === true) {
			if (!isset(static::$cache_blog[__FUNCTION__][$str])) {
				static::$cache_blog[__FUNCTION__][$str] = microtime();
			}
		}
		elseif ($status === false) {
			if (isset(static::$cache_blog[__FUNCTION__][$str])) {
				unset(static::$cache_blog[__FUNCTION__][$str]);
			}
		}
		return isset(static::$cache_blog[__FUNCTION__][$str]);
	}

	public static function get_theme_version($file = '') {
		if (!isset(static::$cache_blog['wp_get_theme'])) {
			static::$cache_blog['wp_get_theme'] = wp_get_theme();
		}
    	if (!(static::$cache_blog['wp_get_theme'] instanceof WP_Theme)) {
    		if (is_file($file) && file_exists($file)) {
    			return filemtime($file);
    		}
    		return null;
    	}
    	// maybe parent theme?
    	if (is_file($file) && file_exists($file) && is_child_theme()) {
    		if (strpos($file, get_template_directory()) !== false) {
	    		return static::$cache_blog['wp_get_theme']->parent()->get('Version');
	    	}
    	}
    	return static::$cache_blog['wp_get_theme']->get('Version');
	}

	public static function get_custom_logo($context = 'url', $size = 'medium', $attr = '') {
		if (!isset(static::$cache_blog['custom_logo'])) {
			static::$cache_blog['custom_logo'] = array();
			if (has_custom_logo()) {
				static::$cache_blog['custom_logo']['id'] = (int)get_theme_mod('custom_logo');
			}
		}
		$res = false;
		if (isset(static::$cache_blog['custom_logo']['id'])) {
			$cache_key = self::get_cache_key('context,size,attr', array($context,$size,$attr));
			if (array_key_exists($cache_key, static::$cache_blog['custom_logo'])) {
				$res = static::$cache_blog['custom_logo'][$cache_key];
			}
			else {
				$res = self::get_image_context(static::$cache_blog['custom_logo']['id'], $context, $size, $attr);
				$res = apply_filters(static::$prefix.'_get_custom_logo',$res,$context,$size,$attr);
				static::$cache_blog['custom_logo'][$cache_key] = $res;
			}
		}
		return $res;
	}

	/* functions - cache_posts */

	public static function cache_posts_init($post_id = null) {
		if (is_object($post_id)) {
			$post_id = isset($post_id->ID) ? $post_id->ID : null;
		}
		if (is_null($post_id)) {
			$post_id = self::get_page_id();
		}
		if (!array_key_exists($post_id, static::$cache_posts)) {
			static::$cache_posts[$post_id] = array();
		}
		return $post_id;
	}

	public static function cache_posts_isset($post_id = null, $cache_key) {
		$post_id = self::cache_posts_init($post_id);
		if (is_array($cache_key)) {
			$arr = static::$cache_posts[$post_id];
			foreach ($cache_key as $value) {
				if (array_key_exists($value, $arr)) {
					$arr = $arr[$value];
				}
				else {
					return false;
				}
			}
			return true;
		}
		else {
			return array_key_exists($cache_key, static::$cache_posts[$post_id]);
		}
	}

	public static function cache_posts_set($post_id = null, $cache_key, $value = null) {
		$post_id = self::cache_posts_init($post_id);
		if (is_array($cache_key)) {
			$json_str = '';
			foreach ($cache_key as $v) {
				$json_str .= '{"'.$v.'":';
			}
			$json_str = $json_str.'"'.false.'"'.str_repeat('}', count($cache_key));
			$arr = json_decode($json_str, true);
			$func = function(&$arr) use (&$func, $value) {
				foreach ($arr as $k => &$v) {
					if (is_array($v)){
						$func($v);
						continue;
					}
					$v = $value;
				}
			};
			$func($arr);
			static::$cache_posts[$post_id] = array_merge_recursive(static::$cache_posts[$post_id], $arr);
		}
		else {
			static::$cache_posts[$post_id][$cache_key] = $value;
		}
		return $value;
	}

	public static function cache_posts_get($post_id = null, $cache_key, $default = false, $clear_key = false) {
		$post_id = self::cache_posts_init($post_id);
		$res = $default;
		if (self::cache_posts_isset($post_id, $cache_key)) {
			if (is_array($cache_key)) {
				$arr = static::$cache_posts[$post_id];
				$error = false;
				foreach ($cache_key as $value) {
					if (array_key_exists($value, $arr)) {
						$arr = $arr[$value];
					}
					else {
						$error = true;
					}
				}
				// TODO: $clear_key
				$res = (!$error) ? $arr : $default;
			}
			else {
				$res = static::$cache_posts[$post_id][$cache_key];
				if ($clear_key) {
					unset(static::$cache_posts[$post_id][$cache_key]);
				}
			}
		}
		return $res;
	}

	public function get_title_ancestors($post_id = null) {
		$post_id = self::cache_posts_init($post_id);

		$title = get_bloginfo('name');
		$ancestors = array(get_bloginfo('name'));

        $post_types_builtin = get_post_types(array('public'=>true,'_builtin'=>true),'names');

		// posts - use the post_id
		if (!empty($post_id) && $post = get_post($post_id)) {
			$title = current_filter() === 'the_title' ? $post->post_title : get_the_title($post_id);
			// search ancestors - pages, maybe custom post types
			$arr = get_ancestors($post_id, $post->post_type);
			if (!empty($arr)) {
				$arr = array_reverse($arr);
				foreach ($arr as $value) {
					$ancestors[] = current_filter() === 'the_title' ? get_post_field('post_title',$value,'raw') : get_the_title($value);
				}
			}
			else {
				// maybe custom post types
		        if (!in_array($post->post_type, $post_types_builtin) && $obj = get_post_type_object($post->post_type)) {
		        	$ancestors[] = $obj->labels->name;
		        }
				// or taxonomies
		    	else {
					$taxonomy_objects = get_object_taxonomies($post->post_type, 'objects');
					if (!empty($taxonomy_objects)) {
						foreach ($taxonomy_objects as $taxonomy) {
							if ($taxonomy->name == 'post_tag' || $taxonomy->name == 'post_format') {
								continue;
							}
							$terms = wp_get_post_terms($post_id, $taxonomy->name);
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
			}
		}
		// no post_id
		else {
			// some pages don't need more ancestors
			if (is_404()) {
				$title = __('Page not found');
			}
			elseif (is_search()) {
				$title = array(__('Search Results'), '"'.get_search_query().'"');
			}
			elseif (is_signup_page()) {
				$title = __('Sign Up');
			}
			elseif (is_login_page()) {
				$title = __('Login');
			}
			elseif (is_posts_page($post_id)) {
				$default_category = get_option('default_category');
				if (!empty($default_category)) {
					$term = get_term($default_category, 'category');
					if (!empty($term) && !is_wp_error($term)) {
						if ($term->name !== 'Uncategorized') {
							$title = $term->name;
						}
					}
				}
			}
			// these could be a combination, use the main query to find out which is first
			else {
				$title_old = $title;
				$title = array();
				$done = array();
				$obj = get_queried_object();
				$obj_class = !empty($obj) ? get_class($obj) : false; // probably one of: WP_Post WP_Post_Type WP_Term WP_Taxonomy
				// 1st level, maybe
				switch ($obj_class) {
					case 'WP_Post':
					case 'WP_Post_Type':
						if (is_post_type_archive()) {
							$title[] = post_type_archive_title('',false);
							$done[] = 'is_post_type_archive';
						}
						elseif ($post_type = get_query_var('post_type', false)) {
							if (!in_array($post_type, $post_types_builtin) && $obj = get_post_type_object($post_type)) {
								$title[] = $obj->labels->name;
								$done[] = 'post_type';
							}
						}
						break;
					case 'WP_Term':
					case 'WP_Taxonomy':
						if (is_category()) {
							$title[] = single_cat_title('',false);
							$done[] = 'is_category';
							if (is_taxonomy_hierarchical('category')) {
								if (isset($obj->term_id)) {
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
						elseif (is_tag()) {
							$title[] = __('Tag');
							$title[] = single_tag_title('',false);
							$done[] = 'is_tag';
						}
						elseif (is_tax()) {
							if (isset($obj->term_id)) {
								$title[] = $obj->name;
								$done[] = 'is_tax';
								if ($tax = get_taxonomy($obj->taxonomy)) {
									$ancestors[] = $tax->labels->name;
								}
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
						break;
					default:
						break;
				}
				// 1st/2nd level
				if (is_author()) {
					if (empty($done)) { $title[] = __('Author'); }
					$title[] = get_the_author();
					$done[] = 'is_author';
				}
				if (is_post_type_archive() && !in_array('is_post_type_archive', $done)) {
					$title[] = post_type_archive_title('',false);
					$done[] = 'is_post_type_archive';
				}
				if ($post_type = get_query_var('post_type', false) && !in_array('post_type', $done)) {
					if (!in_array($post_type, $post_types_builtin) && $obj = get_post_type_object($post_type)) {
						$title[] = $obj->labels->name;
						$done[] = 'post_type';
					}
				}
				if (is_category() && !in_array('is_category', $done)) {
					$title[] = single_cat_title('',false);
					if (empty($done)) {
						if (is_taxonomy_hierarchical('category')) {
							if (isset($obj->term_id)) {
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
					$done[] = 'is_category';
				}
				if (is_tag() && !in_array('is_tag', $done)) {
					if (empty($done)) { $title[] = __('Tag'); }
					$title[] = single_tag_title('',false);
					$done[] = 'is_tag';
				}
				if (is_tax() && !in_array('is_tax', $done)) {
					if (isset($obj->term_id)) {
						$title[] = $obj->name;
						if (empty($done)) {
							if ($tax = get_taxonomy($obj->taxonomy)) {
								$ancestors[] = $tax->labels->name;
							}
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
						$done[] = 'is_tax';
					}
				}
				if (is_date()) {
					$date_format = get_option('date_format');
					if (is_year()) { $date_format = 'Y'; }
					elseif (is_month()) { $date_format = 'F Y'; }
					if (empty($done)) { $title[] = __('Date'); }
					$title[] = get_the_time($date_format);
					$done[] = 'is_date';
				}
				// just in case
				if (empty($title)) {
					$title = $title_old;
				}
			}
		}

		if (is_home_page($post_id)) {
			$ancestors[] = get_bloginfo('description');
		}

		if (is_array($title)) {
			$title = array_map('sanitize_text_field', $title);
			$title = array_map('trim_excess_space', $title);
			$title = array_filter($title);
		}
		else {
			$title = sanitize_text_field($title);
			$title = trim_excess_space($title);
		}
		$ancestors = array_map('sanitize_text_field', $ancestors);
		$ancestors = array_map('trim_excess_space', $ancestors);
		$ancestors = array_unique(array_filter($ancestors));

		return apply_filters(static::$prefix.'_get_title_ancestors', array($title, $ancestors), $post_id);
	}

	public static function get_title_public($post_id = null, $sep = ' - ') {
		$plugin = new self(false);
		return $plugin->get_title($post_id, $sep);
	}

	public function get_title($post_id = null, $sep = ' - ') {
		$post_id = self::cache_posts_init($post_id);
		$res = false;
		if (self::cache_posts_isset($post_id, 'title')) {
			$res = self::cache_posts_get($post_id, 'title');
		}
		else {
			list($title, $ancestors) = $this->get_title_ancestors($post_id);
			$res = self::cache_posts_set($post_id, 'title', $title);
			self::cache_posts_set($post_id, 'ancestors', $ancestors);
		}
		if (is_array($res)) {
			$res = wptexturize(implode($sep, $res));
		}
		return $res;
	}

	public function get_ancestors($post_id = null) {
		$post_id = self::cache_posts_init($post_id);
		$res = false;
		if (self::cache_posts_isset($post_id, 'ancestors')) {
			$res = self::cache_posts_get($post_id, 'ancestors');
		}
		else {
			list($title, $ancestors) = $this->get_title_ancestors($post_id);
			self::cache_posts_set($post_id, 'title', $title);
			$res = self::cache_posts_set($post_id, 'ancestors', $ancestors);
		}
		return $res;
	}

	public static function get_thumbnail_context($post_id = null, $context = 'url', $size = 'medium', $attr = '', $options = array()) {
		$post_id = self::cache_posts_init($post_id);
		$res = false;
		$cache_key = self::get_cache_key('post_id,context,size,attr,options', array($post_id,$context,$size,$attr,$options));
		if (self::cache_posts_isset($post_id, array('thumbnail_extended',$cache_key))) {
			$res = self::cache_posts_get($post_id, array('thumbnail_extended',$cache_key));
		}
		else {
			$options_default = array(
				'post_thumbnail' => true,
				'gallery' => true,
				'attached_media' => true,
				'oembed_thumbnail_url'=>false,
				'custom_logo' => false,
				'search_logo' => false,
			);
			if (empty($post_id)) { // no post, no thumb
				$options['post_thumbnail'] = $options['gallery'] = $options['attached_media'] = $options['oembed_thumbnail_url'] = false;
			}
			$options = array_filter( array_merge($options_default, $options) );
			$image_id = false;
			foreach ($options as $key => $value) {
				if (empty($value)) {
					continue;
				}
				switch ($key) {
					// 1. use featured image
					case 'post_thumbnail':
						$image_id = get_post_thumbnail_id($post_id);
						break;
					// 2. use first single image or first gallery image (whatever comes first)
					case 'gallery':
						$content = get_post_field('post_content', $post_id, 'raw');
						$pos_single = strpos($content, "<img ");
						$pos_gallery = strpos($content, "[gallery ");
						if ($pos_single !== false || $pos_gallery !== false) {
							$search_order = array();
							if ($pos_single !== false && $pos_gallery !== false) {
								if ($pos_single < $pos_gallery) {
									$search_order = array('single','gallery');
								}
								else {
									$search_order = array('gallery','single');
								}
							}
							elseif ($pos_single !== false) {
								$search_order = array('single');
							}
							elseif ($pos_gallery !== false) {
								$search_order = array('gallery');
							}
							foreach ($search_order as $v) {
								switch ($v) {
									case 'single':
										if (preg_match_all("/<img .*?src=\"([^\"]+)\"/is", $content, $matches)) {
											if (!empty($matches[1])) {
												$guid = preg_replace("/\-[0-9]+x[0-9]+(\.[\w]+)$/s", "$1", $matches[1][0]); // remove size suffix
												global $wpdb;
											    $query = "SELECT ID FROM $wpdb->posts WHERE guid = '".$guid."' AND post_type = 'attachment'";
												$sql = $wpdb->get_col($query);
												if (!empty($sql)) {
													$image_id = (int)$sql[0];
												}
											}
										}
										break;
									case 'gallery':
										if (preg_match_all("/\[gallery [^\]]*?ids=\"([0-9]+)/is", $content, $matches)) {
											if (!empty($matches[1])) {
												$image_id = (int)$matches[1][0];
											}
										}
										break;
								}
								if (!empty($image_id)) {
									break;
								}
							}
						}
						break;
					// 3. get media children
					case 'attached_media':
						$media = get_attached_media('image', $post_id);
						if (!empty($media)) {
							$thumbnail_id = (int)get_post_thumbnail_id($post_id); // avoid thumb
							foreach ($media as $k => $v) {
								if ($k !== $thumbnail_id) {
									$image_id = $k;
									break;
								}
							}
						}
						break;
					// 4. oembed_thumbnail_url
					case 'oembed_thumbnail_url':
						if ($image = self::get_oembed_thumbnail_context($post_id, $context, $attr)) {
							$res = $image;
						}
						break;
					// 5. custom_logo
					case 'custom_logo':
						$image_id = self::get_custom_logo('id', $size, $attr);
						break;
					// 6. logo from search
					case 'search_logo':
						$search = get_posts(array(
							'no_found_rows' => true,
							'post_type' => 'attachment',
							'numberposts' => 1,
							'post_status' => array('publish','inherit'),
							's' => 'logo',
						));
						if (!empty($search) && !is_wp_error($search)) {
							$image_id = $search[0]->ID;
						}
						break;
					default:
						break;
				}
				if (empty($res) && !empty($image_id)) {
					$res = self::get_image_context($image_id, $context, $size, $attr);
				}
				if (!empty($res)) {
					break;
				}
			}
			$res = apply_filters(static::$prefix.'_get_thumbnail_context',$res,$post_id,$context,$size,$attr,$options);
			self::cache_posts_set($post_id, array('thumbnail_extended',$cache_key), $res);
		}
		return $res;
	}

	/* functions - html */

	public static function get_post_schema_dates() {
		$res = '';
		global $post;
		if (empty($post)) {
			return $res;
		}
		if (strtotime($post->post_date) < time()) {
			$res .= '<meta itemprop="datePublished" content="'.date('c', strtotime($post->post_date)).'" />'."\n";
			if (strtotime($post->post_modified) < time() && strtotime($post->post_modified) > strtotime($post->post_date)) {
				$res .= '<meta itemprop="dateModified" content="'.date('c', strtotime($post->post_modified)).'" />'."\n";
			}
		}
		return $res;
	}

	public static function print_post_schema($itemtype = 'Article') {
		$res = '';
		switch ($itemtype) {

			case 'Article':
				$image = self::get_thumbnail_context(get_the_ID(), 'url', 'medium', '', array('custom_logo'=>false, 'search_logo'=>false));
				$logo = self::get_custom_logo();
				if (empty($logo) && !empty($image)) {
					$logo = $image;
				}
				$res .= '<meta itemprop="headline" content="'.esc_attr(substr(get_the_title(), 0, 110)).'" />'."\n";
				$res .= '<meta itemprop="author" content="'.esc_attr(get_the_author()).'" />'."\n";
				if (!empty($image)) {
					$res .= '<meta itemprop="image" content="'.esc_url($image).'" />'."\n";
				}
				$res .= '<span itemprop="publisher" itemscope itemtype="'.set_url_scheme('http://schema.org/Organization').'" class="none">'."\n";
				$res .= '<meta itemprop="name" content="'.esc_attr(get_bloginfo('name')).'" />'."\n";
				if (!empty($logo)) {
					$res .= '<meta itemprop="logo" content="'.esc_url($logo).'" />'."\n";
				}
				$res .= '</span>'."\n";
				$res .= self::get_post_schema_dates();
				break;

			default:
				break;
		}
		echo apply_filters(static::$prefix.'_print_post_schema', $res, $itemtype);
	}

	public static function pagination_args($args = array()) {
		return wp_parse_args($args,
			array(
			'prev_text'          => __('Previous'),
			'next_text'          => __('Next'),
			'before_page_number' => '<span class="meta-nav screen-reader-text">'.__('Page').'</span>',
			'mid_size' => 2,
		));
	}

	public static function post_thumbnail($is_singular = null) {
		if (post_password_required() || is_attachment()) {
			return;
		}
		if (is_null($is_singular)) {
			$is_singular = is_singular();
		}
		if ($is_singular && has_post_thumbnail()) :
		?>
		<div class="post-thumbnail post-thumbnail-singular">
			<a class="post-thumbnail" href="<?php the_post_thumbnail_url(); ?>" aria-hidden="true" rel="lightbox-post-thumbnail"><?php the_post_thumbnail('post-thumbnail', array('alt' => the_title_attribute('echo=0'))); ?></a>
		</div><!-- .post-thumbnail -->
		<?php elseif (!$is_singular) :
		$options = array(
			'oembed_thumbnail_url'=>true,
			'custom_logo'=>false,
			'search_logo'=>false,
		);
		$image = self::get_thumbnail_context(get_the_ID(), 'img', 'medium', array('alt' => the_title_attribute('echo=0')), $options);
		if (!empty($image)) :
		?>
		<div class="post-thumbnail">
			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true"><?php echo $image; ?></a>
		</div><!-- .post-thumbnail -->
		<?php endif;
		endif;
	}
}
endif;
?>