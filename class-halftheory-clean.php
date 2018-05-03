<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if (!class_exists('Halftheory_Clean')) :
class Halftheory_Clean {

	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	protected function setup_globals() {
		$this->plugin_name = get_called_class();
		$this->plugin_title = ucwords(str_replace('_', ' ', $this->plugin_name));
		$this->prefix = sanitize_key($this->plugin_name);
		$this->prefix = preg_replace("/[^a-z0-9]/", "", $this->prefix);
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
			remove_action('wp_print_styles', 'print_emoji_styles');
		}, 9);
		
		add_action('wp_head', array($this, 'wp_head'), 20);
		add_filter('site_icon_meta_tags', array($this, 'site_icon_meta_tags'));
		add_action('wp_head', array($this, 'wp_site_icon'), 100);
		remove_filter('wp_title', 'wptexturize');
		add_filter('wp_title', array($this, 'wp_title'));
		//add_filter('feed_links_show_posts_feed', '__return_false');
		add_filter('feed_links_show_comments_feed', '__return_false');
		add_filter('protected_title_format', array($this, 'protected_title_format'));
		add_filter('private_title_format', array($this, 'private_title_format'));
		#add_filter('the_title', array($this, 'the_title'));
		add_filter('get_wp_title_rss', array($this, 'get_wp_title_rss'));
		add_filter('the_author', array($this, 'the_author'));

		add_action('pre_ping', array($this, 'no_self_ping'));
		add_action('pings_open', array($this, 'pings_open'));

		remove_filter('the_content', 'convert_smilies', 20);
	}

	/* install */

	public static function activation($old_theme_name = false, $old_theme_class = false) {
		flush_rewrite_rules();
	}

	public static function deactivation($new_theme_name = false, $new_theme_class = false, $old_theme_class = false) {
		flush_rewrite_rules();
	}

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
		remove_post_type_support('page', 'comments');
		remove_post_type_support('post', 'comments');
		remove_post_type_support('post', 'trackbacks');
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

	public function wp_head() {
		if (!is_front_end()) {
			return;
		}

		global $post;

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
			(array)get_bloginfo('name'),
			$ancestors,
			(array)$title
		);
		$keywords = array_map('sanitize_text_field', $keywords);
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
			#$excerpt = get_the_excerpt_filtered($post); // as of May 2018 throws error in PHP7
			if (empty($excerpt) && !empty($post->post_content)) {
				$excerpt = wp_trim_words(get_the_content_filtered($post->post_content));
			}
		}
		$description = array_merge(
			(array)$excerpt,
			(array)get_bloginfo('description'),
			(array)get_bloginfo('name'),
			$ancestors,
			(array)$title
		);
		$description = array_map('wp_strip_all_tags', $description);
		$description = array_map('strip_all_shortcodes', $description);
		$description = array_map('sanitize_text_field', $description);
		$trim_stop = function($value) {
		    return trim($value, ".");
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
		echo '<meta name="twitter:card" value="summary" />'."\n";

		// Open Graph
		foreach ($og as $key => $value) {
			if (!empty($value)) {
				echo '<meta property="og:'.$key.'" content="'.$value.'" />'."\n";
			}
		}
	}

	public function site_icon_meta_tags($meta_tags = array()) {
		/*
		postmeta
		_wp_attachment_context = site-icon
		has_site_icon()
		get_site_icon_url() // max 512x512
		*/
		return $meta_tags;
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
		list($title, $ancestors) = $this->get_title_ancestors($title, current_filter());
		if (!empty($ancestors)) {
			if ($seplocation == 'right') {
				$ancestors = array_reverse($ancestors);
				$title = $title.implode(" $sep ", $ancestors);
			}
			else {
				$title = implode(" $sep ", $ancestors).$title;
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
	public function the_title($title, $id = 0) {
		if (is_front_end()) {
			$pattern = array();
			$pattern[0] = '/Protected:/';
			$pattern[1] = '/Private:/';
			$replacement = array();
			$replacement[0] = ''; // Enter some text to put in place of Protected:
			$replacement[1] = ''; // Enter some text to put in place of Private:
			$title = preg_replace($pattern, $replacement, $title);
		}
		return $title;
	}

	public function get_wp_title_rss($str) {
		$str .= ' - '.get_bloginfo('description');
		return $str;
	}

	public function the_author($str) {
		if (is_front_end()) {
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

	public function pings_open($open) {
		return false;
	}

	public function get_title_ancestors($title = '', $current_filter = '') {
		$ancestors = array(
			get_bloginfo('name'),
		);
		// some pages don't need more ancestors
		$myTitle = false;
		if (is_search()) {
			$myTitle = __('Search Results');
		}
		elseif (is_404()) {
			$myTitle = __('Page not found');
		}
		elseif (is_login_page()) {
			$myTitle = __('Login');
		}
		elseif (is_signup_page()) {
			$myTitle = __('Sign Up');
		}
		elseif (is_home_page()) {
			$ancestors[] = get_bloginfo('description');
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
			if ($post->post_type == 'post') {
				$arr = get_the_category($post_ID);
				if (!empty($arr)) {
					foreach ($arr as $key => $value) {
						if ($value->name == 'Uncategorized') {
							continue;
						}
						$ancestors[] = $value->name;
					}
				}
				if (is_singular()) {
					$myTitle = $post->post_title;
				}
			}
			else {
				$arr = get_ancestors($post_ID, $post->post_type);
				if (!empty($arr)) {
					$arr = array_reverse($arr);
					foreach ($arr as $key => $value) {
						$ancestors[] = get_post_field('post_title', $value, 'raw');
					}
				}
				$myTitle = $post->post_title;
			}
		}

		if (empty($title) && $myTitle) {
			$title = $myTitle;
		}
		$ancestors = array_map('sanitize_text_field', $ancestors);
		$ancestors = array_unique($ancestors);
		$ancestors = array_filter($ancestors);

		return apply_filters($this->prefix.'_get_title_ancestors', array($title, $ancestors));
	}

	public function post_thumbnail() {
		if ( post_password_required() || is_attachment() || !has_post_thumbnail() ) {
			return;
		}

		if (is_singular()) :
		?>
		<div class="post-thumbnail">
			<a class="post-thumbnail" href="<?php the_post_thumbnail_url(); ?>" aria-hidden="true" rel="lightbox-post-thumbnail">
				<?php the_post_thumbnail( 'post-thumbnail', array( 'alt' => the_title_attribute( 'echo=0' ) ) ); ?>
			</a>
		</div><!-- .post-thumbnail -->

		<?php else : ?>

		<div class="post-thumbnail">
			<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true">
				<?php the_post_thumbnail( 'post-thumbnail', array( 'alt' => the_title_attribute( 'echo=0' ) ) ); ?>
			</a>
		</div><!-- .post-thumbnail -->
		<?php endif; // End is_singular()
	}

}
endif;
?>