<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

if ( ! class_exists('Halftheory_Helper_CDN', false) ) :
    class Halftheory_Helper_CDN {

    	private static $replacements = array();
    	private static $cache_replacements = array();
    		/*
    		src_ver_key => array(
    			cdn => url,
    			handle => str,
    			time => time(),
    		)
    		*/
    	private static $cache_json = array();
    		/*
    		api_url => array(
    			json => str,
    			handle => str,
    			time => time(),
    		)
    		*/
    	private static $cache_directories = array();
    	private static $common = array();

    	public function __construct() {
    		if ( function_exists('is_front_end') ) {
    			if ( ! is_front_end() ) {
    				return;
    			}
    		} elseif ( is_admin() ) {
    			return;
    		}

    		add_action('wp_print_styles', array( $this, 'wp_print_scripts_cdn' ), 1);
    		add_action('wp_print_scripts', array( $this, 'wp_print_scripts_cdn' ), 1);
    		add_action('wp_print_footer_scripts', array( $this, 'wp_print_scripts_cdn' ), 1);
    	}

    	private function init( $replacements_key = 'scripts' ) {
            // Only do this once.
    		if ( array_key_exists($replacements_key, static::$replacements) ) {
    			return;
    		}
        	static::$replacements = array(
    			'scripts' => array(),
    			'styles' => array(),
    				/*
    				handle => cdn
    				*/
    		);
    		static::$common = array(
    			'time' => time(),
    			'time_past_week' => strtotime('-1 week'),
    			'time_past_day' => strtotime('-1 day'),
        		'home_url' => home_url('/'),
        		'theme_directories' => search_theme_directories(),
        		'theme_roots' => get_theme_roots(),
        		'active_plugins' => array(),
        		'helper_plugin' => false,
        	);
            if ( ! is_string(static::$common['theme_roots']) ) {
                static::$common['theme_roots'] = '/themes';
            }
            if ( class_exists('Halftheory_Clean', false) ) {
    			static::$common['active_plugins'] = Halftheory_Clean::get_instance()->get_active_plugins();
    	    	if ( $hp = Halftheory_Clean::get_instance()->get_helper_plugin() ) {
    	    		static::$common['helper_plugin'] = $hp;
    				static::$common['transient_name_replacements'] = Halftheory_Clean::get_instance()::$prefix . '_cdn_replacements';
    				static::$common['transient_name_json'] = Halftheory_Clean::get_instance()::$prefix . '_cdn_json';
    				unset($hp);
    	    	}
    		} else {
    			$func = function ( $str ) {
    				return trailingslashit(WP_PLUGIN_DIR) . $str;
    			};
    			if ( ! function_exists('get_plugins') ) {
    				require_once ABSPATH . 'wp-admin/includes/plugin.php';
    			}
    			static::$common['active_plugins'] = array_map($func, array_keys(get_plugins()));
    		}
    	}

    	/* actions */

    	public function wp_print_scripts_cdn() {
    		if ( current_action() === 'wp_print_styles' ) {
    			global $wp_styles;
    			$wp_styles = $this->cdn_replacements($wp_styles, false);
    		} elseif ( current_action() === 'wp_print_scripts' ) {
    			global $wp_scripts;
    			$wp_scripts = $this->cdn_replacements($wp_scripts);
    		} else {
    			global $wp_styles, $wp_scripts;
    			$wp_styles = $this->cdn_replacements($wp_styles, false);
    			$wp_scripts = $this->cdn_replacements($wp_scripts);
    		}
    	}

    	/* functions */

    	private function cdn_replacements( $wp_dependencies, $is_scripts = true ) {
    		if ( $is_scripts ) {
    			if ( ! ( $wp_dependencies instanceof WP_Scripts ) ) {
    				return $wp_dependencies;
    			}
    			$replacements_key = 'scripts';
    		} else {
    			if ( ! ( $wp_dependencies instanceof WP_Styles ) ) {
    				return $wp_dependencies;
    			}
    			$replacements_key = 'styles';
    		}
        	if ( empty($wp_dependencies->registered) || empty($wp_dependencies->queue) ) {
    			return $wp_dependencies;
        	}
        	$this->init($replacements_key);
        	$this->cache_load();

    		$sort_dir_depth = function ( $str_a, $str_b ) {
    			$a = substr_count($str_a, '/');
    			$b = substr_count($str_b, '/');
    			if ( $a < $b ) {
    				return 1;
    			}
    			if ( $a === $b ) {
    				return strcmp($str_a, $str_b);
    			}
    			if ( $a > $b ) {
    				return -1;
    			}
    		};

    		$walk_filename = function ( &$val, $key, $arr ) {
    			$val = implode('.', array_slice($arr, 0, $key + 1));
    		};

    		$func_src_ver_key = function ( $src, $ver = '' ) {
    			return untrailingslashit(set_url_scheme($src, 'relative')) . '?ver=' . $ver;
    	    };

        	// main function
        	$func_get_cdn = function ( $handle, $item ) use ( $is_scripts, $sort_dir_depth, $walk_filename, $func_src_ver_key ) {
        		if ( strpos($handle, 'wp-') === 0 ) {
        			return null;
        		}
                // must have version.
        		if ( empty($item->ver) ) {
        			return null;
        		}
        		if ( strpos($item->src, static::$common['home_url']) === false ) {
        			return null;
        		}
        		if ( ! $is_scripts && strpos($item->src, 'style.css') !== false ) {
                    // too generic.
        			return null;
        		}
        		$src_ver_key = $func_src_ver_key($item->src, $item->ver);
        		// check cache.
        		if ( array_key_exists($src_ver_key, static::$cache_replacements) ) {
        			if ( empty(static::$cache_replacements[ $src_ver_key ]['cdn']) ) {
        				return null;
        			}
    				static::$cache_replacements[ $src_ver_key ]['handle'] = $handle;
    				static::$cache_replacements[ $src_ver_key ]['time'] = static::$common['time'];
        			return static::$cache_replacements[ $src_ver_key ]['cdn'];
        		}
        		// check directories of previous successes.
    			if ( function_exists('url_exists') ) {
    	    		if ( array_key_exists($handle, static::$cache_directories) ) {
    	    			if ( is_array(static::$cache_directories[ $handle ]) ) {
    	    				foreach ( static::$cache_directories[ $handle ] as $value ) {
    	    					$value .= pathinfo($item->src, PATHINFO_FILENAME);
    	    					if ( url_exists($value) ) {
        							$cdn = $value;
        							$this->cache_cdn($src_ver_key, $cdn, $handle);
        							return $cdn;
    	    					}
    	    				}
    	    			}
    	    		}
        		}
        		// new search.
        		$gh_uri = null;
        		$gh_path = null;
        		if ( strpos($item->src, WP_PLUGIN_URL) !== false ) {
                    // from a plugin? GitHub Plugin URI.
        			foreach ( static::$common['active_plugins'] as $path ) {
        				$plugin_url = WP_PLUGIN_URL . str_replace(array( WP_PLUGIN_DIR, basename($path) ), '', $path);
    		    		if ( strpos($item->src, $plugin_url) !== false ) {
    						if ( ! function_exists('get_plugin_data') ) {
    				        	require_once ABSPATH . 'wp-admin/includes/plugin.php';
    				    	}
    	    				$plugin_data = get_plugin_data($path);
    	    				if ( isset($plugin_data['GitHub Plugin URI']) && ! empty($plugin_data['GitHub Plugin URI']) ) {
    	    					$gh_uri = $plugin_data['GitHub Plugin URI'];
    	    					$gh_path = str_replace($plugin_url, '', $item->src);
    	    					break;
    	    				}
    		    		}
        			}
        		} elseif ( strpos($item->src, WP_CONTENT_URL . static::$common['theme_roots']) !== false ) {
                    // from a theme? GitHub Theme URI.
    		    	foreach ( static::$common['theme_directories'] as $theme => $arr ) {
    		    		$theme_url = trailingslashit( get_theme_root_uri($theme, $arr['theme_root']) ) . trailingslashit( dirname($arr['theme_file']) );
    		    		if ( strpos($item->src, $theme_url) !== false ) {
    		    			$theme_data = wp_get_theme($theme, $arr['theme_root']);
    		    			$tmp = $theme_data->get('GitHub Theme URI');
    		    			if ( ! empty($tmp) ) {
    		    				$gh_uri = $tmp;
    		    				$gh_path = str_replace($theme_url, '', $item->src);
    	    					break;
    		    			}
    			    		// skip root style.css
    			    		if ( $item->src === $theme_url . 'style.css' ) {
    			    			$this->cache_cdn($src_ver_key, null, $handle);
    							return null;
    			    		}
    		    		}
    		    	}
        		}

        		/*
    			Search order:
    			1. jsdelivr - GitHub - https://www.jsdelivr.com/?docs=gh
    			2. cdnjs - search - https://cdnjs.com/api
    			3. jsdelivr - npm - https://www.jsdelivr.com/?docs=npm
        		*/
        		// 1. jsdelivr - GitHub
        		if ( ! empty($gh_uri) ) {
        			$gh_user = basename(dirname($gh_uri));
        			$gh_repo = basename($gh_uri);
        			$api_url = 'https://data.jsdelivr.com/v1/package/gh/' . $gh_user . '/' . $gh_repo . '@' . $item->ver . '/flat';
        			if ( $api_json = $this->file_get_json($api_url, $handle) ) {
            			if ( isset($api_json->files) && ! empty($api_json->files) ) {
            				foreach ( $api_json->files as $file ) {
            					if ( ! is_object($file) ) {
            						continue;
            					}
            					if ( isset($file->name) && ! empty($file->name) ) {
            						if ( $file->name === '/' . $gh_path ) {
            							$cdn = 'https://cdn.jsdelivr.net/gh/' . $gh_user . '/' . $gh_repo . '@' . $item->ver . '/' . $gh_path;
            							$this->cache_cdn($src_ver_key, $cdn, $handle);
            							return $cdn;
            						}
            					}
            				}
            			}
        			}
        		}
        		// create search array.
        		$search_repos = array( $handle );
        		$filename = pathinfo($item->src, PATHINFO_FILENAME);
        		$filename = str_replace(array( 'jquery.', '.min' ), '', $filename);
        		$arr = explode('.', $filename);
    			array_walk($arr, $walk_filename, $arr);
    			$search_repos = array_merge($search_repos, $arr);
        		$search_repos = array_unique($search_repos);
        		// 2. cdnjs - search
    			foreach ( $search_repos as $repo ) {
    				$api_url = 'https://api.cdnjs.com/libraries?search=' . $repo . '&fields=version,assets';
    				if ( $api_json = $this->file_get_json($api_url, $handle) ) {
    					// only use top result.
    					if ( isset($api_json->results) && ! empty($api_json->results) ) {
    						$repo_lo = strtolower($repo);
    						$result_name_lo = strtolower($api_json->results[0]->name);
    						// check relevance.
    						if ( strpos($repo_lo, $result_name_lo) === false && strpos($result_name_lo, $repo_lo) === false ) {
    							continue;
    						}
    						if ( isset($api_json->results[0]->assets) && ! empty($api_json->results[0]->assets) ) {
                                // returned assets.
    							foreach ( $api_json->results[0]->assets as $asset ) {
    	        					if ( ! is_object($asset) ) {
    	        						continue;
    	        					}
    	        					if ( $asset->version === $item->ver ) {
    	        						if ( isset($asset->files) && ! empty($asset->files) ) {
    	        							// sort files - root first, then alphabetical folders.
    	        							$files = $files_root = $files_rest = array();
    	        							foreach ( $asset->files as $file ) {
    	        								$file = trim($file, ' /');
    	        								if ( strpos($file, '/') === false ) {
    	        									$files_root[] = $file;
    	        									continue;
    	        								}
    	        								$files_rest[] = $file;
    	        							}
    	        							$files = array_merge($files_root, $files_rest);
    										// sort files - folder depth, deepest first.
    										$files_dir_depth = $files;
    										usort($files_dir_depth, $sort_dir_depth);

    	        							// match full path + basename.
    	        							foreach ( $files_dir_depth as $file ) {
    			        						if ( strpos($item->src, $file) !== false && basename($file) === basename($item->src) ) {
    			        							$cdn = 'https://cdnjs.cloudflare.com/ajax/libs/' . $api_json->results[0]->name . '/' . $item->ver . '/' . $file;
    			        							$this->cache_cdn($src_ver_key, $cdn, $handle);
    			        							return $cdn;
    			        						}
    	        							}
    	        							// match basename
    	        							foreach ( $files as $file ) {
    			        						if ( basename($file) === basename($item->src) ) {
    			        							$cdn = 'https://cdnjs.cloudflare.com/ajax/libs/' . $api_json->results[0]->name . '/' . $item->ver . '/' . $file;
    			        							$this->cache_cdn($src_ver_key, $cdn, $handle);
    			        							return $cdn;
    			        						}
    	        							}
    	        						}
    	        					}
    							}
    						} elseif ( isset($api_json->results[0]->latest) && isset($api_json->results[0]->version) ) {
                                // or just latest.
    							if ( $api_json->results[0]->version === $item->ver ) {
            						if ( basename($api_json->results[0]->latest) === basename($item->src) ) {
            							$cdn = $api_json->results[0]->latest;
            							$this->cache_cdn($src_ver_key, $cdn, $handle);
            							return $cdn;
            						}
    							}
    						}
    					}
    				}
    			}
        		// 3. jsdelivr - npm
    			foreach ( $search_repos as $repo ) {
        			$api_url = 'https://data.jsdelivr.com/v1/package/npm/' . $repo . '@' . $item->ver . '/flat';
        			if ( $api_json = $this->file_get_json($api_url, $handle) ) {
            			if ( isset($api_json->files) && ! empty($api_json->files) ) {
            				// sort files - root first, then alphabetical folders.
    						$files = $files_root = $files_rest = array();
    						foreach ( $api_json->files as $file ) {
            					if ( ! is_object($file) ) {
            						continue;
            					}
            					if ( isset($file->name) && ! empty($file->name) ) {
    								$file = trim($file->name, ' /');
    								if ( strpos($file, '/') === false ) {
    									$files_root[] = $file;
    									continue;
    								}
    								$files_rest[] = $file;
    							}
    						}
    						$files = array_merge($files_root, $files_rest);
    						// sort files - folder depth, deepest first.
    						$files_dir_depth = $files;
    						usort($files_dir_depth, $sort_dir_depth);

    						// match full path + basename.
    						foreach ( $files_dir_depth as $file ) {
        						if ( strpos($item->src, $file) !== false && basename($file) === basename($item->src) ) {
        							$cdn = 'https://cdn.jsdelivr.net/npm/' . $repo . '@' . $item->ver . '/' . $file;
        							$this->cache_cdn($src_ver_key, $cdn, $handle);
        							return $cdn;
        						}
    						}
    						// match basename.
            				foreach ( $files as $file ) {
        						if ( basename($file) === basename($item->src) ) {
        							$cdn = 'https://cdn.jsdelivr.net/npm/' . $repo . '@' . $item->ver . '/' . $file;
        							$this->cache_cdn($src_ver_key, $cdn, $handle);
        							return $cdn;
        						}
            				}
            			}
        			}
    			}
    			$this->cache_cdn($src_ver_key, null, $handle);
    			return null;
        	}; // func_get_cdn

        	foreach ( $wp_dependencies->queue as $handle ) {
        		if ( array_key_exists($handle, static::$replacements[ $replacements_key ]) ) {
    				continue;
        		}
        		if ( ! isset($wp_dependencies->registered[ $handle ]) ) {
        			static::$replacements[ $replacements_key ][ $handle ] = null;
        			continue;
        		}
        		if ( $cdn = $func_get_cdn($handle, $wp_dependencies->registered[ $handle ]) ) {
    				static::$replacements[ $replacements_key ][ $handle ] = $cdn;
    				// replace.
    				$wp_dependencies->registered[ $handle ]->src = $cdn;
    				// store directory.
    				$this->cache_dir($handle, $cdn);
        		}
        	}

        	$this->cache_save();
        	return $wp_dependencies;
    	}

    	private function cache_load() {
    		if ( ! empty(static::$cache_replacements) || ! empty(static::$cache_json) ) {
    			return; // only do this once
    		}
    		$this->init();
    		if ( ! static::$common['helper_plugin'] ) {
    			return;
    		}
    		if ( $arr = static::$common['helper_plugin']->get_transient(static::$common['transient_name_replacements']) ) {
    			// remove past - empty items remove after 1 day - valid items remove after 1 week.
    			foreach ( $arr as $key => $value ) {
    				if ( empty($value['cdn']) ) {
    					if ( $value['time'] < static::$common['time_past_day'] ) {
    						unset($arr[ $key ]);
    					}
    				} else {
    					if ( $value['time'] < static::$common['time_past_week'] ) {
    						unset($arr[ $key ]);
    					}
    				}
    			}
    			static::$cache_replacements = $arr;
    		}
    		if ( $arr = static::$common['helper_plugin']->get_transient(static::$common['transient_name_json']) ) {
    			foreach ( $arr as $key => $value ) {
    				if ( empty($value['json']) ) {
    					if ( $value['time'] < static::$common['time_past_day'] ) {
    						unset($arr[ $key ]);
    					}
    				} else {
    					if ( $value['time'] < static::$common['time_past_week'] ) {
    						unset($arr[ $key ]);
    					}
    				}
    			}
    			static::$cache_json = $arr;
    		}
    	}

    	private function cache_save() {
    		if ( ! static::$common['helper_plugin'] ) {
    			return;
    		}
        	static::$common['helper_plugin']->set_transient(static::$common['transient_name_replacements'], static::$cache_replacements, '1 week');
        	static::$common['helper_plugin']->set_transient(static::$common['transient_name_json'], static::$cache_json, '1 week');
    	}

    	private function cache_cdn( $src_ver_key, $cdn, $handle ) {
    		$arr = array(
    			'cdn' => $cdn,
    			'handle' => $handle,
    			'time' => static::$common['time'],
    		);
    		static::$cache_replacements[ $src_ver_key ] = $arr;
    	}

    	private function cache_dir( $handle, $cdn ) {
    		if ( ! array_key_exists($handle, static::$cache_directories) ) {
    			static::$cache_directories[ $handle ] = array();
    		}
    		$str = trailingslashit(dirname($cdn));
    		if ( ! array_key_exists($str, static::$cache_directories[ $handle ]) ) {
    			static::$cache_directories[ $handle ][] = $str;
    		}
    	}

    	private function file_get_json( $url, $handle ) {
    		// check cache.
    		if ( array_key_exists($url, static::$cache_json) ) {
    			if ( empty(static::$cache_json[ $url ]['json']) ) {
    				return false;
    			}
    			static::$cache_json[ $url ]['handle'] = $handle;
    			static::$cache_json[ $url ]['time'] = static::$common['time'];
    			return json_decode(static::$cache_json[ $url ]['json']);
    		}
    		if ( function_exists('file_get_contents_extended') ) {
    			$str = file_get_contents_extended($url);
    		} else {
    			$str = file_get_contents($url);
    		}
    		$arr = array(
    			'json' => $str,
    			'handle' => $handle,
    			'time' => static::$common['time'],
    		);
    		static::$cache_json[ $url ] = $arr;
    		if ( $str === false ) {
    			return false;
                // still saved in cache - removed after 1 day.
    		}
    		return json_decode($arr['json']);
    	}
    }
endif;
