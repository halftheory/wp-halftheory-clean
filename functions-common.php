<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

/* functions */

if (!function_exists('make_array')) {
	function make_array($str = '', $sep = ',') {
		if (is_array($str)) {
			return $str;
		}
		if (empty($str)) {
			return array();
		}
		$arr = explode($sep, $str);
		$arr = array_map('trim', $arr);
		$arr = array_filter($arr);
		return $arr;
	}
}

if (!function_exists('is_user_logged_in_cookie')) {
	function is_user_logged_in_cookie() {
		if (function_exists('is_user_logged_in')) {
			return is_user_logged_in();
		}
		if (!isset($_COOKIE)) {
			return false;
		}
		if (empty($_COOKIE)) {
			return false;
		}
		foreach ($_COOKIE as $key => $value) {
			if (strpos($key, 'wordpress_logged_in_') !== false) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('get_current_uri')) {
	function get_current_uri() {
	 	$res  = is_ssl() ? 'https://' : 'http://';
	 	$res .= $_SERVER['HTTP_HOST'];
	 	$res .= $_SERVER['REQUEST_URI'];
		return $res;
	}
}

if (!function_exists('is_front_end')) {
	function is_front_end() {
		if (is_admin() && !wp_doing_ajax()) {
			return false;
		}
		if (wp_doing_ajax()) {
			if (!empty($_SERVER["HTTP_REFERER"])) {
				$url_test = $_SERVER["HTTP_REFERER"];
			}
			else {
				$url_test = get_current_uri();
			}
			if (strpos($url_test, admin_url()) !== false) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('is_login_page')) {
	function is_login_page() {
		$wp_login = 'wp-login.php';
		if (defined('WP_LOGIN_SCRIPT')) {
			$wp_login = WP_LOGIN_SCRIPT;
		}
		if ($GLOBALS['pagenow'] === $wp_login) {
			return true;
		}
		elseif (strpos($_SERVER['PHP_SELF'], $wp_login) !== false) {
			return true;
		}
		elseif (in_array(ABSPATH.$wp_login, get_included_files())) {
			return true;
		}
		if (function_exists('wp_login_url') && function_exists('get_current_uri')) {
			if (wp_login_url() === get_current_uri()) {
				return true;
			}
		}
		return apply_filters('is_login_page', false);
	}
}
if (!function_exists('is_signup_page')) {
	function is_signup_page() {
		// wp-register.php only for backward compat 
		if ($GLOBALS['pagenow'] === 'wp-signup.php') {
			return true;
		}
		elseif ($GLOBALS['pagenow'] === 'wp-register.php') {
			return true;
		}
		elseif (strpos($_SERVER['PHP_SELF'], 'wp-signup.php') !== false) {
			return true;
		}
		elseif (strpos($_SERVER['PHP_SELF'], 'wp-register.php') !== false) {
			return true;
		}
		elseif (in_array(ABSPATH.'wp-signup.php', get_included_files())) {
			return true;
		}
		elseif (in_array(ABSPATH.'wp-register.php', get_included_files())) {
			return true;
		}
		if (function_exists('wp_registration_url')) {
			if (wp_registration_url() === get_current_uri()) {
				return true;
			}
		}
		return apply_filters('is_signup_page', false);
	}
}
if (!function_exists('is_home_page')) {
	function is_home_page() {
		if (is_front_page() && !is_login_page() && !is_signup_page()) {
			return true;
		}
		return apply_filters('is_home_page', false);
	}
}

if (!function_exists('is_localhost')) {
	function is_localhost() {
		if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
			return true;
		}
		return false;
	}
}

if (!function_exists('is_slug_current')) {
	function is_slug_current($slugs, $url = '') {
		if (empty($slugs)) {
			return false;
		}
		if (empty($url)) {
			$url = get_current_uri();
		}
		if (!is_array($slugs)) {
			$slugs = (array)$slugs;
			$slugs = array_filter($slugs);
		}
		foreach ($slugs as $value) {
			if (!preg_match("/[a-z0-9]+/i", $value)) {
				continue;
			}
			if ($value == $url) {
				return true;
			}
			if (strpos($url, $value) !== false) {
				return true;
			}
			# TOTO: preg_match syntax
		}
		return false;
	}
}

if (!function_exists('url_exists')) {
	function url_exists($url = '') {
		if (empty($url)) {
			return false;
		}
		$url_check = @get_headers($url);
		if (!is_array($url_check) || strpos($url_check[0], "404") !== false) {
			return false;
		}
		return true;
	}
}

if (!function_exists('get_visitor_ip')) {
	function get_visitor_ip() {
		if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown")) {
			$ip = getenv("HTTP_CLIENT_IP");
		}
		elseif (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown")) {
			$ip = getenv("HTTP_X_FORWARDED_FOR");
		}
		elseif (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown")) {
			$ip = getenv("REMOTE_ADDR");
		}
		elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown")) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		else {
			$ip = false;
		}
		return $ip;
	}
}

if (!function_exists('get_the_excerpt_filtered')) {
	function get_the_excerpt_filtered($str_or_post = '') {
		if (is_string($str_or_post)) {
			$str = $str_or_post;
		}
		else {
			$str = get_the_excerpt($str_or_post);
		}
		$str = apply_filters('the_excerpt', $str);
		return $str;
	}
}
if (!function_exists('get_the_content_filtered')) {
	function get_the_content_filtered($str = '') {
		if (empty($str)) {
			return $str;
		}
		$str = apply_filters('the_content', $str);
		$str = str_replace(']]>', ']]&gt;', $str);
		return $str;
	}
}

if (!function_exists('fix_potential_html_string')) {
	function fix_potential_html_string($str = '') {
		if (empty($str)) {
			return $str;
		}
		if (strpos($str, "&lt;") !== false) {
			if (substr_count($str, "&lt;") > substr_count($str, "<") || preg_match("/&lt;\/[a-z0-9]+&gt;/is", $str)) {
				$str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
			}
		}
		elseif (strpos($str, "&#039;") !== false) {
			$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		}
		return $str;
	}
}

if (!function_exists('trim_excess_space')) {
	function trim_excess_space($str = '') {
		if (empty($str)) {
			return $str;
		}
		$str = str_replace("&nbsp;", ' ', $str);
		$str = str_replace("&#160;", ' ', $str);
		$str = str_replace("\xc2\xa0", ' ',$str);

		if (strpos($str, "</") !== false) {
			$str = preg_replace("/[\t\n\r ]*(<\/[^>]+>)/s", "$1", $str); // no space before closing tags
		}

		$str = preg_replace("/[\t ]*(\n|\r)[\t ]*/s", "$1", $str);
		$str = preg_replace("/(\n\r){3,}/s", "$1$1", $str);
		$str = preg_replace("/[\n]{3,}/s", "\n\n", $str);
		$str = preg_replace("/[ ]{2,}/s", ' ', $str);
		return trim($str);
	}
}

if (!function_exists('strip_tags_html_comments')) {
	function strip_tags_html_comments($str = '', $allowable_tags = '') {
		if (empty($str)) {
			return $str;
		}
		$str = str_replace("<!--", "###COMMENT_OPEN###", $str);
		$str = str_replace("-->", "###COMMENT_CLOSE###", $str);
		$str = strip_tags($str, $allowable_tags);
		$str = str_replace("###COMMENT_OPEN###", "<!--", $str);
		$str = str_replace("###COMMENT_CLOSE###", "-->", $str);
		return $str;
	}
}

if (!function_exists('strip_all_shortcodes')) {
	function strip_all_shortcodes($str = '') {
		return preg_replace("/\[[^\]]+\]/is", "", $str);
	}
}

if (!function_exists('strip_tags_attr')) {
	function strip_tags_attr($str = '', $allowable_tags_attr = array()) {
		if (empty($str)) {
			return $str;
		}
		if (function_exists('fix_potential_html_string')) {
			$str = fix_potential_html_string($str);
		}
		if (strpos($str, "<") === false) {
			return $str;
		}
		// script/style tags - special case - remove all contents
		$strip_all = array('script', 'style');
		foreach ($strip_all as $value) {
			if (!array_key_exists($value, $allowable_tags_attr)) {
				$str = preg_replace("/<".$value."[^>]*>.*?<\/".$value.">/is", "", $str);
			}
		}
		if (empty($allowable_tags_attr)) {
			return strip_tags($str);
		}
		$allowable_tags = '<'.implode('><', array_keys($allowable_tags_attr)).'>';
		$str = strip_tags($str, $allowable_tags);
		$has_tags = false;
		foreach ($allowable_tags_attr as $tag => $attr) {
			if (strpos($str, "<".$tag.">") !== false || strpos($str, "<".$tag." ") !== false) {
				$has_tags = true;
				break;
			}
		}
		if ($has_tags === false) {
			return $str;
		}
		else {
			if (function_exists('trim_excess_space')) {
				$str = trim_excess_space($str);
			}
			$text_tags = array(
				'b',
				'blockquote',
				'del',
				'div',
				'em',
				'h1',
				'h2',
				'h3',
				'h4',
				'h5',
				'h6',
				'i',
				'p',
				'span',
				'strong',
				'u',
			);
			$void_tags = array(
				'area',
				'base',
				'br',
				'col',
				'command',
				'embed',
				'hr',
				'img',
				'input',
				'keygen',
				'link',
				'menuitem',
				'meta',
				'param',
				'source',
				'track',
				'wbr',
			);
			$wrapper = 'domwrapper';
			$dom = @DOMDocument::loadHTML( '<'.$wrapper.'>'.mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8').'</'.$wrapper.'>', LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD );
			$dom->formatOutput = true;
			$dom->preserveWhiteSpace = false;
			$xpath = new DOMXPath($dom);
			#$tags = $xpath->query('//*');
			$tags = $xpath->query('//*[not(self::br)]');
			$domElemsToRemove = array();
			foreach ($tags as $tag) {
				$my_tag = $tag->tagName;
				if ($my_tag == $wrapper) {
					continue;
				}
				if (!array_key_exists($my_tag, $allowable_tags_attr)) {
					continue;
				}
				if ($allowable_tags_attr[$my_tag] === '*') {
					continue;
				}
				// remove empty, only for text-tags (probably)
				if (empty($allowable_tags_attr[$my_tag]) && in_array($my_tag, $text_tags) && !in_array($my_tag, $void_tags)) {
					if (trim($tag->nodeValue) == "" && $tag->childNodes->length == 0) {
						$domElemsToRemove[] = $tag;
						continue;
					}
				}
				if ($tag->attributes->length == 0) {
					continue;
				}
				// remove attr
				if (!is_array($allowable_tags_attr[$my_tag])) {
					$allowable_tags_attr[$my_tag] = explode(",", $allowable_tags_attr[$my_tag]);
					$allowable_tags_attr[$my_tag] = array_map('trim', $allowable_tags_attr[$my_tag]);
					$allowable_tags_attr[$my_tag] = array_filter($allowable_tags_attr[$my_tag]);
				}
				$remove = array();
				for ($i = 0; $i < $tag->attributes->length; $i++) {
					$my_attr = $tag->attributes->item($i)->name;
					if (!in_array($my_attr, $allowable_tags_attr[$my_tag])) {
						$remove[] = $my_attr;
					}
				}
				if (!empty($remove)) {
					foreach ($remove as $value) {
						$tag->removeAttribute($value);
					}
				}
			}
			foreach($domElemsToRemove as $domElement) {
				$domElement->parentNode->removeChild($domElement);
			}
			$str = trim( strip_tags( html_entity_decode( $dom->saveHTML() ), $allowable_tags ) );
		}
		return $str;
	}
}

if (!function_exists('replace_tags')) {
	function replace_tags($str = '', $arr = array()) {
		if (empty($str) || empty($arr)) {
			return $str;
		}
		if (function_exists('fix_potential_html_string')) {
			$str = fix_potential_html_string($str);
		}
		if (strpos($str, "<") === false) {
			return $str;
		}
		foreach ($arr as $old => $new) {
			if (empty($new)) {
				continue;
			}
			$str = preg_replace("/<".$old."([\/ ]*)>/is", "<".$new."$1>", $str);
			$str = preg_replace("/<".$old." ([^>]+)>/is", "<".$new." $1>", $str);
			$str = preg_replace("/<\/".$old." [^>]*>/is", "</".$new.">", $str);
			$str = preg_replace("/<\/".$old.">/is", "</".$new.">", $str);
		}
		return $str;
	}
}

if (!function_exists('get_excerpt')) {
	function get_excerpt($text = '', $length = 250, $args = array()) {
		if (empty($text)) {
			return $text;
		}
		// resolve vars
		$length = apply_filters('get_excerpt_length', $length);
		$default_args = array(
			'allowable_tags' => array(),
			'plaintext' => false,
			'single_line' => true,
			'trim_title' => array(),
			'trim_urls' => true,
			'add_dots' => true,
		);
		$default_args = apply_filters('get_excerpt_default_args', $default_args);
		if (function_exists('make_array')) {
			$args = make_array($args);
		}
		$args = array_merge($default_args, (array)$args);
		if (function_exists('fix_potential_html_string')) {
			$text = fix_potential_html_string($text);
		}
		// add a space for lines if needed
		if ($args['single_line'] && strpos($text, "<") !== false) {
			$text = preg_replace("/(<p>|<p [^>]*>|<\/p>|<br>|<br\/>|<br \/>)/i", "$1 ", $text);
		}
		// remove what we don't need
		if (function_exists('make_array')) {
			$args['allowable_tags'] = make_array($args['allowable_tags']);
		}
		elseif (!is_array($args['allowable_tags'])) {
			$args['allowable_tags'] = (array)$args['allowable_tags'];
		}
		if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
			// script/style tags - special case - remove all contents
			$strip_all = array('script', 'style');
			foreach ($strip_all as $value) {
				if (!array_key_exists($value, $args['allowable_tags'])) {
					$text = preg_replace("/<".$value."[^>]*>.*?<\/".$value.">/is", "", $text);
				}
			}
			$args['allowable_tags'] = '<'.implode('><', (array)$args['allowable_tags']).'>';
			$text = strip_tags($text, $args['allowable_tags']);
		}
		else {
			$text = wp_strip_all_tags($text, $args['single_line']);
		}
		if (function_exists('strip_all_shortcodes')) {
			$text = strip_all_shortcodes($text);
		}
		elseif (function_exists('strip_shortcodes')) {
			$text = strip_shortcodes($text);
		}
		// remove excess space
		if ($args['single_line']) {
			$text = preg_replace("/[\n\r]+/s", " ", $text);
		}
		$text = preg_replace("/[\t]+/s", " ", $text); // no tabs
		if (function_exists('trim_excess_space')) {
			$text = trim_excess_space($text);
		}
		// trim the top
		$regex_arr = array("(<br>|<br\/>|<br \/>)");
		if (function_exists('make_array')) {
			$args['trim_title'] = make_array($args['trim_title']);
		}
		elseif (!is_array($args['trim_title'])) {
			$args['trim_title'] = (array)$args['trim_title'];
		}
		if (!empty($args['trim_title'])) {
			if (function_exists('fix_potential_html_string')) {
				$args['trim_title'] = array_map('fix_potential_html_string', $args['trim_title']);
			}
			$args['trim_title'] = array_map('trim', $args['trim_title']);
			$args['trim_title'] = array_unique($args['trim_title']);
			$args['trim_title'] = array_filter($args['trim_title']);
			foreach ($args['trim_title'] as $key => $value) {
				if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
					$regex_arr[] = "<[^>]+>[\s]*".$value;
				}
				$regex_arr[] = $value;
			}
		}
		if ($args['trim_urls']) {
		    $regex = "((https?|ftp)://)"; // SCHEME
		    $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
		    $regex .= "([a-z0-9-.]*)\.([a-z]{2,4})"; // Host or IP
		    $regex .= "(:[0-9]{2,5})?"; // Port
		    $regex .= "(/([a-z0-9+\$_%-]\.?)+)*/?"; // Path
		    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+/\$_.-]*)?"; // GET Query
		    $regex .= "(#[a-z_.-][a-z0-9+$%_.-]*)?"; // Anchor
			if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
				$regex_arr[] = "<[^>]+>[\s]*".$regex;
			}
			$regex_arr[] = $regex;
			
		    $regex = "www\."; // SCHEME
		    $regex .= "([a-z0-9-.]*)\.([a-z]{2,4})"; // Host or IP
		    $regex .= "(:[0-9]{2,5})?"; // Port
		    $regex .= "(/([a-z0-9+\$_%-]\.?)+)*/?"; // Path
		    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+/\$_.-]*)?"; // GET Query
		    $regex .= "(#[a-z_.-][a-z0-9+$%_.-]*)?"; // Anchor
			if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
				$regex_arr[] = "<[^>]+>[\s]*".$regex;
			}
			$regex_arr[] = $regex;
		}
		$i = 0;
		while ($i < count($regex_arr)) {
			$i = 0;
			foreach ($regex_arr as $key => $value) {
				$replaced = false;
				$pos = strpos($text, $value);
				if ($pos === 0) {
					$len_res = mb_strlen($text);
					$len_value = mb_strlen($value);
					if ($len_res > $len_value) {
						$text = preg_replace("/^".preg_quote($value, '/')."[\s]*/i", "", $text);
						$replaced = true;
					}
				}
				elseif (preg_match("~^[\s]*$value~i", $text, $match)) {
					$len_res = mb_strlen($text);
					$len_value = mb_strlen($match[0]);
					if ($len_res > $len_value) {
						$text = preg_replace("/^[\s]*".preg_quote($match[0], '/')."[\s]*/i", "", $text);
						$replaced = true;
					}
				}
				if (!$replaced) {
					$i++;
				}
			}
		}
		// correct length
		// TODO: find a fast way of checking multibyte strings here
		if (strlen(strip_tags($text)) <= $length) {
			if ($args['plaintext']) {
				return $text;
			}
		}
		// add dots
		else {
			$length_new = $length;
			if ($args['plaintext'] && !preg_match("/[^a-z0-9]/i", mb_substr($text, $length, 1))) {
				$length_new = mb_strrpos( mb_substr($text, 0, $length), " ");
			}
			elseif (!preg_match("/[^a-z0-9>]/i", mb_substr($text, $length, 1))) {
				$length_new = mb_strrpos( mb_substr($text, 0, $length), " ");
			}
			$text = mb_substr($text,0,$length_new,'UTF-8');
			// check if we cut in the middle of a tag
			if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
				$tags = trim( str_replace('><', '|', $args['allowable_tags']), '><');
				$text = preg_replace("/^(.+)<($tags) [^>]+$/is", "$1", $text);
			}
			if ($args['add_dots']) {
				if ($args['plaintext']) {
					$text .= "...";
				}
				else {
					$text .= '&hellip;';
				}
				$text = preg_replace("/[^a-z0-9>]+(\.\.\.|&#8230;|&hellip;)[\s]*$/i", "$1", $text);
			}
		}
		// add line breaks?
		if ($args['single_line'] === false && $args['plaintext'] === false && strpos($text, '<br />') === false && strpos($text, '</') === false) {
			$text = nl2br($text);
			// TODO: cleanup br tags directly next to p tags
		}
		// close open tags
		if (!empty($args['allowable_tags']) && $args['plaintext'] === false) {
			if (function_exists('force_balance_tags')) {
				$text = force_balance_tags($text);
			}
			else {
				// puts plaintext in a p
				$dom = @DOMDocument::loadHTML( mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8') );
				$text = trim( strip_tags( html_entity_decode( $dom->saveHTML() ), $args['allowable_tags'] ) );
			}
		}
		return $text;
	}
}

if (!function_exists('get_site_logo_url_from_site_icon')) {
	function get_site_logo_url_from_site_icon($size = 'full', $blog_id = 0) {
		if (!has_site_icon($blog_id)) {
			return false;
		}
		$size_icon = $size;
		if (!is_int($size_icon)) {
			$size_icon = 512;
		}
		$url = get_site_icon_url($size_icon, '', $blog_id);  // max 512x512
		if (strpos($url, 'cropped-') !== false) {
			$names = array(
				preg_replace("/^.*?cropped-(.*)$/i", "$1", $url),
				preg_replace("/^.*?cropped-([^\.]*).*$/i", "$1", $url),
			);
			$names = array_merge($names, array_map('sanitize_title', $names) );
			$names = array_unique($names);
			$parent = get_posts(array(
				'no_found_rows' => true,
				'post_type' => 'attachment',
				'numberposts' => 1,
				'exclude' => (array)get_option('site_icon'),
				'post_name__in' => $names,
			));
			if (!empty($parent)) {
				$url = wp_get_attachment_image_url($parent[0]->ID, $size);
			}
		}
		return $url;
	}
}

if (!function_exists('get_page_thumbnail_id')) {
	// returns (int)image_id or (string)image_url
	function get_page_thumbnail_id($id = null, $size = 'thumbnail', $custom_logo = false) {
		$image_id = false;
		// add customizations here
		$image_id = apply_filters('get_page_thumbnail_id', $image_id, $id, $size, $custom_logo);
		if (!empty($image_id)) {
			return $image_id;
		}
		/*
		if ($id == 'buddypress') {
			$image_id = bp_core_fetch_avatar(array('item_id' => bp_displayed_user_id(), 'type' => 'full', 'html' => false));
		}
		*/
		if (empty($id) || !is_int($id)) {
			$id = get_the_ID();
		}
		// 1. use featured image
		$image_id = get_post_thumbnail_id($id);
		if (!empty($image_id)) {
			return (int)$image_id;
		}
		// 2. get media children
		if (empty($image_id)) {
			$media = get_attached_media('image', $id);
			if (!empty($media)) {
				$image_id = key($media);
				// update post thumbnail
				if (is_int($image_id)) {
					update_post_meta($id, '_thumbnail_id', $image_id);
				}
				return $image_id;
			}
		}
		// 3. use first single image or first gallery image (whatever comes first)
		$content = get_post_field('post_content', $id, 'raw');
		if (!empty($content)) {
			$single = false;
			$gallery = false;
			$pos_single = strpos($content, "<img ");
			$pos_gallery = strpos($content, "[gallery ");
			if ($pos_single !== false && $pos_gallery !== false) {
				if ($pos_single < $pos_gallery) {
					$single = true;
				}
				else {
					$gallery = true;
				}
			}
			elseif ($pos_single !== false) {
				$single = true;
			}
			elseif ($pos_gallery !== false) {
				$gallery = true;
			}
			if ($single) {
				preg_match_all("/<img.*?src=\"(.*?)\"/is", $content, $matches);
				if ($matches[1][0]) {
					$guid = preg_replace("/\-[0-9]+x[0-9]+(\.[a-z0-9]+)$/is", "$1", $matches[1][0]);
					global $wpdb;
				    $query = "SELECT ID FROM $wpdb->posts WHERE guid = '".$guid."' AND post_type = 'attachment'";
					$sql = $wpdb->get_col($query);
					if (!empty($sql)) {
						$image_id = (int)$sql[0];
					}
					else {
						$image_id = (string)$guid;
					}
				}
			}
			elseif ($gallery) {
				preg_match_all("/\[gallery [^\]]*?ids=\"([0-9]+)/is", $content, $matches);
				if ($matches[1][0]) {
					$image_id = (int)$matches[1][0];
				}
			}
			if (!empty($image_id)) {
				// update post thumbnail
				if (is_int($image_id)) {
					update_post_meta($id, '_thumbnail_id', $image_id);
				}
				elseif (is_string($image_id)) {
					$image_id = set_url_scheme($image_id);
				}
				return $image_id;
			}
		}
		// 4. custom_logo
		if ($custom_logo) {
			// from db
			if (has_custom_logo()) {
				$image_id = (int)get_theme_mod('custom_logo');
				return $image_id;
			}
			// from search
			else {
				$search = get_posts(array(
					'no_found_rows' => true,
					'post_type' => 'attachment',
					'numberposts' => 1,
					'post_status' => array('publish','inherit'),
					's' => 'logo',
				));
				if (!empty($search)) {
					$image_id = $search[0]->ID;
					return $image_id;
				}
			}
		}
		return false;
	}
}
if (!function_exists('get_the_page_thumbnail_src')) {
	function get_the_page_thumbnail_src($id = null, $size = 'thumbnail', $custom_logo = false) {
		$image = array();
		$image_id = get_page_thumbnail_id($id, $size, $custom_logo);
		if (is_int($image_id)) {
			$image = wp_get_attachment_image_src($image_id, $size, false);
		}
		elseif (is_string($image_id)) {
			$image[] = $image_id;
		}
		return $image;
	}
}
if (!function_exists('get_the_page_thumbnail_url')) {
	function get_the_page_thumbnail_url($id = null, $size = 'thumbnail', $custom_logo = false) {
		$image = '';
		$image_id = get_page_thumbnail_id($id, $size, $custom_logo);
		if (is_int($image_id)) {
			$image = wp_get_attachment_image_url($image_id, $size, false);
		}
		elseif (is_string($image_id)) {
			$image = $image_id;
		}
		return $image;
	}
}
if (!function_exists('get_the_page_thumbnail')) {
	function get_the_page_thumbnail($id = null, $size = 'thumbnail', $custom_logo = false, $attr = array()) {
		$image = '';
		$image_attr = array(
			'itemprop' => 'image',
			'alt'   => trim( get_the_title() ),
			'class' => '',
		);
		$image_id = get_page_thumbnail_id($id, $size, $custom_logo);
		if (is_int($image_id)) {
			$image_attr = array_merge($image_attr, (array)$attr);
			$image = wp_get_attachment_image($image_id, $size, false, $image_attr);
		}
		elseif (is_string($image_id)) {
			$image_attr['src'] = $image_id;
			$image_attr = array_merge($image_attr, (array)$attr);
			$image_attr = array_map('esc_attr', $image_attr);
			$image = '<img';
			foreach ($image_attr as $name => $value) {
				$image .= ' '.$name.'="'.$value.'"';
			}
			$image .= ' />';
		}
		return $image;
	}
}
if (!function_exists('the_page_thumbnail')) {
	function the_page_thumbnail($id = null, $size = 'thumbnail', $custom_logo = false, $attr = array()) {
		echo get_the_page_thumbnail($id, $size, $custom_logo, $attr);
	}
}
?>