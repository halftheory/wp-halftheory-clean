<?php
if ( isset($_SERVER['HTTP_HOST']) ) {
	if ( strpos($_SERVER['HTTP_HOST'], 'local') === false ) {
		// Exit if accessed directly.
		defined('ABSPATH') || exit;
	}
}

/* functions */

if ( ! function_exists('is_true') ) {
	function is_true( $value ) {
		if ( is_bool($value) ) {
			return $value;
		} elseif ( is_numeric($value) ) {
			if ( (int) $value === 1 ) {
				return true;
			} elseif ( (int) $value === 0 ) {
				return false;
			}
		} elseif ( is_string($value) ) {
			if ( $value === '1' || $value === 'true' ) {
				return true;
			} elseif ( $value === '0' || $value === 'false' ) {
				return false;
			}
		} elseif ( empty($value) ) {
			return false;
		}
		return false;
	}
}

if ( ! function_exists('empty_notzero') ) {
	function empty_notzero( $value ) {
		if ( is_numeric($value) ) {
			if ( (int) $value === 0 ) {
				return false;
			}
		}
		if ( empty($value) ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists('make_array') ) {
	function make_array( $str = '', $sep = ',' ) {
		if ( is_array($str) ) {
			return $str;
		}
		if ( empty_notzero($str) ) {
			return array();
		}
		$arr = explode($sep, $str);
		$arr = array_map('trim', $arr);
		$arr = array_filter($arr,
			function ( $v ) {
				return ! empty_notzero($v);
			}
		);
		return $arr;
	}
}

if ( ! function_exists('array_value_unset') ) {
	function array_value_unset( $arr = array(), $value = '', $removals = -1 ) {
		$arr = make_array($arr);
		if ( empty($arr) ) {
			return $arr;
		}
		if ( $removals >= 1 ) {
			$i = 0;
			while ( $i < $removals && in_array($value, $arr, true) ) {
				$key = array_search($value, $arr, true);
				unset($arr[ $key ]);
				$i++;
			}
		} else {
			// remove all.
			$arr = array_diff($arr, array( $value ));
		}
		return $arr;
	}
}

if ( ! function_exists('in_array_int') ) {
	function in_array_int( $needle, $haystack = array(), $strict = true ) {
		$func = function ( $v ) {
			return (int) $v;
		};
		$haystack = array_map($func, make_array($haystack));
		return in_array( (int) $needle, $haystack, $strict);
	}
}

if ( ! function_exists('is_user_logged_in_cookie') ) {
	function is_user_logged_in_cookie() {
		if ( function_exists('is_user_logged_in') ) {
			return is_user_logged_in();
		}
		if ( ! isset($_COOKIE) ) {
			return false;
		}
		if ( empty($_COOKIE) ) {
			return false;
		}
		foreach ( $_COOKIE as $key => $value ) {
			if ( strpos($key, 'wordpress_logged_in_') !== false ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists('get_visitor_ip') ) {
	function get_visitor_ip() {
		$ip = false;
		if ( is_localhost() ) {
			return $ip;
		}
		if ( getenv('HTTP_CLIENT_IP') && stripos(getenv('HTTP_CLIENT_IP'), 'unknown') === false ) {
			$ip = getenv('HTTP_CLIENT_IP');
		} elseif ( getenv('HTTP_X_FORWARDED_FOR') && stripos(getenv('HTTP_X_FORWARDED_FOR'), 'unknown') === false ) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif ( getenv('REMOTE_ADDR') && stripos(getenv('REMOTE_ADDR'), 'unknown') === false ) {
			$ip = getenv('REMOTE_ADDR');
		} elseif ( isset($_SERVER['REMOTE_ADDR']) ) {
			if ( $_SERVER['REMOTE_ADDR'] && stripos($_SERVER['REMOTE_ADDR'], 'unknown') === false ) {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
		}
		return $ip;
	}
}

if ( ! function_exists('get_current_uri') ) {
	function get_current_uri( $keep_query = false ) {
	 	$res  = is_ssl() ? 'https://' : 'http://';
	 	$res .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
	 	$res .= isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
		if ( wp_doing_ajax() && isset($_SERVER['HTTP_REFERER']) ) {
			if ( ! empty($_SERVER["HTTP_REFERER"]) ) {
				$res = $_SERVER["HTTP_REFERER"];
			}
		}
		if ( ! $keep_query ) {
			$remove = array();
			if ( $str = wp_parse_url($res, PHP_URL_QUERY) ) {
				$remove[] = '?' . $str;
			}
			if ( $str = wp_parse_url($res, PHP_URL_FRAGMENT) ) {
				$remove[] = '#' . $str;
			}
			$res = str_replace($remove, '', $res);
		}
		return $res;
	}
}

if ( ! function_exists('get_url_path') ) {
	function get_url_path( $str = '' ) {
		$replace_arr = array(
			set_url_scheme(home_url(), 'http'),
			set_url_scheme(home_url(), 'https'),
		);
		return trim( str_replace($replace_arr, '', $str), '/ ' );
	}
}

if ( ! function_exists('is_front_end') ) {
	function is_front_end() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			if ( strpos(get_current_uri(), admin_url()) !== false ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists('is_login_page') ) {
	function is_login_page() {
		$res = false;
		$wp_login = 'wp-login.php';
		if ( defined('WP_LOGIN_SCRIPT') ) {
			$wp_login = WP_LOGIN_SCRIPT;
		}
		if ( $GLOBALS['pagenow'] === $wp_login ) {
			$res = true;
		} elseif ( isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], $wp_login) !== false ) {
			$res = true;
		} elseif ( in_array(ABSPATH . $wp_login, get_included_files(), true) ) {
			$res = true;
		}
		if ( ! $res && function_exists('wp_login_url') && function_exists('get_current_uri') ) {
			if ( wp_login_url() === get_current_uri() ) {
				$res = true;
			}
		}
		return apply_filters('is_login_page', $res);
	}
}
if ( ! function_exists('is_signup_page') ) {
	function is_signup_page() {
		$res = false;
		// wp-register.php only for backward compat.
		if ( $GLOBALS['pagenow'] === 'wp-signup.php' ) {
			$res = true;
		} elseif ( $GLOBALS['pagenow'] === 'wp-register.php' ) {
			$res = true;
		} elseif ( isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'wp-signup.php') !== false ) {
			$res = true;
		} elseif ( isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'wp-register.php') !== false ) {
			$res = true;
		} elseif ( in_array(ABSPATH . 'wp-signup.php', get_included_files(), true) ) {
			$res = true;
		} elseif ( in_array(ABSPATH . 'wp-register.php', get_included_files(), true) ) {
			$res = true;
		}
		if ( ! $res && function_exists('wp_registration_url') ) {
			if ( wp_registration_url() === get_current_uri() ) {
				$res = true;
			}
		}
		return apply_filters('is_signup_page', $res);
	}
}
if ( ! function_exists('is_home_page') ) {
	function is_home_page( $post_id = null ) {
		$res = false;
		if ( ! is_null($post_id) ) {
			if ( get_option('show_on_front') === 'page' && (int) get_option('page_on_front') === $post_id ) {
				$res = true;
			}
		} elseif ( is_front_page() && ! is_login_page() && ! is_signup_page() ) {
			$res = true;
		}
		return apply_filters('is_home_page', $res, $post_id);
	}
}
if ( ! function_exists('is_posts_page') ) {
	function is_posts_page( $post_id = null ) {
		$res = false;
		if ( is_tax() || is_tag() || is_category() ) {
			$res = false;
		} elseif ( ! is_null($post_id) ) {
			if ( (int) get_posts_page_id() === $post_id ) {
				$res = true;
			}
		} else {
			global $wp_query;
			if ( $wp_query->is_posts_page ) {
				$res = true;
			} elseif ( $wp_query->is_home && get_option('show_on_front') === 'posts' ) {
				$res = true;
			}
		}
		return apply_filters('is_posts_page', $res, $post_id);
	}
}

if ( ! function_exists('get_posts_page_id') ) {
	function get_posts_page_id() {
		$id = 0;
		global $wp_query;
		if ( $wp_query->is_posts_page ) {
			$id = isset($wp_query->queried_object_id) ? (int) $wp_query->queried_object_id : $id;
		} elseif ( $wp_query->is_home && get_option('show_on_front') === 'posts' ) {
			$id = isset($wp_query->queried_object_id) ? (int) $wp_query->queried_object_id : $id;
		} else {
			$id = (int) get_option('page_for_posts');
		}
		return apply_filters('get_posts_page_id', $id);
	}
}

if ( ! function_exists('is_localhost') ) {
	function is_localhost() {
		if ( isset($_SERVER['HTTP_HOST']) ) {
			if ( strpos($_SERVER['HTTP_HOST'], 'localhost') === 0 || strpos($_SERVER['HTTP_HOST'], '.local') !== false ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists('is_slug_current') ) {
	function is_slug_current( $slugs, $url = '' ) {
		if ( empty($slugs) ) {
			return false;
		}
		if ( empty($url) ) {
			$url = get_current_uri();
		}
		$slugs = make_array($slugs, '/');
		$slugs = array_filter($slugs);
		foreach ( $slugs as $value ) {
			if ( ! preg_match("/[\w]+/i", $value) ) {
				continue;
			}
			if ( $value === $url ) {
				return true;
			}
			if ( strpos($url, $value) !== false ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists('has_filter_extended') ) {
	function has_filter_extended( $tag, $function_to_check = false ) {
		global $wp_filter;
		if ( ! isset($wp_filter[ $tag ]) ) {
			return false;
		}
		$res = $wp_filter[ $tag ]->has_filter($tag, $function_to_check);
		// check for class names.
		if ( $res === false && is_array($function_to_check) ) {
			if ( count($function_to_check) > 1 ) {
				// clear the keys.
				$function_to_check = array_values($function_to_check);
				if ( is_string($function_to_check[0]) && is_string($function_to_check[1]) ) {
					if ( method_exists($function_to_check[0], $function_to_check[1]) ) {
						foreach ( $wp_filter[ $tag ]->callbacks as $priority => $callbacks ) {
							foreach ( $callbacks as $function_key => $callback ) {
								if ( ! is_array($callback) ) {
									continue;
								}
								if ( ! isset($callback['function']) ) {
									continue;
								}
								if ( ! is_array($callback['function']) ) {
									continue;
								}
								if ( count($callback['function']) < 2 ) {
									continue;
								}
								if ( is_object($callback['function'][0]) && is_string($callback['function'][1]) ) {
									if ( is_a($callback['function'][0], $function_to_check[0]) && $callback['function'][1] === $function_to_check[1] ) {
										return $priority;
									}
								}
							}
						}
					}
				}
			}
		}
		return $res;
	}
}

if ( ! function_exists('get_filter_next_priority') ) {
	function get_filter_next_priority( $tag, $priority_start = 10 ) {
		global $wp_filter;
		$i = $priority_start;
		if ( isset($wp_filter[ $tag ]) ) {
			while ( $wp_filter[ $tag ]->offsetExists($i) === true ) {
				$i++;
			}
		}
		return $i;
	}
}

if ( ! function_exists('url_exists') ) {
	function url_exists( $url = '' ) {
		if ( empty($url) ) {
			return false;
		}
		$arr = @get_headers($url);
		if ( $arr === false || ! is_array($arr) ) {
			return false;
		}
		// array could be indexed or associative.
		reset($arr);
		if ( strpos(current($arr), '404 Not Found') !== false ) {
			return false;
		} elseif ( strpos(current($arr), '301 Moved Permanently') !== false ) {
			// maybe 404 is hiding in next header.
			$http_count = 0;
			$max = 2;
			foreach ( $arr as $value ) {
				if ( strpos($value, 'HTTP/') === 0 ) {
					$http_count++;
					if ( $http_count > $max ) {
						break;
					}
					if ( strpos($value, '404 Not Found') !== false ) {
						return false;
					}
				}
			}
		}
		return true;
	}
}

if ( ! function_exists('get_urls') ) {
	function get_urls( $content = '', $scheme = 'http' ) {
		if ( ! preg_match('#(^|\s|>)https?://#i', $content) ) {
			return false;
		}
		$urls = array();
		// Find URLs on their own line.
		if ( preg_match_all('|^(\s*)(https?://[^\s<>"\']+)(\s*)$|im', $content, $matches) ) {
			if ( $matches[2] ) {
				$urls = array_merge($urls, $matches[2]);
			}
		}
		// Find URLs in their own paragraph.
		if ( preg_match_all('|(<p(?: [^>]*)?>\s*)(https?://[^\s<>"\']+)(\s*<\/p>)|i', $content, $matches) ) {
			if ( $matches[2] ) {
				$urls = array_merge($urls, $matches[2]);
			}
		}
		if ( empty($urls) ) {
			return false;
		}
		$urls = set_url_scheme_array($urls, $scheme);
		$urls = array_unique($urls);
		return $urls;
	}
}

if ( ! function_exists('wp_redirect_extended') ) {
	function wp_redirect_extended( $location, $status = 302, $x_redirect_by = false ) {
		if ( headers_sent() ) {
			?>
	<script type="text/javascript"><!--
	setTimeout("window.location.href = '<?php echo esc_url($location); ?>'",0);
	//--></script>
			<?php
			return true;
		} elseif ( function_exists('wp_redirect') ) {
			return wp_redirect($location, $status, $x_redirect_by);
		} else {
			header('Location: ' . $location, true, $status);
			return true;
		}
	}
}

// replaces old 'get_file_contents' function.
if ( ! function_exists('file_get_contents_extended') ) {
	function file_get_contents_extended( $filename = '' ) {
		if ( empty($filename) ) {
			return false;
		}
		$is_url = false;
		if ( strpos($filename, 'http') === 0 ) {
			if ( function_exists('url_exists') ) {
				if ( url_exists($filename) === false ) {
					return false;
				}
			}
			$is_url = true;
		}
		$str = '';
		// use user_agent when available.
		$user_agent = 'PHP' . phpversion() . '/' . __FUNCTION__;
		if ( isset($_SERVER['HTTP_USER_AGENT']) ) {
			if ( ! empty($_SERVER['HTTP_USER_AGENT']) ) {
				$user_agent = $_SERVER['HTTP_USER_AGENT'];
			}
		}
		// try php.
		$options = array( 'http' => array( 'user_agent' => $user_agent ) );
		// try 'correct' way.
		if ( $str_php = @file_get_contents($filename, false, stream_context_create($options)) ) {
			$str = $str_php;
		}
		// try 'insecure' way.
		if ( empty($str) ) {
			$options['ssl'] = array(
				'verify_peer' => false,
				'verify_peer_name' => false,
			);
			if ( $str_php = @file_get_contents($filename, false, stream_context_create($options)) ) {
				$str = $str_php;
			}
		}
		// try curl.
		if ( empty($str) && $is_url) {
			if ( function_exists('curl_init') ) {
				$c = @curl_init();
				// try 'correct' way.
				curl_setopt($c, CURLOPT_URL, $filename);
                curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($c, CURLOPT_MAXREDIRS, 10);
                $str = curl_exec($c);
                // try 'insecure' way.
                if ( empty($str) ) {
                    curl_setopt($c, CURLOPT_URL, $filename);
                    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($c, CURLOPT_USERAGENT, $user_agent);
                    $str = curl_exec($c);
                }
				curl_close($c);
			}
		}
		if ( function_exists('fix_potential_html_string') ) {
			$str = fix_potential_html_string($str);
		}
		if ( function_exists('trim_excess_space') ) {
			$str = trim_excess_space($str);
		}
		if ( empty($str) ) {
			return false;
		}
		return $str;
	}
}

if ( ! function_exists('get_fresh_file_or_transient_contents') ) {
	function get_fresh_file_or_transient_contents( $localfile_or_transient, $remotefile_or_callback, $expiration = '1 week', $force_refresh = false ) {
		// NOTE: $expiration is 0, string, or time().
		$str = '';
		$refresh = false;
		$is_transient = false;
		// 1. get current string + decide if we should refresh
		// transient.
		// TODO: more ideal transient detection?
		if ( ! is_file($localfile_or_transient) && strpos($localfile_or_transient, '/') === false && strpos($localfile_or_transient, '.') === false ) {
			$is_transient = true;
			$localfile_or_transient = substr($localfile_or_transient, 0, 172);
			$str_new = get_transient($localfile_or_transient);
			if ( $str_new !== false ) {
				$str = $str_new;
				if ( $expiration !== 0 ) {
					$expiration_check = false;
					if ( is_string($expiration) ) {
						$expiration_check = strtotime('-' . trim($expiration, ' -+'));
					} elseif ( is_numeric($expiration) ) {
						$expiration_check = (int) $expiration;
					}
					if ( $expiration_check ) {
						$timeout = get_option('_transient_timeout_' . $localfile_or_transient);
						if ( is_numeric($timeout) && $timeout !== false ) {
							if ( (int) $timeout < $expiration_check ) {
								$refresh = true;
							}
						}
					}
				}
			} else {
				$refresh = true;
			}
		} elseif ( is_file($localfile_or_transient) ) {
			// file.
			if ( function_exists('file_get_contents_extended') ) {
				$str_new = file_get_contents_extended($localfile_or_transient);
			} else {
				$str_new = @file_get_contents($localfile_or_transient);
			}
			if ( $str_new !== false ) {
				$str = $str_new;
				if ( $expiration !== 0 ) {
					$expiration_check = false;
					if ( is_string($expiration) ) {
						$expiration_check = strtotime('-' . trim($expiration, ' -+'));
					} elseif ( is_numeric($expiration) ) {
						$expiration_check = (int) $expiration;
					}
					if ( $expiration_check ) {
						if ( filemtime($localfile_or_transient) < $expiration_check ) {
							$refresh = true;
						}
					}
				}
			} else {
				$refresh = true;
			}
		} else {
			$refresh = true;
		}
		// 2. get new
		if ( $refresh || $force_refresh ) {
			// callback function.
			if ( is_callable($remotefile_or_callback) ) {
				$str_new = $remotefile_or_callback();
			} else {
				// file, url.
				if ( function_exists('file_get_contents_extended') ) {
					$str_new = file_get_contents_extended($remotefile_or_callback);
				} else {
					$str_new = @file_get_contents($remotefile_or_callback);
				}
			}
			// success - save.
			if ( $str_new !== false ) {
				$str = $str_new;
				// set transient.
				if ( $is_transient ) {
					$expiration_transient = false;
					if ( $expiration === 0 ) {
						$expiration_transient = 0;
					} elseif ( is_string($expiration) ) {
						$expiration_transient = strtotime('+' . trim($expiration, ' -+')) - time();
					} elseif ( is_numeric($expiration) ) {
						$expiration_transient = (int) $expiration - time();
					}
					if ( ! $expiration_transient || $expiration_transient < 0 ) {
						$expiration_transient = 0;
					}
					set_transient($localfile_or_transient, $str, $expiration_transient);
				} else {
					// put file.
					$dirname = dirname($localfile_or_transient);
					if ( ! empty($dirname) && $dirname !== $localfile_or_transient ) {
						if ( $dirname === '.' ) {
							$dirname = __DIR__;
						}
						@chmod($dirname, 0777);
					}
					if ( @file_put_contents($localfile_or_transient, $str) ) {
						@chmod($localfile_or_transient, 0777);
					}
				}
			}
		}
		return $str;
	}
}

if ( ! function_exists('get_the_excerpt_fallback') ) {
	function get_the_excerpt_fallback( $str = '', $post = null ) {
		if ( ! empty_notzero($str) ) {
			return $str;
		}
		$post_id = null;
		if ( is_object($post) && isset($post->ID) ) {
			$post_id = $post->ID;
		} elseif ( is_numeric($post) && ! empty($post) ) {
			$post_id = (int) $post;
		} elseif ( in_the_loop() ) {
			$post_id = get_the_ID();
		}
		if ( empty($post_id) ) {
			return $str;
		}
		$func = function ( $post_id = 0 ) {
			// content.
			$str = get_the_content('', false, $post_id);
			if ( ! empty_notzero(trim(strip_shortcodes($str))) ) {
				return $str;
			}
			// children.
			$post_types = array_values(get_post_types(array( 'public' => true ), 'names' ));
			$remove = array( 'attachment', 'revision', 'rl_gallery' );
			$post_types = array_values(array_diff($post_types, $remove));
			$args = array(
				'post_parent' => $post_id,
				'orderby' => 'menu_order',
				'order' => 'ASC',
				'post_status' => array( 'publish', 'inherit' ),
				'post_type' => $post_types,
				'fields' => 'ids',
				'nopaging' => true,
			);
			$arr = get_children( $args );
			if ( ! empty($arr) ) {
				$func_title = function ( $v ) {
					return get_the_title($v);
				};
				$arr = array_map($func_title, $arr);
				return implode(', ', $arr) . '.';
			}
			// taxonomies.
			$arr = get_the_taxonomies($post_id, array( 'template' => __('###%s###%l'), 'term_template' => '%2$s' ));
			if ( ! empty($arr) ) {
				$func_striptax = function ( $str = '' ) {
					$str = preg_replace("/^###[^#]*###/i", '', $str);
					if ( is_title_bad($str, array( 'Blog', 'blog' )) ) {
						return '';
					}
					return $str;
				};
				$arr = array_map($func_striptax, $arr);
				$arr = array_filter($arr);
				if ( ! empty($arr) ) {
					return implode(', ', $arr) . '.';
				}
			}
			return $str;
		};
		$res = $func($post_id);
		return apply_filters('get_the_excerpt_fallback', $res, $post_id);
	}
}
if ( ! function_exists('get_the_excerpt_filtered') ) {
	function get_the_excerpt_filtered( $str_or_post, $length = null, $args = null ) {
		$str = '';
		if ( empty($str_or_post) ) {
			return $str;
		}
		// add filters.
		$func = function ( $res = true ) {
			return true;
		};
		add_filter('the_excerpt_conditions', $func);
		if ( ! is_null($length) ) {
			$func_length = function ( $value ) use ( $length ) {
				return $length;
			};
			add_filter('get_excerpt_length', $func_length);
		}
		if ( ! is_null($args) ) {
			$func_args = function ( $value ) use ( $args ) {
				return $args;
			};
			add_filter('get_excerpt_args', $func_args);
		}
		if ( is_numeric($str_or_post) || is_object($str_or_post) ) {
			// post.
			if ( ! in_the_loop() ) {
				setup_postdata($str_or_post);
			}
			$str = get_the_excerpt($str_or_post);
			// fallback to content.
			if ( empty_notzero($str) ) {
				if ( function_exists('get_the_excerpt_fallback') ) {
					$str = get_the_excerpt_fallback($str, $str_or_post);
				} else {
					$str = get_the_content('', false, $str_or_post);
				}
				$str = apply_filters('get_the_excerpt', $str);
			}
		} else {
			// string.
			$str = apply_filters('get_the_excerpt', $str_or_post);
		}
		// don't need this for now.
		// $str = apply_filters('the_excerpt', $str);
		// remove filters.
		remove_filter('the_excerpt_conditions', $func);
		if ( ! is_null($length) ) {
			remove_filter('get_excerpt_length', $func_length);
		}
		if ( ! is_null($args) ) {
			remove_filter('get_excerpt_args', $func_args);
		}
		return $str;
	}
}
if ( ! function_exists('get_the_content_filtered') ) {
	function get_the_content_filtered( $str = '' ) {
		if ( empty($str) ) {
			return $str;
		}
		$func = function ( $res = true ) {
			return true;
		};
		add_filter('the_content_conditions', $func);
		$str = apply_filters('the_content', $str);
		$str = str_replace(']]>', ']]&gt;', $str);
		remove_filter('the_content_conditions', $func);
		return $str;
	}
}
if ( ! function_exists('the_excerpt_conditions') ) {
	function the_excerpt_conditions( $str = '' ) {
		$res = true;
		if ( empty($str) ) {
			$res = false;
		}
		if ( did_action('get_header') === 0 && ! wp_doing_ajax() && ! is_feed() ) {
			$res = false;
		}
		if ( is_404() ) {
			$res = false;
		}
		if ( function_exists('is_signup_page') ) {
			if ( is_signup_page() ) {
				$res = false;
			}
		}
		if ( function_exists('is_signup_page') ) {
			if ( is_login_page() ) {
				$res = false;
			}
		}
		if ( ! in_the_loop() ) {
			// allow term_description().
			if ( ! is_tax() && ! is_tag() && ! is_category() ) {
				$res = false;
			}
		}
		return apply_filters('the_excerpt_conditions', $res);
	}
}
if ( ! function_exists('the_content_conditions') ) {
	function the_content_conditions( $str = '' ) {
		$res = true;
		if ( empty($str) ) {
			$res = false;
		}
		if ( did_action('get_header') === 0 && ! wp_doing_ajax() && ! is_feed() ) {
			$res = false;
		}
		if ( is_404() ) {
			$res = false;
		}
		if ( function_exists('is_signup_page') ) {
			if ( is_signup_page() ) {
				$res = false;
			}
		}
		if ( function_exists('is_signup_page') ) {
			if ( is_login_page() ) {
				$res = false;
			}
		}
		if ( ! is_main_query() && ! wp_doing_ajax() ) {
			$res = false;
		}
		if ( ! in_the_loop() && current_filter() === 'the_content' ) {
			// allow term_description().
			if ( ! is_tax() && ! is_tag() && ! is_category() ) {
				$res = false;
			}
		}
		if ( ! is_singular() ) {
			if ( ! is_tax() && ! is_tag() && ! is_category() && ! is_posts_page() && ! is_search() ) {
				$res = false;
			}
		}
		return apply_filters('the_content_conditions', $res);
	}
}

if ( ! function_exists('set_url_scheme_blob') ) {
	function set_url_scheme_blob( $str = '', $scheme = null ) {
		if ( strpos($str, 'http') === false ) {
			return $str;
		}
		// find scheme.
		if ( ! $scheme ) {
			$scheme = is_ssl() ? 'https' : 'http';
		} elseif ( $scheme === 'admin' || $scheme === 'login' || $scheme === 'login_post' || $scheme === 'rpc' ) {
			$scheme = is_ssl() || force_ssl_admin() ? 'https' : 'http';
		} elseif ( $scheme !== 'http' && $scheme !== 'https' && $scheme !== 'relative' ) {
			$scheme = is_ssl() ? 'https' : 'http';
		}
		// replace.
		if ( $scheme === 'relative' ) {
			$str = preg_replace('#\w+://[^/]*#', '', $str);
		} else {
			$str = preg_replace('#\w+://#', $scheme . '://', $str);
		}
		return $str;
	}
}

if ( ! function_exists('set_url_scheme_array') ) {
	function set_url_scheme_array( $arr = array(), $scheme = null ) {
		$arr = make_array($arr);
		$func = function ( $str = '' ) use ( $scheme ) {
			return set_url_scheme($str, $scheme);
		};
		$arr = array_map($func, $arr);
		return $arr;
	}
}

if ( ! function_exists('fix_potential_html_string') ) {
	function fix_potential_html_string( $str = '' ) {
		if ( empty($str) ) {
			return $str;
		}
		if ( strpos($str, '&lt;') !== false ) {
			if ( substr_count($str, '&lt;') > substr_count($str, '<') || preg_match("/&lt;\/[\w]+&gt;/is", $str) ) {
				$str = html_entity_decode($str, ENT_NOQUOTES, 'UTF-8');
			}
		} elseif ( strpos($str, '&#039;') !== false ) {
			$str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
		}
		return $str;
	}
}

if ( ! function_exists('unwptexturize') ) {
	function unwptexturize( $str = '' ) {
		if ( empty($str) ) {
			return $str;
		}
		// reverse wptexturize.
		$replace = array(
			'&#8220;' => '"',
			'&#8221;' => '"',
			'&#8243;' => '"',
			'&#8217;' => "'",
			'&#8216;' => "'",
			'&#8242;' => "'",
			'&#8211;' => '-',
			'&#8212;' => '-',
		);
		$str = str_replace(array_keys($replace), $replace, $str);
		return $str;
	}
}

if ( ! function_exists('trim_excess_space') ) {
	function trim_excess_space( $str = '' ) {
		if ( empty($str) ) {
			return $str;
		}
		$replace_with_space = array( '&nbsp;', '&#160;', "\xc2\xa0" );
		$str = str_replace($replace_with_space, ' ', $str);

		if ( strpos($str, '</') !== false ) {
			// no space before closing tags.
			$str = preg_replace("/[\t\n\r ]*(<\/[^>]+>)/s", '$1', $str);
		}
		if ( strpos($str, '<br') !== false ) {
			// no br at start/end.
			$str = preg_replace("/^<br[\/ ]*>/s", '', $str);
			$str = preg_replace("/<br[\/ ]*>$/s", '', $str);
		}

		$str = preg_replace("/[\t ]*(\n|\r)[\t ]*/s", '$1', $str);
		$str = preg_replace("/(\n\r){3,}/s", '$1$1', $str);
		$str = preg_replace("/[\n]{3,}/s", "\n\n", $str);
		$str = preg_replace("/[ ]{2,}/s", ' ', $str);
		return trim($str);
	}
}

if ( ! function_exists('strip_single_tag') ) {
	function strip_single_tag( $str = '', $tag = '' ) {
        if ( empty($tag) ) {
            return $str;
        }
        if ( strpos($str, '<' . $tag) === false ) {
            return $str;
        }
        // has closing tag.
        $str = preg_replace("/[\s]*<$tag [^>]*>.*?<\/[ ]*$tag>[\s]*/is", '', $str);
        $str = preg_replace("/[\s]*<$tag>.*?<\/[ ]*$tag>[\s]*/is", '', $str);
        // no closing tag.
        $str = preg_replace("/[\s]*<$tag [^>]+>[\s]*/is", '', $str);
        $str = preg_replace("/[\s]*<" . $tag . "[ \/]*>[\s]*/is", '', $str);
        return $str;
	}
}

if ( ! function_exists('strip_tags_html_comments') ) {
	function strip_tags_html_comments( $str = '', $allowable_tags = '' ) {
		if ( empty($str) ) {
			return $str;
		}
		$replace = array(
			'<!--' => '###COMMENT_OPEN###',
			'-->' => '###COMMENT_CLOSE###',
		);
		$str = str_replace(array_keys($replace), $replace, $str);
		$str = strip_tags($str, $allowable_tags);
		$str = str_replace($replace, array_keys($replace), $str);
		return $str;
	}
}

if ( ! function_exists('strip_all_shortcodes') ) {
	function strip_all_shortcodes( $str = '' ) {
		$go = true;
		// inline scripts.
		if ( strpos($str, '<script') !== false ) {
			$go = false;
		}
		// [] inside html tag.
		if ( $go && preg_match("/<[a-z]+ [^>\[\]]+\[[^>]+>/is", $str) ) {
			$go = false;
		}
		if ( $go ) {
			// more than 4 letters.
			$str = preg_replace("/\[[^\]]{5,}\]/is", '', $str);
		}
		return $str;
	}
}

if ( ! function_exists('strip_shortcodes_extended') ) {
	function strip_shortcodes_extended( $str = '', $tagnames = array(), $strict = false ) {
		if ( empty($str) || empty($tagnames) ) {
			return $str;
		}
		$remove = $tagnames;
		if ( ! $strict ) {
			global $shortcode_tags;
			$shortcode_tags_keys = array_keys($shortcode_tags);
			foreach ( $tagnames as $value ) {
				foreach ( $shortcode_tags_keys as $v ) {
					if ( stripos($v, $value) !== false ) {
						$remove[] = $v;
					}
				}
			}
			$remove = array_unique($remove);
		}
		$pattern = get_shortcode_regex($remove);
		$str = preg_replace("/$pattern/", '', $str);
		return $str;
	}
}

if ( ! function_exists('strip_tags_attr') ) {
	function strip_tags_attr( $str = '', $allowable_tags_attr = array() ) {
		if ( empty($str) ) {
			return $str;
		}
		if ( function_exists('fix_potential_html_string') ) {
			$str = fix_potential_html_string($str);
		}
		if ( strpos($str, '<') === false ) {
			return $str;
		}
		// script/style tags - special case - remove all contents.
		$strip_all = array( 'script', 'style' );
        foreach ( $strip_all as $tag ) {
			if ( array_key_exists($tag, $allowable_tags_attr) ) {
                continue;
			}
			if ( function_exists('strip_single_tag') ) {
				$str = strip_single_tag($str, $tag);
			}
        }
		if ( empty($allowable_tags_attr) ) {
			return strip_tags($str);
		}
		$allowable_tags = '<' . implode('><', array_keys($allowable_tags_attr)) . '>';
		$str = strip_tags($str, $allowable_tags);
		$has_tags = false;
		foreach ( $allowable_tags_attr as $tag => $attr ) {
			if ( strpos($str, '<' . $tag . '>') !== false || strpos($str, '<' . $tag . ' ') !== false ) {
				$has_tags = true;
				break;
			}
		}
		if ( $has_tags === false ) {
			return $str;
		}
		if ( function_exists('trim_excess_space') ) {
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
			'basefont',
			'br',
			'col',
			'command',
			'embed',
			'frame',
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
		$dom = @DOMDocument::loadHTML('<' . $wrapper . '>' . mb_convert_encoding($str, 'HTML-ENTITIES', 'UTF-8') . '</' . $wrapper . '>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		$xpath = new DOMXPath($dom);
		#$tags = $xpath->query('//*');
		$tags = $xpath->query('//*[not(self::br)][not(descendant-or-self::pre)][not(descendant-or-self::script)][not(descendant-or-self::style)]');
		$domElemsToRemove = array();
		foreach ( $tags as $tag ) {
			$my_tag = $tag->tagName;
			if ( $my_tag === $wrapper ) {
				continue;
			}
			if ( ! array_key_exists($my_tag, $allowable_tags_attr) ) {
				continue;
			}
			if ( $allowable_tags_attr[ $my_tag ] === '*' ) {
				continue;
			}
			// remove empty, only for text-tags (probably).
			if ( in_array($my_tag, $text_tags, true) && ! in_array($my_tag, $void_tags, true) ) {
				if ( trim($tag->nodeValue) === '' && (int) $tag->childNodes->length === 0 && ( empty($allowable_tags_attr[ $my_tag ]) || (int) $tag->attributes->length === 0 ) ) {
					$domElemsToRemove[] = $tag;
					continue;
				}
			}
			if ( (int) $tag->attributes->length === 0 ) {
				continue;
			}
			// remove attr.
			if ( function_exists('make_array') ) {
				$allowable_tags_attr[ $my_tag ] = make_array($allowable_tags_attr[ $my_tag ]);
			} elseif ( ! is_array($allowable_tags_attr[ $my_tag ]) ) {
				$allowable_tags_attr[ $my_tag ] = explode(',', $allowable_tags_attr[ $my_tag ]);
				$allowable_tags_attr[ $my_tag ] = array_map('trim', $allowable_tags_attr[ $my_tag ]);
				$allowable_tags_attr[ $my_tag ] = array_filter($allowable_tags_attr[ $my_tag ]);
			}
			$remove = array();
			for ( $i = 0; $i < $tag->attributes->length; $i++ ) {
				$my_attr = $tag->attributes->item($i)->name;
				if ( ! in_array($my_attr, $allowable_tags_attr[ $my_tag ], true) ) {
					$remove[] = $my_attr;
				}
			}
			if ( ! empty($remove) ) {
				foreach ( $remove as $value ) {
					$tag->removeAttribute($value);
				}
			}
		}
		foreach ( $domElemsToRemove as $domElement ) {
			$domElement->parentNode->removeChild($domElement);
		}
		#$str = trim( strip_tags( html_entity_decode( $dom->saveHTML() ), $allowable_tags ) ); // conflicts with <3
		$str = trim( strip_tags( $dom->saveHTML(), $allowable_tags ) );
		if ( ! empty($domElemsToRemove) && function_exists('trim_excess_space') ) {
			$str = trim_excess_space($str);
		}
		// wp adds single space before closer, so we should match it.
		if ( preg_match_all("/(<(" . implode('|', $void_tags) . ") [^>]+)>/is", $str, $matches) ) {
			if ( ! empty($matches[0]) ) {
				foreach ( $matches[0] as $key => $value ) {
					$str = str_replace($value, rtrim($matches[1][ $key ], '/ ') . ' />', $str);
				}
			}
		}
		return $str;
	}
}

if ( ! function_exists('replace_tags') ) {
	function replace_tags( $str = '', $arr = array() ) {
		if ( empty($str) || empty($arr) ) {
			return $str;
		}
		if ( function_exists('fix_potential_html_string') ) {
			$str = fix_potential_html_string($str);
		}
		if ( strpos($str, '<') === false ) {
			return $str;
		}
		foreach ( $arr as $old => $new ) {
			if ( empty($new) ) {
				continue;
			}
			$str = preg_replace("/<" . $old . "([\/ ]*)>/is", "<" . $new . "$1>", $str);
			$str = preg_replace("/<" . $old . " ([^>]+)>/is", "<" . $new . " $1>", $str);
			$str = preg_replace("/<\/" . $old . " [^>]*>/is", "</" . $new . ">", $str);
			$str = preg_replace("/<\/" . $old . ">/is", "</" . $new . ">", $str);
		}
		return $str;
	}
}

if ( ! function_exists('get_excerpt') ) {
	function get_excerpt( $text = '', $length = 250, $args = array() ) {
		if ( empty($text) ) {
			return $text;
		}
		// resolve vars.
		$length = apply_filters('get_excerpt_length', $length);
		$args = apply_filters('get_excerpt_args', $args);
		$default_args = array(
			'allowable_tags' => array(),
			'plaintext' => false,
			'single_line' => true,
			'trim_title' => array(),
			'trim_urls' => true,
			'strip_shortcodes' => false,
			'strip_emails' => true,
			'strip_urls' => false,
			'add_dots' => true,
			'add_stop' => true,
		);
		if ( function_exists('make_array') ) {
			$args = make_array($args);
		}
		$args = array_merge($default_args, (array) $args);

		if ( function_exists('fix_potential_html_string') ) {
			$text = fix_potential_html_string($text);
		}
		// add a space for lines if needed.
		if ( $args['single_line'] && strpos($text, '<') !== false ) {
			$text = preg_replace("/(<p>|<p [^>]*>|<\/p>|<br[\/ ]*>)/is", '$1 ', $text);
		}
		// remove what we don't need.
		if ( function_exists('excerpt_remove_blocks') ) {
			$text = excerpt_remove_blocks($text);
		}
		if ( function_exists('make_array') ) {
			$args['allowable_tags'] = make_array($args['allowable_tags']);
		} elseif ( ! is_array($args['allowable_tags']) ) {
			$args['allowable_tags'] = (array) $args['allowable_tags'];
		}
		if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
			// script/style tags - special case - remove all contents.
			$strip_all = array( 'script', 'style' );
	        foreach ( $strip_all as $tag ) {
				if ( array_key_exists($tag, $args['allowable_tags']) ) {
	                continue;
				}
				if ( function_exists('strip_single_tag') ) {
					$text = strip_single_tag($text, $tag);
				}
	        }
			$args['allowable_tags'] = '<' . implode('><', (array) $args['allowable_tags']) . '>';
			$text = strip_tags($text, $args['allowable_tags']);
		} else {
			$text = wp_strip_all_tags($text, $args['single_line']);
		}
		if ( $args['strip_shortcodes'] && function_exists('strip_shortcodes') ) {
			$text = strip_shortcodes($text);
		}
		if ( function_exists('strip_all_shortcodes') ) {
			$text = strip_all_shortcodes($text);
		}
		if ( $args['strip_emails'] ) {
			$regex = '([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})';
			$text = preg_replace("#" . $regex . "[\s]*#is", '', $text);
		}
		if ( $args['strip_urls'] ) {
			$urls = wp_extract_urls(wp_specialchars_decode($text));
			if ( ! empty($urls) ) {
				$text = wp_specialchars_decode($text);
				$text = str_replace($urls, '', $text);
			}

		    $regex = "www\."; // SCHEME
		    $regex .= "([a-z0-9-.]*)\.([a-z]{2,13})"; // Host or IP
		    $regex .= "(:[0-9]{2,5})?"; // Port
		    $regex .= "(/([a-z0-9+\$_%-]\.?)+)*/?"; // Path
		    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+/\$_.-]*)?"; // GET Query
		    $regex .= "(\#[a-z_.-][a-z0-9+$%_.-]*)?"; // Anchor
			$text = preg_replace("#".$regex."[\s]*#is", "", $text);
		}
		if ( function_exists('unwptexturize') ) {
			$text = unwptexturize($text);
		}
		// remove repeating symbols, emojis.
        $no_repeat = array( "&lt;", "&gt;", "&amp;", "&ndash;", "&bull;", "&sect;", "&hearts;", "&hellip;", "...", "++", "--", "~~", "##", "**", "==", "__", "_ ", "//" );
		foreach ( $no_repeat as $value ) {
			if ( strpos($text, $value) !== false ) {
				$text = preg_replace("/(" . preg_quote($value, '/') . "[\s]*){2,}/s", '$1', $text);
			}
		}
		// no emojis.
		$text = preg_replace("/&#(8[0-9]{3}|9[0-9]{3}|1[0-9]{4});/s", '', $text);
		// remove excess space.
		if ( $args['single_line'] ) {
			$text = preg_replace("/[\r\n ]+/s", ' ', $text);
		}
		// no tabs.
		$text = preg_replace("/[\t]+/s", ' ', $text);
		if ( function_exists('trim_excess_space') ) {
			$text = trim_excess_space($text);
		}
		// trim the top.
		$regex_arr = array( "(<br[\/ ]*>)" );
		if ( function_exists('make_array') ) {
			$args['trim_title'] = make_array($args['trim_title']);
		} elseif ( ! is_array($args['trim_title']) ) {
			$args['trim_title'] = (array) $args['trim_title'];
		}
		if ( ! empty($args['trim_title']) ) {
			if ( function_exists('fix_potential_html_string') ) {
				$args['trim_title'] = array_map('fix_potential_html_string', $args['trim_title']);
			}
			$args['trim_title'] = array_map('trim', $args['trim_title']);
			$args['trim_title'] = array_unique($args['trim_title']);
			$args['trim_title'] = array_filter($args['trim_title']);
			foreach ( $args['trim_title'] as $value ) {
				if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
					$regex_arr[] = "<[^>]+>[\s]*" . $value;
				}
				$regex_arr[] = $value;
			}
		}
		if ( $args['trim_urls'] ) {
		    $regex = "((https?|ftp)://)"; // SCHEME
		    $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(:[a-z0-9+!*(),;?&=\$_.-]+)?@)?"; // User and Pass
		    $regex .= "([a-z0-9-.]*)\.([a-z]{2,13})"; // Host or IP
		    $regex .= "(:[0-9]{2,5})?"; // Port
		    $regex .= "(/([a-z0-9+\$_%-]\.?)+)*/?"; // Path
		    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+/\$_.-]*)?"; // GET Query
		    $regex .= "(#[a-z_.-][a-z0-9+$%_.-]*)?"; // Anchor
			if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
				$regex_arr[] = "<[^>]+>[\s]*".$regex;
			}
			$regex_arr[] = $regex;

		    $regex = "www\."; // SCHEME
		    $regex .= "([a-z0-9-.]*)\.([a-z]{2,13})"; // Host or IP
		    $regex .= "(:[0-9]{2,5})?"; // Port
		    $regex .= "(/([a-z0-9+\$_%-]\.?)+)*/?"; // Path
		    $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+/\$_.-]*)?"; // GET Query
		    $regex .= "(#[a-z_.-][a-z0-9+$%_.-]*)?"; // Anchor
			if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
				$regex_arr[] = "<[^>]+>[\s]*".$regex;
			}
			$regex_arr[] = $regex;
		}
		$i = 0;
		while ( $i < count($regex_arr) ) {
			$i = 0;
			foreach ( $regex_arr as $value ) {
				$replaced = false;
				// probably titles.
				if ( strpos($text, $value) === 0 ) {
					$len_res = mb_strlen($text);
					$len_value = mb_strlen($value);
					if ( $len_res > $len_value ) {
						$text = preg_replace("/^" . preg_quote($value, '/') . "[\s]*/is", '', $text);
						$replaced = true;
					}
				} elseif ( preg_match("~^[\s]*$value~i", $text, $match) ) {
					// probably urls.
					$len_res = mb_strlen($text);
					$len_value = mb_strlen($match[0]);
					if ( $len_res > $len_value ) {
						$text = preg_replace("/^[\s]*" . preg_quote($match[0], '/') . "[\s]*/is", '', $text);
						$replaced = true;
					}
				}
				if ( ! $replaced ) {
					$i++;
				}
			}
		}
		// correct length.
		$last_char = "\w" . preg_quote(":;@&%=+$?_.-#/>",'/');
		// TODO: find a fast way of checking multibyte strings here.
		if ( strlen(strip_tags($text)) <= $length ) {
			if ( $args['add_stop'] && ! empty(strip_tags($text)) ) {
				$text = preg_replace("/[^$last_char]+[\s]*$/is", '', $text);
				$text = rtrim($text, '. ') . '.';
			}
			if ( $args['plaintext'] ) {
				return $text;
			}
		} else {
			// reduce length.
			$length_new = $length;
			if ( $args['plaintext'] && ! preg_match("/[^$last_char]/is", mb_substr($text, $length, 1)) ) {
				$length_new = mb_strrpos( mb_substr($text, 0, $length), ' ');
			} elseif ( ! preg_match("/[^$last_char]/is", mb_substr($text, $length, 1)) ) {
				$length_new = mb_strrpos( mb_substr($text, 0, $length), ' ');
			}
			$text = mb_substr($text, 0, $length_new, 'UTF-8') . ' ' . wp_trim_words(mb_substr($text, $length_new, null, 'UTF-8'), 1, '');
			// check if we cut in the middle of a tag.
			if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
				$tags = trim( str_replace('><', '|', $args['allowable_tags']), '><');
				$text = preg_replace("/^(.+)<($tags) [^>]+$/is", '$1', $text);
				if ( function_exists('force_balance_tags') ) {
					$text = force_balance_tags($text);
				}
			}
			if ( $args['add_dots'] && ! empty(strip_tags($text)) ) {
				$text = preg_replace("/[^$last_char]+[\s]*$/is", '', $text);
				// add a space if the last word is a url - avoids conflicts with make_clickable.
				if ( $arr = preg_split("/[\s,;]+/s", $text) ) {
					if ( strpos(end($arr), 'http') === 0 ) {
						$text .= ' ';
					} elseif ( function_exists('make_clickable') ) {
						if ( end($arr) !== make_clickable(end($arr)) ) {
							$text .= ' ';
						}
					}
				}
				if ( $args['plaintext'] ) {
					$text .= __('...');
				} else {
					$text .= __('&hellip;');
				}
			} elseif ( $args['add_stop'] && ! empty(strip_tags($text)) ) {
				$text = preg_replace("/[^$last_char]+[\s]*$/is", '', $text);
				$text = rtrim($text, '. ') . '.';
			}
		}
		// add line breaks?
		if ( $args['single_line'] === false && $args['plaintext'] === false && strpos($text, '<br') === false ) {
			$text = nl2br($text);
			// TODO: cleanup br tags directly next to p tags.
		}
		// close open tags.
		if ( ! empty($args['allowable_tags']) && $args['plaintext'] === false ) {
			if ( function_exists('force_balance_tags') ) {
				$text = force_balance_tags($text);
			} else {
				// puts plaintext in a <p>.
				$dom = @DOMDocument::loadHTML( mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8') );
				$text = trim( strip_tags( html_entity_decode( $dom->saveHTML() ), $args['allowable_tags'] ) );
			}
		}
		return $text;
	}
}

if ( ! function_exists('get_site_logo_url_from_site_icon') ) {
	function get_site_logo_url_from_site_icon( $size = 'full', $blog_id = 0 ) {
		if ( ! has_site_icon($blog_id) ) {
			return false;
		}
		$size_icon = $size;
		if ( ! is_int($size_icon) ) {
			$size_icon = 512;
		}
		// max 512x512.
		$url = get_site_icon_url($size_icon, '', $blog_id);
		if ( strpos($url, 'cropped-') !== false ) {
			$names = array(
				preg_replace("/^.*?cropped-(.*)$/i", '$1', $url),
				preg_replace("/^.*?cropped-([^\.]*).*$/i", '$1', $url),
			);
			$names = array_merge($names, array_map('sanitize_title', $names) );
			$names = array_unique($names);
			$parent = get_posts(
				array(
					'no_found_rows' => true,
					'post_type' => 'attachment',
					'numberposts' => 1,
					'exclude' => (array) get_option('site_icon'),
					'post_name__in' => $names,
				)
			);
			if ( ! empty($parent) ) {
				$url = wp_get_attachment_image_url($parent[0]->ID, $size);
			}
		}
		return $url;
	}
}

if ( ! function_exists('link_terms') ) {
	function link_terms( $str = '', $links = array(), $args = array() ) {
		if ( empty($str) ) {
			return $str;
		}
		$text_tags = array(
			'b',
			'blockquote',
			'br',
			'del',
			'div',
			'em',
			'i',
			'p',
			'strong',
			'u',
		);
		$defaults = array(
			'limit' => 1,
			'count_existing_links' => true,
			'in_html_tags' => $text_tags,
			'exclude_current_uri' => true,
			'minify' => true,
		);
		$args = wp_parse_args($args, $defaults);
		$args['limit'] = (int) $args['limit'];
		$args['in_html_tags'] = make_array($args['in_html_tags']);
		$current_uri = ! empty($args['exclude_current_uri']) ? get_current_uri() : '';
		$count_key = '###COUNT###';
		$wptext_functions = array(
			'wptexturize',
			'convert_smilies',
			'convert_chars',
		);
		$sort_longest_first = function ( $a, $b ) {
    		return strlen($b) - strlen($a);
		};

		// get all term/link pairs.
		$links = apply_filters('link_terms_links_before', $links, $str, $args, $count_key);
		if ( empty($links) ) {
			return $str;
		}
		foreach ( $links as $k => $v ) {
			if ( $v === $current_uri || esc_url($v) === $current_uri ) {
				unset($links[ $k ]);
				continue;
			}
			// unlimited - single level array.
			if ( $args['limit'] === -1 ) {
				$links[ $k ] = '<a href="' . esc_url($v) . '">' . esc_html($k) . '</a>';
				$k_wp = $k;
				foreach ( $wptext_functions as $func ) {
					$k_wp = $func($k_wp);
					if ( ! isset($links[ $k_wp ]) ) {
						$links[ $k_wp ] = '<a href="' . esc_url($v) . '">' . esc_html($k_wp) . '</a>';
					}
				}
			} else {
				$links[ $k ] = array(
					$count_key => 0,
					$k => '<a href="' . esc_url($v) . '">' . esc_html($k) . '</a>',
				);
				$k_wp = $k;
				foreach ( $wptext_functions as $func ) {
					$k_wp = $func($k_wp);
					if ( ! isset($links[ $k ][ $k_wp ]) ) {
						$links[ $k ][ $k_wp ] = '<a href="' . esc_url($v) . '">' . esc_html($k_wp) . '</a>';
					}
				}
				// longest key first.
				uasort($links[ $k ], $sort_longest_first);
				// existing links.
				if ( ! empty($args['count_existing_links']) && strpos($str, esc_url($v)) !== false ) {
					if ( preg_match_all("/<a [^>]*?href=\"" . preg_quote(esc_url($v), '/') . "\"/is", $str, $matches) ) {
						$links[ $k ][ $count_key ] = count($matches);
					}
				}
			}
		}
		$links = apply_filters('link_terms_links_after', $links, $str, $args, $count_key);
		if ( empty($links) ) {
			return $str;
		}
		if ( $args['limit'] >= 1 ) {
			// longest key first - not needed with strtr.
			$links_keys = array_keys($links);
			usort($links_keys, $sort_longest_first);
			$links_old = $links;
			$links = array();
			foreach ( $links_keys as $key ) {
				$links[ $key ] = $links_old[ $key ];
			}
			unset($links_keys);
			unset($links_old);
		}

		// find / replace.
		$textarr = wp_html_split($str);
		$link_open = false;
		$changed = false;
		// Loop through delimiters (elements) only.
		for ( $i = 0, $c = count($textarr); $i < $c; $i += 2 ) {
			// check the previous tag.
			if ( $i > 0 ) {
				// skip link text.
				if ( strpos($textarr[ $i - 1 ], '<a ') === 0 ) {
					$link_open = true;
					continue;
				} elseif ( strpos($textarr[ $i - 1 ], '</a>') === 0 ) {
					// after a link is fine.
					$link_open = false;
				} elseif ( ! empty($args['in_html_tags']) ) {
					if ( ! preg_match("/^<(" . implode('|', $args['in_html_tags']) . ")( |\/|>)/is", $textarr[ $i - 1 ]) ) {
						continue;
					}
				}
			}
			if ( $link_open ) {
				continue;
			}
			// unlimited.
			if ( $args['limit'] === -1 ) {
				foreach ( $links as $search => $replace ) {
					if ( strpos($textarr[ $i ], $search) !== false ) {
						$textarr[ $i ] = strtr($textarr[ $i ], $links);
						$changed = true;
						// After one strtr() break out of the foreach loop and look at next element.
						break;
					}
				}
			} else {
				foreach ( $links as $key => $pairs ) {
					foreach ( $pairs as $k => $v ) {
						if ( $k === $count_key ) {
							continue;
						}
						if ( strpos($textarr[ $i ], $k) !== false ) {
							$limit = absint($args['limit'] - $links[ $key ][ $count_key ]);
							$count = 1;
							$line_new = preg_replace('/' . preg_quote($k, '/') . '/', $v, $textarr[ $i ], $limit, $count);
							// send changes back to the main array to avoid keywords inside urls.
							$textarr = array_merge( array_slice($textarr, 0, $i), wp_html_split($line_new), array_slice($textarr, $i + 1));
							$c = count($textarr);
							$changed = true;
							$links[ $key ][ $count_key ] += $count;
							// this pair is done.
							if ( $links[ $key ][ $count_key ] >= $args['limit'] ) {
								unset($links[ $key ]);
								break;
							}
						}
					}
				}
			}
		}
		if ( $changed ) {
			if ( ! empty($args['minify']) ) {
				$func = function ( $v ) {
					return trim($v, "\t\r");
				};
				$textarr = array_map($func, $textarr);
			}
			$str = implode($textarr);
		}
		return $str;
	}
}

if ( ! function_exists('get_oembed_providers_hosts') ) {
	function get_oembed_providers_hosts( $types = array() ) {
		$res = array();
		$oembed = _wp_oembed_get_object();
		if ( ! is_object($oembed) ) {
			return $res;
		}
		$res = $oembed->providers;
		$func = function ( $val ) {
			return wp_parse_url($val[0], PHP_URL_HOST) . wp_parse_url($val[0], PHP_URL_PATH);
		};
		$res = array_map($func, $res);
		$res = array_unique($res);
		// reduce.
		$types = make_array($types);
		if ( ! empty($types) ) {
			$hosts = array(
                'video' => array( 'youtube.com', 'vimeo.com', 'dailymotion.com', 'hulu.com', 'collegehumor.com', 'facebook.com/plugins/video', 'screencast.com', 'wordpress.tv', 'public-api.wordpress.com' ),
                'audio' => array( 'soundcloud.com', 'spotify.com', 'mixcloud.com' ),
			);
			$hosts = apply_filters('get_oembed_providers_hosts_hosts', $hosts);
			$res_new = array();
			foreach ( $types as $type ) {
				if ( array_key_exists($type, $hosts) ) {
					foreach ( $hosts[ $type ] as $host ) {
						foreach ( $res as $value ) {
							if ( strpos($value, $host) !== false ) {
								$res_new[] = $value;
								$res_new[] = $host;
							}
						}
					}
				}
			}
			$res = array_unique($res_new);
		}
		$sort_longest_first = function ( $a, $b ) {
    		return strlen($b) - strlen($a);
		};
		uasort($res, $sort_longest_first);
		return array_values($res);
	}
}

if ( ! function_exists('switch_to_native_blog') ) {
	function switch_to_native_blog() {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( ! ms_is_switched() ) {
			return false;
		}
		$switched = get_current_blog_id();
		restore_current_blog();
		return $switched;
	}
}

if ( ! function_exists('switch_from_native_blog') ) {
	function switch_from_native_blog( $switched = false ) {
		if ( ! $switched ) {
			return;
		}
		if ( ! is_multisite() ) {
			return;
		}
		if ( is_numeric($switched) ) {
			switch_to_blog($switched);
		}
	}
}

if ( ! function_exists('get_nav_menu_items_by_location') ) {
	function get_nav_menu_items_by_location( $location = '', $args = array() ) {
		$locations = get_nav_menu_locations();
		if ( empty($locations) ) {
			return false;
		}
		// if no location, use the first menu.
		if ( empty($location) ) {
			$location = key($locations);
		}
		$obj = wp_get_nav_menu_object($locations[ $location ]);
		if ( ! $obj ) {
			return false;
		}
		return wp_get_nav_menu_items($obj->name, $args);
	}
}

if ( ! function_exists('has_post_video') ) {
	function has_post_video( $post = null ) {
		$post = get_post($post);
		if ( ! $post || ! is_object($post) ) {
			return false;
		}
		if ( $urls = get_urls($post->post_content, null) ) {
			$providers = get_oembed_providers_hosts('video');
			$oembed = _wp_oembed_get_object();
			foreach ( $urls as $url ) {
				foreach ( $providers as $provider ) {
					if ( strpos($url, $provider) !== false ) {
						if ( $oembed->get_data($url, array( 'discover' => false )) ) {
							return $url;
						}
					}
				}
			}
		}
		return false;
	}
}

if ( ! function_exists('get_post_type_archive_title') ) {
	function get_post_type_archive_title( $post_type = null ) {
		// a more flexible version of this: https://developer.wordpress.org/reference/functions/post_type_archive_title/
		if ( empty($post_type) ) {
			$post_type = get_post_type();
		}
		$title = '';
		$post_type_obj = get_post_type_object($post_type);
		if ( ! $post_type_obj ) {
        	return $title;
    	}
    	$title = $post_type_obj->labels->name;
    	// try a page corresponding to the archive link.
		if ( $link = get_post_type_archive_link($post_type) ) {
			if ( $link !== get_home_url() ) {
				$switched = switch_to_native_blog();
				if ( $page = get_page_by_path(get_url_path($link)) ) {
					$title = get_the_title($page);
				}
				switch_from_native_blog($switched);
			}
		}
    	return apply_filters('post_type_archive_title', $title, $post_type);
	}
}

if ( ! function_exists('is_title_bad') ) {
	function is_title_bad( $str = '', $bad_titles = array() ) {
		$arr = array_merge(array( '', 'Uncategorized', 'uncategorized', 'Uncategorised', 'uncategorised' ), make_array($bad_titles));
		return in_array(trim($str), $arr, true);
	}
}

if ( ! function_exists('get_default_category_term') ) {
	function get_default_category_term( $field = null, $check_field = false ) {
		$res = false;
		$default_category = get_option('default_category');
		if ( ! empty($default_category) ) {
			$term = get_term( (int) $default_category, 'category');
			if ( ! empty($term) && ! is_wp_error($term) && is_object($term) ) {
				if ( empty($field) ) {
					$res = $term;
				} elseif ( isset($term->$field) ) {
					if ( $check_field ) {
						$res = is_title_bad($term->$field) ? false : $term->$field;
					} else {
						$res = $term->$field;
					}
				}
			}
		}
		return $res;
	}
}

